<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\User;
use App\Models\AttendanceModification;
use App\Models\AttendanceApplication;
use Illuminate\Support\Collection;
use App\Http\Requests\AttendanceModificationRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    // 管理者用トップページ
    public function index(Request $request)
    {
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : Carbon::today();
    
        // スタッフの打刻を取得
        $rawAttendances = Attendance::with('user')
            ->whereDate('timestamp', $date)
            ->get()
            ->groupBy('user_id');
    
        // 管理者の修正、申請からも取得
        $attendances = $rawAttendances->map(function ($records, $userId) use ($date) {
            $user = $records->first()->user;
    
            // Attendance元データ（最後のfallback用）
            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;
    
            $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))->sortBy('timestamp')->values();
            $defaultBreakMinutes = 0;
            for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                $start = optional($breaks->get($i))->timestamp;
                $end = optional($breaks->get($i + 1))->timestamp;
                if ($start && $end) {
                    $defaultBreakMinutes += $end->diffInMinutes($start);
                }
            }
    
            // AttendanceModification（最優先）
            $modifications = AttendanceModification::where('user_id', $userId)
                ->whereDate('modified_at', $date)
                ->orderByDesc('modified_at')
                ->get()
                ->keyBy('field');
    
            $modClockIn = $modifications->get('clock_in')?->new_value;
            $modClockOut = $modifications->get('clock_out')?->new_value;
    
            if ($modClockIn) {
                $clockIn = Carbon::createFromFormat('H:i', $modClockIn);
            }
            if ($modClockOut) {
                $clockOut = Carbon::createFromFormat('H:i', $modClockOut);
            }
    
            // 休憩修正
            $breakStarts = [];
            $breakEnds = [];
            $modBreaks = $modifications->filter(fn($mod) => str_starts_with($mod->field, 'break_'));
            foreach ($modBreaks as $field => $mod) {
                if (str_starts_with($field, 'break_start_')) {
                    $index = (int) str_replace('break_start_', '', $field);
                    $breakStarts[$index] = $mod->new_value;
                } elseif (str_starts_with($field, 'break_end_')) {
                    $index = (int) str_replace('break_end_', '', $field);
                    $breakEnds[$index] = $mod->new_value;
                }
            }
    
            $allBreakIndexes = collect(array_unique(array_merge(array_keys($breakStarts), array_keys($breakEnds))))->sort()->values();
            $breakMinutes = 0;
            if ($allBreakIndexes->isNotEmpty()) {
                foreach ($allBreakIndexes as $i) {
                    $start = $breakStarts[$i] ?? null;
                    $end = $breakEnds[$i] ?? null;
                    if ($start && $end) {
                        $breakMinutes += Carbon::createFromFormat('H:i', $end)->diffInMinutes(Carbon::createFromFormat('H:i', $start));
                    }
                }
            } else {
                // 承認済み申請（次優先）
                $applications = AttendanceApplication::where('user_id', $userId)
                    ->whereDate('old_time', $date)
                    ->where('status', '承認')
                    ->orderBy('created_at', 'desc')
                    ->get();
    
                if ($applications->isNotEmpty()) {
                    $multi = $applications->firstWhere('event_type', '複数申請');
                    if ($multi) {
                        preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch);
                        preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch);
                        preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multi->note, $breakMatches, PREG_SET_ORDER);
                        // 複数申請のnoteを解析
    
                        if (!$modClockIn && !empty($inMatch[1])) {
                            $clockIn = Carbon::createFromFormat('H:i', $inMatch[1]);
                        }
                        if (!$modClockOut && !empty($outMatch[1])) {
                            $clockOut = Carbon::createFromFormat('H:i', $outMatch[1]);
                        }
    
                        foreach ($breakMatches as $match) {
                            $start = Carbon::createFromFormat('H:i', $match[1]);
                            $end = Carbon::createFromFormat('H:i', $match[2]);
                            $breakMinutes += $end->diffInMinutes($start);
                        }
                    } else {
                        $clockInRaw = $applications->where('event_type', 'clock_in')->first()?->new_time;
                        $clockOutRaw = $applications->where('event_type', 'clock_out')->first()?->new_time;
                        // 個別申請がある場合
    
                        if (!$modClockIn && $clockInRaw) {
                            $clockIn = Carbon::parse($clockInRaw);
                        }
                        if (!$modClockOut && $clockOutRaw) {
                            $clockOut = Carbon::parse($clockOutRaw);
                        }
    
                        $startApps = $applications->where('event_type', 'break_start')->values();
                        $endApps = $applications->where('event_type', 'break_end')->values();
    
                        for ($i = 0; $i < min($startApps->count(), $endApps->count()); $i++) {
                            $start = Carbon::parse($startApps[$i]->new_time);
                            $end = Carbon::parse($endApps[$i]->new_time);
                            $breakMinutes += $end->diffInMinutes($start);
                        }
                    }
                } else {
                    $breakMinutes = $defaultBreakMinutes;
                }
            }
    
            // 勤務時間計算
            $workMinutes = ($clockIn && $clockOut) ? max(0, $clockOut->diffInMinutes($clockIn) - $breakMinutes) : 0;
    
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
    
    // 管理者用 打刻詳細表示
    public function edit(Request $request, $id)
    {
        $record = Attendance::findOrFail($id);
        $user_id = $record->user_id;
        $date = Carbon::parse($record->timestamp);
        $user = User::findOrFail($user_id);

        // 当日の全打刻
        $records = Attendance::where('user_id', $user_id)
            ->whereDate('timestamp', $date)
            ->get();

        // 承認済み申請
        $applications = AttendanceApplication::where('user_id', $user_id)
            ->whereDate('old_time', $date)
            ->where('status', '承認')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // 1. 初期 Attendance 値（あくまで最後の fallback 用）
        $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
        $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

        // 2. 管理者による修正（最優先）
        $modifications = AttendanceModification::where('attendance_id', $record->id)
            ->orderByDesc('modified_at')
            ->get()
            ->keyBy('field');

        $modClockIn = $modifications->get('clock_in')?->new_value;
        $modClockOut = $modifications->get('clock_out')?->new_value;

        if ($modClockIn) {
            $clockIn = Carbon::createFromFormat('H:i', $modClockIn);
        }
        if ($modClockOut) {
            $clockOut = Carbon::createFromFormat('H:i', $modClockOut);
        }

        // 3. 承認済み申請（管理者修正がない場合のみ）
        if (!$modClockIn) {
            $clockInApp = $applications->where('event_type', 'clock_in')->first()?->new_time;
            if ($clockInApp) {
                $clockIn = Carbon::parse($clockInApp);
            }
        }
        if (!$modClockOut) {
            $clockOutApp = $applications->where('event_type', 'clock_out')->first()?->new_time;
            if ($clockOutApp) {
                $clockOut = Carbon::parse($clockOutApp);
            }
        }

        $breakPairs = [];

        // 1. AttendanceModification を使ってペアを構築
        $modBreaks = $modifications->filter(fn($mod) => str_starts_with($mod->field, 'break_'));
        $breakStarts = [];
        $breakEnds = [];

        foreach ($modBreaks as $field => $mod) {
            if (str_starts_with($field, 'break_start_')) {
                $index = (int) str_replace('break_start_', '', $field);
                $breakStarts[$index] = $mod->new_value;
            } elseif (str_starts_with($field, 'break_end_')) {
                $index = (int) str_replace('break_end_', '', $field);
                $breakEnds[$index] = $mod->new_value;
            }
        }

        
        $allBreakIndexes = collect(array_unique(array_merge(array_keys($breakStarts), array_keys($breakEnds))))->sort()->values();

        if ($allBreakIndexes->isNotEmpty()) {
            foreach ($allBreakIndexes as $i) {
                $start = $breakStarts[$i] ?? null;
                $end = $breakEnds[$i] ?? null;

                $breakPairs[] = [
                    'start' => $start ? Carbon::createFromFormat('H:i', $start)->format('H:i') : null,
                    'end' => $end ? Carbon::createFromFormat('H:i', $end)->format('H:i') : null,
                ];
            }
        } else {
            // 2. 申請（承認済み）を確認
            $startApps = $applications->where('event_type', 'break_start')->values();
            $endApps = $applications->where('event_type', 'break_end')->values();

            if ($startApps->count() && $endApps->count()) {
                $pairCount = min($startApps->count(), $endApps->count());
                for ($i = 0; $i < $pairCount; $i++) {
                    $breakPairs[] = [
                        'start' => Carbon::parse($startApps[$i]->new_time)->format('H:i'),
                        'end' => Carbon::parse($endApps[$i]->new_time)->format('H:i'),
                    ];
                }
            } else {
                // 3. Attendanceから取得（typeが break_start / break_end）
                $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))
                    ->sortBy('timestamp')
                    ->values();

                for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                    $breakPairs[] = [
                        'start' => optional($breaks->get($i))->timestamp?->format('H:i'),
                        'end' => optional($breaks->get($i + 1))->timestamp?->format('H:i'),
                    ];
                }
            }
        }

        // 備考取得（Attendanceから）
        $noteRecord = Attendance::where('user_id', $user_id)
            ->where('type', 'note')
            ->whereDate('timestamp', $date)
            ->first();

        $note = $noteRecord?->note ?? '';

        return view('admin.attendance.show', compact(
            'user', 'date', 'clockIn', 'clockOut', 'record', 'breakPairs', 'note'
        ));
    }

    // 管理者用 打刻更新処理
    public function update(AttendanceModificationRequest $request, $id)
    {
        $record = Attendance::findOrFail($id);
        $userId = $record->user_id;
        $date = $record->timestamp->toDateString();

        $records = Attendance::where('user_id', $userId)
            ->whereDate('timestamp', $date)
            ->get();

        $applications = AttendanceApplication::where('user_id', $userId)
            ->whereDate('old_time', $date)
            ->whereIn('status', ['承認', '承認待ち'])
            ->get();

        // 出勤・退勤更新
        foreach (['clock_in', 'clock_out'] as $type) {
            if ($request->filled($type)) {
                $attendanceRecord = $records->firstWhere('type', $type);
                $before = $attendanceRecord?->timestamp;

                if ($attendanceRecord) {
                    $attendanceRecord->update([
                        'timestamp' => Carbon::parse($date . ' ' . $request->input($type))
                    ]);

                    AttendanceModification::create([
                        'admin_id' => auth('admin')->id(),
                        'user_id' => $userId,
                        'attendance_id' => $attendanceRecord->id,
                        'field' => $type,
                        'old_value' => $before ? $before->format('H:i') : null,
                        'new_value' => $request->input($type),
                        'modified_at' => now(),
                    ]);
                }

                // 関連申請も更新
                $applications->where('event_type', $type)->each(function ($application) use ($request, $type) {
                    $application->new_time = Carbon::parse($request->input($type));
                    if ($application->status === '承認待ち') {
                        $application->status = null;
                    }
                    $application->save();
                });
            }
        }

        // 休憩更新
        foreach ($records->filter(fn($r) => str_starts_with($r->type, 'break'))->values() as $index => $rec) {
            $pairIndex = (int) floor($index / 2) + 1;
            $field = $rec->type === 'break_start' ? "break_start_{$pairIndex}" : "break_end_{$pairIndex}";
            $time = $request->input($field);

            if ($time) {
                $before = $rec->timestamp;

                $rec->update([
                    'timestamp' => Carbon::parse($date . ' ' . $time)
                ]);

                AttendanceModification::create([
                    'admin_id' => auth('admin')->id(),
                    'user_id' => $userId,
                    'attendance_id' => $rec->id,
                    'field' => $field,
                    'old_value' => $before ? $before->format('H:i') : null,
                    'new_value' => $time,
                    'modified_at' => now(),
                ]);

                $applications->where('event_type', $rec->type)->each(function ($application) use ($time) {
                    $application->new_time = Carbon::parse($time);
                    if ($application->status === '承認待ち') {
                        $application->status = null;
                    }
                    $application->save();
                });
            }
        }

        // 備考更新
        if ($request->filled('note')) {
            $beforeNote = null; // 最初に空白だということを宣言

            $noteRecord = Attendance::where('user_id', $userId)
                ->where('type', 'note')
                ->whereDate('timestamp', $date)
                ->first();

            if (!$noteRecord) {
                // ないなら新規作成
                $noteRecord = Attendance::create([
                    'user_id' => $userId,
                    'timestamp' => Carbon::parse($date),
                    'type' => 'note',
                    'note' => $request->input('note'),
                ]);
            } else {
                // あれば更新
                $beforeNote = $noteRecord->note;

                $noteRecord->update([
                    'note' => $request->input('note'),
                ]);
            }

            // 変更履歴
            AttendanceModification::create([
                'admin_id' => auth('admin')->id(),
                'user_id' => $userId,
                'attendance_id' => $noteRecord->id,
                'field' => 'note',
                'old_value' => $beforeNote,
                'new_value' => $request->input('note'),
                'modified_at' => now(),
            ]);
        }

        return redirect()->route('admin.attendance.editshow', ['id' => $record->id])
            ->with('success', '勤怠情報を更新しました。');
    }

    public function staffListShow(Request $request)
    {
        $users = User::all();//　すべてのスタッフを取得

        return view('admin.staff_list', compact('users'));
    }

    public function adminApplicationListShow(Request $request)
    {
        $status = $request->input('status', '承認待ち'); // ← デフォルトで承認待ち

        $query = AttendanceApplication::with(['user', 'attendance']);

        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->orderBy('created_at', 'desc')->get();

        // 申請の配列の中から備考を抽出
        foreach ($applications as $application) {
            if (preg_match('/備考：([^\r\n\/]+)/u', $application->note, $matches)) {
                $application->noteText = trim($matches[1]);
            } else {
                $application->noteText = $application->note;
            }
        }

        return view('admin.application.admin_application_list', compact('applications', 'status'));
    }

    public function StaffAttendanceShow($user_id, Request $request)
    {
        $user = User::findOrFail($user_id);
        $baseDate = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $currentMonth = $baseDate->format('Y-m');

        $rawAttendances = Attendance::where('user_id', $user_id)
            ->whereBetween('timestamp', [
                $baseDate->copy()->startOfMonth(),
                $baseDate->copy()->endOfMonth(),
            ])
            ->orderBy('timestamp')
            ->get()
            ->groupBy(function ($record) {
                return $record->timestamp->toDateString();
            });

        $attendances = $rawAttendances->map(function ($records, $date) {
            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

            $base = $records->first();

            $applications = AttendanceApplication::where('attendance_id', $base->id)
                ->where('status', '承認')
                ->orderBy('created_at', 'desc')
                ->get();

            $multi = $applications->firstWhere('event_type', '複数申請');

            $breakMinutes = 0;

            if ($multi) {
                // ▼ 複数申請noteを解析
                preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch);
                preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch);
                preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multi->note, $breakMatches, PREG_SET_ORDER);

                $clockIn = !empty($inMatch[1]) ? Carbon::createFromFormat('H:i', $inMatch[1]) : $clockIn;
                $clockOut = !empty($outMatch[1]) ? Carbon::createFromFormat('H:i', $outMatch[1]) : $clockOut;

                foreach ($breakMatches as $match) {
                    $start = Carbon::createFromFormat('H:i', $match[1]);
                    $end = Carbon::createFromFormat('H:i', $match[2]);
                    $breakMinutes += $end->diffInMinutes($start);
                }
            } else {
                // ▼ 個別申請がある場合
                $clockIn = $applications->where('event_type', 'clock_in')->first()?->new_time ?? $clockIn;
                $clockOut = $applications->where('event_type', 'clock_out')->first()?->new_time ?? $clockOut;

                $breakStarts = $applications->where('event_type', 'break_start')->values();
                $breakEnds = $applications->where('event_type', 'break_end')->values();

                if ($breakStarts->count() && $breakEnds->count()) {
                    for ($i = 0; $i < min($breakStarts->count(), $breakEnds->count()); $i++) {
                        $start = Carbon::parse($breakStarts[$i]->new_time);
                        $end = Carbon::parse($breakEnds[$i]->new_time);
                        $breakMinutes += $end->diffInMinutes($start);
                    }
                } else {
                    // ▼ 申請もなければ Attendanceそのまま
                    $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))
                        ->sortBy('timestamp')->values();

                    for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                        $start = $breaks->get($i)?->timestamp;
                        $end = $breaks->get($i + 1)?->timestamp;
                        if ($start && $end) {
                            $breakMinutes += $end->diffInMinutes($start);
                        }
                    }
                }
            }

            $workMinutes = ($clockIn && $clockOut)
                ? Carbon::parse($clockOut)->diffInMinutes(Carbon::parse($clockIn)) - $breakMinutes
                : 0;

            return (object)[
                'date' => $date,
                'clock_in' => $clockIn ? Carbon::parse($clockIn) : null,
                'clock_out' => $clockOut ? Carbon::parse($clockOut) : null,
                'break_minutes' => $breakMinutes,
                'work_minutes' => max(0, $workMinutes),
                'record_id' => $base->id,
            ];
        });

        return view('admin.staff_attendance', compact('user', 'attendances', 'currentMonth'));
    }

    // 管理者から見た勤怠申請詳細
    public function applicationDetail($id)
    {
        $baseApplication = AttendanceApplication::with(['user', 'attendance'])->findOrFail($id);
        $record = $baseApplication->attendance;
        $user = $baseApplication->user;

        $baseDate = Carbon::parse($baseApplication->old_time)->toDateString();

        $applications = AttendanceApplication::where('attendance_id', $record->id)
            ->where('user_id', $record->user_id)
            ->where('status', '承認待ち')
            ->orderBy('created_at', 'asc')
            ->get();

        // ↓↓↓ ここから分岐を追加（複数申請対応）
        $clockIn = null;
        $clockOut = null;
        $breakPairs = [];

        if ($baseApplication->event_type === '複数申請') {
            // note から出勤・退勤・休憩を抽出
            $note = $baseApplication->note;

            preg_match('/出勤：(\d{2}:\d{2})/', $note, $inMatch);
            preg_match('/退勤：(\d{2}:\d{2})/', $note, $outMatch);
            preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $note, $breakMatches, PREG_SET_ORDER);

            if (!empty($inMatch[1])) {
                $clockIn = Carbon::createFromFormat('H:i', $inMatch[1]);
            }
            if (!empty($outMatch[1])) {
                $clockOut = Carbon::createFromFormat('H:i', $outMatch[1]);
            }

            foreach ($breakMatches as $match) {
                $breakPairs[] = [
                    'start' => $match[1],
                    'end' => $match[2],
                ];
            }
        } else {
            // ↓↓↓ 従来の個別申請タイプ処理
            $clockInRaw = $applications->where('event_type', 'clock_in')->first()?->new_time
                ?? Attendance::where('user_id', $record->user_id)
                    ->whereDate('timestamp', $baseDate)
                    ->where('type', 'clock_in')
                    ->first()?->timestamp;

            $clockOutRaw = $applications->where('event_type', 'clock_out')->first()?->new_time
                ?? Attendance::where('user_id', $record->user_id)
                    ->whereDate('timestamp', $baseDate)
                    ->where('type', 'clock_out')
                    ->first()?->timestamp;

            $clockIn = $clockInRaw ? \Carbon\Carbon::parse($clockInRaw) : null;
            $clockOut = $clockOutRaw ? \Carbon\Carbon::parse($clockOutRaw) : null;

            $breakStarts = $applications->where('event_type', 'break_start')->values();
            $breakEnds = $applications->where('event_type', 'break_end')->values();

            if ($breakStarts->count() && $breakEnds->count()) {
                for ($i = 0; $i < min($breakStarts->count(), $breakEnds->count()); $i++) {
                    $start = Carbon::parse($breakStarts[$i]->new_time)->format('H:i');
                    $end = Carbon::parse($breakEnds[$i]->new_time)->format('H:i');
                    $breakPairs[] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            }

            if (empty($breakPairs)) {
                $fallbackStarts = Attendance::where('user_id', $record->user_id)
                    ->whereDate('timestamp', $baseDate)
                    ->where('type', 'break_start')
                    ->orderBy('timestamp')->get();

                $fallbackEnds = Attendance::where('user_id', $record->user_id)
                    ->whereDate('timestamp', $baseDate)
                    ->where('type', 'break_end')
                    ->orderBy('timestamp')->get();

                for ($i = 0; $i < min($fallbackStarts->count(), $fallbackEnds->count()); $i++) {
                    $breakPairs[] = [
                        'start' => Carbon::parse($fallbackStarts[$i]->timestamp)->format('H:i'),
                        'end' => Carbon::parse($fallbackEnds[$i]->timestamp)->format('H:i'),
                    ];
                }
            }
        }

        $isPending = true;

        $isPending = $baseApplication->status === '承認待ち';
        $isApproved = $baseApplication->status === '承認';

        // 修正後（ベースの申請情報をそのまま使う）
        $application = $baseApplication;

        $noteRaw = $baseApplication->note ?? '';
        if (preg_match('/備考：(.+)/u', $noteRaw, $matches)) {
            $noteToDisplay = trim($matches[1]);
        } else {
            $noteToDisplay = $noteRaw;
        }

        return view('staff.attendance.detail', compact(
            'application',
            'record',
            'clockIn',
            'clockOut',
            'breakPairs',
            'isPending',
            'isApproved',
            'noteToDisplay'
        ));
    }

    public function approve($id)
    {
        $application = AttendanceApplication::findOrFail($id);
        $application->status = '承認';
        $application->save();

        return redirect()->back()->with('success', '修正申請を承認しました');
    }

    //CSV出力
    public function exportStaffAttendance($user_id, Request $request)
    {
        $user = User::findOrFail($user_id);
        $baseDate = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $currentMonth = $baseDate->format('Y-m');

        $rawAttendances = Attendance::where('user_id', $user_id)
            ->whereBetween('timestamp', [
                $baseDate->copy()->startOfMonth(),
                $baseDate->copy()->endOfMonth(),
            ])
            ->orderBy('timestamp')
            ->get()
            ->groupBy(function ($record) {
                return $record->timestamp->toDateString();
            });

        $attendances = $rawAttendances->map(function ($records, $date) {
            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

            $base = $records->first();

            $applications = AttendanceApplication::where('attendance_id', $base->id)
                ->where('status', '承認')
                ->orderBy('created_at', 'desc')
                ->get();

            $clockIn = $applications->where('event_type', 'clock_in')->first()?->new_time ?? $clockIn;
            $clockOut = $applications->where('event_type', 'clock_out')->first()?->new_time ?? $clockOut;

            $breakStarts = $applications->where('event_type', 'break_start')->values();
            $breakEnds = $applications->where('event_type', 'break_end')->values();

            $breakMinutes = 0;

            if ($breakStarts->count() && $breakEnds->count()) {
                for ($i = 0; $i < min($breakStarts->count(), $breakEnds->count()); $i++) {
                    $start = Carbon::parse($breakStarts[$i]->new_time);
                    $end = Carbon::parse($breakEnds[$i]->new_time);
                    $breakMinutes += $end->diffInMinutes($start);
                }
            } else {
                $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))
                    ->sortBy('timestamp')->values();

                for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                    $start = $breaks->get($i)?->timestamp;
                    $end = $breaks->get($i + 1)?->timestamp;
                    if ($start && $end) {
                        $breakMinutes += $end->diffInMinutes($start);
                    }
                }
            }

            $workMinutes = ($clockIn && $clockOut)
                ? Carbon::parse($clockOut)->diffInMinutes(Carbon::parse($clockIn)) - $breakMinutes
                : 0;

            return [
                'date' => $date,
                'clock_in' => $clockIn ? Carbon::parse($clockIn)->format('H:i') : '',
                'clock_out' => $clockOut ? Carbon::parse($clockOut)->format('H:i') : '',
                'break_time' => $breakMinutes > 0 ? gmdate('H:i', $breakMinutes * 60) : '',
                'work_time' => $workMinutes > 0 ? gmdate('H:i', $workMinutes * 60) : '',
            ];
        })->values()->toArray(); // ★ CSV出力用に配列にする

        $csvHeader = ['日付', '出勤', '退勤', '休憩', '合計'];

        $response = new StreamedResponse(function () use ($csvHeader, $attendances) {
            $handle = fopen('php://output', 'w');

            // 文字化け対策：ヘッダーとデータ両方にShift_JIS変換
            mb_convert_variables('SJIS-win', 'UTF-8', $csvHeader);
            fputcsv($handle, $csvHeader);

            foreach ($attendances as $row) {
                mb_convert_variables('SJIS-win', 'UTF-8', $row);
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_' . $currentMonth . '.csv"',
        ]);

        return $response;
    }

}
