@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
    <link rel="stylesheet" href="{{ asset('css/admin_application_list.css') }}?v={{ time() }}" />
@endsection

@section('content')
<div class="application-container">
    <h1>申請一覧</h1>

    {{-- タブ --}}
    <div class="tab-menu">
        <a href="{{ route('admin.application.list', ['status' => '承認待ち']) }}"
           class="{{ request('status', '承認待ち') === '承認待ち' ? 'active' : '' }}">
            承認待ち
        </a>
        <a href="{{ route('admin.application.list', ['status' => '承認']) }}"
           class="{{ request('status') === '承認' ? 'active' : '' }}">
            承認済み
        </a>
    </div>

    {{-- テーブル --}}
    <table class="application-table">
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
            @foreach($applications as $application)
                <tr>
                    <td>
                        @if($application->status === '承認待ち')
                            <span class="badge bg-warning">未承認</span>
                        @elseif($application->status === '承認')
                            <span class="badge bg-success">承認済み</span>
                        @elseif($application->status === '却下')
                            <span class="badge bg-danger">却下</span>
                        @else
                            <span class="badge bg-secondary">不明</span>
                        @endif
                    </td>
                    <td>{{ $application->user->name }}</td>
                    <td>{{ optional($application->attendance)->timestamp ? \Carbon\Carbon::parse($application->attendance->timestamp)->format('Y/m/d') : '未登録' }}</td>
                    <td>{{ $application->note }}</td>
                    <td>{{ $application->created_at->format('Y/m/d') }}</td>
                    <td>
                        <a href="{{ route('admin.application.detail', $application->id) }}">詳細</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
    </table>

    {{-- ページネーション 
    <div class="pagination">
        {{ $applications->links() }}
    </div>  --}}
</div>
@endsection
