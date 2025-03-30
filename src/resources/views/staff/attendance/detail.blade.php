@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
        <div style="margin-top: 50px;">
            <h2 class="title-text">勤怠詳細・修正申請</h2>
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

                {{-- 休憩開始時間 --}}
                <div class="form-group">
                    <label>休憩開始時間</label>
                    <input type="text" name="break_start"
                        value="{{ old('break_start', $breakStart ? $breakStart->format('H:i') : '') }}"
                        class="form-control">
                </div>

                {{-- 休憩終了時間 --}}
                <div class="form-group">
                    <label>休憩終了時間</label>
                    <input type="text" name="break_end"
                        value="{{ old('break_end', $breakEnd ? $breakEnd->format('H:i') : '') }}"
                        class="form-control">
                </div>

                {{-- 備考 --}}
                <div class="form-group">
                    <label>備考</label>
                    <textarea name="note" class="form-control">{{ old('note', $record->note) }}</textarea>
                </div>

                {{-- 修正申請ボタン --}}
                <div class="btn-container">
                    <button type="submit" class="btn btn-submit">修正</button>
                </div>
            </form>
        </div>
    </body>
@endsection
