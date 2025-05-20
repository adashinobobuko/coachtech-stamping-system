@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
    <link rel="stylesheet" href="{{ asset('css/attendance_detail.css') }}?v={{ time() }}" />
@endsection

@section('content')

@php
    $record = $record ?? $application->attendance ?? null;
    $isAdmin = auth('admin')->check();
@endphp

<body>
    <div class="wrapper">
        <div class="page-title">
            <h2 class="title-text">勤怠詳細・修正申請</h2>
        </div>

        {{-- 修正フォーム全体 --}}
        <form method="POST" action="{{ route('attendance.update', ['id' => $record->id ?? $application->attendance->id]) }}">
            @csrf
            <input type="hidden" name="is_correction" value="1">

            <div class="container" style="margin-top: 50px;">
                {{-- エラーメッセージ --}}
                @if ($errors->any())
                    <div class="alert alert-danger" style="margin-top: 20px;">
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- 成功メッセージ --}}
                @if(session('success'))
                    <div class="alert alert-success" style="margin-top: 20px;">
                        {{ session('success') }}
                    </div>
                @endif

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

                {{-- 出勤・退勤時間 --}}
                <div class="form-group">
                    <label>出勤・退勤</label>
                    @if ($isPending || $isApproved || $hasModification)
                        <span>{{ $clockIn ? $clockIn->format('H:i') : '―' }}</span>
                        <div class="tilde">～</div>
                        <span>{{ $clockOut ? $clockOut->format('H:i') : '―' }}</span>
                    @else
                        <input type="time" name="clock_in" value="{{ old('clock_in', $clockIn ? $clockIn->format('H:i') : '') }}" class="form-control">
                        <div class="tilde">～</div>
                        <input type="time" name="clock_out" value="{{ old('clock_out', $clockOut ? $clockOut->format('H:i') : '') }}" class="form-control">
                    @endif
                </div>

                {{-- 休憩時間 --}}
                @foreach ($breakPairs as $index => $break)
                    @php
                        $breakStartName = 'break_start_' . ($index + 1);
                        $breakEndName = 'break_end_' . ($index + 1);
                    @endphp
                    <div class="form-group">
                        <label>休憩{{ $index + 1 }}</label>
                        @if ($isPending || $isApproved || $hasModification)
                            <span>{{ $break['start'] ?? '―' }}</span>
                            <div class="tilde">～</div>
                            <span>{{ $break['end'] ?? '―' }}</span>
                        @else
                            <input type="time" name="{{ $breakStartName }}" value="{{ old($breakStartName, $break['start'] ?? '') }}" class="form-control" style="width: 100px;">
                            <div class="tilde">～</div>
                            <input type="time" name="{{ $breakEndName }}" value="{{ old($breakEndName, $break['end'] ?? '') }}" class="form-control" style="width: 100px;">
                        @endif
                    </div>
                @endforeach

            {{-- 備考 --}}
            <div class="form-group">
                <label>備考</label>
                @if ($isAdmin || $isPending || $isApproved || $hasModification)
                    {{-- 管理者または申請中・承認済み・修正済みの場合 --}}
                    <div class="form-control" style="background-color: #f9f9f9; white-space: pre-wrap;">
                        {{ $noteToDisplay }}
                    </div>
                @else
                    <textarea name="note" class="form-control">{{ old('note', $application->note ?? '') }}</textarea>
                @endif
            </div>

            </div>
            {{-- containerここまで --}}

            {{-- 修正ボタン（コンテナの外） --}}
            @if (!$isAdmin && !$isPending && !$isApproved && !$hasModification)
            <div class="btn-container-fixed">
                <button type="submit" class="btn-submit" form="attendance-update-form">修正</button>
            </div>
            @endif

        </form>

        {{-- 管理者用 承認ボタン --}}
        @if ($isAdmin)
            @if ($application?->status === '承認待ち')
                <form method="POST" action="{{ route('admin.attendance.approve', ['id' => $application->id]) }}">
                    @csrf
                    <div class="btn-container">
                        <button type="submit" class="btn btn-success">承認</button>
                    </div>
                </form>
            @elseif ($application?->status === '承認')
                <div class="status-button-outside">
                    <button class="btn-approved" disabled>承認済み</button>
                </div>
            @endif
        @endif

        {{-- 申請中・承認済みメッセージ --}}
        @if (!$isAdmin)
            @if ($isPending)
                <div class="notice-message">※承認待ちのため修正はできません。</div>
            @elseif ($isApproved)
                <div class="notice-message">※承認済みのため、修正はできません。</div>
            @elseif ($hasModification)
                <div class="notice-message">※管理者によって修正済みのため、修正はできません。</div>
            @endif
        @endif

        {{-- エラーメッセージ --}}
        @if(session('error'))
            <div class="alert alert-danger" style="margin-top: 20px;">
                {{ session('error') }}
            </div>
        @endif

    </div>
</body>
@endsection
