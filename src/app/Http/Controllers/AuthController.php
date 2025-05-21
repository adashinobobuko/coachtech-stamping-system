<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;


class AuthController extends Controller
{
    //ページへのアクセス関連
    public function showStaffLogin()
    {
        return view('staff.auth.login');
    }

    public function showAdminLogin()
    {
        return view('admin.auth.login');
    }

    public function showStaffRegister()
    {
        return view('staff.auth.register');
    }

    // ユーザー登録処理
    public function register(RegisterRequest $request)
    {
        $emailVerificationToken = Str::random(64);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verification_token' => $emailVerificationToken,
        ]);

        $verificationUrl = route('verify.email', ['token' => $emailVerificationToken]);

        Mail::send('email.verify', [
            'user' => $user,
            'verificationUrl' => $verificationUrl, 
        ], function ($message) use ($user) {
            $message->to($user->email);
            $message->subject('メール認証のお願い');
        });

        session()->put('user_id', $user->id);

        return redirect()->route('verify.form')->with([
            'email' => $user->email,
            'message' => '認証メールを送信しました！メールを確認してください。'
        ]);
    }

    //メール認証待機画面の表示
    public function showVerifyForm()
    {
        $user = User::find(session('user_id'));

        if (!$user) {
            return redirect()->route('staff.register')->with('error', '登録情報が見つかりませんでした。');
        }

        // 認証リンクを生成
        $verificationUrl = route('verify.email', ['token' => $user->email_verification_token]);

        return view('staff.auth.verifyform', compact('user', 'verificationUrl'));
    }

    //メール認証の処理
    public function verifyEmail($token)
    {
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return redirect()->route('staff.login')->with('error', '無効な認証リンクです。');
        }

        // 認証を完了させる
        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->save();

        // 自動ログイン
        Auth::login($user);

        // プロフィール編集ページへリダイレクト
        return redirect()->route('staff.index')->with('message', 'ログインが完了しました');
    }

    //認証メール再送信
    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->email_verified_at) {
            return redirect()->route('login')->with('message', 'すでに認証済みです。');
        }

        // 新しいトークンを発行
        $user->email_verification_token = Str::random(64);
        $user->save();

        // 認証メール再送
        $verificationUrl = url('/verify-email?token=' . $user->email_verification_token);

        Mail::send('email.verify', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
        ], function ($message) use ($user) {
            $message->to($user->email);
            $message->subject('メール認証のお願い（再送）');
        });

        return back()->with('message', '確認メールを再送しました！');
    }

    //スタッフログイン処理
    public function staffLogin(LoginRequest $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->email_verified_at) {
            return back()->withErrors([
                'email' => 'メールアドレスが認証されていません。',
            ]);
        }

        if (!$user) {
            return back()->withErrors([
                'email' => 'このメールアドレスは登録されていません。',
            ]);
        }

        if (!$user->email_verified_at) {
            return back()->withErrors([
                'email' => 'メールアドレスが認証されていません。',
            ]);
        }

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            // セッションが開始されていなければ、開始する
            if (!session()->isStarted()) {
                session()->start();
            }

            // セッションを再生成
            $request->session()->regenerate();

            return redirect()->intended('/attendance');
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません。',
        ])->onlyInput('email');
    }

    //スタッフログアウト処理
    public function staffLogout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('message', 'ログアウトしました。');
    }

    //管理者ログイン処理
    public function adminlogin(LoginRequest $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::guard('admin')->attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/admin/attendance/list');
        }

        return back()->withErrors([
            'email' => 'ログイン情報が登録されていません。',
        ])->onlyInput('email');
    }

    //管理者ログアウト処理
    public function adminlogout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login')->with('message', 'ログアウトしました。');
    }
}
