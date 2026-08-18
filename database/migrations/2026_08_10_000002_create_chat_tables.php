<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 本地化聊天系统三张核心表（替代 Firestore 实时数据库）。
 *
 * conversations        会话：1 = 单聊（DM）/ 2 = 群聊（房间）
 * conversation_members 会话成员：每用户每会话一行的个人状态
 *                      （未读数、消息请求状态、清空/删除标记）
 * messages             消息：TEXT / IMAGE / VIDEO / STORY_REPLY
 *
 * 设计要点：
 * - 单聊通过 dm_user_a + dm_user_b（按小 id 在前排列）唯一约束防止重复会话
 * - 群聊 conversation.room_id 关联 rooms 表
 * - channel_key 用于 WebSocket 会话频道订阅（conv.{id}.{channel_key}），
 *   只有会话成员能从接口拿到该 key
 * - conversation_members.cleared_before_id 记录"清空聊天"位置，
 *   拉消息时只返回该 id 之后的消息
 * - is_hidden 表示"删除会话"：列表隐藏，新消息到达时自动取消隐藏
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type')->comment('1 = 单聊 / 2 = 群聊(房间)');
            $table->unsignedInteger('room_id')->nullable()->index();
            $table->unsignedInteger('dm_user_a')->nullable();
            $table->unsignedInteger('dm_user_b')->nullable();
            $table->text('last_msg')->nullable();
            $table->unsignedBigInteger('last_msg_id')->nullable();
            $table->timestamp('last_msg_time')->nullable();
            $table->string('channel_key', 32);
            $table->timestamps();

            $table->unique(['dm_user_a', 'dm_user_b']);
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedInteger('user_id')->index();
            // 请求状态：0 = 待对方处理（陌生人消息请求）/ 1 = 正常 / 2 = 已拒绝
            $table->unsignedTinyInteger('request_status')->default(1);
            $table->integer('unread_count')->default(0);
            $table->unsignedBigInteger('cleared_before_id')->default(0);
            $table->boolean('is_hidden')->default(false)->comment('删除会话标记');
            $table->boolean('is_mute')->default(false);
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedInteger('sender_id')->index();
            $table->string('msg_type', 20)->default('TEXT');
            $table->text('msg')->nullable();
            $table->string('content', 500)->nullable()->comment('图片/视频 URL');
            $table->string('thumbnail', 500)->nullable();
            $table->unsignedInteger('story_id')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
    }
};
