<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * 邮箱账号本地化认证控制器。
 *
 * 替代原先的 Firebase 邮箱登录：注册 / 登录 / 邮箱验证 / 找回密码 / 重置密码。
 * 密码以 bcrypt 哈希存到 users.password 字段；邮箱验证和找回密码通过 Laravel
 * 内置的签名 URL + Password Broker 完成，邮件链接直接指回后端的 GET 接口。
 *
 * 社交登录（Google / Apple）仍走原 addUser 接口，不受影响。
 *
 * 注意：为兼容前端 ApiService（非 2xx 会抛异常且不调用 completion），
 * 这里所有失败都返回 HTTP 200 + status=false，校验失败也走同样的格式。
 */
class EmailAuthController extends Controller
{
    /**
     * 邮箱注册。
     *
     * POST /api/emailRegister
     *   identity        string  邮箱
     *   password        string  密码（≥ 8 位）
     *   full_name       string  昵称
     *   device_token    string  推送 token
     *   device_type     int     0=Android / 1=iOS
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identity'     => ['required', 'email', 'max:255'],
            'password'     => ['required', 'string', PasswordRule::min(8)],
            'full_name'    => ['required', 'string', 'max:255'],
            'device_token' => ['nullable', 'string', 'max:500'],
            'device_type'  => ['nullable', 'integer', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();

        $exists = User::where('identity', $data['identity'])->exists();
        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => trans('string.email_already_registered'),
            ]);
        }

        $user = new User();
        $user->identity      = $data['identity'];
        $user->full_name     = $data['full_name'];
        $user->password      = Hash::make($data['password']);
        $user->login_type    = 2; // 邮箱登录
        $user->device_type   = $data['device_type'] ?? 0;
        $user->device_token  = $data['device_token'] ?? '';
        $user->save();

        // 发送邮箱验证邮件。SMTP 未配置或发送失败不影响注册本身，
        // 用户可以稍后通过 /resendVerification 重发。
        $this->safeSendMail(function () use ($user) {
            $user->sendEmailVerificationNotification();
        });

        // 重新读出，保证字段、默认值齐全
        $user = User::find($user->id);

        return response()->json([
            'status'  => true,
            'message' => trans('string.verification_link_sent'),
            'data'    => $user,
        ]);
    }

    /**
     * 邮箱登录。
     *
     * POST /api/emailLogin
     *   identity        string  邮箱
     *   password        string  密码
     *   device_token    string  推送 token
     *   device_type     int     0=Android / 1=iOS
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identity'     => ['required', 'email'],
            'password'     => ['required', 'string'],
            'device_token' => ['nullable', 'string', 'max:500'],
            'device_type'  => ['nullable', 'integer', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();

        $user = User::where('identity', $data['identity'])->first();

        if (!$user || !$user->password || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => trans('string.login_credentials_mismatch'),
            ]);
        }

        // 仅 login_type=2 的邮箱账号才走邮箱验证流程
        if ((int) $user->login_type === 2 && !$user->hasVerifiedEmail()) {
            return response()->json([
                'status'         => false,
                'message'        => trans('string.please_verify_to_sign_in'),
                'need_verify'    => true,
                'identity'       => $user->identity,
            ]);
        }

        // 登录成功：刷新设备信息
        $user->device_type  = $data['device_type'] ?? $user->device_type;
        $user->device_token = $data['device_token'] ?? $user->device_token;
        $user->save();

        return response()->json([
            'status'  => true,
            'message' => trans('string.login_success'),
            'data'    => $user,
        ]);
    }

    /**
     * 重发邮箱验证邮件。
     *
     * POST /api/resendVerification
     *   identity  string  邮箱
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identity' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $user = User::where('identity', $request->input('identity'))->first();
        if (!$user) {
            // 不暴露存在性，统一返回成功
            return response()->json([
                'status'  => true,
                'message' => trans('string.verification_link_sent'),
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status'  => false,
                'message' => trans('string.email_already_verified'),
            ]);
        }

        $mailSent = $this->safeSendMail(function () use ($user) {
            $user->sendEmailVerificationNotification();
        });

        return response()->json([
            'status'  => true,
            'message' => $mailSent
                ? trans('string.verification_link_sent')
                : trans('string.verification_link_sent') . ' ' . trans('string.mail_config_missing'),
        ]);
    }

    /**
     * 用户点击邮件里的验证链接会落到这里（GET）。
     *
     * GET /api/verifyEmail/{id}/{hash}
     * 由 Laravel 签名 URL 保护，签名不匹配或过期会自动 403。
     */
    public function verify(Request $request, $id, $hash)
    {
        if (!URL::hasValidSignature($request)) {
            return $this->renderHtml(
                trans('string.verify_failed_title'),
                trans('string.verify_failed_body'),
                false
            );
        }

        $user = User::find($id);
        if (!$user) {
            return $this->renderHtml(
                trans('string.verify_failed_title'),
                trans('string.verify_failed_body'),
                false
            );
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return $this->renderHtml(
                trans('string.verify_failed_title'),
                trans('string.verify_failed_body'),
                false
            );
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->renderHtml(
            trans('string.verify_success_title'),
            trans('string.verify_success_body'),
            true
        );
    }

    /**
     * 发送密码重置邮件。
     *
     * POST /api/forgotPassword
     *   identity  string  邮箱
     *
     * 注：Password Broker 默认按 email 字段查用户，本项目 users 表
     * 用 identity 存邮箱。这里手动按 identity 查到用户后，再调用
     * broker 的 token repository 生成 token，并通过 User 模型上
     * 重写的 sendPasswordResetNotification 发邮件。
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identity' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $user = User::where('identity', $request->input('identity'))->first();
        if ($user) {
            $token = Password::broker()->createToken($user);
            $this->safeSendMail(function () use ($user, $token) {
                $user->sendPasswordResetNotification($token);
            });
        }

        return response()->json([
            'status'  => true,
            'message' => trans('string.reset_link_sent'),
        ]);
    }

    /**
     * 密码重置邮件里的链接落到这里（GET），渲染一个简单的 HTML 表单。
     *
     * GET /api/resetPassword/{token}?email=xxx
     */
    public function showResetForm(Request $request, $token)
    {
        $email = htmlspecialchars($request->query('email', ''), ENT_QUOTES);
        $token = htmlspecialchars($token, ENT_QUOTES);
        $action = route('api.resetPassword.submit');
        $title = trans('string.reset_password_action');
        $emailLabel = trans('string.email_label');
        $passwordLabel = trans('string.password_label');
        $confirmLabel = trans('string.confirm_password_label');
        $submitLabel = $title;

        return response()->view('auth.reset-password', [
            'token'         => $token,
            'email'         => $email,
            'action'        => $action,
            'title'         => $title,
            'emailLabel'    => $emailLabel,
            'passwordLabel' => $passwordLabel,
            'confirmLabel'  => $confirmLabel,
            'submitLabel'   => $submitLabel,
        ]);
    }

    /**
     * 提交密码重置。
     *
     * POST /api/resetPassword
     *   token                 string
     *   email                 string  邮箱（实际是 users.identity）
     *   password              string  新密码
     *   password_confirmation string
     *
     * 注：Password::broker()->reset() 内部会按 email 字段查用户，但本项目
     * users 表用 identity 存邮箱。这里手动按 email 参数查到用户后，直接
     * 用 token repository 校验 token 并更新密码。
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'confirmed', PasswordRule::min(8)],
        ]);

        if ($validator->fails()) {
            return $this->renderHtml(
                trans('string.reset_failed_title'),
                trans('string.reset_failed_body'),
                false
            );
        }

        $user = User::where('identity', $request->input('email'))->first();
        if (!$user) {
            return $this->renderHtml(
                trans('string.reset_failed_title'),
                trans('string.reset_failed_body'),
                false
            );
        }

        // 直接查 password_resets 表校验 token。
        // Laravel 9 的 PasswordBroker 没有暴露 tokenRepository() 方法，
        // 用 DB facade 操作 password_resets 表最简单可靠。
        // 注意：Laravel 默认的 DatabaseTokenRepository 使用 Hash::make() 存 token，
        // 所以库里是 bcrypt 哈希，必须用 Hash::check() 比较，不能用 sha256 + hash_equals。
        $tokenRecord = \DB::table('password_resets')
            ->where('email', $user->getEmailForPasswordReset())
            ->first();

        if (!$tokenRecord || !Hash::check($request->input('token'), $tokenRecord->token)) {
            return $this->renderHtml(
                trans('string.reset_failed_title'),
                trans('string.reset_failed_body'),
                false
            );
        }

        // 检查 token 是否过期（config/auth.php 中 expire=60 分钟）
        $expiresAt = now()->subMinutes(config('auth.passwords.users.expire', 60));
        if (\Carbon\Carbon::parse($tokenRecord->created_at)->lt($expiresAt)) {
            return $this->renderHtml(
                trans('string.reset_failed_title'),
                trans('string.reset_failed_body'),
                false
            );
        }

        $user->password = Hash::make($request->input('password'));
        $user->save();

        // 删除已使用的 token
        \DB::table('password_resets')
            ->where('email', $user->getEmailForPasswordReset())
            ->delete();

        return $this->renderHtml(
            trans('string.reset_success_title'),
            trans('string.reset_success_body'),
            true
        );
    }

    /**
     * 校验失败时统一返回 200 + status=false，方便前端处理。
     */
    private function validationError(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        $messages = $validator->errors()->all();
        $msg = $messages[0] ?? trans('string.validation_failed');

        return response()->json([
            'status'  => false,
            'message' => $msg,
        ]);
    }

    /**
     * 安全发送邮件：捕获异常并记录日志，不影响主流程。
     * SMTP 未配置时邮件发送会失败，但注册/找回密码等操作本身仍应成功。
     *
     * @return bool 是否发送成功
     */
    private function safeSendMail(callable $callback): bool
    {
        try {
            $callback();
            return true;
        } catch (\Throwable $e) {
            Log::error('Mail send failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return false;
        }
    }

    /**
     * 简单渲染一个 HTML 结果页（验证 / 重置完成）。
     */
    private function renderHtml(string $title, string $body, bool $success): \Illuminate\Http\Response
    {
        $color = $success ? '#16a34a' : '#dc2626';
        $icon  = $success ? '&#10003;' : '&#10007;';
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
<style>
  body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; background:#f5f5f5; margin:0; padding:0; }
  .card { max-width: 420px; margin: 80px auto; background:#fff; border-radius:12px; padding:32px; box-shadow:0 4px 14px rgba(0,0,0,.06); text-align:center; }
  .icon { width:64px; height:64px; border-radius:50%; margin:0 auto 16px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:32px; background:{$color}; }
  h1 { font-size:20px; margin:0 0 12px; color:#111827; }
  p  { font-size:14px; color:#6b7280; margin:0; line-height:1.6; }
</style>
</head>
<body>
  <div class="card">
    <div class="icon">{$icon}</div>
    <h1>{$title}</h1>
    <p>{$body}</p>
  </div>
</body>
</html>
HTML;

        return response($html);
    }
}
