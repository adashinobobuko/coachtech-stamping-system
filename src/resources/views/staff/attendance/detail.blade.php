@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
    <link rel="stylesheet" href="{{ asset('css/attendance_detail.css') }}?v={{ time() }}" />
@endsection

@section('content')

@php
    $record = $record ?? $application->attendance ?? null;
@endphp

@php
    $isAdmin = auth('admin')->check();
@endphp

    <body>
        <div style="margin-top: 50px;">
            <h2 class="title-text">勤怠詳細・修正申請</h2>

            @if ($errors->any())
            <div class="alert alert-danger" style="margin-top: 20px;">
                <ul style="margin: 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif


            {{-- 成功メッセージの表示 --}}
            @if(session('success'))
                <div class="alert alert-success" style="margin-top: 20px;">
                    {{ session('success') }}
                </div>
            @endif

            {{-- 修正フォーム --}}
            <form method="POST" action="{{ route('attendance.update', ['id' => $record->id ?? $application->attendance->id]) }}">
            <input type="hidden" name="is_correction" value="1">
                @csrf

                {{-- 名前 --}}
                <div class="form-group">
                    <label>名前</label>
                    <span>{{ $record->user->name ?? $application->attendance->user->name }}</span>
                </div>

                {{-- 日付 --}}
                <div class="form-group">
                    <label>日付</label>
                    <span>{{ ($record->timestamp ?? $application->attendance->timestamp)->format('Y年n月j日') }}</span>
                </div>

                {{-- 出勤時間・退勤時間 --}}
                <div class="form-group">
                    <label>出勤・退勤</label>
                    <input type="time" name="clock_in"
                        value="{{ old('clock_in', $clockIn ? $clockIn->format('H:i') : '') }}"
                        class="form-control"><span>～</span>
                    <input type="time" name="clock_out"
                        value="{{ old('clock_out', $clockOut ? $clockOut->format('H:i') : '') }}"
                        class="form-control" @if($isPending) readonly @endif>
                </div>

                {{-- 休憩1回目 --}}
                @if (!empty($breakPairs[0]))
                <div class="form-group">
                    <label>休憩</label>
                        <input type="time" name="break_start_1"
                            value="{{ old('break_start_1', $breakPairs[0]['start'] ?? '') }}"
                            class="form-control" style="width: 100px;">
                        <span>～</span>
                        <input type="time" name="break_end_1"
                            value="{{ old('break_end_1', $breakPairs[0]['end'] ?? '') }}"
                            class="form-control" style="width: 100px;" @if($isPending) readonly @endif>
                </div>
                @endif

                {{-- 休憩2回目 --}}
                @if (!empty($breakPairs[1]))
                <div class="form-group">
                    <label>休憩2</label>
                        <input type="time" name="break_start_2"
                            value="{{ old('break_start_2', $breakPairs[1]['start'] ?? '') }}"
                            class="form-control" style="width: 100px;">
                        <span>～</span>
                        <input type="time" name="break_end_2"
                            value="{{ old('break_end_2', $breakPairs[1]['end'] ?? '') }}"
                            class="form-control" style="width: 100px;" @if($isPending) readonly @endif>
                </div>
                @endif


                {{-- 備考 --}}
                <div class="form-group">
                    <label>備考</label>
                    <textarea name="note" class="form-control" @if($isPending) readonly @endif>{{ old('note', $application->note ?? '') }}</textarea>
                </div>

                {{-- 申請対象日 --}}
                @if (!empty($application))
                    <div class="form-group">
                        <label>申請対象日</label>
                        <input type="text"
                            value="{{ \Carbon\Carbon::parse($application->old_time)->format('Y年n月j日') }}"
                            class="form-control"
                            readonly>
                    </div>
                @endif

                {{-- 管理者には不可視、スタッフ用 --}}
                @if (!$isAdmin)
                    @if ($isPending)
                        <div style="margin-top: 20px; color: red;">
                            ※承認待ちのため修正はできません。
                        </div>
                    @else
                        <div class="btn-container">
                            <button type="submit" class="btn btn-submit">修正申請</button>
                        </div>
                    @endif
                @endif
            </form>

            {{-- スタッフには不可視、管理者用 --}}
            @if ($isAdmin && $application?->status === '承認待ち')
                <form method="POST" action="{{ route('admin.attendance.approve', ['id' => $application->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-success">承認</button>
                </form>

                <form method="POST" action="{{ route('admin.attendance.reject', ['id' => $application->id]) }}" style="margin-top: 10px;">
                    @csrf
                    <button type="submit" class="btn btn-danger">却下</button>
                </form>
            @endif

            {{-- エラーメッセージ --}}
                @if(session('error'))
                    <div class="alert alert-danger" style="margin-top: 20px;">
                        {{ session('error') }}
                    </div>
                @endif


            {{-- 戻るリンク --}}
            <div style="margin-top: 20px;">
                <a href="{{ route('attendance.list') }}">勤怠一覧に戻る</a> |
                <a href="{{ route('attendance.applications') }}">申請一覧に戻る</a>
            </div>
        </div>
    </body>
@endsection
