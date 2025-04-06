<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Collection;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        // 指定日のデータを取得
        $rawAttendances = Attendance::with('user')
            ->whereDate('timestamp', $date)
            ->get()
            ->groupBy('user_id');

        $attendances = $rawAttendances->map(function ($records, $userId) {
            $user = $records->first()->user;

            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;
            $breakStart = $records->firstWhere('type', 'break_start')?->timestamp;
            $breakEnd = $records->firstWhere('type', 'break_end')?->timestamp;

            $breakMinutes = 0;
            $breakPairs = $records->whereIn('type', ['break_start', 'break_end'])->sortBy('timestamp')->values();

            for ($i = 0; $i < $breakPairs->count() - 1; $i++) {
                if ($breakPairs[$i]->type === 'break_start' && $breakPairs[$i + 1]->type === 'break_end') {
                    $breakMinutes += $breakPairs[$i + 1]->timestamp->diffInMinutes($breakPairs[$i]->timestamp);
                    $i++; // skip next because it's already used as break_end
                }
            }
            $workMinutes = ($clockIn && $clockOut) ? $clockOut->diffInMinutes($clockIn) - $breakMinutes : 0;

            return (object)[
                'id' => $records->first()->id,
                'user' => $user,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_minutes' => $breakMinutes,
                'work_minutes' => $workMinutes,
            ];
        });
        
        return view('admin.dashboard', compact('attendances', 'date'));
    }

    public function show(Request $request, $user_id)
    {
        $date = Carbon::parse($request->input('date'));

        $records = Attendance::where('user_id', $user_id)
            ->whereDate('timestamp', $date)
            ->get();

        $user = User::findOrFail($user_id);

        $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
        $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

        $breaks = $records->filter(function ($r) {
            return str_starts_with($r->type, 'break');
        })->sortBy('timestamp')->values();

        $break1Start = optional($breaks->get(0))->timestamp?->format('H:i');
        $break1End   = optional($breaks->get(1))->timestamp?->format('H:i');
        $break2Start = optional($breaks->get(2))->timestamp?->format('H:i');
        $break2End   = optional($breaks->get(3))->timestamp?->format('H:i');

        $note = ''; // 必要に応じて AttendanceApplication から取得可

        return view('admin.attendance.show', compact(
            'user', 'date', 'clockIn', 'clockOut',
            'break1Start', 'break1End', 'break2Start', 'break2End',
            'note'
        ));
    }

    public function staffListShow(Request $request)
    {
        $users = User::all();//　すべてのスタッフを取得

        return view('admin.staff_list', compact('users'));
    }

    public function StaffAttendanceShow($user_id)
    {
        $user = User::findOrFail($user_id);
        $attendances = Attendance::where('user_id', $user_id)->get();
        $currentMonth = now()->format('Y-m');

        return view('admin.staff_attendance', compact('user', 'attendances','currentMonth'));
    }
}
