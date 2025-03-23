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
            @if (Auth::check())
            <div class="logged-out">
                <a href=""  onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    ログアウト
                </a>
                <form id="logout-form" action="" method="POST" style="display: none;">
                        @csrf
                </form>
            </div>
            @else
            <div class="tologin">
                <a href="">ログイン</a>
            </div>
            @endif
        </div>        
    </div>    
    <main>@yield('content')</main>
</body>
</html>