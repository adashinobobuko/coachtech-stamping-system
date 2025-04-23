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

        $attendances = $rawAttendances->map(function ($records, $userId) {
            $user = $records->first()->user;

            // デフォルトの打刻
            $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
            $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

            // デフォルトの休憩時間（申請がなければ使う）
            $breakMinutes = 0;
            $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))->sortBy('timestamp')->values();
            for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
                $start = optional($breaks->get($i))->timestamp;
                $end = optional($breaks->get($i + 1))->timestamp;
                if ($start && $end) {
                    $breakMinutes += $end->diffInMinutes($start);
                }
            }

            // 承認済みの申請
            $applications = AttendanceApplication::where('attendance_id', $records->first()->id)
                ->where('status', '承認')
                ->orderBy('created_at', 'desc')
                ->get();

            // 複数申請が優先
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
                // 個別申請で上書き（複数申請がない場合）
                $clockInRaw = $applications->where('event_type', 'clock_in')->first()?->new_time;
                $clockOutRaw = $applications->where('event_type', 'clock_out')->first()?->new_time;

                $clockIn = $clockInRaw ? Carbon::parse($clockInRaw) : $clockIn;
                $clockOut = $clockOutRaw ? Carbon::parse($clockOutRaw) : $clockOut;

                $startApps = $applications->where('event_type', 'break_start')->values();
                $endApps = $applications->where('event_type', 'break_end')->values();

                $breakMinutes = 0;
                for ($i = 0; $i < min($startApps->count(), $endApps->count()); $i++) {
                    $start = Carbon::parse($startApps[$i]->new_time);
                    $end = Carbon::parse($endApps[$i]->new_time);
                    $breakMinutes += $end->diffInMinutes($start);
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


    // 管理者用打刻詳細
    public function show(Request $request, $user_id)
    {
        $date = Carbon::parse($request->input('date'));

        $records = Attendance::where('user_id', $user_id)
            ->whereDate('timestamp', $date)
            ->get();

        $user = User::findOrFail($user_id);
        $record = $records->first(); // 代表レコードとして使用

        if (!$record) {
            abort(404, '打刻データが見つかりません');
        }

        // デフォルト（Attendance）から clockIn / clockOut
        $clockIn = $records->firstWhere('type', 'clock_in')?->timestamp;
        $clockOut = $records->firstWhere('type', 'clock_out')?->timestamp;

        // デフォルトの休憩ペア（Attendance）
        $breaks = $records->filter(fn($r) => str_starts_with($r->type, 'break'))
            ->sortBy('timestamp')->values();

        $breakPairs = [];
        for ($i = 0; $i < $breaks->count() - 1; $i += 2) {
            $breakPairs[] = [
                'start' => optional($breaks->get($i))->timestamp?->format('H:i'),
                'end' => optional($breaks->get($i + 1))->timestamp?->format('H:i'),
            ];
        }

        // 承認済みの申請を全取得
        $applications = AttendanceApplication::where('attendance_id', $record->id)
            ->where('status', '承認')
            ->orderBy('created_at', 'desc')
            ->get();

        // 申請に clock_in / clock_out があれば優先
        $clockInRaw = $applications->where('event_type', 'clock_in')->first()?->new_time ?? $clockIn;
        $clockOutRaw = $applications->where('event_type', 'clock_out')->first()?->new_time ?? $clockOut;

        $clockIn = $clockInRaw ? Carbon::parse($clockInRaw) : null;
        $clockOut = $clockOutRaw ? Carbon::parse($clockOutRaw) : null;

        // note は一番新しい申請から取得（または null）
        $note = $applications->first()?->note ?? '';

        // 申請から休憩時間を取得（上書き）
        $startApps = $applications->where('event_type', 'break_start')->values();
        $endApps = $applications->where('event_type', 'break_end')->values();

        if ($startApps->count() && $endApps->count()) {
            $breakPairs = []; // 上書きする
            $pairCount = min($startApps->count(), $endApps->count());

            for ($i = 0; $i < $pairCount; $i++) {
                $start = Carbon::parse($startApps[$i]->new_time)->format('H:i');
                $end = Carbon::parse($endApps[$i]->new_time)->format('H:i');

                $breakPairs[] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        $multi = $applications->firstWhere('event_type', '複数申請');

        if ($multi) {
            preg_match('/出勤：(\d{2}:\d{2})/', $multi->note, $inMatch);
            preg_match('/退勤：(\d{2}:\d{2})/', $multi->note, $outMatch);
            preg_match_all('/休憩\d+：(\d{2}:\d{2})～(\d{2}:\d{2})/', $multi->note, $breakMatches, PREG_SET_ORDER);

            $clockIn = !empty($inMatch[1]) ? Carbon::createFromFormat('H:i', $inMatch[1]) : $clockIn;
            $clockOut = !empty($outMatch[1]) ? Carbon::createFromFormat('H:i', $outMatch[1]) : $clockOut;

            $breakPairs = [];
            foreach ($breakMatches as $match) {
                $breakPairs[] = [
                    'start' => $match[1],
                    'end' => $match[2],
                ];
            }
        }

        return view('admin.attendance.show', compact(
            'user', 'date', 'clockIn', 'clockOut', 'record',
            'breakPairs', 'note'
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

    public function adminApplicationListShow(Request $request)
    {
        $status = $request->input('status', '承認待ち'); // ← デフォルトで承認待ち

        $query = AttendanceApplication::with(['user', 'attendance']);

        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->orderBy('created_at', 'desc')->get();

        return view('admin.application.admin_application_list', compact('applications'));
    }

    public function StaffAttendanceShow($user_id)
    {
        $user = User::findOrFail($user_id);
        $currentMonth = now()->format('Y-m');

        $rawAttendances = Attendance::where('user_id', $user_id)
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

        $application = $applications->first(); // 代表表示用

        $isPending = $baseApplication->status === '承認待ち';
        $isApproved = $baseApplication->status === '承認';

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

    // public function reject($id)
    // {
    //     $application = AttendanceApplication::findOrFail($id);
    //     $application->status = '却下';
    //     $application->save();

    //     return redirect()->back()->with('success', '修正申請を却下しました');
    // }

}
