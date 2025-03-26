<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;

class AttendanceController extends Controller
{
    public function index()
    {
    return view('staff.index');
    }

    public function store(Request $request)
    {
        Attendance::create([
            'user_id' => auth()->id(),
            'type' => $request->input('type'),
            'timestamp' => now(),
        ]);

        return redirect()->route('dashboard')->with('status', '打刻しました');
    }
}
