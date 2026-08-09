<?php

return [
    'App_Name' => 'Chatter',

    // 邮箱验证通知
    'email_verification_subject' => '验证您的 :app 邮箱地址',
    'email_verification_line1'   => '请点击下方按钮验证您的邮箱地址。',
    'email_verification_action'  => '验证邮箱',
    'email_verification_line2'   => '如果您没有注册账号，请忽略此邮件。',

    // 找回密码通知
    'reset_password_subject' => '重置您的 :app 密码',
    'reset_password_line1'   => '您收到此邮件是因为我们收到了针对您账号的密码重置请求。',
    'reset_password_action'  => '重置密码',
    'reset_password_line2'   => '此密码重置链接将在 60 分钟后失效。',
    'reset_password_line3'   => '如果您没有请求重置密码，请忽略此邮件。',

    // 邮箱认证 API 返回
    'email_already_registered'  => '该邮箱已被注册。',
    'verification_link_sent'    => '若邮箱存在，验证邮件已发送。',
    'login_credentials_mismatch'=> '邮箱或密码错误。',
    'please_verify_to_sign_in'  => '请先验证邮箱后再登录。',
    'login_success'             => '登录成功。',
    'email_already_verified'    => '邮箱已验证，可直接登录。',
    'reset_link_sent'           => '若邮箱存在，重置链接已发送。',
    'validation_failed'         => '提交的数据不合法。',
    'mail_config_missing'       => '（服务器尚未配置邮件服务，请联系管理员。）',

    // 验证 / 重置结果页文案
    'verify_success_title'  => '邮箱验证成功',
    'verify_success_body'   => '您的邮箱已验证，现在可以返回 App 登录。',
    'verify_failed_title'   => '链接无效或已过期',
    'verify_failed_body'    => '此验证链接已失效，请在 App 中重新发送验证邮件。',
    'reset_success_title'   => '密码重置成功',
    'reset_success_body'    => '您现在可以使用新密码返回 App 登录。',
    'reset_failed_title'    => '密码重置失败',
    'reset_failed_body'     => '链接已失效或凭据不正确，请重新申请找回密码。',

    // 重置密码表单
    'email_label'             => '邮箱',
    'password_label'          => '新密码',
    'confirm_password_label'  => '确认密码',
];
