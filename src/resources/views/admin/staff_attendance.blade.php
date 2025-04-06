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
        @php
            use Carbon\Carbon;
            $month = $currentMonth ?? now()->format('Y-m');
            $daysInMonth = Carbon::createFromFormat('Y-m', $month)->daysInMonth;
        @endphp

        @for ($day = 1; $day <= $daysInMonth; $day++)
        @php
            $date = sprintf('%s-%02d', $month, $day);
            $dayAttendances = $attendances->filter(function ($item) use ($date) {
                return \Carbon\Carbon::parse($item->timestamp)->format('Y-m-d') === $date;
            });

            $in = $dayAttendances->firstWhere('type', 'clock_in');
            $out = $dayAttendances->firstWhere('type', 'clock_out');

            $breakPairs = $dayAttendances->filter(function ($item) {
                return in_array($item->type, ['break_start', 'break_end']);
            })->sortBy('timestamp')->values();

            $breakMinutes = 0;
            for ($i = 0; $i < $breakPairs->count(); $i += 2) {
                if (
                    isset($breakPairs[$i]) && $breakPairs[$i]->type === 'break_start' &&
                    isset($breakPairs[$i + 1]) && $breakPairs[$i + 1]->type === 'break_end'
                ) {
                    $breakMinutes += $breakPairs[$i + 1]->timestamp->diffInMinutes($breakPairs[$i]->timestamp);
                }
            }

            $breakTime = $breakMinutes > 0 ? gmdate('H:i', $breakMinutes * 60) : '-';

            $totalMinutes = 0;
            if ($in && $out) {
                $worked = $out->timestamp->diffInMinutes($in->timestamp);
                $totalMinutes = max($worked - $breakMinutes, 0);
            }

            $total = $totalMinutes > 0 ? gmdate('H:i', $totalMinutes * 60) : '-';
        @endphp

            <tr>
                <td>{{ Carbon::parse($date)->format('m/d(D)') }}</td>
                <td>{{ $in ? $in->timestamp->format('H:i') : '-' }}</td>
                <td>{{ $out ? $out->timestamp->format('H:i') : '-' }}</td>
                <td>{{ $breakTime }}</td>
                <td>{{ $total }}</td>
                <td>
                    <a href="{{ route('admin.attendance.show', ['id' => $user->id, 'date' => $date]) }}">詳細</a>
                </td>
            </tr>
        @endfor
    </tbody>
</table>

        </div>
    </body>
@endsection
