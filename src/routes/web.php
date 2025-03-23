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

Route::get('/', [StampingController::class, 'index'])->name('staff.index');

Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');

Route::get('/login', [AuthController::class, 'showLoginform'])->name('show.loginform');
Route::get('/admin/login', [AuthController::class, 'showAdminLoginform'])->name('show.adminlogin');