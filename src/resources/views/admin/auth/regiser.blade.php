@extends('admin.layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/register.css') }}">
@endsection

@section('content')
<div class="register__content">
  <div class="register-form__heading">
    <h3>会員登録</h3>
  </div>
  <form class="form" action="" method="post">
    @csrf
    <div class="form__group">
      <label for="name" class="form__label--name">ユーザー名</label><br>
      <input type="text" id="name" name="name" value="{{ old('name') }}" class="form__input">
      @if ($errors->has('name'))
          <div class="form__error">{{ $errors->first('name') }}</div>
      @endif
    </div>

    <div class="form__group">
      <label for="email" class="form__label--mail">メールアドレス</label><br>
      <input type="email" id="email" name="email" value="{{ old('email') }}" class="form__input">
      @error('email')
        <div class="form__error">{{ $message }}</div>
      @enderror
    </div>

    <div class="form__group">
      <label for="password" class="form__label--pass">パスワード</label><br>
      <input type="password" id="password" name="password" class="form__input">
      @error('password')
        <div class="form__error">{{ $message }}</div>
      @enderror
    </div>

    <div class="form__group">
      <label for="password_confirmation" class="form__label--confirm">確認用パスワード</label><br>
      <input type="password" id="password_confirmation" name="password_confirmation" class="form__input">
      @error('password_confirmation')
        <div class="form__error">{{ $message }}</div>
      @enderror
    </div>

    <div class="form__group">
      <button type="submit" class="form__button-submit">登録する</button>
    </div>
  </form>

  <div class="tologin">
    <a href="{{ route('admin.login') }}">ログインはこちら</a>
  </div>
</div>
@endsection