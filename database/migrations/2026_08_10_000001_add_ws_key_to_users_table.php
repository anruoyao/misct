<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 users 表添加 ws_key 字段。
 *
 * ws_key 是用户私有频道密钥，用于 WebSocket 订阅鉴权：
 * 频道名形如 user.{id}.{ws_key}，只有拿到 ws_key 的客户端才能收到
 * 该用户的实时事件（新消息通知、会话变更等）。ws_key 只在用户本人
 * 相关的接口响应中返回（登录 / 注册），对其他用户不可见。
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ws_key', 64)->nullable()->after('device_token');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ws_key');
        });
    }
};
