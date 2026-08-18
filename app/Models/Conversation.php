<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $table = 'conversations';

    // 会话类型
    const TYPE_DM   = 1;
    const TYPE_ROOM = 2;

    protected $fillable = [
        'type',
        'room_id',
        'dm_user_a',
        'dm_user_b',
        'last_msg',
        'last_msg_id',
        'last_msg_time',
        'channel_key',
    ];

    protected $casts = [
        'last_msg_time' => 'datetime',
    ];

    public function members()
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * WebSocket 会话频道名（仅会话成员可从接口拿到 channel_key）。
     */
    public function channelName(): string
    {
        return 'conv.' . $this->id . '.' . $this->channel_key;
    }
}
