@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/show.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}">
@endsection

@section('content')
<div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
    <h2 class="text-xl font-bold mb-6">勤怠詳細</h2>

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
                <input type="time" value="{{ $clockIn ? $clockIn->format('H:i') : '' }}" disabled> 〜
                <input type="time" value="{{ $clockOut ? $clockOut->format('H:i') : '' }}" disabled>
            </td>
        </tr>
        <tr class="border-b">
            <th class="p-2">休憩</th>
            <td class="p-2">
                <input type="time" value="{{ $break1Start }}" disabled> 〜
                <input type="time" value="{{ $break1End }}" disabled>
            </td>
        </tr>
        <tr class="border-b">
            <th class="p-2">休憩2</th>
            <td class="p-2">
                <input type="time" value="{{ $break2Start }}" disabled> 〜
                <input type="time" value="{{ $break2End }}" disabled>
            </td>
        </tr>
        <tr class="border-b">
            <th class="p-2">備考</th>
            <td class="p-2">
                <textarea class="w-full border rounded" rows="3" disabled>{{ $note }}</textarea>
            </td>
        </tr>
    </table>

    <div class="mt-6 text-right">
    {{-- <a href="{{ route('admin.attendance.edit', ['user_id' => $user->id, 'date' => $date->format('Y-m-d')]) }}"
        class="bg-black text-white px-6 py-2 rounded hover:bg-gray-800"> --}}
            修正
        </a>
    </div>
</div>
@endsection
