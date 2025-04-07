<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\AttendanceApplication;

class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        if (!auth()->check()) {
            return redirect()->route('staff.login')->with('error', 'ログインしてください');
        }

        $user = auth()->user();
        $status = $this->getStatus($user);
        $attendances = Attendance::where('user_id', $user->id)
                                ->orderBy('timestamp', 'desc')
                                ->get();

        return view('staff.index', compact('status', 'attendances'));
    }

    public function getStatus($user)
    {
        $attendances = Attendance::where('user_id', $user->id)
            ->orderBy('timestamp')
            ->get();

        $status = '勤務外';
        $inWork = false;
        $onBreak = false;

        foreach ($attendances as $attendance) {
            switch ($attendance->type) {
                case 'clock_in':
                    $inWork = true;
                    $onBreak = false;
                    $status = '出勤中';
                    break;
                case 'break_start':
                    if ($inWork && !$onBreak) {
                        $onBreak = true;
                        $status = '休憩中';
                    }
                    break;
                case 'break_end':
                    if ($inWork && $onBreak) {
                        $onBreak = false;
                        $status = '出勤中';
                    }
                    break;
                case 'clock_out':
                    $inWork = false;
                    $onBreak = false;
                    $status = '勤務外';
                    break;
            }
        }

        return $status;
    }

    public function checkPreviousStatus($user)
    {
        $previous = Attendance::where('user_id', $user->id)
                            ->whereIn('type', ['clock_in', 'break_start'])
                            ->orderBy('timestamp', 'desc')
                            ->skip(1)
                            ->first();

        return $previous && $previous->type === 'clock_in' ? '出勤中' : '勤務外';
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $now = now();

        $request->validate([
            'type' => 'required|in:clock_in,break_start,break_end,clock_out',
        ]);

        $type = $request->type;
        $status = $this->getStatus($user);

        if ($type === 'clock_in') {
            if ($status === '出勤中') {
                return back()->with('error', 'すでに出勤しています。');
            }

            $alreadyClockedIn = Attendance::where('user_id', $user->id)
                ->where('type', 'clock_in')
                ->whereDate('timestamp', $now->toDateString())
                ->exists();

            if ($alreadyClockedIn) {
                return back()->with('error', '本日はすでに出勤しています。');
            }
        }

        if ($type === 'clock_out' && $status === '勤務外') {
            return back()->with('error', '出勤していないため退勤できません。');
        }

        if ($type === 'break_start' && $status !== '出勤中') {
            return back()->with('error', '休憩開始は出勤後にのみ可能です。');
        }

        if ($type === 'break_end' && $status !== '休憩中') {
            return back()->with('error', '休憩中ではないため休憩終了できません。');
        }

        Attendance::create([
            'user_id' => $user->id,
            'type' => $type,
            'timestamp' => $now,
        ]);

        $status = $this->getStatus($user);
        $message = match ($type) {
            'clock_in' => '出勤しました。',
            'break_start' => '休憩を開始しました。',
            'break_end' => '休憩終了しました。',
            'clock_out' => 'お疲れさまでした',
        };

        return back()->with('success', $message)->with('status', $status);
    }

    public function list(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));

        $startOfMonth = now()->create($month)->startOfMonth();
        $endOfMonth = now()->create($month)->endOfMonth();

        $records = Attendance::where('user_id', $user->id)
            ->whereBetween('timestamp', [$startOfMonth, $endOfMonth])
            ->orderBy('timestamp')
            ->get();

        $attendances = $this->formatAttendanceData($records);

        $prevMonth = $startOfMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $startOfMonth->copy()->addMonth()->format('Y-m');

        return view('staff.attendance.list', compact('attendances', 'month', 'prevMonth', 'nextMonth', 'startOfMonth'));
    }

    public function detail($id)
    {
        $userId = auth()->id();

        // 「承認待ち」の申請があるかどうかを最優先で確認
        $application = AttendanceApplication::where('attendance_id', $id)
            ->where('user_id', $userId)
            ->where('status', '承認待ち')
            ->first();

        $isPending = false;

        if ($application) {
            $record = $application->attendance;
            $isPending = true;
            $viewType = 'application';
        } else {
            // 承認待ち申請がなければ通常の勤怠データを表示
            $record = Attendance::with('user')->findOrFail($id);
            $application = null;
            $viewType = 'attendance';
        }

        $baseDate = $application
        ? \Carbon\Carbon::parse($application->old_time)->toDateString()
        : $record->timestamp->toDateString();

        // 出勤・退勤の打刻取得
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

        // viewに必要な情報を渡す
        return view('staff.attendance.detail', compact(
            'record',
            'application',
            'clockIn',
            'clockOut',
            'breakPairs',
            'viewType',
            'isPending'
        ));
    }

    public function applicationDetail($id)
    {
        $userId = auth()->id();

        $application = AttendanceApplication::with('attendance', 'user')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $baseDate = \Carbon\Carbon::parse($application->old_time)->toDateString();

    $user_id = $application->user_id;

    $clock_in = Attendance::where('user_id', $user_id)
        ->whereDate('timestamp', $baseDate)
        ->where('type', 'clock_in')
        ->first()->timestamp ?? null;

    $clock_out = Attendance::where('user_id', $user_id)
        ->whereDate('timestamp', $baseDate)
        ->where('type', 'clock_out')
        ->first()->timestamp ?? null;

    $break_starts = Attendance::where('user_id', $user_id)
        ->whereDate('timestamp', $baseDate)
        ->where('type', 'break_start')
        ->orderBy('timestamp')->get();

    $break_ends = Attendance::where('user_id', $user_id)
        ->whereDate('timestamp', $baseDate)
        ->where('type', 'break_end')
        ->orderBy('timestamp')->get();

    $record = $application->attendance;

    $breakPairs = [];
    for ($i = 0; $i < min($break_starts->count(), $break_ends->count()); $i++) {
        $breakPairs[] = [
            'start' => $break_starts[$i]->timestamp->format('H:i'),
            'end' => $break_ends[$i]->timestamp->format('H:i'),
        ];
    }

    return view('staff.attendance.detail', [
        'application' => $application,
        'record' => $record,
        'clockIn' => $clock_in,
        'clockOut' => $clock_out,
        'breakPairs' => $breakPairs,
        'isPending' => true,
    ]);

    }

    public function update(Request $request, $id)
    {
        $userId = auth()->id();

        // すでに「承認待ち」申請があるかチェック
        $pendingExists = AttendanceApplication::where('attendance_id', $id)
            ->where('user_id', $userId)
            ->where('status', '承認待ち')
            ->exists();

        if ($pendingExists) {
            return redirect()->route('staff.attendance.show', ['id' => $id])
                ->with('error', 'すでに申請中です。承認が完了するまで再申請できません。');
        }

        // 勤怠データ取得（以降は通常通り）
        $record = Attendance::with('user')->findOrFail($id);

        $application = AttendanceApplication::where('attendance_id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($application) {
            $application->update([
                'clock_in' => $request->clock_in,
                'clock_out' => $request->clock_out,
                'break_start' => $request->break_start,
                'break_end' => $request->break_end,
                'note' => $request->note,
                'status' => '承認待ち',
            ]);
        } else {
            AttendanceApplication::create([
                'attendance_id' => $record->id,
                'user_id' => $userId,
                'type' => '修正申請',
                'old_time' => $record->timestamp->format('Y-m-d H:i:s'),
                'new_time' => now()->format('Y-m-d H:i:s'),
                'clock_in' => $request->clock_in,
                'clock_out' => $request->clock_out,
                'break_start' => $request->break_start,
                'break_end' => $request->break_end,
                'note' => $request->note,
                'status' => '承認待ち',
            ]);
        }

        return redirect()->route('staff.attendance.show', ['id' => $record->id])
            ->with('success', '修正申請が完了しました。');
    }

    public function applicationindex(Request $request)
    {
        $status = $request->input('status', '承認待ち');

        $applications = AttendanceApplication::with('attendance')
            ->where('user_id', auth()->id())
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($application) {
                return Carbon::parse($application->old_time)->format('Y-m-d');
            });

        return view('staff.attendance.applicationlist', compact('applications', 'status'));
    }

    private function formatAttendanceData($records)
    {
        $attendances = [];

        foreach ($records as $record) {
            $timestamp = Carbon::parse($record->timestamp);
            $date = $timestamp->format('Y-m-d');

            if (!isset($attendances[$date])) {
                $attendances[$date] = [
                    'id' => $record->id,
                    'clock_in' => null,
                    'clock_out' => null,
                    'break' => '0:00',
                    'total' => '0:00',
                ];
            }

            switch ($record->type) {
                case 'clock_in':
                    $attendances[$date]['clock_in'] = $timestamp->format('H:i');
                    break;
                case 'clock_out':
                    $attendances[$date]['clock_out'] = $timestamp->format('H:i');
                    break;
                case 'break_start':
                    $attendances[$date]['break_start'] = $timestamp;
                    break;
                case 'break_end':
                    if (isset($attendances[$date]['break_start'])) {
                        $breakDuration = $timestamp->diff($attendances[$date]['break_start']);
                        $attendances[$date]['break'] = $breakDuration->format('%H:%I');
                    }
                    break;
            }

            if ($attendances[$date]['clock_in'] && $attendances[$date]['clock_out']) {
                $workDuration = Carbon::parse($attendances[$date]['clock_out'])
                    ->diffInMinutes(Carbon::parse($attendances[$date]['clock_in']));
                $attendances[$date]['total'] = floor($workDuration / 60) . ':' . str_pad($workDuration % 60, 2, '0', STR_PAD_LEFT);
            }
        }

        return $attendances;
    }
}
