<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .card {
            max-width: 420px;
            margin: 60px auto;
            background: #fff;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .06);
        }
        h1 { font-size: 20px; margin: 0 0 20px; color: #111827; text-align: center; }
        label { display: block; font-size: 13px; color: #374151; margin: 12px 0 6px; }
        input[type="email"], input[type="password"] {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            font-size: 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
        }
        input:focus { border-color: #2563eb; }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #2563eb;
            color: #fff;
            font-size: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $title }}</h1>
        <form action="{{ $action }}" method="POST">
            <label>{{ $emailLabel }}</label>
            <input type="email" name="email" value="{{ $email }}" readonly>
            <input type="hidden" name="token" value="{{ $token }}">
            <label>{{ $passwordLabel }}</label>
            <input type="password" name="password" required minlength="8">
            <label>{{ $confirmLabel }}</label>
            <input type="password" name="password_confirmation" required minlength="8">
            <button type="submit">{{ $submitLabel }}</button>
        </form>
    </div>
</body>
</html>
