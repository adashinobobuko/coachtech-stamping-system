@extends('admin.layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/index.css') }}?v={{ time() }}" />
@endsection

@section('content')
    <body>
    <h1>勤怠の管理画面</h1>
    </body>
@endsection