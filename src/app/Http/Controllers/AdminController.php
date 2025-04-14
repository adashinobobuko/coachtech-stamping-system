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
    // 管理者用トップページ
    public function index(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        $rawAttendances = Attendance::with('user')
            ->whereDate('timestamp', $date)
            ->get()
            ->groupBy('user_id');

        $attendances = $rawAttendances->map(function ($records, $userId) use ($date) {
            $user = $records->first()->user;

            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

            $breakStart = $records->firstWhere('type', 'break_start')?->timestamp;
            $breakEnd = $records->firstWhere('type', 'break_end')?->timestamp;

            // 承認済みの修正申請があれば、修正後の打刻を反映
            $application = AttendanceApplication::where('attendance_id', $records->first()->id)
                ->where('status', '承認')
                ->latest()
                ->first();

            if ($application) {
                $clockIn = $application->clock_in ? Carbon::parse($application->clock_in) : $clockIn;
                $clockOut = $application->clock_out ? Carbon::parse($application->clock_out) : $clockOut;
                $breakStart = $application->break_start ? Carbon::parse($application->break_start) : $breakStart;
                $breakEnd = $application->break_end ? Carbon::parse($application->break_end) : $breakEnd;
            }

            // 休憩時間計算（そのままでOK）
            $breakMinutes = 0;

            if ($breakStart && $breakEnd) {
                $breakMinutes += $breakEnd->diffInMinutes($breakStart);
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

    // 管理者用打刻詳細
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

        // 修正申請の反映
        $application = \App\Models\AttendanceApplication::where('attendance_id', $records->first()?->id)
            ->where('status', '承認')
            ->latest()
            ->first();

        if ($application) {
            $clockIn = $application->clock_in ? Carbon::parse($application->clock_in) : $clockIn;
            $clockOut = $application->clock_out ? Carbon::parse($application->clock_out) : $clockOut;
            $break1Start = $application->break_start;
            $break1End = $application->break_end;
            $break2Start = $application->break_start2;
            $break2End = $application->break_end2;
            $note = $application->note ?? '';
        }

        return view('admin.attendance.show', compact(
            'user', 'date', 'clockIn', 'clockOut', 'record',
            'break1Start', 'break1End', 'break2Start', 'break2End',
            'note'
        ));
    }

    // 管理者用打刻詳細（管理者が直接修正）
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
        $user = User::findOrFail($user_id);
        $currentMonth = now()->format('Y-m');

        // 勤怠レコード取得＆修正申請反映
        $rawAttendances = Attendance::where('user_id', $user_id)
            ->orderBy('timestamp')
            ->get()
            ->groupBy(function ($record) {
                return $record->timestamp->toDateString(); // 日付でまとめる
            });

        $attendances = $rawAttendances->map(function ($records, $date) {
            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;
            $breakStart = $records->firstWhere('type', 'break_start')?->timestamp;
            $breakEnd = $records->firstWhere('type', 'break_end')?->timestamp;

            // 代表レコード
            $base = $records->first();

            // 修正申請の反映（承認済み）
            $application = \App\Models\AttendanceApplication::where('attendance_id', $base->id)
                ->where('status', '承認')
                ->latest()
                ->first();

            if ($application) {
                $clockIn = $application->clock_in ? Carbon::parse($application->clock_in) : $clockIn;
                $clockOut = $application->clock_out ? Carbon::parse($application->clock_out) : $clockOut;
                $breakStart = $application->break_start ? Carbon::parse($application->break_start) : $breakStart;
                $breakEnd = $application->break_end ? Carbon::parse($application->break_end) : $breakEnd;
            }

            // 勤務時間・休憩時間計算
            $breakMinutes = ($breakStart && $breakEnd) ? $breakEnd->diffInMinutes($breakStart) : 0;
            $workMinutes = ($clockIn && $clockOut) ? $clockOut->diffInMinutes($clockIn) - $breakMinutes : 0;

            return (object)[
                'date' => $date,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'break_minutes' => $breakMinutes,
                'work_minutes' => $workMinutes,
                'record_id' => $base->id,
            ];
        });

        return view('admin.staff_attendance', compact('user', 'attendances', 'currentMonth'));
    }

    public function adminApplicationListShow(Request $request)
    {
        $status = $request->input('status'); // 任意でフィルタ

        //dd($status); // 一度だけ

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

        // 申請された打刻を優先的に表示（Carbonでフォーマット統一）
        $clockIn = $application->clock_in
            ? \Carbon\Carbon::parse($application->clock_in)
            : Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'clock_in')
                ->first()->timestamp ?? null;

        $clockOut = $application->clock_out
            ? \Carbon\Carbon::parse($application->clock_out)
            : Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'clock_out')
                ->first()->timestamp ?? null;

        // 休憩ペア
        $breakPairs = [];

        if ($application->break_start && $application->break_end) {
            $breakPairs[] = [
                'start' => $application->break_start,
                'end' => $application->break_end,
            ];
        }

        if ($application->break_start2 && $application->break_end2) {
            $breakPairs[] = [
                'start' => $application->break_start2,
                'end' => $application->break_end2,
            ];
        }

        // 念のため不足分はAttendanceモデルから補完
        if (empty($breakPairs)) {
            $breakStarts = Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'break_start')
                ->orderBy('timestamp')->get();

            $breakEnds = Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'break_end')
                ->orderBy('timestamp')->get();

            for ($i = 0; $i < min($breakStarts->count(), $breakEnds->count()); $i++) {
                $breakPairs[] = [
                    'start' => $breakStarts[$i]->timestamp->format('H:i'),
                    'end' => $breakEnds[$i]->timestamp->format('H:i'),
                ];
            }
        }

        $isPending = true; // 管理者画面＝未承認の申請中

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
        $application->status = '承認';
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
