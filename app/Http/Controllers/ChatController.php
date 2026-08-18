<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * 本地化聊天 API（替代 Firestore 实时数据库 + 客户端直推 FCM）。
 *
 * 所有接口 POST + apikey header（checkHeader 中间件），响应格式与项目
 * 其余接口一致：{status, message, data}。客户端字段命名保持与原
 * Firestore 结构对齐（camelCase），Flutter 模型几乎零改动。
 *
 * 同时这套接口即网页版可直接复用的聊天 API。
 */
class ChatController extends Controller
{
    /**
     * POST /api/chat/getOrCreateConversation
     *   user_id  int  当前用户
     *   peer_id  int  单聊对方（二选一）
     *   room_id  int  房间群聊（二选一）
     *   conversation_id  string  已有会话 id（可选，优先使用）
     */
    public function getOrCreateConversation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }
        $userId = (int) $request->input('user_id');

        $conversationId = $request->input('conversation_id');
        if ($conversationId && !str_starts_with((string) $conversationId, 'room_')) {
            $conversation = Conversation::find((int) $conversationId);
            if ($conversation && ChatService::memberOf($conversation, $userId)) {
                return $this->conversationResponse($conversation, $userId);
            }
        }

        if ($request->filled('room_id')) {
            $conversation = ChatService::ensureRoomConversation((int) $request->input('room_id'));
        } elseif ($request->filled('peer_id')) {
            [$conversation] = ChatService::ensureDmConversation($userId, (int) $request->input('peer_id'));
        } else {
            return GlobalFunction::sendSimpleResponse(false, 'peer_id 或 room_id 必填其一');
        }

        return $this->conversationResponse($conversation, $userId);
    }

    /**
     * POST /api/chat/sendMessage
     *   user_id         int     发送者
     *   conversation_id string  会话 id（与 to_user_id / room_id 三选一）
     *   to_user_id      int     单聊对方（懒创建会话）
     *   room_id         int     房间群聊（懒创建会话）
     *   msg_type        string  TEXT / IMAGE / VIDEO / STORY_REPLY
     *   msg             string  文本内容
     *   content         string  媒体 URL
     *   thumbnail       string  缩略图 URL
     *   story_id        int     回复的动态 id
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
            'msg_type' => ['nullable', 'in:TEXT,IMAGE,VIDEO,STORY_REPLY'],
            'msg' => ['nullable', 'string', 'max:5000'],
            'content' => ['nullable', 'string', 'max:500'],
            'thumbnail' => ['nullable', 'string', 'max:500'],
            'story_id' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $user = User::find((int) $request->input('user_id'));
        if (!$user) {
            return GlobalFunction::sendSimpleResponse(false, '用户不存在');
        }

        // 解析会话
        if ($request->filled('conversation_id') && !str_starts_with((string) $request->input('conversation_id'), 'room_')) {
            $conversation = Conversation::find((int) $request->input('conversation_id'));
        } elseif ($request->filled('to_user_id')) {
            [$conversation] = ChatService::ensureDmConversation((int) $user->id, (int) $request->input('to_user_id'));
        } elseif ($request->filled('room_id')) {
            $conversation = ChatService::ensureRoomConversation((int) $request->input('room_id'));
        } else {
            $conversation = null;
        }

        if (!$conversation) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }

        $member = ChatService::memberOf($conversation, (int) $user->id);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '不是会话成员');
        }

        // 单聊屏蔽校验（沿用 users.block_user_ids）
        if ((int) $conversation->type === Conversation::TYPE_DM) {
            $peerId = (int) ((int) $conversation->dm_user_a === (int) $user->id ? $conversation->dm_user_b : $conversation->dm_user_a);
            $peer = User::find($peerId);
            if ($peer && (ChatService::isBlockedByString($peer->block_user_ids, (int) $user->id)
                || ChatService::isBlockedByString($user->block_user_ids, $peerId))) {
                return GlobalFunction::sendSimpleResponse(false, '无法发送消息（已屏蔽）');
            }
        }

        $message = ChatService::sendMessage($conversation, $user, [
            'msg_type'  => $request->input('msg_type', 'TEXT'),
            'msg'       => $request->input('msg'),
            'content'   => $request->input('content'),
            'thumbnail' => $request->input('thumbnail'),
            'story_id'  => $request->input('story_id'),
        ]);

        return response()->json([
            'status'  => true,
            'message' => '发送成功',
            'data'    => array_merge($message->toClientArray(), [
                'conversationId' => (string) $conversation->id,
            ]),
        ]);
    }

    /**
     * POST /api/chat/fetchMessages
     *   user_id         int
     *   conversation_id string
     *   before_id       string  分页游标（取更早的消息）
     */
    public function fetchMessages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
            'conversation_id' => ['required'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $conversation = Conversation::find((int) $request->input('conversation_id'));
        if (!$conversation) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }

        $user = User::find((int) $request->input('user_id'));
        $member = ChatService::memberOf($conversation, (int) $request->input('user_id'));
        if (!$member || !$user) {
            return GlobalFunction::sendSimpleResponse(false, '不是会话成员');
        }

        $query = ChatMessage::where('conversation_id', $conversation->id)
            ->where('id', '>', (int) $member->cleared_before_id)
            ->orderByDesc('id')
            ->limit(21);

        if ($request->filled('before_id')) {
            $query->where('id', '<', (int) $request->input('before_id'));
        }

        $messages = $query->get();
        $hasMore = $messages->count() > 20;
        $messages = $messages->take(20);

        return response()->json([
            'status'  => true,
            'message' => '获取成功',
            'data'    => [
                'conversationId' => (string) $conversation->id,
                'hasMore'        => $hasMore,
                'messages'       => $messages->map(fn ($m) => $m->toClientArray())->values()->all(),
            ],
        ]);
    }

    /**
     * POST /api/chat/fetchConversations
     *   user_id int
     */
    public function fetchConversations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $user = User::find((int) $request->input('user_id'));
        if (!$user) {
            return GlobalFunction::sendSimpleResponse(false, '用户不存在');
        }

        // 清理已删除房间的残留会话
        try {
            $roomConversationIds = Conversation::where('type', Conversation::TYPE_ROOM)->pluck('room_id', 'id');
            if ($roomConversationIds->isNotEmpty()) {
                $existingRoomIds = DB::table('rooms')->whereIn('id', $roomConversationIds->values())->pluck('id');
                $staleIds = $roomConversationIds->filter(fn ($roomId) => !$existingRoomIds->contains($roomId))->keys();
                if ($staleIds->isNotEmpty()) {
                    ChatMessage::whereIn('conversation_id', $staleIds)->delete();
                    ConversationMember::whereIn('conversation_id', $staleIds)->delete();
                    Conversation::whereIn('id', $staleIds)->delete();
                }
            }
        } catch (\Throwable $e) {
            // 清理失败不影响主流程
        }

        // 房间成员可能在别的端刚加入，同步一遍其所有房间会话
        $roomIds = DB::table('room_users')
            ->where('user_id', $user->id)
            ->whereIn('type', [2, 3, 5])
            ->pluck('room_id');
        foreach ($roomIds as $roomId) {
            try {
                ChatService::ensureRoomConversation((int) $roomId);
            } catch (\Throwable $e) {
                continue;
            }
        }

        $rows = ConversationMember::with('conversation')
            ->where('user_id', $user->id)
            ->where('is_hidden', false)
            ->get()
            ->filter(fn ($m) => $m->conversation)
            ->sortByDesc(fn ($m) => $m->conversation->last_msg_time ?: $m->conversation->created_at);

        $list = $rows->map(fn ($m) => ChatService::conversationPayloadFor($m->conversation, $user, $m))->values()->all();

        return response()->json([
            'status'  => true,
            'message' => '获取成功',
            'data'    => $list,
        ]);
    }

    /**
     * POST /api/chat/markAsRead   {user_id, conversation_id}
     */
    public function markAsRead(Request $request): JsonResponse
    {
        $member = $this->findMember($request);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }
        $member->update(['unread_count' => 0]);
        return GlobalFunction::sendSimpleResponse(true, '已标记已读');
    }

    /**
     * POST /api/chat/setUnread  {user_id, conversation_id, value}
     * value: -1 = 标记未读（列表显示红点），0 = 取消
     */
    public function setUnread(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'value' => ['required', 'integer', 'in:0,-1'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator);
        }
        $member = $this->findMember($request);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }
        $member->update(['unread_count' => (int) $request->input('value')]);
        return GlobalFunction::sendSimpleResponse(true, '已更新');
    }

    /**
     * POST /api/chat/clearChat  {user_id, conversation_id}
     * 清空聊天记录（只影响自己，隐藏 cleared_before_id 之前的消息）
     */
    public function clearChat(Request $request): JsonResponse
    {
        $member = $this->findMember($request);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }
        $maxId = (int) (ChatMessage::where('conversation_id', $member->conversation_id)->max('id') ?? 0);
        $member->update(['cleared_before_id' => $maxId]);
        return GlobalFunction::sendSimpleResponse(true, '已清空');
    }

    /**
     * POST /api/chat/deleteChat  {user_id, conversation_id}
     * 删除会话：列表隐藏，新消息到达后自动重新出现
     */
    public function deleteChat(Request $request): JsonResponse
    {
        $member = $this->findMember($request);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }
        $maxId = (int) (ChatMessage::where('conversation_id', $member->conversation_id)->max('id') ?? 0);
        $member->update([
            'is_hidden'         => true,
            'cleared_before_id' => $maxId,
            'unread_count'      => 0,
        ]);
        return GlobalFunction::sendSimpleResponse(true, '已删除');
    }

    /**
     * POST /api/chat/acceptRequest  {user_id, conversation_id}
     * 接受陌生人的消息请求
     */
    public function acceptRequest(Request $request): JsonResponse
    {
        $member = $this->findMember($request);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }
        $member->update(['request_status' => ConversationMember::REQUEST_ACCEPTED]);
        return GlobalFunction::sendSimpleResponse(true, '已接受');
    }

    /**
     * POST /api/chat/rejectRequest  {user_id, conversation_id}
     * 拒绝消息请求：清空并隐藏
     */
    public function rejectRequest(Request $request): JsonResponse
    {
        $member = $this->findMember($request);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '会话不存在');
        }
        $maxId = (int) (ChatMessage::where('conversation_id', $member->conversation_id)->max('id') ?? 0);
        $member->update([
            'request_status'    => ConversationMember::REQUEST_REJECTED,
            'cleared_before_id' => $maxId,
            'is_hidden'         => true,
            'unread_count'      => 0,
        ]);
        return GlobalFunction::sendSimpleResponse(true, '已拒绝');
    }

    // ------------------------------------------------------------------

    protected function findMember(Request $request): ?ConversationMember
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
            'conversation_id' => ['required'],
        ]);
        if ($validator->fails()) {
            return null;
        }
        return ConversationMember::where('conversation_id', (int) $request->input('conversation_id'))
            ->where('user_id', (int) $request->input('user_id'))
            ->first();
    }

    protected function conversationResponse(Conversation $conversation, int $userId): JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return GlobalFunction::sendSimpleResponse(false, '用户不存在');
        }
        $member = ChatService::memberOf($conversation, $userId);
        if (!$member) {
            return GlobalFunction::sendSimpleResponse(false, '不是会话成员');
        }
        return response()->json([
            'status'  => true,
            'message' => '获取成功',
            'data'    => ChatService::conversationPayloadFor($conversation, $user, $member),
        ]);
    }

    protected function validationError(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        $messages = $validator->errors()->all();
        return response()->json([
            'status'  => false,
            'message' => $messages[0] ?? '提交的数据不合法',
        ]);
    }
}
