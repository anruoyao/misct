<?php

namespace App\Http\Controllers;
use App\Models\Admin;
use App\Models\GlobalFunction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Crypt;

class LoginController extends Controller
{

    function login()
    {
        $setting = Setting::first();
        if ($setting) {
            Session::put('app_name', $setting->app_name);
        }
        if (Session::get('user_name')) {
            return redirect('/index');
        }
        return view('login');
    }

    public function checklogin(Request $request)
    {
        $data = Admin::where('user_name', $request->user_name)->first();

        if ($data) {
            try {
                $decryptedPassword = Crypt::decrypt($data->user_password);

                if ($request->user_password === $decryptedPassword) {
                    $request->session()->put('user_name', $data->user_name);
                    $request->session()->put('user_type', $data->user_type);

                    return GlobalFunction::sendDataResponse(true, '登录成功。', $data);
                }
            } catch (\Exception $e) {
                return GlobalFunction::sendSimpleResponse(false, '密码解密失败。');
            }
        }

        return GlobalFunction::sendSimpleResponse(false, '账号或密码错误。');
    }

    public function forgotPasswordForm(Request $request)
    {
        $databaseUsername = env('DB_USERNAME');
        $databasePassword = env('DB_PASSWORD');

        if ($request->database_username == $databaseUsername && $request->database_password == $databasePassword) {

            $encryptedPassword = Crypt::encrypt($request->new_password);

            $admin = Admin::where('user_name', 'admin')->first();

            if (!$admin) {
                return GlobalFunction::sendSimpleResponse(false, '未找到管理员。');
            }

            $admin->user_password = $encryptedPassword;
            $admin->save();

            return GlobalFunction::sendSimpleResponse(true, '密码更新成功。');
        } else {
            return GlobalFunction::sendSimpleResponse(false, '账号或密码错误。');
        }
    }



    function logout()
    {

        session()->pull('user_name');
        session()->pull('user_type');
        return  redirect(url('/'));
    }
}
