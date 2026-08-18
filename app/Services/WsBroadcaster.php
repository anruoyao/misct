<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * WebSocket 广播网关。
 *
 * 业务代码通过本类发布实时事件 / 查询在线状态：
 * - publish()  写入 chat_events 表，ws:serve 进程（Workerman）每 250ms
 *              轮询本表并推送给订阅了对应频道的 WebSocket 连接
 * - isOnline() 查询 users.is_online（由 WS 进程在订阅/断开时维护）
 *
 * 两个方法都不会抛异常，WS 进程未启动时业务照常（只是收不到实时推送）。
 */
class WsBroadcaster
{
    public static function publish(string $channel, string $event, array $data): bool
    {
        try {
            DB::table('chat_events')->insert([
                'channel'    => $channel,
                'event'      => $event,
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isOnline(int $userId): bool
    {
        try {
            return (int) (DB::table('users')->where('id', $userId)->value('is_online') ?? 0) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
