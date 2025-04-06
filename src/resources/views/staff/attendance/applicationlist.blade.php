@extends('staff.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
<body>
    <div style="margin-top: 50px;">
        <h2 class="title-text">申請一覧</h2>

        {{-- タブメニュー --}}
        <div class="tab-menu">
            <a href="{{ route('attendance.applications', ['status' => '承認待ち']) }}" 
               class="{{ $status == '承認待ち' ? 'active' : '' }}">
                承認待ち
            </a>
            <a href="{{ route('attendance.applications', ['status' => '承認']) }}" 
               class="{{ $status == '承認' ? 'active' : '' }}">
                承認済み
            </a>
        </div>

        {{-- 申請一覧テーブル --}}
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>名前</th>
                    <th>対象日時</th>
                    <th>申請理由</th>
                    <th>申請日時</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($applications as $date => $dailyApplications)
                <tr>
                    <td colspan="6"><strong>{{ $date }} の申請</strong></td>
                </tr>
                @foreach ($dailyApplications as $application)
                    <tr>
                        <td>{{ $application->status }}</td>
                        <td>{{ $application->user->name }}</td>
                        <td>{{ $application->old_time }}</td>
                        <td>{{ $application->note }}</td>
                        <td>{{ $application->created_at->format('Y-m-d H:i') }}</td>
                        <td><a href="{{ route('staff.attendance.show', ['id' => $application->id]) }}">詳細</a></td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</body>
@endsection
