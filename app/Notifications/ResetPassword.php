<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 自定义找回密码通知。
 *
 * 默认的 Illuminate\Auth\Notifications\ResetPassword 会把重置链接指向前端
 * /password/reset/{token} 路由。本项目是 Flutter App + API 后端，没有前端
 * 页面，这里把链接指向后端 GET /api/resetPassword/{token}，由
 * EmailAuthController@showResetForm 渲染一个简单 HTML 表单，POST 提交到
 * /api/resetPassword 完成密码重置。用户无需 App 即可在邮件里完成重置。
 */
class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $url = url(route('api.resetPassword.show', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]));

        return (new MailMessage())
            ->subject(trans('string.reset_password_subject', ['app' => config('app.name')]))
            ->line(trans('string.reset_password_line1'))
            ->action(trans('string.reset_password_action'), $url)
            ->line(trans('string.reset_password_line2'))
            ->line(trans('string.reset_password_line3'));
    }
}
