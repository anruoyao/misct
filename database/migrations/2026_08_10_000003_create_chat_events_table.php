<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 聊天实时事件表 + 用户在线标记。
 *
 * 架构说明：Laravel（PHP-FPM 进程）把要广播的事件写入 chat_events，
 * ws:serve（Workerman 单进程）每 250ms 轮询本表，把新事件推送给
 * 订阅了对应频道的 WebSocket 连接。
 *
 * 为什么不直接跨进程调用：Workerman 的多个 Worker 是相互独立的进程，
 * 内存不共享；通过 DB 事件表解耦后只需一个 WS 进程，无状态同步问题，
 * 且 PHP-FPM 侧发布事件只是普通 INSERT，稳定可靠。
 * （后续规模大了可平滑替换为 Redis Stream / pub-sub，接口不变）
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_events', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 64)->index();
            $table->string('event', 50);
            $table->text('data')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_online')->default(0)->after('ws_key');
            $table->timestamp('online_at')->nullable()->after('is_online');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_events');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'online_at']);
        });
    }
};
