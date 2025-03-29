@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
        <div style="margin-top: 50px;">
            <h2 class="title-text">勤怠一覧</h2>

            {{-- 月選択 --}}
            <div class="month-nav">
                <a href="{{ route('attendance.list', ['month' => $prevMonth]) }}" class="btn">← 前月</a>
                <span class="current-month">{{ $startOfMonth->format('Y年m月') }}</span>
                <a href="{{ route('attendance.list', ['month' => $nextMonth]) }}" class="btn">翌月 →</a>
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
                            <td>{{ $date }}</td>
                            <td>{{ $data['clock_in'] ?? '--:--' }}</td>
                            <td>{{ $data['clock_out'] ?? '--:--' }}</td>
                            <td>{{ $data['break'] ?? '0:00' }}</td>
                            <td>{{ $data['total'] }}</td>
                            <td>
                            @if (isset($data['id']))
                                <a href="{{ route('staff.attendance.detail', ['id' => $data['id']]) }}" class="btn-detail">詳細</a>
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
