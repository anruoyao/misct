<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'msg_type',
        'msg',
        'content',
        'thumbnail',
        'story_id',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * 输出给客户端的消息结构（字段名与原 Firestore 保持一致，
     * Flutter 端模型无需大改）。
     */
    public function toClientArray(): array
    {
        return [
            'id'        => (string) $this->id,
            'msg'       => $this->msg ?? '',
            'msgType'   => $this->msg_type,
            'content'   => $this->content ?? '',
            'thumbnail' => $this->thumbnail ?? '',
            'senderId'  => (int) $this->sender_id,
            'storyId'   => $this->story_id,
            'createdAt' => $this->created_at ? $this->created_at->getTimestamp() * 1000 : null,
        ];
    }
}
