<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;


//  スタッフルート
Route::middleware(['auth'])->group(function () {
    // トップページ（打刻ページ）
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('staff.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::redirect('/', '/attendance');

    // スタッフログアウト
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('staff.logout');
});


//  スタッフログイン・登録
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showStaffLogin'])->name('staff.login');
    Route::post('/login', [AuthController::class, 'staffLogin'])->name('staff.login.submit');
    Route::get('/register', [AuthController::class, 'showStaffRegister'])->name('staff.register');
    Route::post('/register', [AuthController::class, 'register'])->name('staff.register.submit');
});

//  メール認証関連
Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verify.email');
Route::get('/verify-form', [AuthController::class, 'showVerifyForm'])->name('verify.form');
Route::post('/resend-email', [AuthController::class, 'resendVerificationEmail'])->name('resend.email');

// 勤怠一覧表示のルート
Route::middleware(['auth'])->group(function () {
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    Route::get('/attendance/{id}', [AttendanceController::class, 'detail'])->name('attendance.detail');
    Route::post('/attendance/update/{id}', [AttendanceController::class, 'update'])->name('attendance.update');
});



//  管理者ルート
Route::middleware(['auth:admin'])->group(function () {
    Route::get('/admin/attendance/list', [AdminController::class, 'index'])->name('admin.index');
    Route::post('/admin/logout', [AuthController::class, 'adminlogout'])->name('admin.logout');
});

//  管理者ログイン・登録
Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'adminlogin'])->name('admin.login.submit');