<!DOCTYPE html>
<html>
<head>
    <title>メール認証</title>
</head>
<body>
    <p>{{ $user->name }} さん、以下のリンクをクリックしてメール認証を完了してください。</p>
    <a href="{{ $verificationUrl }}">メール認証を完了する</a>
</body>
</html>