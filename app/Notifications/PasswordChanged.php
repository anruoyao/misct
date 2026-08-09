<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 密码被修改后的安全通知。
 *
 * 用户通过找回密码流程成功修改密码后，发送此邮件告知账号所有者。
 * 若用户本人操作，邮件起到确认作用；若非本人操作，用户可及时察觉
 * 并通过找回密码重新夺回账号控制权。
 */
class PasswordChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage())
            ->subject(trans('string.password_changed_subject', ['app' => config('app.name')]))
            ->line(trans('string.password_changed_line1'))
            ->line(trans('string.password_changed_line2'))
            ->line(trans('string.password_changed_line3'));
    }
}
