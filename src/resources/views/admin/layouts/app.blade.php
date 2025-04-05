<!DOCTYPE html>
<html lang="jp">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CoachtechStampingSystem</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @yield('css')
</head>
<body>
    <div class="header">
        <div class="header-logo">
            <a href="" class="header-logo_a">
                <img src="{{ asset('images/logo.svg') }}" alt="coachtechのロゴ">
            </a>
        </div>
        <div class="taball">
            <a href="{{ route('admin.index') }}">勤怠一覧</a>
            <a href="{{ route('admin.staff.list') }}">スタッフ一覧</a>
            <a href="">申請一覧</a>
            @if (Auth::check())
            <div class="logged-out">
                <form id="logout-form" action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-red-600 hover:underline">ログアウト</button>
                </form>
            </div>
            @else
            <div class="tologin">
                <a href="{{ route('admin.login') }}">ログイン</a>
            </div>
            @endif
        </div>        
    </div>    
    <main>@yield('content')</main>
</body>
</html>