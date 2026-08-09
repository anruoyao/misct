<?php

return [
    'App_Name' => 'Chatter',

    // 邮箱验证通知
    'email_verification_subject' => 'Verify your email address for :app',
    'email_verification_line1'   => 'Please click the button below to verify your email address.',
    'email_verification_action'  => 'Verify Email',
    'email_verification_line2'   => 'If you did not create an account, no further action is required.',

    // 找回密码通知
    'reset_password_subject' => 'Reset your password for :app',
    'reset_password_line1'   => 'You are receiving this email because we received a password reset request for your account.',
    'reset_password_action'  => 'Reset Password',
    'reset_password_line2'   => 'This password reset link will expire in 60 minutes.',
    'reset_password_line3'   => 'If you did not request a password reset, no further action is required.',

    // 邮箱认证 API 返回
    'email_already_registered'  => 'This email is already registered.',
    'verification_link_sent'    => 'If the email exists, a verification link has been sent.',
    'login_credentials_mismatch'=> 'Email or password is incorrect.',
    'please_verify_to_sign_in'  => 'Please verify your email before signing in.',
    'login_success'             => 'Signed in successfully.',
    'email_already_verified'    => 'Email already verified. You can sign in directly.',
    'reset_link_sent'           => 'If the email exists, a password reset link has been sent.',
    'validation_failed'         => 'Submitted data is invalid.',
    'mail_config_missing'       => '(Email service is not configured on the server. Please contact the administrator.)',

    // 验证 / 重置结果页文案
    'verify_success_title'  => 'Email Verified',
    'verify_success_body'   => 'Your email has been verified. You can now return to the app and sign in.',
    'verify_failed_title'   => 'Invalid or Expired Link',
    'verify_failed_body'    => 'This verification link is invalid or has expired. Please resend the verification email from the app.',
    'reset_success_title'   => 'Password Reset',
    'reset_success_body'    => 'Your password has been reset. You can now return to the app and sign in with the new password.',
    'reset_failed_title'    => 'Password Reset Failed',
    'reset_failed_body'     => 'The link has expired or the credentials are invalid. Please request a new password reset link.',

    // 重置密码表单
    'email_label'             => 'Email',
    'password_label'          => 'New Password',
    'confirm_password_label'  => 'Confirm Password',
];
