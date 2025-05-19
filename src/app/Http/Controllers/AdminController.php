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

            $clockInRecord = $records->firstWhere('type', 'clock_in');
            $clockOutRecord = $records->firstWhere('type', 'clock_out');

            $modClockIn = null;
            $modClockOut = null;

            if ($clockInRecord) {
                $modClockIn = AttendanceModification::where('attendance_id', $clockInRecord->id)
                    ->where('field', 'clock_in')
                    ->latest('modified_at')
                    ->first();
            }
            if ($clockOutRecord) {
                $modClockOut = AttendanceModification::where('attendance_id', $clockOutRecord->id)
                    ->where('field', 'clock_out')
                    ->latest('modified_at')
                    ->first();
            }

            $applications = AttendanceApplication::where('user_id', $userId)
                ->whereBetween('old_time', [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()])
                ->where('status', '承認')
                ->get();

            $multi = $applications->firstWhere('event_type', '複数申請');

            $clockIn = $modClockIn ? Carbon::createFromFormat('H:i', $modClockIn->new_value) : null;
            $clockOut = $modClockOut ? Carbon::createFromFormat('H:i', $modClockOut->new_value) : null;

            if (!$clockIn) {
                if ($applications->firstWhere('event_type', 'clock_in')?->new_time) {
                    $clockIn = Carbon::parse($applications->firstWhere('event_type', 'clock_in')->new_time);
                } elseif ($multi && preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch)) {
                    $clockIn = Carbon::createFromFormat('H:i', $inMatch[1]);
                } elseif ($clockInRecord?->timestamp) {
                    $clockIn = $clockInRecord->timestamp;
                }
            }

            if (!$clockOut) {
                if ($applications->firstWhere('event_type', 'clock_out')?->new_time) {
                    $clockOut = Carbon::parse($applications->firstWhere('event_type', 'clock_out')->new_time);
                } elseif ($multi && preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch)) {
                    $clockOut = Carbon::createFromFormat('H:i', $outMatch[1]);
                } elseif ($clockOutRecord?->timestamp) {
                    $clockOut = $clockOutRecord->timestamp;
                }
            }

            $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))->sortBy('timestamp')->values();
            $defaultBreakMinutes = 0;
            for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                $start = optional($breaks->get($i))->timestamp;
                $end = optional($breaks->get($i + 1))->timestamp;
                if ($start && $end) {
                    $defaultBreakMinutes += $end->diffInMinutes($start);
                }
            }

            $modifications = AttendanceModification::whereIn('attendance_id', $records->pluck('id'))
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
                if ($applications->isNotEmpty()) {
                    $multi = $applications->firstWhere('event_type', '複数申請');
                    if ($multi) {
                        preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multi->note, $breakMatches, PREG_SET_ORDER);
                        foreach ($breakMatches as $match) {
                            $start = Carbon::createFromFormat('H:i', $match[1]);
                            $end = Carbon::createFromFormat('H:i', $match[2]);
                            $breakMinutes += $end->diffInMinutes($start);
                        }
                    } else {
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

            $workMinutes = ($clockIn && $clockOut) ? max(0, $clockOut->diffInMinutes($clockIn) - $breakMinutes) : 0;

            return (object) [
                'id' => $clockInRecord?->id ?? $records->first()->id,
                'user' => $user,
                'clock_in' => $clockIn instanceof Carbon ? $clockIn->format('H:i') : null,
                'clock_out' => $clockOut instanceof Carbon ? $clockOut->format('H:i') : null,
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

        // 出勤・退勤レコードを明示的に取得
        $clockInRecord = $records->firstWhere('type', 'clock_in');
        $clockOutRecord = $records->firstWhere('type', 'clock_out');

        // 出勤・退勤の修正履歴を取得してエラーを防ぐ
        $modClockIn = null;
        $modClockOut = null;

        // 出勤時刻
        if ($clockInRecord) {
            $modClockIn = AttendanceModification::where('attendance_id', $clockInRecord->id)
                ->where('field', 'clock_in')
                ->latest('modified_at')
                ->first();

            $clockIn = $modClockIn
                ? Carbon::createFromFormat('H:i', $modClockIn->new_value)
                : $clockInRecord->timestamp;
        }

        // 退勤時刻
        if ($clockOutRecord) {
            $modClockOut = AttendanceModification::where('attendance_id', $clockOutRecord->id)
                ->where('field', 'clock_out')
                ->latest('modified_at')
                ->first();

            $clockOut = $modClockOut
                ? Carbon::createFromFormat('H:i', $modClockOut->new_value)
                : $clockOutRecord->timestamp;
        }

        // 3. 承認済み申請（管理者修正がない場合のみ）
        if (!$modClockIn) {
            $multiApp = $applications->first(function ($app) {
                return $app->event_type === '複数申請' && str_contains($app->note, '出勤：');
            });

            if ($multiApp && preg_match('/出勤：(\d{2}:\d{2})/', $multiApp->note, $matches)) {
                $clockIn = Carbon::parse($multiApp->old_time)->setTimeFrom(Carbon::createFromFormat('H:i', $matches[1]));
            }
        }

        if (!$modClockOut) {
            $multiApp = $applications->first(function ($app) {
                return $app->event_type === '複数申請' && str_contains($app->note, '退勤：');
            });

            if ($multiApp && preg_match('/退勤：(\d{2}:\d{2})/', $multiApp->note, $matches)) {
                $clockOut = Carbon::parse($multiApp->old_time)->setTimeFrom(Carbon::createFromFormat('H:i', $matches[1]));
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
            // 2. 申請（複数申請）を確認
            $multiApp = $applications->firstWhere('event_type', '複数申請');
        
            if ($multiApp && preg_match_all('/休憩\d+：(\d{2}:\d{2})〜(\d{2}:\d{2})/', $multiApp->note, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $breakPairs[] = [
                        'start' => $match[1],
                        'end' => $match[2],
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

    // スタッフの打刻月次一覧
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
            ->groupBy(fn($r) => $r->timestamp->toDateString());

            $attendances = $rawAttendances->map(function ($records, $date) use ($user_id) {
                $base = $records->first();
            
                $applications = AttendanceApplication::where('user_id', $user_id)
                    ->whereBetween('old_time', [
                        Carbon::parse($date)->startOfDay(),
                        Carbon::parse($date)->endOfDay(),
                    ])
                    ->where('status', '承認')
                    ->orderByDesc('created_at')
                    ->get();
            
                $clockInRecord = $records->firstWhere('type', 'clock_in');
                $clockOutRecord = $records->firstWhere('type', 'clock_out');
            
                $modClockIn = null;
                $modClockOut = null;
            
                if ($clockInRecord) {
                    $modClockIn = AttendanceModification::where('attendance_id', $clockInRecord->id)
                        ->where('field', 'clock_in')
                        ->latest('modified_at')
                        ->first();
                }
                if ($clockOutRecord) {
                    $modClockOut = AttendanceModification::where('attendance_id', $clockOutRecord->id)
                        ->where('field', 'clock_out')
                        ->latest('modified_at')
                        ->first();
                }
            
                // 出勤・退勤の優先表示（Modification → Application → Attendance）
                $clockIn = null;
                $clockOut = null;
                
                if ($modClockIn) {
                    $clockIn = Carbon::createFromFormat('H:i', $modClockIn->new_value);
                }
                if ($modClockOut) {
                    $clockOut = Carbon::createFromFormat('H:i', $modClockOut->new_value);
                }
                
                $multi = $applications->firstWhere('event_type', '複数申請');
                
                if (!$modClockIn) {
                    $appIn = $applications->firstWhere('event_type', 'clock_in');
                    if ($appIn?->new_time) {
                        $clockIn = Carbon::parse($appIn->new_time);
                    } elseif ($multi && preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch)) {
                        $clockIn = Carbon::createFromFormat('H:i', $inMatch[1]);
                    } elseif ($clockInRecord?->timestamp) {
                        $clockIn = $clockInRecord->timestamp;
                    }
                }
                
                if (!$modClockOut) {
                    $appOut = $applications->firstWhere('event_type', 'clock_out');
                    if ($appOut?->new_time) {
                        $clockOut = Carbon::parse($appOut->new_time);
                    } elseif ($multi && preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch)) {
                        $clockOut = Carbon::createFromFormat('H:i', $outMatch[1]);
                    } elseif ($clockOutRecord?->timestamp) {
                        $clockOut = $clockOutRecord->timestamp;
                    }
                }
            
                // 休憩取得
                $breakPairs = [];
            
                // 1. Modificationの休憩（field = break_start_0, break_end_0...）
                $modifications = AttendanceModification::where('user_id', $user_id)
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
            
                $allIndexes = collect(array_unique(array_merge(array_keys($breakStarts), array_keys($breakEnds))))->sort()->values();
                if ($allIndexes->isNotEmpty()) {
                    foreach ($allIndexes as $i) {
                        $start = $breakStarts[$i] ?? null;
                        $end = $breakEnds[$i] ?? null;
                        if ($start && $end) {
                            $breakPairs[] = [
                                'start' => Carbon::createFromFormat('H:i', $start),
                                'end' => Carbon::createFromFormat('H:i', $end),
                            ];
                        }
                    }
                } else {
                    // 2. 複数申請 noteから
                    $multi = $applications->firstWhere('event_type', '複数申請');
                    if ($multi && preg_match_all('/休憩\d+：(\d{2}:\d{2})〜(\d{2}:\d{2})/', $multi->note, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $breakPairs[] = [
                                'start' => Carbon::createFromFormat('H:i', $m[1]),
                                'end' => Carbon::createFromFormat('H:i', $m[2]),
                            ];
                        }
            
                        // 申請に出退勤が含まれている場合
                        if (preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch)) {
                            $clockIn = Carbon::createFromFormat('H:i', $inMatch[1]);
                        }
                        if (preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch)) {
                            $clockOut = Carbon::createFromFormat('H:i', $outMatch[1]);
                        }
            
                    } else {
                        // 3. 個別申請 or Attendance
                        $starts = $applications->where('event_type', 'break_start')->values();
                        $ends = $applications->where('event_type', 'break_end')->values();
            
                        if ($starts->count() && $ends->count()) {
                            for ($i = 0; $i < min($starts->count(), $ends->count()); $i++) {
                                $breakPairs[] = [
                                    'start' => Carbon::parse($starts[$i]->new_time),
                                    'end' => Carbon::parse($ends[$i]->new_time),
                                ];
                            }
                        } else {
                            $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))->sortBy('timestamp')->values();
                            for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                                $start = $breaks->get($i)?->timestamp;
                                $end = $breaks->get($i + 1)?->timestamp;
                                if ($start && $end) {
                                    $breakPairs[] = [
                                        'start' => $start,
                                        'end' => $end,
                                    ];
                                }
                            }
                        }
                    }
                }
            
                $breakMinutes = collect($breakPairs)->sum(fn($pair) => $pair['end']->diffInMinutes($pair['start']));
            
                $workMinutes = ($clockIn && $clockOut)
                    ? Carbon::parse($clockOut)->diffInMinutes(Carbon::parse($clockIn)) - $breakMinutes
                    : 0;
            
                return (object)[
                    'date' => $date,
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
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

    // 管理者用 申請承認
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

        $attendances = $rawAttendances->map(function ($records, $date) use ($user_id) {
        // 🔧 エラー対策：最初のAttendanceを基準レコードとして保存
        $base = $records->first();

        // 出勤・退勤レコードを明示的に取得
        $clockInRecord = $records->firstWhere('type', 'clock_in');
        $clockOutRecord = $records->firstWhere('type', 'clock_out');

        // 管理者による修正（出勤）
        if ($clockInRecord) {
            $modClockIn = AttendanceModification::where('attendance_id', $clockInRecord->id)
                ->where('field', 'clock_in')
                ->latest('modified_at')
                ->first();

            if ($modClockIn) {
                $clockIn = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $modClockIn->new_value);
            } else {
                $clockIn = $clockInRecord->timestamp;
            }
        }

            // 管理者による修正（退勤）
            if ($clockOutRecord) {
                $modClockOut = AttendanceModification::where('attendance_id', $clockOutRecord->id)
                    ->where('field', 'clock_out')
                    ->latest('modified_at')
                    ->first();

                if ($modClockOut) {
                    $clockOut = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $modClockOut->new_value);
                } else {
                    $clockOut = $clockOutRecord->timestamp;
                }
            }

            // 初期化
            $breakMinutes = 0;
            $breakStarts = [];
            $breakEnds = [];

            // ✅ 休憩修正用：その日のすべての修正を取得
            $modifications = AttendanceModification::where('user_id', $user_id)
            ->whereDate('modified_at', $date)
            ->get()
            ->keyBy('field');

            foreach ($modifications as $field => $mod) {
                if (str_starts_with($field, 'break_start_')) {
                    $index = (int) str_replace('break_start_', '', $field);
                    $breakStarts[$index] = $mod->new_value;
                } elseif (str_starts_with($field, 'break_end_')) {
                    $index = (int) str_replace('break_end_', '', $field);
                    $breakEnds[$index] = $mod->new_value;
                }
            }

            $allIndexes = collect(array_unique(array_merge(array_keys($breakStarts), array_keys($breakEnds))))->sort()->values();
            if ($allIndexes->isNotEmpty()) {
                foreach ($allIndexes as $i) {
                    $start = $breakStarts[$i] ?? null;
                    $end = $breakEnds[$i] ?? null;
                    if ($start && $end) {
                        // 休憩時間（修正 > 申請 > Attendance）
                        $breakMinutes += Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $end)
                        ->diffInMinutes(Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $start));    
                    }
                }
            } else {
                // 修正がなければ申請を見る
                $applications = AttendanceApplication::where('attendance_id', $base->id)
                    ->where('status', '承認')
                    ->orderBy('created_at', 'desc')
                    ->get();

                if (!$modClockIn) {
                    $clockIn = $applications->where('event_type', 'clock_in')->first()?->new_time ?? $clockIn;
                }

                if (!$modClockOut) {
                    $clockOut = $applications->where('event_type', 'clock_out')->first()?->new_time ?? $clockOut;
                }

                $startApps = $applications->where('event_type', 'break_start')->values();
                $endApps = $applications->where('event_type', 'break_end')->values();

                if ($startApps->count() && $endApps->count()) {
                    for ($i = 0; $i < min($startApps->count(), $endApps->count()); $i++) {
                        $start = Carbon::parse($startApps[$i]->new_time);
                        $end = Carbon::parse($endApps[$i]->new_time);
                        $breakMinutes += $end->diffInMinutes($start);
                    }
                } else {
                    // 申請もなければAttendanceから
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

            // 勤務時間計算
            if ($clockIn && $clockOut) {
                $start = $clockIn instanceof \Carbon\Carbon ? $clockIn : Carbon::parse($clockIn);
                $end = $clockOut instanceof \Carbon\Carbon ? $clockOut : Carbon::parse($clockOut);
                $workMinutes = $end->diffInMinutes($start) - $breakMinutes;
            } else {
                $workMinutes = 0;
            }

            // 出力用の整形
            $clockInFormatted = $clockIn instanceof \Carbon\Carbon ? $clockIn->format('H:i') : ($clockIn ? Carbon::parse($clockIn)->format('H:i') : '');
            $clockOutFormatted = $clockOut instanceof \Carbon\Carbon ? $clockOut->format('H:i') : ($clockOut ? Carbon::parse($clockOut)->format('H:i') : '');

            return [
                'date' => $date,
                'clock_in' => $clockInFormatted,
                'clock_out' => $clockOutFormatted,
                'break_time' => $breakMinutes > 0 ? gmdate('H:i', $breakMinutes * 60) : '',
                'work_time' => $workMinutes > 0 ? gmdate('H:i', $workMinutes * 60) : '',
            ];
            
        })->values()->toArray();

        $csvHeader = ['日付', '出勤', '退勤', '休憩', '合計'];

        $response = new StreamedResponse(function () use ($csvHeader, $attendances) {
            $handle = fopen('php://output', 'w');

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
