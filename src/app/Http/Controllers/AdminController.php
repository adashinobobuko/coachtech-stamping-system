<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\User;
use App\Models\AttendanceModification;
use App\Models\AttendanceApplication;
use Illuminate\Support\Collection;
use App\Http\Requests\AttendanceRequest;

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

        $attendances = $rawAttendances->map(function ($records, $userId) {
            $user = $records->first()->user;

            // デフォルトの打刻
           $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

            $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))->sortBy('timestamp')->values();
            $breakMinutes = 0;
            for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                $start = optional($breaks->get($i))->timestamp;
                $end = optional($breaks->get($i + 1))->timestamp;
                if ($start && $end) {
                    $breakMinutes += $end->diffInMinutes($start);
                }
            }

            // 承認済みの申請があるなら、申請データで上書き
            $applications = AttendanceApplication::where('attendance_id', $records->first()->id)
                ->where('status', '承認')
                ->orderBy('created_at', 'desc')
                ->get();

            if ($applications->isNotEmpty()) {
                $multi = $applications->firstWhere('event_type', '複数申請');
                if ($multi) {
                    preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch);
                    preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch);
                    preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multi->note, $breakMatches, PREG_SET_ORDER);

                    $clockIn = !empty($inMatch[1]) ? Carbon::createFromFormat('H:i', $inMatch[1]) : $clockIn;
                    $clockOut = !empty($outMatch[1]) ? Carbon::createFromFormat('H:i', $outMatch[1]) : $clockOut;

                    $breakMinutes = 0;
                    foreach ($breakMatches as $match) {
                        $start = Carbon::createFromFormat('H:i', $match[1]);
                        $end = Carbon::createFromFormat('H:i', $match[2]);
                        $breakMinutes += $end->diffInMinutes($start);
                    }
                } else {
                    // 個別申請
                    $clockInRaw = $applications->where('event_type', 'clock_in')->first()?->new_time;
                    $clockOutRaw = $applications->where('event_type', 'clock_out')->first()?->new_time;

                    $clockIn = $clockInRaw ? Carbon::parse($clockInRaw) : $clockIn;
                    $clockOut = $clockOutRaw ? Carbon::parse($clockOutRaw) : $clockOut;

                    $startApps = $applications->where('event_type', 'break_start')->values();
                    $endApps = $applications->where('event_type', 'break_end')->values();

                    if ($startApps->count() && $endApps->count()) {
                        $breakMinutes = 0;
                        for ($i = 0; $i < min($startApps->count(), $endApps->count()); $i++) {
                            $start = Carbon::parse($startApps[$i]->new_time);
                            $end = Carbon::parse($endApps[$i]->new_time);
                            $breakMinutes += $end->diffInMinutes($start);
                        }
                    }
                }
            }

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
        // まず、打刻レコード（Attendance）を取る
        $record = Attendance::findOrFail($id);

        // そこから user_id を取得
        $user_id = $record->user_id;

        // 日付は recordのtimestampから取得
        $date = Carbon::parse($record->timestamp); 

        // 同じ日付の打刻まとめて取る（出勤、退勤、休憩など）
        $records = Attendance::where('user_id', $user_id)
            ->whereDate('timestamp', $date)
            ->get();

        $user = User::findOrFail($user_id);

        // 出退勤データ取得
        $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
        $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

        // 休憩ペア作成
        $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))
            ->sortBy('timestamp')
            ->values();

        $breakPairs = [];
        for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
            $breakPairs[] = [
                'start' => optional($breaks->get($i))->timestamp?->format('H:i'),
                'end'   => optional($breaks->get($i + 1))->timestamp?->format('H:i'),
            ];
        }

        // 備考データ取得
        // 備考データ取得（Attendanceテーブルを直接検索する！）
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
    public function update(Request $request, $id)
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
            $beforeNote = null; // 最初に宣言！

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

            return (object)[
                'date' => $date,
                'clock_in' => $clockIn ? Carbon::parse($clockIn) : null,
                'clock_out' => $clockOut ? Carbon::parse($clockOut) : null,
                'break_minutes' => $breakMinutes,
                'work_minutes' => $workMinutes,
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

}
