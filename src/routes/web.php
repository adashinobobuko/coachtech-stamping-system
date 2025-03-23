<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StampingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//トップページ（打刻ページ）の表示
Route::get('/attendance', [StampingController::class, 'index'])->name('staff.index');

//管理者ダッシュボードの表示
Route::get('/admin/attendance/list', [AdminController::class, 'index'])->name('admin.index');

//ログインルート
Route::get('/login', [AuthController::class, 'showStaffLogin'])->name('staff.login');
Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');

//登録ルート
Route::get('/register', [AuthController::class, 'showStaffRegister'])->name('staff.register');
Route::get('/admin/register', [AuthController::class, 'showAdminLogin'])->name('admin.register');
//TODO:管理者登録は必要なのか？シーディングで一人作成するのか？