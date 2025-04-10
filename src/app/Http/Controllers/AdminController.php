<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\User;
use App\Models\AttendanceApplication;
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

        // ユーザーごとにデータをまとめる(加工処理)
        $attendances = $rawAttendances->map(function ($records, $userId) {
            $user = $records->first()->user;

            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;
            $breakStart = $records->firstWhere('type', 'break_start')?->timestamp;
            $breakEnd = $records->firstWhere('type', 'break_end')?->timestamp;

            $breakMinutes = 0;
            $breakPairs = $records->whereIn('type', ['break_start', 'break_end'])->sortBy('timestamp')->values();

            // break_start と break_end のペアを順に処理して、休憩時間（分）を合算
            for ($i = 0; $i < $breakPairs->count() - 1; $i++) {
                if ($breakPairs[$i]->type === 'break_start' && $breakPairs[$i + 1]->type === 'break_end') {
                    $breakMinutes += $breakPairs[$i + 1]->timestamp->diffInMinutes($breakPairs[$i]->timestamp);
                    $i++; // ++iでループを行いの次のペアに進む
                }
            }
            $workMinutes = ($clockIn && $clockOut) ? $clockOut->diffInMinutes($clockIn) - $breakMinutes : 0; // どちらかの変数がなければ勤務時間は0分

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

        $record = $records->first(); // 代表レコードとして（IDなどに使用）

        if (!$record) {
            abort(404, '打刻データが見つかりません');
        }

        return view('admin.attendance.show', compact(
            'user', 'date', 'clockIn', 'clockOut', 'record',
            'break1Start', 'break1End', 'break2Start', 'break2End',
            'note'
        ));
    }

    public function edit($id)
    {
        $record = Attendance::with('user')->findOrFail($id);

        $date = $record->timestamp->copy(); // 画面表示用の日付

        // 対象ユーザーの該当日全打刻
        $records = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $record->timestamp)
            ->get();

        $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
        $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

        $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))->sortBy('timestamp')->values();
        $breakPairs = [];
        for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
            $breakPairs[] = [
                'start' => optional($breaks->get($i))->timestamp?->format('H:i'),
                'end' => optional($breaks->get($i + 1))->timestamp?->format('H:i'),
            ];
        }

        $isPending = false;

        return view('staff.attendance.detail', compact(
            'record', 'clockIn', 'clockOut', 'breakPairs', 'isPending'
        ));
    }

    public function staffListShow(Request $request)
    {
        $users = User::all();//　すべてのスタッフを取得

        return view('admin.staff_list', compact('users'));
    }

    public function StaffAttendanceShow($user_id)
    {
        // 指定されたユーザーの出勤情報を取得、選択されている年、月を表示
        $user = User::findOrFail($user_id);
        $attendances = Attendance::where('user_id', $user_id)->get();
        $currentMonth = now()->format('Y-m');

        // 月ごとの出勤情報を取得
        return view('admin.staff_attendance', compact('user', 'attendances','currentMonth'));
    }

    public function adminApplicationListShow(Request $request)
    {
        $status = $request->input('status'); // 任意でフィルタ

        $query = AttendanceApplication::with(['user', 'attendance']);

        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->orderBy('created_at', 'desc')->get();

        return view('admin.application.admin_application_list', compact('applications'));
    }

    // 管理者から見た勤怠申請詳細
    public function applicationDetail($id)
    {
        $application = AttendanceApplication::with(['user', 'attendance'])->findOrFail($id);
        $record = $application->attendance;

        $baseDate = \Carbon\Carbon::parse($application->old_time)->toDateString();

        $clockIn = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $baseDate)
            ->where('type', 'clock_in')
            ->first()->timestamp ?? null;

        $clockOut = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $baseDate)
            ->where('type', 'clock_out')
            ->first()->timestamp ?? null;

        $breakStarts = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $baseDate)
            ->where('type', 'break_start')
            ->orderBy('timestamp')->get();

        $breakEnds = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $baseDate)
            ->where('type', 'break_end')
            ->orderBy('timestamp')->get();

        $breakPairs = [];
        for ($i = 0; $i < min($breakStarts->count(), $breakEnds->count()); $i++) {
            $breakPairs[] = [
                'start' => $breakStarts[$i]->timestamp->format('H:i'),
                'end' => $breakEnds[$i]->timestamp->format('H:i'),
            ];
        }

        $isPending = true; // 管理者画面なので未承認

        return view('staff.attendance.detail', compact(
            'application',
            'record',
            'clockIn',
            'clockOut',
            'breakPairs',
            'isPending'
        ));
    }

    public function approve($id)
    {
        $application = AttendanceApplication::findOrFail($id);
        $application->status = '承認済み';
        $application->save();

        return redirect()->back()->with('success', '修正申請を承認しました');
    }

    public function reject($id)
    {
        $application = AttendanceApplication::findOrFail($id);
        $application->status = '却下';
        $application->save();

        return redirect()->back()->with('success', '修正申請を却下しました');
    }

}
