<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
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

    public function showAdminRegister()
    {
        return view('admin.auth.register');
    }
}
