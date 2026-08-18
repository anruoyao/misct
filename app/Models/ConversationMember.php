<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationMember extends Model
{
    protected $table = 'conversation_members';

    // 请求状态
    const REQUEST_PENDING  = 0; // 陌生人消息，等待对方接受
    const REQUEST_ACCEPTED = 1; // 正常会话
    const REQUEST_REJECTED = 2; // 已拒绝

    protected $fillable = [
        'conversation_id',
        'user_id',
        'request_status',
        'unread_count',
        'cleared_before_id',
        'is_hidden',
        'is_mute',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'is_mute'   => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
