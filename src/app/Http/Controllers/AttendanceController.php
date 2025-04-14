<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\AttendanceApplication;
use App\Http\Requests\AttendanceRequest;

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
            ->orderByDesc('created_at')
            ->first();

        $isPending = false;

        if ($application) {
            $record = $application->attendance;
            $viewType = 'application';

            // ステータスが「承認待ち」のときだけ isPending = true
            if ($application->status === '承認待ち') {
                $isPending = true;
            }
        } else {
            $record = Attendance::with('user')->findOrFail($id);
            $application = null;
            $viewType = 'attendance';
        }

        $baseDate = $application
        ? \Carbon\Carbon::parse($application->old_time)->toDateString()
        : $record->timestamp->toDateString();

        // 出勤・退勤の時刻を取得（申請がある場合は申請内容を優先し、なければAttendanceから取得）

        // 休憩時間のペアを取得
        // 申請があれば申請された休憩時間（最大2ペア）を使用
        // 申請がない、または申請に休憩時間の情報がない場合は Attendance モデルから取得しペアリング
        $clockIn = $application && $application->clock_in
            ? Carbon::parse($application->clock_in)
            : optional(Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'clock_in')
                ->first())->timestamp;

        $clockOut = $application && $application->clock_out
            ? Carbon::parse($application->clock_out)
            : optional(Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'clock_out')
                ->first())->timestamp;

        
        $breakPairs = [];

        if ($application && $application->break_start && $application->break_end) {
            $breakPairs[] = [
                'start' => \Carbon\Carbon::parse($application->break_start)->format('H:i'),
                'end'   => \Carbon\Carbon::parse($application->break_end)->format('H:i'),
            ];
        }

        if ($application && $application->break_start2 && $application->break_end2) {
            $breakPairs[] = [
                'start' => \Carbon\Carbon::parse($application->break_start2)->format('H:i'),
                'end'   => \Carbon\Carbon::parse($application->break_end2)->format('H:i'),
            ];
        }

        if (!$application || empty($breakPairs)) {
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
        $record = $application->attendance;

        // 出勤・退勤（申請内容を優先）
        $clockIn = $application->clock_in
            ? \Carbon\Carbon::parse($application->clock_in)
            : null;

        $clockOut = $application->clock_out
            ? \Carbon\Carbon::parse($application->clock_out)
            : null;

        // 休憩
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

        // 念のため不足時は Attendance モデルで補完
        if (empty($breakPairs)) {
            $breakStarts = Attendance::where('user_id', $user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'break_start')
                ->orderBy('timestamp')->get();

            $breakEnds = Attendance::where('user_id', $user_id)
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

        return view('staff.attendance.detail', [
            'application' => $application,
            'record' => $record,
            'clockIn' => $clockIn,
            'clockOut' => $clockOut,
            'breakPairs' => $breakPairs,
            'isPending' => true,
        ]);
    }

    public function update(AttendanceRequest $request, $id)
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
                'break_start' => $request->input('break_start_1'),
                'break_end' => $request->input('break_end_1'),
                'break_start2' => $request->input('break_start_2'),
                'break_end2' => $request->input('break_end_2'),
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
                'break_start' => $request->input('break_start_1'),
                'break_end' => $request->input('break_end_1'),
                'break_start2' => $request->input('break_start_2'),
                'break_end2' => $request->input('break_end_2'),
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
        $userId = auth()->id();

        // 日付ごとにまとめて承認済み申請を取得（最新1件だけ）
        $approvedApplications = AttendanceApplication::where('user_id', $userId)
            ->where('status', '承認')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($app) {
                return Carbon::parse($app->old_time)->format('Y-m-d');
            });

        $attendances = [];

        foreach ($records as $record) {
            $timestamp = Carbon::parse($record->timestamp);
            $date = $timestamp->format('Y-m-d');

            // 日付がまだ存在しない場合は初期化
            if (!isset($attendances[$date])) {
                $attendances[$date] = [
                    'id' => $record->id,
                    'clock_in' => null,
                    'clock_out' => null,
                    'break' => '0:00',
                    'total' => '0:00',
                ];
            }

            // 承認済み申請がある場合はそちらを優先
            $application = $approvedApplications[$date][0] ?? null;

            if ($application) {
                $attendances[$date]['clock_in'] = $application->clock_in;
                $attendances[$date]['clock_out'] = $application->clock_out;

                $breakMinutes = 0;
                if ($application->break_start && $application->break_end) {
                    $breakMinutes += Carbon::parse($application->break_end)
                        ->diffInMinutes(Carbon::parse($application->break_start));
                }
                if ($application->break_start2 && $application->break_end2) {
                    $breakMinutes += Carbon::parse($application->break_end2)
                        ->diffInMinutes(Carbon::parse($application->break_start2));
                }

                $attendances[$date]['break'] = gmdate('H:i', $breakMinutes * 60);

                if ($application->clock_in && $application->clock_out) {
                    $worked = Carbon::parse($application->clock_out)
                        ->diffInMinutes(Carbon::parse($application->clock_in));
                    $attendances[$date]['total'] = gmdate('H:i', max(0, $worked - $breakMinutes) * 60);
                }

            } else {
                // 通常の勤怠データを処理
                switch ($record->type) {
                    case 'clock_in':
                        $attendances[$date]['clock_in'] = $record->timestamp->format('H:i');
                        break;
                    case 'clock_out':
                        $attendances[$date]['clock_out'] = $record->timestamp->format('H:i');
                        break;
                    case 'break_start':
                        $attendances[$date]['break_start'] = $record->timestamp;
                        break;
                    case 'break_end':
                        if (isset($attendances[$date]['break_start'])) {
                            $breakDuration = $record->timestamp->diff($attendances[$date]['break_start']);
                            $attendances[$date]['break'] = $breakDuration->format('%H:%I');
                        }
                        break;
                }

                if ($attendances[$date]['clock_in'] && $attendances[$date]['clock_out']) {
                    $workDuration = Carbon::parse($attendances[$date]['clock_out'])
                        ->diffInMinutes(Carbon::parse($attendances[$date]['clock_in']));
                    $breakParts = explode(':', $attendances[$date]['break']);
                    $breakMinutes = ((int)$breakParts[0]) * 60 + (int)$breakParts[1];
                    $attendances[$date]['total'] = gmdate('H:i', max(0, $workDuration - $breakMinutes) * 60);
                }
            }
        }

        return $attendances;
    }

}
