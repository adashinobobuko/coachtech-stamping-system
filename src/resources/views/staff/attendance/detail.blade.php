@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
        <div style="margin-top: 50px;">
            <h2 class="title-text">勤怠詳細・修正申請</h2>

            {{-- 成功メッセージの表示 --}}
            @if(session('success'))
                <div class="alert alert-success" style="margin-top: 20px;">
                    {{ session('success') }}
                </div>
            @endif

            {{-- 修正フォーム --}}
            <form method="POST" action="{{ route('attendance.update', ['id' => $record->id]) }}">
                @csrf

                {{-- 名前 --}}
                <div class="form-group">
                    <label>名前</label>
                    <input type="text" name="name" value="{{ $record->user->name }}" class="form-control" readonly>
                </div>

                {{-- 日付 --}}
                <div class="form-group">
                    <label>日付</label>
                    <input type="text" name="date" value="{{ $record->timestamp->format('Y年n月j日') }}" class="form-control" readonly>
                </div>

                {{-- 出勤時間 --}}
                <div class="form-group">
                    <label>出勤時間</label>
                    <input type="text" name="clock_in"
                        value="{{ old('clock_in', $clockIn ? $clockIn->format('H:i') : '') }}"
                        class="form-control">
                </div>

                {{-- 退勤時間 --}}
                <div class="form-group">
                    <label>退勤時間</label>
                    <input type="text" name="clock_out"
                        value="{{ old('clock_out', $clockOut ? $clockOut->format('H:i') : '') }}"
                        class="form-control">
                </div>

                {{-- 休憩1回目 --}}
                @if (!empty($breakPairs[0]))
                <div class="form-group">
                    <label>休憩</label>
                        <input type="text" name="break_start"
                            value="{{ old('break_start', $breakPairs[0]['start']) }}"
                            class="form-control" style="width: 100px;">
                        <span>～</span>
                        <input type="text" name="break_end"
                            value="{{ old('break_end', $breakPairs[0]['end']) }}"
                            class="form-control" style="width: 100px;">
                </div>
                @endif

                {{-- 休憩2回目 --}}
                @if (!empty($breakPairs[1]))
                <div class="form-group">
                    <label>休憩2</label>
                        <input type="text" name="break_start2"
                            value="{{ old('break_start2', $breakPairs[1]['start']) }}"
                            class="form-control" style="width: 100px;">
                        <span>～</span>
                        <input type="text" name="break_end2"
                            value="{{ old('break_end2', $breakPairs[1]['end']) }}"
                            class="form-control" style="width: 100px;">
                </div>
                @endif


                {{-- 備考 --}}
                <div class="form-group">
                    <label>備考</label>
                    <textarea name="note" class="form-control">{{ old('note', isset($application) ? $application->note : '') }}</textarea>
                </div>

                {{-- 修正申請ボタン --}}
                <div class="btn-container">
                    <button type="submit" class="btn btn-submit">修正申請</button>
                </div>
            </form>

            {{-- 戻るリンク --}}
            <div style="margin-top: 20px;">
                <a href="{{ route('attendance.list') }}">勤怠一覧に戻る</a> |
                <a href="{{ route('attendance.applications') }}">申請一覧に戻る</a>
            </div>
        </div>
    </body>
@endsection
