<?php

namespace App\Models;

use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public $table = "users";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'identity',
        'full_name',
        'password',
        'login_type',
        'device_type',
        'device_token',
        'email_verified_at',
        'username',
        'bio',
        'interest_ids',
        'profile',
        'background_image',
        'is_push_notifications',
        'is_invited_to_room',
        'is_verified',
        'is_block',
        'block_user_ids',
        'saved_music_ids',
        'saved_reel_ids',
        'following',
        'followers',
        'is_moderator',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * 项目用 identity 字段存邮箱，覆盖默认 email 字段相关方法。
     */
    public function getEmailForVerification()
    {
        return $this->identity;
    }

    /**
     * 找回密码邮件也走 identity 字段。
     */
    public function getEmailForPasswordReset()
    {
        return $this->identity;
    }

    /**
     * 路由找回密码通知到 mail channel，并使用 identity 作为收件邮箱。
     */
    public function routeNotificationForMail($notification = null)
    {
        return $this->identity;
    }

    /**
     * 使用自定义的邮箱验证通知。
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail());
    }

    /**
     * 使用自定义的找回密码通知。
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPassword($token));
    }

    public function post()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class, 'user_id', 'id');
    }
}
