<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StampingController;

// トップページ（打刻ページ）
Route::get('/attendance', [StampingController::class, 'index'])->name('staff.index');
Route::redirect('/', '/attendance');

// 管理者ダッシュボード
Route::get('/admin/attendance/list', [AdminController::class, 'index'])->name('admin.index');

Route::get('/login', [AuthController::class, 'showStaffLogin'])->name('staff.login');
Route::get('/register', [AuthController::class, 'showStaffRegister'])->name('staff.register');

// 管理者ログイン（Fortifyと分離してカスタム処理）
Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
// Todo:管理者用のログイン処理やlogoutも追加で定義する必要があります（POSTなど）

//メール認証関連
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verify.email');
Route::get('/verify-form', [AuthController::class, 'showVerifyForm'])->name('verify.form');
Route::post('/resend-email', [AuthController::class, 'resendVerificationEmail'])->name('resend.email');
