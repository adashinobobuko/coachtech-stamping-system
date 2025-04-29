@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/show.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}">
@endsection

@section('content')

{{-- タイトルを外に出す --}}
<div class="page-title">
    <h2 class="title-text">勤怠詳細</h2>
</div>

<div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
    {{-- ↓ 白いコンテナの中は勤怠データだけ --}}
    <form method="POST" action="{{ route('admin.attendance.update', ['id' => $record->id]) }}">
        @csrf

        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <table class="table-auto w-full text-left border border-collapse border-gray-300">
            <tr class="border-b">
                <th class="p-2 w-1/3">名前</th>
                <td class="p-2">{{ $user->name }}</td>
            </tr>
            <tr class="border-b">
                <th class="p-2">日付</th>
                <td class="p-2">{{ $date->year }}年{{ $date->month }}月{{ $date->day }}日</td>
            </tr>
            <tr class="border-b">
                <th class="p-2">出勤・退勤</th>
                <td class="p-2">
                    <input type="time" name="clock_in" value="{{ $clockIn ? $clockIn->format('H:i') : '' }}"> 〜
                    <input type="time" name="clock_out" value="{{ $clockOut ? $clockOut->format('H:i') : '' }}">
                </td>
            </tr>
            <tr class="border-b">
                <th class="p-2">休憩</th>
                <td class="p-2">
                    @if (count($breakPairs) > 0)
                        @foreach ($breakPairs as $index => $pair)
                            @php
                                $breakStartName = 'break_start_' . ($index + 1);
                                $breakEndName = 'break_end_' . ($index + 1);
                            @endphp
                            <div style="margin-bottom: 8px;">
                                <label>休憩{{ $index + 1 }}</label>
                                <input type="time" name="{{ $breakStartName }}" value="{{ $pair['start'] ?? '' }}" style="width: 100px;">
                                〜
                                <input type="time" name="{{ $breakEndName }}" value="{{ $pair['end'] ?? '' }}" style="width: 100px;">
                            </div>
                        @endforeach
                    @endif
                </td>
            </tr>
            <tr class="border-b">
                <th class="p-2">備考</th>
                <td class="p-2">
                    <textarea name="note" class="w-full border rounded" rows="3">{{ old('note', $note) }}</textarea>
                </td>
            </tr>     
        </table>
    </div> {{-- ★ここで白カード終了 --}}

    {{-- 修正ボタンを外に出す --}}
    <div class="btn-container">
        <button type="submit" class="btn btn-submit">修正</button>
    </div>

    </form>
</div>
@endsection
