<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <title>{!! Session::get('app_name') !!}</title>
    <!-- General CSS Files -->
    <link rel="stylesheet" href="{{ asset('asset/css/app.min.css') }}">
    <link rel="stylesheet" href="{{ asset('asset/bundles/bootstrap-social/bootstrap-social.css') }}">
    <link rel="stylesheet" href="{{ asset('asset/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('asset/style/app.css') }}">
    <link rel="stylesheet" href="{{ asset('asset/cdncss/iziToast.css') }}" type="text/css" />
    <!-- Template CSS -->
    <link rel="stylesheet" href="{{ asset('asset/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('asset/css/components.css') }}">
    <!-- Custom style CSS -->
    <link rel="stylesheet" href="{{ asset('asset/css/loginPage.css') }}">
    <link rel='shortcut icon' type='image/x-icon' href="{{ asset('asset/img/favicon.png') }}" style="width: 2px !important;" />
</head>

<body>
    <div class="loader"></div>

    <div class="main-login-row">
        <div class="width-50 ">
            <div class="main-login-two-box">
                <div class="img-full-box" style="background-image: url(./asset/img/login.jpg)">
                    <div class="center-title-text">
                        <div class="bottom-blur-inner">
                            <h1 class="main-title font-70 gil-heavy"> {!! Session::get('app_name') !!} </h1>
                            <div class="width-c-50">
                                <p class="m-0 font-20 text-contant gil-reg">
                                    Always be there, <br> even when you're far away.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="width-50 ">
            <div class="main-login-one-box  center">
                <div class="center container ">
                    <div class="form-login-main-box ">
                        @if (Session::has('message'))
                        <div class="center-h alert-err fixed-alert mb-4 ">
                            <div class="d-flex ">
                                <div class="px-2 m-0 center ">
                                    <iconify-icon icon="ep:warning-filled" class="font-alert"></iconify-icon>
                                </div>
                                <div class="center">
                                    <span
                                        class="m-0 alert-title-salon gil-reg font-16">{{ Session::get('message') }}</span>
                                </div>
                            </div>
                        </div>
                        @endif

                        <h1 class="login-headr font-30 gil-heavy m-0">{{ __('logIn')}}</h1>
                        <h2 class="login-title font-20 gil-reg mb-4">{{ __('toAccessTheDashboard')}}</h2>

                        <form method="POST" action="login" id="loginForm">
                            @csrf
                            <div class="form-x-box main-card ">
                                <div>
                                    <div class="d-flex flex-column mb-3 w-100 form-group">
                                        <label for="Username" class="gil-med font-18 text-salon-black">{{ __('username')}}</label>
                                        <input name="user_name" type="text" class="form-control gil-med font-18 px-3" required id="user_name">
                                    </div>
                                    <div class="d-flex flex-column mb-3 w-100">
                                        <label for="password" class="gil-med font-18 text-salon-black">{{ __('password')}}</label>
                                        <div class="position-relative">
                                            <input name="user_password" type="password" class="form-control gil-med font-18 px-3" required id="user_password">
                                            <div class="password-icon">
                                                <i data-feather="eye" class="eye1"></i>
                                                <i data-feather="eye-off" class="eye-off1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn theme-btn text-white gil-med">
                                    {{ __('logIn')}}
                                </button>
                                <hr>
                                <div class="">
                                    <div class="text-center">
                                        <a href="javascript:;" class="gil-med font-18" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal"> {{ __('Forgot Password')}}? </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5 fw-normal" id="exampleModalLabel"> {{ __('Forgot Password')}} </h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="forgotPasswordForm" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="database_username" class="form-label">{{ __('Database Username')}}</label>
                            <input class="form-control" type="text" id="database_username" name="database_username" placeholder="Enter your database username" required="">
                        </div>
                        <div class="mb-3">
                            <label for="database_password" class="form-label">{{ __('Database Password')}}</label>
                            <div class="position-relative">
                                <input type="password" id="database_password" name="database_password" class="form-control" placeholder="Enter your database password" required="">
                                <div class="password-icon">
                                    <i data-feather="eye" class="eye4"></i>
                                    <i data-feather="eye-off" class="eye-off4"></i>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">{{ __('New Password') }}</label>
                            <div class="position-relative">
                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter new your password" required="">
                                <div class="password-icon">
                                    <i data-feather="eye" class="eye2"></i>
                                    <i data-feather="eye-off" class="eye-off2"></i>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">{{ __('Confirm Password') }}</label>
                            <div class="position-relative">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Enter confirm your password" required="">
                                <div class="password-icon">
                                    <i data-feather="eye" class="eye3"></i>
                                    <i data-feather="eye-off" class="eye-off3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close')}}</button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('Save')}}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <input type="hidden" value="{{ env('APP_URL')}}" id="appUrl">

    <script src="{{ asset('asset/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('asset/js/app.min.js') }}"></script>
    <script src="{{ asset('asset/script/app.js') }}"></script>
    <script src="{{ asset('asset/cdnjs/iziToast.min.js') }}"></script>
    <!-- JS Libraies -->
    <!-- Template JS File -->
    <script src="{{ asset('asset/js/scripts.js') }}"></script>
    <script src="{{ asset('asset/script/env.js') }}"></script>
    <!-- Custom JS File -->
    <script src="{{ asset('asset/js/custom.js') }}"></script>
    <script src="{{ asset('asset/script/login.js') }}"></script>
</body>

</html>