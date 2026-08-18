<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 自建 WebSocket 服务器（替代 Firestore 实时监听 + FCM 在线推送）。
 *
 * 通过 `php artisan ws:serve start --d` 启动（crontab 守护见 ws-keeper.sh），
 * nginx 将 https://域名/ws 反代到本进程的 WS_PORT（默认 6001）。
 *
 * 客户端协议（JSON 文本帧）：
 *   -> {"type":"subscribe","channels":["user.7.ab12..","conv.5.cd34.."]}
 *   <- {"type":"subscribed","channels":[...]}
 *   -> {"type":"unsubscribe","channels":[...]}
 *   -> {"type":"ping"}    <- {"type":"pong"}
 *   <- {"type":"event","channel":"...","event":"...","data":{...}}
 *
 * 事件来源：PHP-FPM 侧把要广播的事件写入 chat_events 表（WsBroadcaster），
 * 本进程每 250ms 轮询一次并推送给订阅者 —— 单进程内闭环，无跨进程状态问题。
 *
 * 在线判定：客户端订阅 user.{id}.* 频道即视为该用户在线（写 users.is_online），
 * 所有连接断开后置离线。isOnline() 供离线 FCM 兜底判断使用。
 */
class ServeWebSocket extends Command
{
    // workerAction 直接透传给 Workerman：start / stop / restart / status
    protected $signature = 'ws:serve {workerAction=start : start|stop|restart|status} {--d : 以守护进程模式运行}';

    protected $description = '启动自建 WebSocket 服务器（Workerman，聊天实时通道）';

    /** @var array<string, array<int, TcpConnection>> 频道 => 连接集合 */
    protected array $channelClients = [];

    /** @var array<int, int> userId => 订阅了其用户频道的连接数（本进程内） */
    protected array $onlineUsers = [];

    /** @var int 已处理到的 chat_events.id */
    protected int $lastEventId = 0;

    public function handle()
    {
        $wsPort = (int) (env('WS_PORT', 6001));

        // Workerman 的 Worker::runAll() 从 $argv 解析命令，
        // 而 artisan 会占用 $argv[1]，这里重写成 Workerman 期望的形式
        global $argv;
        $argv = ['artisan', $this->argument('workerAction')];
        if ($this->option('d')) {
            $argv[] = '-d';
        }

        $ws = new Worker("websocket://0.0.0.0:{$wsPort}");
        $ws->count = 1; // 必须单进程：订阅状态在内存中
        $ws->name = 'chatter-ws';

        $ws->onWorkerStart = function () {
            // 启动时不重放历史事件
            try {
                $this->lastEventId = (int) (DB::table('chat_events')->max('id') ?? 0);
            } catch (\Throwable $e) {
                $this->lastEventId = 0;
            }

            // 每 250ms 轮询待广播事件
            Timer::add(0.25, function () {
                try {
                    $events = DB::table('chat_events')
                        ->where('id', '>', $this->lastEventId)
                        ->orderBy('id')
                        ->limit(200)
                        ->get();

                    foreach ($events as $event) {
                        $this->dispatch($event->channel, $event->event, json_decode((string) $event->data, true) ?: []);
                        $this->lastEventId = (int) $event->id;
                    }

                    // 低频清理一天前的事件
                    if (random_int(1, 500) === 1) {
                        DB::table('chat_events')->where('created_at', '<', now()->subDay())->delete();
                    }
                } catch (\Throwable $e) {
                    // 长进程常见 MySQL gone away，断开后下次轮询自动重连
                    try {
                        DB::disconnect();
                    } catch (\Throwable $ignored) {
                    }
                }
            });
        };

        $ws->onConnect = function (TcpConnection $connection) {
            $connection->channels = [];
        };

        $ws->onMessage = function (TcpConnection $connection, $data) {
            $payload = json_decode((string) $data, true);
            if (!is_array($payload)) {
                return;
            }

            switch ($payload['type'] ?? '') {
                case 'ping':
                    $connection->send(json_encode(['type' => 'pong']));
                    return;

                case 'subscribe':
                    $channels = $payload['channels'] ?? [];
                    $ok = [];
                    if (is_array($channels)) {
                        foreach ($channels as $channel) {
                            $channel = (string) $channel;
                            if ($this->isValidChannel($channel) && !isset($connection->channels[$channel])) {
                                $connection->channels[$channel] = true;
                                $this->channelClients[$channel][$connection->id] = $connection;
                                $this->incOnline($channel);
                                $ok[] = $channel;
                            }
                        }
                    }
                    $connection->send(json_encode(['type' => 'subscribed', 'channels' => $ok]));
                    return;

                case 'unsubscribe':
                    $channels = $payload['channels'] ?? [];
                    if (is_array($channels)) {
                        foreach ($channels as $channel) {
                            $this->removeFromChannel($connection, (string) $channel);
                        }
                    }
                    return;
            }
        };

        $cleanup = function (TcpConnection $connection) {
            foreach (array_keys($connection->channels ?? []) as $channel) {
                $this->removeFromChannel($connection, $channel);
            }
            $connection->channels = [];
        };

        $ws->onClose = $cleanup;
        $ws->onError = $cleanup;

        Worker::runAll();
        return 0;
    }

    protected function isValidChannel(string $channel): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_.\-]{3,64}$/', $channel);
    }

    protected function dispatch(string $channel, string $event, array $data): void
    {
        $clients = $this->channelClients[$channel] ?? [];
        if (!$clients) {
            return;
        }
        $frame = json_encode(['type' => 'event', 'channel' => $channel, 'event' => $event, 'data' => $data], JSON_UNESCAPED_UNICODE);
        foreach ($clients as $client) {
            $client->send($frame);
        }
    }

    protected function incOnline(string $channel): void
    {
        if (preg_match('/^user\.(\d+)\./', $channel, $m)) {
            $userId = (int) $m[1];
            $wasOffline = ($this->onlineUsers[$userId] ?? 0) === 0;
            $this->onlineUsers[$userId] = ($this->onlineUsers[$userId] ?? 0) + 1;
            if ($wasOffline) {
                try {
                    DB::table('users')->where('id', $userId)->update(['is_online' => 1, 'online_at' => now()]);
                } catch (\Throwable $e) {
                    DB::disconnect();
                }
            }
        }
    }

    protected function removeFromChannel(TcpConnection $connection, string $channel): void
    {
        if (isset($connection->channels[$channel])) {
            unset($connection->channels[$channel]);
        }
        if (isset($this->channelClients[$channel][$connection->id])) {
            unset($this->channelClients[$channel][$connection->id]);
            if (empty($this->channelClients[$channel])) {
                unset($this->channelClients[$channel]);
            }
            if (preg_match('/^user\.(\d+)\./', $channel, $m)) {
                $userId = (int) $m[1];
                if (isset($this->onlineUsers[$userId])) {
                    $this->onlineUsers[$userId]--;
                    if ($this->onlineUsers[$userId] <= 0) {
                        unset($this->onlineUsers[$userId]);
                        try {
                            DB::table('users')->where('id', $userId)->update(['is_online' => 0]);
                        } catch (\Throwable $e) {
                            DB::disconnect();
                        }
                    }
                }
            }
        }
    }
}
