@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
        <div style="margin-top: 100px;">
            <div class="status-badge">勤務外</div>

            <h2 class="date-text">{{ now()->format('Y年n月j日 (D)') }}</h2>

            <div id="clock">--:--</div>

            <form method="POST" action="{{ route('attendance.store') }}">
                @csrf
                <input type="hidden" name="type" value="clock_in">
                <button type="submit" class="btn">出勤</button>
            </form>
        </div>

        <script>
            function updateClock() {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                document.getElementById('clock').textContent = `${h}:${m}`;
            }

            setInterval(updateClock, 1000);
            updateClock();
        </script>
    </body>
@endsection