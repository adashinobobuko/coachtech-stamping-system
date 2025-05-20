@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/show.css') }}?v={{ time() }}">
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}">
@endsection

@section('content')

{{-- タイトルを外に出す --}}
<h2 class="page-title">勤怠詳細</h2>

<div class="max-w-2xl mx-auto bg-white p-6 rounded shadow">
    <form method="POST" action="{{ route('admin.attendance.update', ['id' => $record->id]) }}" id="attendance-update-form">
        @csrf
        @if ($errors->any())
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                <ul class="list-disc pl-5 alert alert-danger no-bullet">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <table class="table-auto w-full text-left border border-collapse border-gray-300 form-group">
            <tr class="border-b">
                <th class="p-2 w-1/3">名前</th>
                <td class="p-2 bold">{{ $user->name }}</td>
            </tr>
            <tr class="border-b">
                <th class="p-2">日付</th>
                <td class="p-2 bold">{{ $date->year }}年{{ $date->month }}月{{ $date->day }}日</td>
            </tr>
            <tr class="border-b">
                <th class="p-2">出勤・退勤</th>
                <td class="p-2">
                    <input type="time" name="clock_in" value="{{ $clockIn ? $clockIn->format('H:i') : '' }}"> 〜
                    <input type="time" name="clock_out" value="{{ $clockOut ? $clockOut->format('H:i') : '' }}">
                </td>
            </tr>
            
            @php
                $validBreaks = array_filter($breakPairs, fn($pair) => !empty($pair['start']) || !empty($pair['end']));
            @endphp

            @if (count($validBreaks) > 0)
                @foreach ($validBreaks as $index => $pair)
                    @php
                        $breakStartName = 'break_start_' . ($index + 1);
                        $breakEndName = 'break_end_' . ($index + 1);
                    @endphp
                    <tr class="border-b">
                        <th class="p-2">
                            @if ($index === 0)
                                休憩
                            @else
                                休憩{{ $index + 1 }}
                            @endif
                        </th>
                        <td class="p-2">
                            <div class="break-time-row">
                                <input type="time" name="{{ $breakStartName }}" value="{{ $pair['start'] ?? '' }}" class="break-input">
                                〜
                                <input type="time" name="{{ $breakEndName }}" value="{{ $pair['end'] ?? '' }}" class="break-input">
                            </div>
                        </td>
                    </tr>
                @endforeach
            @endif

            <tr class="border-b">
                <th class="p-2">備考</th>
                <td class="p-2">
                    <textarea name="note" class="w-full border rounded" rows="3">{{ old('note', $note) }}</textarea>
                </td>
            </tr>
        </table>

    </form>

</div>

{{-- 修正ボタンを外に出す --}}
<div class="btn-container-fixed">
    <button type="submit" form="attendance-update-form" class="btn-fixed-submit">修正</button>
</div>
@endsection
