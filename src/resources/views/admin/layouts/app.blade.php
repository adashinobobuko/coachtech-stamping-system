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

        @if (auth('admin')->check())
            <div class="taball">
                <a href="{{ route('admin.index') }}">勤怠一覧</a>
                <a href="{{ route('admin.staff.list') }}">スタッフ一覧</a>
                <a href="{{ route('admin.application.list') }}">申請一覧</a>
                <div class="logged-out">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-staff').submit();">ログアウト</a>
                    <form id="logout-form-staff" action="{{ route('staff.logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            </div>
        @endif
    </div>
    <main>@yield('content')</main>
</body>
</html>