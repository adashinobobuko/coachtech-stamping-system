@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
        <div style="margin-top: 100px;">
            {{-- ステータスバッジを表示 --}}
            <div class="status-badge">
                {{ $status }}
            </div>

            {{-- 日付の表示 --}}
            <h2 class="date-text">{{ now()->format('Y年n月j日 (D)') }}</h2>

            {{-- 時計の表示 --}}
            <p id="clock">{{ now()->format('H:i') }}</p>

            {{-- ボタンの表示・非表示 --}}
            @if ($status === '勤務外' || $status === '退勤済')
                {{-- 出勤ボタン --}}
                <form method="POST" action="{{ route('attendance.store') }}">
                    @csrf
                    <input type="hidden" name="type" value="clock_in">
                    <button type="submit" class="btn">出勤</button>
                </form>
            @elseif ($status === '出勤中')
            <div class="button-group">
                {{-- 退勤ボタン --}}
                <form method="POST" action="{{ route('attendance.store') }}">
                    @csrf
                    <input type="hidden" name="type" value="clock_out">
                    <button type="submit" class="btn">退勤</button>
                </form>
                {{-- 休憩開始ボタン --}}
                <form method="POST" action="{{ route('attendance.store') }}">
                    @csrf
                    <input type="hidden" name="type" value="break_start">
                    <button type="submit" class="white-btn">休憩入</button>
                </form>
            </div>
            @elseif ($status === '休憩中')
                {{-- 休憩終了ボタン --}}
                <form method="POST" action="{{ route('attendance.store') }}" style="margin-top: 10px;">
                    @csrf
                    <input type="hidden" name="type" value="break_end">
                    <button type="submit" class="white-btn">休憩戻</button>
                </form>
            @endif
            
            @if (session('success'))
                <div class="alert alert-success" style="margin-top: 10px;">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger" style="margin-top: 10px;">
                    {{ session('error') }}
                </div>
            @endif
        </div>

        <script>
            function updateClock() {
                const now = new Date();
                const jstTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
                const h = String(jstTime.getHours()).padStart(2, '0');
                const m = String(jstTime.getMinutes()).padStart(2, '0');
                document.getElementById('current-time').textContent = `${h}:${m}`;
            }

            setInterval(updateClock, 1000);
            updateClock();
        </script>
    </body>
@endsection
