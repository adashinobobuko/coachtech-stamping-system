@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/show.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
    <h2 class="text-xl font-bold mb-6">勤怠詳細</h2>

    <form method="POST" action="{{ route('admin.attendance.update', ['id' => $record->id]) }}">
        @csrf

        {{-- エラーメッセージを上にまとめて全部表示 --}}
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
                <td class="p-2">{{ $date->year }}年　{{ $date->month }}月　{{ $date->day }}日</td>
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

        <div class="mt-6 text-right">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-500">
                修正
            </button>
        </div>
    </form>
</div>
@endsection
