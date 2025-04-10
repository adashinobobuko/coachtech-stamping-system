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
                    <input type="time" name="break_start" value="{{ $break1Start }}"> 〜
                    <input type="time" name="break_end" value="{{ $break1End }}">
                </td>
            </tr>
            <tr class="border-b">
                <th class="p-2">休憩2</th>
                <td class="p-2">
                    <input type="time" name="break_start2" value="{{ $break2Start }}"> 〜
                    <input type="time" name="break_end2" value="{{ $break2End }}">
                </td>
            </tr>
            <tr class="border-b">
                <th class="p-2">備考</th>
                <td class="p-2">
                    <textarea name="note" class="w-full border rounded" rows="3">{{ $note }}</textarea>
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
