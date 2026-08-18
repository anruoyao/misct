<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use Google\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 本地化聊天核心服务（替代 Firestore chats/users 集合）。
 *
 * 职责：
 * - 会话的查找 / 懒创建（单聊、房间群聊）
 * - 房间成员变动后同步 conversation_members
 * - 消息发送（写库 + 未读数 + WS 广播 + 离线 FCM 兜底）
 * - 输出与原 Firestore 字段结构一致的客户端 payload，
 *   Flutter 端模型基本无需改动
 */
class ChatService
{
    /**
     * 确保用户拥有 ws_key（用户私有 WS 频道密钥），没有则生成。
     */
    public static function ensureWsKey(User $user): string
    {
        if (empty($user->ws_key)) {
            $user->ws_key = bin2hex(random_bytes(16));
            $user->save();
        }
        return $user->ws_key;
    }

    /**
     * 查找或创建单聊会话。
     * 返回 [Conversation, bool $created]
     */
    public static function ensureDmConversation(int $userA, int $userB): array
    {
        $a = min($userA, $userB);
        $b = max($userA, $userB);

        $conversation = Conversation::where('type', Conversation::TYPE_DM)
            ->where('dm_user_a', $a)
            ->where('dm_user_b', $b)
            ->first();

        if ($conversation) {
            return [$conversation, false];
        }

        return [DB::transaction(function () use ($a, $b) {
            // 并发兜底：唯一索引冲突时重查
            try {
                $conversation = Conversation::create([
                    'type'        => Conversation::TYPE_DM,
                    'dm_user_a'   => $a,
                    'dm_user_b'   => $b,
                    'channel_key' => bin2hex(random_bytes(16)),
                ]);
            } catch (\Throwable $e) {
                $conversation = Conversation::where('type', Conversation::TYPE_DM)
                    ->where('dm_user_a', $a)
                    ->where('dm_user_b', $b)
                    ->first();
                if (!$conversation) {
                    throw $e;
                }
                return $conversation;
            }

            $userA = User::find($a);
            $userB = User::find($b);

            // 请求状态沿用原 Firestore 逻辑：
            // 对方关注了我（或互关）=> 正常会话；否则落到对方的"消息请求"
            $bFollowsA = $userA && $userB && self::isFollowing($b, $a);

            ConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $a,
                'request_status'  => ConversationMember::REQUEST_ACCEPTED,
            ]);
            ConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $b,
                'request_status'  => ConversationMember::REQUEST_ACCEPTED,
            ]);

            // b 的行：如果 b 没有关注 a，b 侧显示为"消息请求"
            if (!$bFollowsA) {
                ConversationMember::where('conversation_id', $conversation->id)
                    ->where('user_id', $b)
                    ->update(['request_status' => ConversationMember::REQUEST_PENDING]);
            }

            return $conversation;
        }), true];
    }

    /**
     * 查找或创建房间群聊会话，并把 room_users 中已是成员的人
     * 同步进 conversation_members。房间成员变动（加入/退出/移除）
     * 后都应调用本方法。
     */
    public static function ensureRoomConversation(int $roomId): Conversation
    {
        $conversation = Conversation::where('type', Conversation::TYPE_ROOM)
            ->where('room_id', $roomId)
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'type'        => Conversation::TYPE_ROOM,
                'room_id'     => $roomId,
                'channel_key' => bin2hex(random_bytes(16)),
            ]);
        }

        self::syncRoomMembers($conversation);

        return $conversation;
    }

    /**
     * 同步房间成员（room_users type in [2,3,5] 视为正式成员）。
     * 新成员补建行（保留已有行的未读/清空状态），已退出的删除行。
     */
    public static function syncRoomMembers(Conversation $conversation): void
    {
        $roomUsers = DB::table('room_users')
            ->where('room_id', $conversation->room_id)
            ->whereIn('type', [2, 3, 5]) // 2 成员 / 3 协管 / 5 管理员
            ->get();

        $memberUserIds = $roomUsers->pluck('user_id')->all();
        $existing = ConversationMember::where('conversation_id', $conversation->id)->get()->keyBy('user_id');

        foreach ($roomUsers as $roomUser) {
            if (!$existing->has($roomUser->user_id)) {
                ConversationMember::create([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $roomUser->user_id,
                    'request_status'  => ConversationMember::REQUEST_ACCEPTED,
                    'is_mute'         => $roomUser->is_mute == 1,
                ]);
            } elseif ($existing[$roomUser->user_id]->is_mute != ($roomUser->is_mute == 1)) {
                $existing[$roomUser->user_id]->update(['is_mute' => $roomUser->is_mute == 1]);
            }
        }

        // 移除已不是房间成员的行
        ConversationMember::where('conversation_id', $conversation->id)
            ->whereNotIn('user_id', $memberUserIds)
            ->delete();
    }

    public static function isFollowing(int $followerId, int $targetId): bool
    {
        return DB::table('following_lists')
            ->where('my_user_id', $followerId)
            ->where('user_id', $targetId)
            ->exists();
    }

    /**
     * 校验用户是否为会话成员（房间会话会先同步一次成员）。
     */
    public static function memberOf(Conversation $conversation, int $userId): ?ConversationMember
    {
        if ((int) $conversation->type === Conversation::TYPE_ROOM) {
            self::syncRoomMembers($conversation);
        }
        return ConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 发送消息（写库 + 计数 + 实时推送 + 离线推送）。
     *
     * @return ChatMessage
     */
    public static function sendMessage(Conversation $conversation, User $sender, array $data): ChatMessage
    {
        $message = DB::transaction(function () use ($conversation, $sender, $data) {
            $message = ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $sender->id,
                'msg_type'        => $data['msg_type'] ?? 'TEXT',
                'msg'             => $data['msg'] ?? null,
                'content'         => $data['content'] ?? null,
                'thumbnail'       => $data['thumbnail'] ?? null,
                'story_id'        => $data['story_id'] ?? null,
            ]);

            $preview = self::messagePreview($message);

            $conversation->last_msg = $preview;
            $conversation->last_msg_id = $message->id;
            $conversation->last_msg_time = $message->created_at ?? now();
            $conversation->save();

            // 其他成员：未读 +1、取消隐藏；发送者：未读清零、标记可见
            ConversationMember::where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $sender->id)
                ->update([
                    'unread_count' => DB::raw('unread_count + 1'),
                    'is_hidden'    => false,
                ]);
            ConversationMember::where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['unread_count' => 0, 'is_hidden' => false]);

            return $message;
        });

        // ---- 实时推送（失败不影响消息发送结果）----
        try {
            $payload = $message->toClientArray();

            // 1) 会话频道：正在这个聊天页面的设备
            WsBroadcaster::publish($conversation->channelName(), 'message.new', $payload);

            // 2) 每个成员的用户频道：更新会话列表 + 弹通知
            $members = ConversationMember::with('conversation')
                ->where('conversation_id', $conversation->id)
                ->get();

            $senderName = $sender->full_name ?? '';
            $isRoom = (int) $conversation->type === Conversation::TYPE_ROOM;
            $title = $isRoom ? self::roomTitle($conversation) : $senderName;
            $bodyPreview = self::messagePreview($message);

            foreach ($members as $member) {
                if ((int) $member->user_id === (int) $sender->id) {
                    continue;
                }
                if ($member->is_mute) {
                    continue; // 房间静默的成员只计数不推送
                }

                $recipient = User::find($member->user_id);
                if (!$recipient) {
                    continue;
                }

                $conversationPayload = self::conversationPayloadFor($conversation, $recipient, $member);
                WsBroadcaster::publish(self::userChannel($recipient), 'conversation.updated', $conversationPayload);

                $online = WsBroadcaster::isOnline((int) $recipient->id);
                if ($online) {
                    // 在线：App 通过 WS 事件直接弹系统通知
                    WsBroadcaster::publish(self::userChannel($recipient), 'chat.notification', [
                        'title'          => $title,
                        'body'           => $bodyPreview,
                        'conversationId' => (string) $conversation->id,
                    ]);
                } elseif (!empty($recipient->device_token) && (int) $recipient->is_push_notifications === 1) {
                    // 离线：走 FCM（海外/具备 Google 服务的设备），大陆离线暂无通道
                    self::fcmSend(
                        $recipient->device_token,
                        (int) $recipient->device_type,
                        $title,
                        $bodyPreview,
                        ['conversationId' => (string) $conversation->id]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Chat push failed: ' . $e->getMessage());
        }

        return $message;
    }

    /**
     * 用户私有频道名。
     */
    public static function userChannel(User $user): string
    {
        return 'user.' . $user->id . '.' . self::ensureWsKey($user);
    }

    /**
     * 会话列表 / 会话详情 payload（字段名对齐原 Firestore 结构）。
     *
     * type 含义（对"我"而言）：0 = 消息请求待处理，1 = 正常单聊，2 = 房间群聊
     */
    public static function conversationPayloadFor(Conversation $conversation, User $forUser, ?ConversationMember $member = null): array
    {
        $member = $member ?: self::memberOf($conversation, (int) $forUser->id);
        $isRoom = (int) $conversation->type === Conversation::TYPE_ROOM;

        $payload = [
            'conversationId'   => (string) $conversation->id,
            'conversationKey'  => $conversation->channel_key,
            'type'             => $isRoom ? 2 : (($member && (int) $member->request_status === ConversationMember::REQUEST_PENDING) ? 0 : 1),
            'lastMsg'          => (string) ($conversation->last_msg ?? ''),
            'newMsgCount'      => (int) ($member->unread_count ?? 0),
            'time'             => ($conversation->last_msg_time ?: $conversation->created_at ?: now())->getTimestamp() * 1000,
            'isDeleted'        => false,
            'deletedId'        => (string) ($member->cleared_before_id ?? 0),
            'requestStatus'    => (int) ($member->request_status ?? 1),
            'isHidden'         => (bool) ($member->is_hidden ?? false),
            'userIdOrRoomId'   => null,
            'title'            => '',
            'profileImage'     => '',
            'iBlocked'         => false,
            'iAmBlocked'       => false,
            'usersIds'         => null,
            'isMute'           => (bool) ($member->is_mute ?? false),
        ];

        if ($isRoom) {
            $room = DB::table('rooms')->where('id', $conversation->room_id)->first();
            $payload['userIdOrRoomId'] = (int) $conversation->room_id;
            $payload['title'] = $room->title ?? '';
            $payload['profileImage'] = $room->photo ?? '';
            $payload['usersIds'] = ConversationMember::where('conversation_id', $conversation->id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } else {
            $peerId = (int) ((int) $conversation->dm_user_a === (int) $forUser->id ? $conversation->dm_user_b : $conversation->dm_user_a);
            $peer = User::find($peerId);
            $payload['userIdOrRoomId'] = $peerId;
            $payload['title'] = $peer->full_name ?? '';
            $payload['profileImage'] = $peer->profile ?? '';

            $myBlocks = ($forUser->block_user_ids ?? '');
            $peerBlocks = ($peer->block_user_ids ?? '');
            $payload['iBlocked'] = self::isBlockedByString($myBlocks, $peerId);
            $payload['iAmBlocked'] = self::isBlockedByString($peerBlocks, (int) $forUser->id);
        }

        // 清空聊天后：若最后一条消息早于清空位置，列表展示空文案
        if ($member && $conversation->last_msg_id && (int) $member->cleared_before_id >= (int) $conversation->last_msg_id) {
            $payload['lastMsg'] = '';
        }

        return $payload;
    }

    /**
     * 判断 block_user_ids 逗号串中是否包含目标用户。
     */
    public static function isBlockedByString(?string $blockIds, int $targetId): bool
    {
        if (!$blockIds) {
            return false;
        }
        return in_array((string) $targetId, array_filter(explode(',', $blockIds)), true);
    }

    protected static function roomTitle(Conversation $conversation): string
    {
        return (string) (DB::table('rooms')->where('id', $conversation->room_id)->value('title') ?? '');
    }

    protected static function messagePreview(ChatMessage $message): string
    {
        switch ($message->msg_type) {
            case 'IMAGE':
                return 'Image';
            case 'VIDEO':
                return 'Video';
            default:
                return (string) ($message->msg ?? '');
        }
    }

    /**
     * FCM HTTP v1 发送（无 die()，异常安全；仅离线兜底用）。
     */
    protected static function fcmSend(string $deviceToken, int $deviceType, string $title, string $body, array $data = []): void
    {
        try {
            if (!file_exists(base_path('googleCredentials.json'))) {
                return;
            }

            $client = new Client();
            $client->setAuthConfig(base_path('googleCredentials.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $token = $client->fetchAccessTokenWithAssertion()['access_token'] ?? '';
            if (!$token) {
                return;
            }

            $credentials = json_decode(file_get_contents(base_path('googleCredentials.json')), true);
            $url = 'https://fcm.googleapis.com/v1/projects/' . $credentials['project_id'] . '/messages:send';

            $notification = ['title' => $title, 'body' => $body];
            $message = [
                'token' => $deviceToken,
                'data'  => array_merge($data, ['title' => $title, 'body' => $body]),
                'apns'  => ['payload' => ['aps' => ['sound' => 'default']]],
            ];
            if ($deviceType === 1) { // iOS 需要 notification 节点才能弹横幅
                $message['notification'] = $notification;
            }

            Http::withToken($token)->timeout(5)->post($url, ['message' => $message]);
        } catch (\Throwable $e) {
            Log::info('FCM fallback send failed: ' . $e->getMessage());
        }
    }
}
