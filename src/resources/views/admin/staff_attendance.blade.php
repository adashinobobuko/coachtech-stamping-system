@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/staff_attendance_list.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}">
@endsection

@section('content')
    <body>
        <div class="attendance-container">
            <h1>{{ $user->name }}さんの勤怠</h1>

        @php
            $date = \Carbon\Carbon::parse($currentMonth . '-01');
            $prevMonth = $date->copy()->subMonth()->format('Y-m');
            $nextMonth = $date->copy()->addMonth()->format('Y-m');
        @endphp

        <div class="month-navigation">
            <a href="{{ route('admin.staff.attendance', ['id' => $user->id, 'date' => $prevMonth]) }}" class="currentMonth">← 前月</a>
            <span>{{ $date->format('Y年n月') }}</span>
            <a href="{{ route('admin.staff.attendance', ['id' => $user->id, 'date' => $nextMonth]) }}" class="currentMonth">翌月 →</a>
        </div>

    @if ($attendances->isEmpty())
    <p>この月の打刻データはありません。</p>
    @else
    <table class="attendance-table">
        <thead>
            <tr>
                <th>日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($attendances as $attendance)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($attendance->date)->format('m/d(D)') }}</td>
                    <td>{{ $attendance->clock_in ? $attendance->clock_in->format('H:i') : '' }}</td>
                    <td>{{ $attendance->clock_out ? $attendance->clock_out->format('H:i') : '' }}</td>
                    <td>{{ $attendance->break_minutes > 0 ? gmdate('H:i', $attendance->break_minutes * 60) : '' }}</td>
                    <td>{{ $attendance->work_minutes > 0 ? gmdate('H:i', $attendance->work_minutes * 60) : '' }}</td>
                    <td>

                    <a href="{{ route('admin.attendance.editshow', ['id' => $attendance->record_id]) }}">詳細</a>

                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    
    <a href="{{ route('admin.attendance.csv', ['id' => $user->id, 'date' => $currentMonth]) }}" class="btn btn-primary">CSV出力</a>

        </div>
    </body>
@endsection
