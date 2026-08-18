<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket 广播网关。
 *
 * Laravel 业务代码通过本类与 ws:serve 进程（Workerman）通信：
 * - publish()  向某个频道广播事件（内部 HTTP POST）
 * - isOnline() 查询用户是否有活跃 WS 连接
 *
 * 两个方法都不会抛异常：WS 进程未启动时只记日志、返回失败，
 * 不影响主业务流程（降级为收不到实时推送，仍可轮询接口）。
 */
class WsBroadcaster
{
    public static function publish(string $channel, string $event, array $data): bool
    {
        try {
            $response = Http::timeout(3)->post(self::baseUrl() . '/publish', [
                'token'   => env('WS_INTERNAL_TOKEN', ''),
                'channel' => $channel,
                'event'   => $event,
                'data'    => $data,
            ]);
            return $response->ok() && (bool) $response->json('status');
        } catch (\Throwable $e) {
            Log::warning('WS publish failed: ' . $e->getMessage());
            return false;
        }
    }

    public static function isOnline(int $userId): bool
    {
        try {
            $response = Http::timeout(3)->get(self::baseUrl() . '/online', [
                'token' => env('WS_INTERNAL_TOKEN', ''),
                'user'  => $userId,
            ]);
            return $response->ok() && (bool) ($response->json('online') ?? false);
        } catch (\Throwable $e) {
            Log::warning('WS isOnline failed: ' . $e->getMessage());
            return false;
        }
    }

    protected static function baseUrl(): string
    {
        return 'http://127.0.0.1:' . (int) env('WS_HTTP_PORT', 6002);
    }
}
