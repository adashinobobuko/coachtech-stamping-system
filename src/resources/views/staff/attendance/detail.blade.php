@extends('staff.layouts.app') {{-- ← 固定する --}}

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
                    @if ($isPending || $isApproved)
                        <span>{{ $clockIn ? $clockIn->format('H:i') : '―' }}</span>
                        <span>～</span>
                        <span>{{ $clockOut ? $clockOut->format('H:i') : '―' }}</span>
                    @else
                        <input type="time" name="clock_in"
                            value="{{ old('clock_in', $clockIn ? $clockIn->format('H:i') : '') }}"
                            class="form-control">
                        <span>～</span>
                        <input type="time" name="clock_out"
                            value="{{ old('clock_out', $clockOut ? $clockOut->format('H:i') : '') }}"
                            class="form-control">
                    @endif
                </div>

                @foreach ($breakPairs as $index => $break)
                    @php
                        $breakStartName = 'break_start_' . ($index + 1);
                        $breakEndName = 'break_end_' . ($index + 1);
                    @endphp

                    <div class="form-group">
                        <label>休憩{{ $index + 1 }}</label>
                        @if ($isPending || $isApproved)
                            {{-- 休憩時間が未設定の場合は「―」を表示 --}}
                            <span>{{ $break['start'] ?? '―' }}</span>
                            <span>～</span>
                            <span>{{ $break['end'] ?? '―' }}</span>
                        @else
                            <input type="time" name="{{ $breakStartName }}"
                                value="{{ old($breakStartName, $break['start'] ?? '') }}"
                                class="form-control" style="width: 100px;">
                            <span>～</span>
                            <input type="time" name="{{ $breakEndName }}"
                                value="{{ old($breakEndName, $break['end'] ?? '') }}"
                                class="form-control" style="width: 100px;">
                        @endif
                    </div>
                @endforeach

                {{-- 備考 --}}
                @php
                    $noteRaw = old('note', $application->note ?? '');
                    // 「備考：」以降だけを抽出（なければ全体表示）
                    if (preg_match('/備考：(.+)/u', $noteRaw, $matches)) {
                        $noteDisplay = trim($matches[1]);
                    } else {
                        $noteDisplay = $noteRaw;
                    }
                @endphp

                <div class="form-group">
                    <label>備考</label>
                    @if ($isAdmin)
                        {{-- 管理者用：noteToDisplay（備考コメントのみ） --}}
                        <div class="form-control" style="background-color: #f9f9f9;">{{ $noteToDisplay }}</div>
                    @elseif ($isPending || $isApproved)
                        {{-- スタッフ用：承認済や承認待ちは noteToDisplay を表示 --}}
                        <div class="form-control" style="background-color: #f9f9f9;">{{ $noteToDisplay }}</div>
                    @else
                        {{-- スタッフ用：申請可能時は note の全体編集 --}}
                        <textarea name="note" class="form-control">{{ old('note', $application->note ?? '') }}</textarea>
                    @endif
                </div>

                {{-- 管理者には不可視、スタッフ用 --}}
                @if (!$isAdmin)
                    @if ($isPending)
                        <div style="margin-top: 20px; color: red;">
                            ※承認待ちのため修正はできません。
                        </div>
                    @elseif ($isApproved)
                        <div class="status-button">
                            <div style="margin-top: 20px; color: white; background-color: black; padding: 10px;">
                                {{-- 承認済みの場合は修正不可 --}}
                                承認済み
                            </div>
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
