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
            <a href="{{ route('staff.index') }}" class="header-logo_a">
                <img src="{{ asset('images/logo.svg') }}" alt="coachtechのロゴ">
            </a>
        </div>
        <div class="taball">
            @if (auth('admin')->check())
                {{-- 管理者用メニュー --}}
                <a href="{{ route('admin.index') }}">勤怠一覧</a>
                <a href="{{ route('admin.staff.list') }}">スタッフ一覧</a>
                <a href="{{ route('admin.application.list') }}">申請一覧</a>
                <div class="logged-out">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-admin').submit();">ログアウト</a>
                    <form id="logout-form-admin" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            @elseif (Auth::check())
                {{-- スタッフ用メニュー --}}
                <a href="{{ route('staff.index') }}">勤怠</a>
                <a href="{{ route('attendance.list') }}">勤怠一覧</a>
                <a href="{{ route('attendance.applications') }}">申請</a>
                <div class="logged-out">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-staff').submit();">ログアウト</a>
                    <form id="logout-form-staff" action="{{ route('staff.logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </div>
            @endif
        </div>
    </div>    
    <main>@yield('content')</main>
</body>
</html>