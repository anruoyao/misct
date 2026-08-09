<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * 自定义邮箱验证通知。
 *
 * 默认的 Illuminate\Auth\Notifications\VerifyEmail 会生成指向 web 前端
 * /email/verify/{id}/{hash} 的链接，但本项目是 Flutter App + API 后端，
 * 没有可用的前端路由。这里把验证链接指向后端 GET /api/verifyEmail，
 * 用户点击邮件即可完成验证，由 EmailAuthController@verify 渲染结果页。
 */
class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage())
            ->subject(trans('string.email_verification_subject', ['app' => config('app.name')]))
            ->line(trans('string.email_verification_line1'))
            ->action(trans('string.email_verification_action'), $verificationUrl)
            ->line(trans('string.email_verification_line2'));
    }

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'api.verifyEmail',
            now()->addMinutes(60),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
