<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\AttendanceApplication;
use App\Http\Requests\AttendanceRequest;
use App\Models\AttendanceModification;

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
            ->whereDate('timestamp', now()->toDateString())
            ->orderBy('timestamp')
            ->get();

        $status = '勤務外';
        $inWork = false;
        $onBreak = false;
        $clockedOutToday = false;

        foreach ($attendances as $attendance) {
            switch ($attendance->type) {
            case 'clock_in':
                if (!$clockedOutToday) {
                $inWork = true;
                $onBreak = false;
                $status = '出勤中';
                }
                break;
            case 'break_start':
                if ($inWork && !$onBreak && !$clockedOutToday) {
                $onBreak = true;
                $status = '休憩中';
                }
                break;
            case 'break_end':
                if ($inWork && $onBreak && !$clockedOutToday) {
                $onBreak = false;
                $status = '出勤中';
                }
                break;
            case 'clock_out':
                $inWork = false;
                $onBreak = false;
                $clockedOutToday = true;
                $status = '退勤済み';
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
            'timestamp' => 'nullable|date',
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
            'timestamp' => $request->input('timestamp') ?? $now,
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
    
        $application = AttendanceApplication::where('attendance_id', $id)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();
    
        $isPending = false;
    
        if ($application) {
            $record = $application->attendance;
            $viewType = 'application';
    
            if ($application->status === '承認待ち') {
                $isPending = true;
            }
        } else {
            $record = Attendance::with('user')->findOrFail($id);
            $application = null;
            $viewType = 'attendance';
        }
    
        $baseDate = $application
            ? Carbon::parse($application->old_time)->toDateString()
            : $record->timestamp->toDateString();
    
        $clockInRecord = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $baseDate)
            ->where('type', 'clock_in')
            ->first();
    
        $clockOutRecord = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $baseDate)
            ->where('type', 'clock_out')
            ->first();
    
        $modClockIn = $clockInRecord
            ? AttendanceModification::where('attendance_id', $clockInRecord->id)
                ->where('field', 'clock_in')
                ->latest('modified_at')
                ->first()
            : null;
    
        $modClockOut = $clockOutRecord
            ? AttendanceModification::where('attendance_id', $clockOutRecord->id)
                ->where('field', 'clock_out')
                ->latest('modified_at')
                ->first()
            : null;
    
        $clockIn = $modClockIn
            ? Carbon::createFromFormat('H:i', $modClockIn->new_value)
            : null;
    
        $clockOut = $modClockOut
            ? Carbon::createFromFormat('H:i', $modClockOut->new_value)
            : null;

                
        $multiApplication = AttendanceApplication::where('attendance_id', $id)
            ->where('user_id', $userId)
            ->where('event_type', '複数申請')
            ->orderByDesc('created_at')
            ->first();
        
        $inMatch = [];
        $outMatch = [];
        $breakMatches = [];
        
        if ($multiApplication) {
            preg_match('/出勤：(\d{2}:\d{2})/', $multiApplication->note, $inMatch);
            preg_match('/退勤：(\d{2}:\d{2})/', $multiApplication->note, $outMatch);
            preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multiApplication->note, $breakMatches, PREG_SET_ORDER);
        }
    
        // === 出勤時刻 ===
        if ($modClockIn) {
            $clockIn = Carbon::createFromFormat('H:i', $modClockIn->new_value); // 管理者修正が最優先
        } elseif ($multiApplication && !empty($inMatch[1])) {
            $clockIn = Carbon::createFromFormat('H:i', $inMatch[1]); // 承認済みApplicationのnote
        } elseif ($clockInRecord) {
            $clockIn = $clockInRecord->timestamp; // 最後に元データ
        }

        // === 退勤時刻 ===
        if ($modClockOut) {
            $clockOut = Carbon::createFromFormat('H:i', $modClockOut->new_value);
        } elseif ($multiApplication && !empty($outMatch[1])) {
            $clockOut = Carbon::createFromFormat('H:i', $outMatch[1]);
        } elseif ($clockOutRecord) {
            $clockOut = $clockOutRecord->timestamp;
        }

        $attendanceIds = Attendance::where('user_id', $record->user_id)
            ->whereDate('timestamp', $baseDate)
            ->pluck('id');
    
        $modBreaks = AttendanceModification::whereIn('attendance_id', $attendanceIds)
            ->where('user_id', $record->user_id)
            ->whereDate('modified_at', $baseDate)
            ->where(function ($q) {
                $q->where('field', 'like', 'break_start_%')
                  ->orWhere('field', 'like', 'break_end_%');
            })
            ->get()
            ->keyBy('field');
    
        $breakPairs = [];
        $modBreakStarts = [];
        $modBreakEnds = [];
    
        foreach ($modBreaks as $field => $mod) {
            if (str_starts_with($field, 'break_start_')) {
                $index = (int) str_replace('break_start_', '', $field);
                $modBreakStarts[$index] = $mod->new_value;
            } elseif (str_starts_with($field, 'break_end_')) {
                $index = (int) str_replace('break_end_', '', $field);
                $modBreakEnds[$index] = $mod->new_value;
            }
        }
    
        if (!empty($modBreakStarts) || !empty($modBreakEnds)) {
            $allIndexes = collect(array_unique(array_merge(array_keys($modBreakStarts), array_keys($modBreakEnds))))->sort()->values();
            foreach ($allIndexes as $i) {
                $start = $modBreakStarts[$i] ?? null;
                $end = $modBreakEnds[$i] ?? null;
                if ($start && $end) {
                    $breakPairs[] = [
                        'start' => Carbon::createFromFormat('H:i', $start)->format('H:i'),
                        'end' => Carbon::createFromFormat('H:i', $end)->format('H:i'),
                    ];
                }
            }
        }
    
        if (empty($breakPairs) && $multiApplication) {
            preg_match('/出勤：(\d{2}:\d{2})/', $multiApplication->note, $inMatch);
            preg_match('/退勤：(\d{2}:\d{2})/', $multiApplication->note, $outMatch);
            preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multiApplication->note, $breakMatches, PREG_SET_ORDER);
    
            if (!$clockIn && !empty($inMatch[1])) {
                $clockIn = Carbon::createFromFormat('H:i', $inMatch[1]);
            }
            if (!$clockOut && !empty($outMatch[1])) {
                $clockOut = Carbon::createFromFormat('H:i', $outMatch[1]);
            }
    
            foreach ($breakMatches as $match) {
                $breakPairs[] = [
                    'start' => $match[1],
                    'end' => $match[2],
                ];
            }

        }
    
        if (empty($breakPairs)) {
            $applicationBreaks = AttendanceApplication::where('attendance_id', $record->id)
                ->where('user_id', $record->user_id)
                ->whereIn('event_type', ['break_start', 'break_end'])
                ->orderBy('new_time')
                ->get();
    
            $start = null;
    
            foreach ($applicationBreaks as $entry) {
                if ($entry->event_type === 'break_start') {
                    $start = Carbon::parse($entry->new_time);
                } elseif ($entry->event_type === 'break_end' && $start) {
                    $breakPairs[] = [
                        'start' => $start->format('H:i'),
                        'end' => Carbon::parse($entry->new_time)->format('H:i'),
                    ];
                    $start = null;
                }
            }
        }
    
        if (empty($breakPairs)) {
            $breakStarts = Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'break_start')
                ->orderBy('timestamp')->get();
    
            $breakEnds = Attendance::where('user_id', $record->user_id)
                ->whereDate('timestamp', $baseDate)
                ->where('type', 'break_end')
                ->orderBy('timestamp')->get();
    
            $pairCount = min($breakStarts->count(), $breakEnds->count());
    
            for ($i = 0; $i < $pairCount; $i++) {
                $breakPairs[] = [
                    'start' => Carbon::parse($breakStarts[$i]->timestamp)->format('H:i'),
                    'end' => Carbon::parse($breakEnds[$i]->timestamp)->format('H:i'),
                ];
            }
        }
    
        $isApproved = $application && $application->status === '承認';
    
        $noteToDisplay = '';
        if ($application) {
            $noteRaw = $application->note ?? '';
            if (preg_match('/備考：(.+)/u', $noteRaw, $matches)) {
                $noteToDisplay = trim($matches[1]);
            } else {
                $noteToDisplay = $noteRaw;
            }
        }

        // 管理者修正がある場合は、備考を優先
        $hasModification = $modClockIn || $modClockOut || $modBreaks->isNotEmpty();
    
        return view('staff.attendance.detail', compact(
            'record',
            'application',
            'clockIn',
            'clockOut',
            'breakPairs',
            'viewType',
            'isPending',
            'isApproved',
            'noteToDisplay',
            'hasModification'
        ));
        
    }
    
    public function applicationDetail($id)
    {
        $userId = auth()->id();

        $application = AttendanceApplication::with('attendance', 'user')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $attendanceId = $application->attendance_id;
        $record = $application->attendance;
        $baseDate = \Carbon\Carbon::parse($application->old_time)->toDateString();
        $user_id = $application->user_id;

        $applications = AttendanceApplication::where('attendance_id', $attendanceId)
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('event_type');

        $clockIn = null;
        $clockOut = null;
        $breakPairs = [];
        $noteToDisplay = '';

        // ✅ 複数申請優先で処理
        $multi = $applications->firstWhere('event_type', '複数申請');
        if ($multi) {
            preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch);
            preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch);
            preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multi->note, $breakMatches, PREG_SET_ORDER);

            $clockIn = isset($inMatch[1]) ? Carbon::createFromFormat('H:i', $inMatch[1]) : null;
            $clockOut = isset($outMatch[1]) ? Carbon::createFromFormat('H:i', $outMatch[1]) : null;

            foreach ($breakMatches as $match) {
                $breakPairs[] = ['start' => $match[1], 'end' => $match[2]];
            }

            if (preg_match('/備考：(.+)/u', $multi->note, $matches)) {
                $noteToDisplay = trim($matches[1]);
            }
        } else {
            // ⬇ 通常の個別申請で取得
            $clockInRaw = $applications->where('event_type', 'clock_in')->first()?->new_time;
            $clockOutRaw = $applications->where('event_type', 'clock_out')->first()?->new_time;

            $clockIn = $clockInRaw ? Carbon::parse($clockInRaw) : null;
            $clockOut = $clockOutRaw ? Carbon::parse($clockOutRaw) : null;

            $breakStarts = $applications->where('event_type', 'break_start')->values();
            $breakEnds   = $applications->where('event_type', 'break_end')->values();

            for ($i = 0; $i < min($breakStarts->count(), $breakEnds->count()); $i++) {
                $breakPairs[] = [
                    'start' => Carbon::parse($breakStarts[$i]->new_time)->format('H:i'),
                    'end'   => Carbon::parse($breakEnds[$i]->new_time)->format('H:i'),
                ];
            }

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
                        'end'   => $breakEnds[$i]->timestamp->format('H:i'),
                    ];
                }
            }

            // 備考（通常申請）
            $noteRaw = $application->note;
            if (preg_match('/備考：(.+)/u', $noteRaw, $matches)) {
                $noteToDisplay = trim($matches[1]);
            } else {
                $noteToDisplay = $noteRaw;
            }
        }

        // ✅ 共通の return を最後にまとめる
        return view('staff.attendance.detail', [
            'application' => $application,
            'record' => $record,
            'clockIn' => $clockIn,
            'clockOut' => $clockOut,
            'breakPairs' => $breakPairs,
            'isPending' => $application->status === '承認待ち',
            'isApproved' => $application->status === '承認',
            'noteToDisplay' => $noteToDisplay,
        ]);
    }

    public function update(AttendanceRequest $request, $id)
    {
        $userId = auth()->id();

        $pendingExists = AttendanceApplication::where('attendance_id', $id)
            ->where('user_id', $userId)
            ->where('status', '承認待ち')
            ->exists();

        if ($pendingExists) {
            return redirect()->route('staff.attendance.show', ['id' => $id])
                ->with('error', 'すでに申請中です。承認が完了するまで再申請できません。');
        }

        $record = Attendance::with('user')->findOrFail($id);

        // 申請内容を文字列でまとめる
        $summary = [];

        if ($request->clock_in) {
            $summary[] = '出勤：' . $request->clock_in;
        }
        if ($request->clock_out) {
            $summary[] = '退勤：' . $request->clock_out;
        }

        // 休憩も無限対応
        $breakStarts = $request->only(array_filter(array_keys($request->all()), fn($key) => str_starts_with($key, 'break_start_')));
        foreach ($breakStarts as $key => $start) {
            $index = str_replace('break_start_', '', $key);
            $end = $request->input('break_end_' . $index);

            if ($start && $end) {
                $summary[] = '休憩' . $index . '：' . $start . '～' . $end;
            }
        }

        AttendanceApplication::create([
            'attendance_id' => $record->id,
            'user_id' => $userId,
            'type' => '修正申請',
            'event_type' => '複数申請', // 識別用
            'note' => implode(' / ', $summary) . ' / 備考：' . $request->note,
            'status' => '承認待ち',
            'old_time' => $record->timestamp,
        ]);

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
            ->map(function ($application) {
                // 備考だけを抽出
                $application->noteText = '';
                if (preg_match('/備考：([^\r\n\/]+)/u', $application->note, $matches)) {
                    $application->noteText = trim($matches[1]);
                } else {
                    $application->noteText = $application->note;
                }
                return $application;
            })
            ->groupBy(function ($application) {
                return Carbon::parse($application->old_time)->format('Y-m-d');
            });

        return view('staff.attendance.applicationlist', compact('applications', 'status'));
    }

    private function formatAttendanceData($records)
    {
        $userId = auth()->id();
    
        $latestApplications = AttendanceApplication::with('attendance')
            ->where('user_id', $userId)
            ->whereIn('status', ['承認', '承認待ち'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('attendance_id');
    
        $groupedApplications = $latestApplications->groupBy(function ($app) {
            return optional($app->attendance)->timestamp->format('Y-m-d');
        });
    
        $attendances = [];
        $recordsByDate = $records->groupBy(fn($r) => $r->timestamp->format('Y-m-d'));
    
        foreach ($recordsByDate as $date => $dailyRecords) {
            $attendances[$date] = [
                'id' => $dailyRecords->first()->id,
                'clock_in' => null,
                'clock_out' => null,
                'break' => '0:00',
                'total' => '0:00',
            ];
    
            $breakMinutes = 0; // ← ここで一度だけ初期化
    
            $clockInRecord = $dailyRecords->firstWhere('type', 'clock_in');
            $clockOutRecord = $dailyRecords->firstWhere('type', 'clock_out');
    
            $modClockIn = $clockInRecord ? AttendanceModification::where('attendance_id', $clockInRecord->id)
                ->where('field', 'clock_in')
                ->latest('modified_at')
                ->first() : null;
    
            $modClockOut = $clockOutRecord ? AttendanceModification::where('attendance_id', $clockOutRecord->id)
                ->where('field', 'clock_out')
                ->latest('modified_at')
                ->first() : null;
    
            if ($modClockIn) {
                $attendances[$date]['clock_in'] = Carbon::createFromFormat('H:i', $modClockIn->new_value)->format('H:i');
            }
            if ($modClockOut) {
                $attendances[$date]['clock_out'] = Carbon::createFromFormat('H:i', $modClockOut->new_value)->format('H:i');
            }
    
            $modifications = AttendanceModification::whereIn('attendance_id', $dailyRecords->pluck('id'))
                ->whereDate('modified_at', $date)
                ->get()
                ->filter(fn($mod) => str_starts_with($mod->field, 'break_'))
                ->keyBy('field');
    
            $breakStarts = [];
            $breakEnds = [];
    
            foreach ($modifications as $field => $mod) {
                if (str_starts_with($field, 'break_start_')) {
                    $index = (int) str_replace('break_start_', '', $field);
                    $breakStarts[$index] = $mod->new_value;
                } elseif (str_starts_with($field, 'break_end_')) {
                    $index = (int) str_replace('break_end_', '', $field);
                    $breakEnds[$index] = $mod->new_value;
                }
            }
    
            foreach (collect(array_unique(array_merge(array_keys($breakStarts), array_keys($breakEnds))))->sort() as $i) {
                $start = $breakStarts[$i] ?? null;
                $end = $breakEnds[$i] ?? null;
                if ($start && $end) {
                    $breakMinutes += Carbon::createFromFormat('H:i', $end)->diffInMinutes(Carbon::createFromFormat('H:i', $start));
                }
            }
    
            if (isset($groupedApplications[$date])) {
                $apps = $groupedApplications[$date];
                $multi = $apps->firstWhere('event_type', '複数申請');
    
                if ($multi) {
                    preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch);
                    preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch);
                    preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multi->note, $breakMatches, PREG_SET_ORDER);
    
                    if (!$attendances[$date]['clock_in'] && isset($inMatch[1])) {
                        $attendances[$date]['clock_in'] = $inMatch[1];
                    }
                    if (!$attendances[$date]['clock_out'] && isset($outMatch[1])) {
                        $attendances[$date]['clock_out'] = $outMatch[1];
                    }
    
                    foreach ($breakMatches as $m) {
                        $breakMinutes += Carbon::createFromFormat('H:i', $m[2])->diffInMinutes(Carbon::createFromFormat('H:i', $m[1]));
                    }
                } else {
                    $in = $apps->firstWhere('event_type', 'clock_in')?->new_time;
                    $out = $apps->firstWhere('event_type', 'clock_out')?->new_time;
    
                    if (!$attendances[$date]['clock_in'] && $in) {
                        $attendances[$date]['clock_in'] = Carbon::parse($in)->format('H:i');
                    }
                    if (!$attendances[$date]['clock_out'] && $out) {
                        $attendances[$date]['clock_out'] = Carbon::parse($out)->format('H:i');
                    }
    
                    $startApps = $apps->where('event_type', 'break_start')->values();
                    $endApps = $apps->where('event_type', 'break_end')->values();
    
                    for ($i = 0; $i < min($startApps->count(), $endApps->count()); $i++) {
                        $breakMinutes += Carbon::parse($endApps[$i]->new_time)->diffInMinutes(Carbon::parse($startApps[$i]->new_time));
                    }
                }
            } else {
                if (!$attendances[$date]['clock_in']) {
                    $in = $clockInRecord?->timestamp;
                    $attendances[$date]['clock_in'] = $in ? Carbon::parse($in)->format('H:i') : null;
                }
                if (!$attendances[$date]['clock_out']) {
                    $out = $clockOutRecord?->timestamp;
                    $attendances[$date]['clock_out'] = $out ? Carbon::parse($out)->format('H:i') : null;
                }
    
                $breaks = $dailyRecords->filter(fn($r) => str_starts_with($r->type, 'break'))->sortBy('timestamp')->values();
                for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                    $start = $breaks[$i]?->timestamp;
                    $end = $breaks[$i + 1]?->timestamp;
                    if ($start && $end) {
                        $breakMinutes += Carbon::parse($end)->diffInMinutes(Carbon::parse($start));
                    }
                }
            }
    
            // 休憩最終反映
            if ($breakMinutes > 0) {
                $attendances[$date]['break'] = gmdate('H:i', $breakMinutes * 60);
            }
    
            // 合計時間（出勤・退勤・休憩があるとき）
            if ($attendances[$date]['clock_in'] && $attendances[$date]['clock_out']) {
                $start = Carbon::createFromFormat('H:i', $attendances[$date]['clock_in']);
                $end = Carbon::createFromFormat('H:i', $attendances[$date]['clock_out']);
                $work = $end->diffInMinutes($start) - $breakMinutes;
                $attendances[$date]['total'] = gmdate('H:i', max(0, $work) * 60);
            }
        }
    
        return $attendances;
    }
}
