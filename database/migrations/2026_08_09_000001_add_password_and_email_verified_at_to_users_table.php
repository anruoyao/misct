<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 把 Firebase 邮箱登录本地化所需的字段补到 users 表。
     * - password: 本地 bcrypt 密码哈希（仅 login_type=2 的邮箱用户使用）
     * - email_verified_at: 邮箱验证时间戳（null 表示未验证）
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // 旧 users 表既无 password 也无 email_verified_at，新增字段
            if (!Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->after('identity');
            }
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('password');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
            if (Schema::hasColumn('users', 'password')) {
                $table->dropColumn('password');
            }
        });
    }
};
