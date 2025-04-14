@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/staff_attendance_list.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}">
@endsection

@section('content')
    <body>
        <div class="attendance-container">
            <h1>{{ $user->name }}さんの勤怠</h1>

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
                        <a href="{{ route('admin.attendance.show', ['id' => $user->id, 'date' => $attendance->date]) }}">
                            詳細
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

        </div>
    </body>
@endsection
