<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request as HttpRequest;
use Workerman\Protocols\Http\Response as HttpResponse;
use Workerman\Worker;

/**
 * 自建 WebSocket 服务器（替代 Firebase Cloud Firestore 实时监听 + FCM 在线推送）。
 *
 * 通过 `php artisan ws:serve` 启动，建议配合 Supervisor 常驻：
 *
 *   监听端口（.env 可配置）：
 *   - WS_PORT      默认 6001：客户端 WebSocket，经 nginx 反代 wss://域名/ws
 *   - WS_HTTP_PORT 默认 6002：仅绑定 127.0.0.1，供 Laravel 内部发布事件 / 查询在线状态
 *
 * 客户端协议（JSON 文本帧）：
 *   -> {"type":"subscribe","channels":["user.7.ab12..","conv.5.cd34.."]}
 *   <- {"type":"subscribed","channels":[...]}
 *   -> {"type":"unsubscribe","channels":[...]}
 *   -> {"type":"ping"}    <- {"type":"pong"}
 *   <- {"type":"event","channel":"...","event":"...","data":{...}}
 *
 * 内部 HTTP 协议（需 WS_INTERNAL_TOKEN）：
 *   POST /publish {token, channel, event, data}  向频道内所有连接广播事件
 *   GET  /online?token=&user=                    查询用户是否在线（按 user.{id}. 频道判断）
 *
 * 在线判定：客户端订阅了以 user.{id}. 开头的频道即视为该用户在线，
 * 断开或取消订阅时计数递减，减到 0 视为离线。单进程（count=1）运行，
 * 状态全部保存在进程内存中，规模到数千连接无压力。
 */
class ServeWebSocket extends Command
{
    // workerAction 直接透传给 Workerman：start / stop / restart / status / connections
    protected $signature = 'ws:serve {workerAction=start : start|stop|restart|status|connections} {--d : 以守护进程模式运行}';

    protected $description = '启动自建 WebSocket 服务器（Workerman）';

    /** @var array<string, array<int, TcpConnection>> 频道 => 连接集合 */
    protected array $channelClients = [];

    /** @var array<int, int> userId => 订阅了其用户频道的连接数 */
    protected array $onlineUsers = [];

    public function handle()
    {
        $wsPort   = (int) (env('WS_PORT', 6001));
        $httpPort = (int) (env('WS_HTTP_PORT', 6002));
        $token    = (string) env('WS_INTERNAL_TOKEN', '');

        if ($token === '') {
            $this->error('请在 .env 中配置 WS_INTERNAL_TOKEN');
            return 1;
        }

        // Workerman 的 Worker::runAll() 从 $argv 解析命令，
        // 而 artisan 会占用 $argv[1]，这里重写成 Workerman 期望的形式
        global $argv;
        $argv = ['artisan', $this->argument('workerAction')];
        if ($this->option('d')) {
            $argv[] = '-d';
        }

        // ---- 内部 HTTP 服务：发布事件 / 查询在线状态 ----
        $http = new Worker("http://127.0.0.1:{$httpPort}");
        $http->count = 1;
        $http->onMessage = function (TcpConnection $connection, HttpRequest $request) use ($token) {
            $path = $request->path();
            $auth = $request->header('x-internal-token', $request->get('token', ''));

            if ($auth !== $token) {
                return $connection->send(new HttpResponse(403, [], json_encode(['status' => false, 'message' => 'forbidden'])));
            }

            if ($path === '/publish' && $request->method() === 'POST') {
                $body = json_decode($request->body(), true) ?: [];
                $channel = $body['channel'] ?? '';
                $event   = $body['event'] ?? '';
                $data    = $body['data'] ?? [];

                if (!$this->isValidChannel($channel) || $event === '') {
                    return $connection->send(new HttpResponse(200, [], json_encode(['status' => false, 'message' => 'bad payload'])));
                }

                $sent = $this->publish($channel, $event, $data);
                return $connection->send(new HttpResponse(200, [], json_encode(['status' => true, 'sent' => $sent])));
            }

            if ($path === '/online' && $request->method() === 'GET') {
                $user = (int) $request->get('user', 0);
                return $connection->send(new HttpResponse(200, [], json_encode([
                    'status' => true,
                    'online' => $user > 0 && ($this->onlineUsers[$user] ?? 0) > 0,
                ])));
            }

            return $connection->send(new HttpResponse(200, [], json_encode(['status' => true])));
        };

        // ---- 对外 WebSocket 服务 ----
        $ws = new Worker("websocket://0.0.0.0:{$wsPort}");
        $ws->count = 1;
        $ws->name = 'chatter-ws';

        $ws->onConnect = function (TcpConnection $connection) {
            $connection->channels = [];
        };

        $ws->onMessage = function (TcpConnection $connection, $data) {
            $payload = json_decode((string) $data, true);
            if (!is_array($payload)) {
                return;
            }

            $type = $payload['type'] ?? '';

            switch ($type) {
                case 'ping':
                    $connection->send(json_encode(['type' => 'pong']));
                    return;

                case 'subscribe':
                    $channels = $payload['channels'] ?? [];
                    $ok = [];
                    if (is_array($channels)) {
                        foreach ($channels as $channel) {
                            if ($this->isValidChannel((string) $channel) && !isset($connection->channels[$channel])) {
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

    protected function publish(string $channel, string $event, array $data): int
    {
        $clients = $this->channelClients[$channel] ?? [];
        $frame = json_encode(['type' => 'event', 'channel' => $channel, 'event' => $event, 'data' => $data], JSON_UNESCAPED_UNICODE);
        foreach ($clients as $client) {
            $client->send($frame);
        }
        return count($clients);
    }

    protected function incOnline(string $channel): void
    {
        if (preg_match('/^user\.(\d+)\./', $channel, $m)) {
            $userId = (int) $m[1];
            $this->onlineUsers[$userId] = ($this->onlineUsers[$userId] ?? 0) + 1;
        }
    }

    protected function removeFromChannel(TcpConnection $connection, string $channel): void
    {
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
                    }
                }
            }
        }
        if (isset($connection->channels[$channel])) {
            unset($connection->channels[$channel]);
        }
    }
}
