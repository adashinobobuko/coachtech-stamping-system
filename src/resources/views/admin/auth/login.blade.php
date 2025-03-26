@extends('admin.layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/login.css') }}">
@endsection

@section('content')
<div class="login__content">
  <div class="flashmessage">
      @if(session('message'))
        <div class="flashmessage__success">
            {{ session('message') }}
        </div>
      @endif
  </div>
  <div class="login-form__heading">
    <h3>ログイン</h3>
  </div>
  <form class="form" action="{{ route('admin.login.submit') }}" method="post">
    @csrf
      <div class="form__group">
        <label for="email" class="form__label--item__mail">メールアドレス</label><br>
        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form__input--text">
        @error('email')
        <div class="form__error">{{ $message }}</div>
        @enderror
      </div>

      <div class="form__group">
        <label for="password" class="form__label--item__pass">パスワード</label><br>
        <input type="password" id="password" name="password" class="form__input--text">
        @error('password')
        <div class="form__error">{{ $message }}</div>
        @enderror
      </div>

      <div class="form__button">
        <button type="submit" class="form__button-submit">ログインする</button>
      </div>
    </form>
  <!--
  <div class="toregister">
    <a href="">会員登録はこちら</a>
  </div>-->

</div>
@endsection