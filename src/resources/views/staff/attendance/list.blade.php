@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
    <link rel="stylesheet" href="{{ asset('css/attendance_list.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
        <div style="margin-top: 50px;">

            <div class="title-text-wrapper">
                <h2 class="title-text">勤怠一覧</h2>
            </div>

                {{-- 月選択 --}}
            <div class="month-nav">
                <button class="btn" onclick="location.href='{{ route('attendance.list', ['month' => $prevMonth]) }}'">← 前月</button>
                <span class="current-month"><i class="material-icons">event</i>  <!-- カレンダーアイコン -->
                {{ $startOfMonth->format('Y/m') }}</span>
                <button class="btn" onclick="location.href='{{ route('attendance.list', ['month' => $nextMonth]) }}'">翌月 →</button>
            </div>

            {{-- 打刻一覧テーブル --}}
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
                    @foreach ($attendances as $date => $data)
                        <tr>
                            <td class="attendance-in">{{ \Carbon\Carbon::parse($date)->format('m/d') }}（{{ ['日','月','火','水','木','金','土'][\Carbon\Carbon::parse($date)->dayOfWeek] }}）</td>
                            <td class="attendance-in">{{ $data['clock_in'] ?? '' }}</td>
                            <td class="attendance-in">{{ $data['clock_out'] ?? '' }}</td>
                            <td class="attendance-in">{{ $data['break'] ?? '0:00' }}</td>
                            <td class="attendance-in">{{ $data['total'] }}</td>
                            <td>
                            @if (isset($data['id']))
                                <a href="{{ route('staff.attendance.show', ['id' => $data['id']]) }}" class="btn-detail">詳細</a>
                            @else
                                <span class="btn-detail-disabled">詳細なし</span>
                            @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </body>
@endsection
