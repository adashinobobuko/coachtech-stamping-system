@extends('staff.layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/verify.css') }}">
@endsection

@section('content')
<div class="container">

    <h4>登録していただいたメールアドレスに認証メールを送付しました。</br>
    メール認証を完了してください。</h4>

    <a class="verify__button" href="{{ $verificationUrl }}">
        認証はこちらから
    </a>
    <!--セッションを使いログインを行う-->

    <form action="{{ route('resend.email') }}" method="POST">
        @csrf
        <input type="hidden" name="email" value="{{ session('email') }}">
        <button type="submit" class="btn btn-warning">認証メールを再送する</button>
    </form>

</div>
@endsection