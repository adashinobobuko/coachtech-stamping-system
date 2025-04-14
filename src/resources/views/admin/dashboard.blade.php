@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ time() }}" />
@endsection

@section('content')
<div class="container mx-auto p-4">
    <h2 class="text-xl font-bold mb-4"> {{ $date->format('Y年n月j日') }}の勤怠</h2>

    <div class="flex justify-between items-center mb-4">
        <a href="{{ route('admin.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}" class="text-blue-500">← 前日</a>
        <span class="text-lg font-semibold">{{ $date->format('Y/m/d') }}</span>
        <a href="{{ route('admin.index', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}" class="text-blue-500">翌日 →</a>
    </div>

    <table class="w-full border border-gray-300 text-center">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-2 py-1">名前</th>
                <th class="border px-2 py-1">出勤</th>
                <th class="border px-2 py-1">退勤</th>
                <th class="border px-+-+-2 py-1">休憩</th>
                <th class="border px-2 py-1">合計</th>
                <th class="border px-2 py-1">詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($attendances as $attendance)
            <tr>
                <td class="border px-2 py-1">{{ $attendance->user->name }}</td>
                <td>{{ $attendance->clock_in ? $attendance->clock_in->format('H:i') : '' }}</td>
                <td>{{ $attendance->clock_out ? $attendance->clock_out->format('H:i') : '' }}</td>
                <td>{{ gmdate('H:i', $attendance->break_minutes * 60) }}</td>
                <td>{{ gmdate('H:i', $attendance->work_minutes * 60) }}</td>
                <td class="border px-2 py-1">
                <td class="border px-2 py-1">
                    <a href="{{ route('admin.attendance.show', ['id' => $attendance->user->id, 'date' => $date->format('Y-m-d')]) }}" class="text-blue-500">詳細</a>
                </td>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection