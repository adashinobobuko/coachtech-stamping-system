<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// トップページ判定（ログイン中ユーザーのロールで分岐）
Route::get('/', function () {
    if (auth('admin')->check()) {
        return redirect()->route('admin.index');
    } elseif (auth('web')->check()) {
        return redirect()->route('staff.index');
    } else {
        return redirect()->route('staff.login');
    }
});


// スタッフログイン・登録
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showStaffLogin'])->name('staff.login');
    Route::post('/login', [AuthController::class, 'staffLogin'])->name('staff.login.submit');
    Route::get('/register', [AuthController::class, 'showStaffRegister'])->name('staff.register');
    Route::post('/register', [AuthController::class, 'register'])->name('staff.register.submit');
});

// メール認証関連（スタッフ専用）
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verify.email');
    Route::get('/verify-form', [AuthController::class, 'showVerifyForm'])->name('verify.form');
    Route::post('/resend-email', [AuthController::class, 'resendVerificationEmail'])->name('resend.email');

// スタッフ用ルート
Route::middleware(['auth:web'])->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('staff.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    Route::get('/stamp_correction_request/list', [AttendanceController::class, 'applicationindex'])->name('attendance.applications');
    Route::get('/attendance/{id}', [AttendanceController::class, 'detail'])->name('staff.attendance.show');
    Route::get('/application/detail/{id}', [AttendanceController::class, 'applicationDetail'])->name('staff.application.show');
    Route::post('/attendance/update/{id}', [AttendanceController::class, 'update'])->name('attendance.update');

    Route::post('/logout', [AuthController::class, 'staffLogout'])->name('staff.logout');
});

// 管理者ログイン・登録
Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'adminlogin'])->name('admin.login.submit');

// 管理者用ルート
Route::prefix('admin')->middleware(['auth:admin'])->group(function () {
    Route::get('/attendance/list', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/attendance/{id}', [AdminController::class, 'show'])->name('admin.attendance.show');
    Route::post('/attendance/update/{id}', [AttendanceController::class, 'update'])->name('admin.attendance.update');
    Route::post('/logout', [AuthController::class, 'adminlogout'])->name('admin.logout');
    Route::get('/staff/list', [AdminController::class, 'staffListShow'])->name('admin.staff.list');
    Route::get('/attendance/staff/{id}', [AdminController::class, 'StaffAttendanceShow'])->name('admin.staff.attendance');
    Route::get('/stamp_correction_request/list',[AdminController::class,'adminApplicationListShow'])->name('admin.application.list');
    Route::get('/stamp_correction_request/approve/{id}',[AdminController::class,'applicationDetail'])->name('admin.application.detail');
    Route::post('/admin/stamp_correction_request/approve/{id}', [AdminController::class, 'approve'])->name('admin.attendance.approve');
    Route::post('/admin/stamp_correction_request/reject/{id}', [AdminController::class, 'reject'])->name('admin.attendance.reject');
});
