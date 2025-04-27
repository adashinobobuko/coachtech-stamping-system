@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ time() }}" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <style>

    .date-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f4f3f7;
        padding: 0.5rem 2rem;
        border-radius: 8px;
        font-weight: bold;
        max-width: 500px;
        margin: 0 auto 1rem;
    }
    </style>
@endsection

@section('content')
<div class="container mx-auto p-4">
    <h2 class="text-xl font-bold mb-4"> {{ $date->format('Y年n月j日') }}の勤怠</h2>

    <div class="flex justify-center mb-6">
        <div class="date-nav">
            <a href="{{ route('admin.index', ['date' => $date->copy()->subDay()->format('Y-m-d')]) }}" class="text-purple-700">← 前日</a>

            <div class="flex items-center gap-2">
                <span class="material-icons text-base">calendar_today</span>
                {{ $date->format('Y/m/d') }}
            </div>

            <a href="{{ route('admin.index', ['date' => $date->copy()->addDay()->format('Y-m-d')]) }}" class="text-purple-700">翌日 →</a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl shadow-mdb g-white">
        <table class="w-full text-center">
            <thead class="bg-gray-50 text-gray-500 text-sm font-bold">
                <tr>
                    <th class="py-3">名前</th>
                    <th class="py-3">出勤</th>
                    <th class="py-3">退勤</th>
                    <th class="py-3">休憩</th>
                    <th class="py-3">合計</th>
                    <th class="py-3">詳細</th>
                </tr>
            </thead>
            <tbody class="text-gray-700 text-sm">
                @foreach ($attendances as $attendance)
                <tr class="border-t border-gray-200">
                    <td class="py-3 font-semibold text-gray-600">{{ $attendance->user->name }}</td>
                    <td class="py-3">{{ $attendance->clock_in?->format('H:i') }}</td>
                    <td class="py-3">{{ $attendance->clock_out?->format('H:i') }}</td>
                    <td class="py-3">{{ gmdate('H:i', $attendance->break_minutes * 60) }}</td>
                    <td class="py-3">{{ gmdate('H:i', $attendance->work_minutes * 60) }}</td>
                    <td class="py-3 font-bold text-black">
                        <a href="{{ route('admin.attendance.editshow', ['id' => $attendance->id]) }}">詳細</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection