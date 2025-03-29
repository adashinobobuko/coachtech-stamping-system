@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
        <div style="margin-top: 50px;">
            <h2 class="title-text">勤怠詳細</h2>

            <div class="attendance-detail-container">
                <table class="attendance-detail-table">
                    {{-- 名前 --}}
                    <tr>
                        <th>名前</th>
                        <td>{{ $record->user->name }}</td>
                    </tr>

                    {{-- 日付 --}}
                    <tr>
                        <th>日付</th>
                        <td>{{ $record->timestamp->format('Y年n月j日') }}</td>
                    </tr>

                    {{-- 出勤・退勤 --}}
                    <tr>
                        <th>出勤・退勤</th>
                        <td>
                            {{ $clockIn ? $clockIn->format('H:i') : '--:--' }} 〜
                            {{ $clockOut ? $clockOut->format('H:i') : '--:--' }}
                        </td>
                    </tr>

                    {{-- 休憩 --}}
                    <tr>
                        <th>休憩</th>
                        <td>
                            {{ $breakStart ? $breakStart->format('H:i') : '--:--' }} 〜
                            {{ $breakEnd ? $breakEnd->format('H:i') : '--:--' }}
                        </td>
                    </tr>

                    {{-- 備考 --}}
                    <tr>
                        <th>備考</th>
                        <td>{{ $record->note ?? 'なし' }}</td>
                    </tr>
                </table>

                {{-- 修正ボタン --}}
                <div class="btn-container">
                    <a href="{{ route('attendance.edit', ['id' => $record->id]) }}" class="btn btn-edit">修正</a>
                </div>
            </div>
        </div>
    </body>
@endsection
