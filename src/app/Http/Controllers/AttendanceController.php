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

    // 休憩終了後の状態を取得
    public function checkPreviousStatus($user)
    {
        $previous = Attendance::where('user_id', $user->id)
                            ->whereIn('type', ['clock_in', 'break_start'])
                            ->orderBy('timestamp', 'desc')
                            ->skip(1) // 1つ前の記録
                            ->first();

        return $previous && $previous->type === 'clock_in' ? '出勤中' : '勤務外';
    }

    //　打刻の処理
    public function store(Request $request)
    {
        $user = auth()->user();
        $now = now();

        $request->validate([
            'type' => 'required|in:clock_in,break_start,break_end,clock_out',
        ]);

        $type = $request->type;
        $status = $this->getStatus($user);

        // 出勤・退勤・休憩のバリデーション 出勤は１日一回にしばる
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

        // 打刻を保存
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

    // 勤怠データリスト
    public function list(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));

        $startOfMonth = now()->create($month)->startOfMonth();
        $endOfMonth = now()->create($month)->endOfMonth();

        // 指定された月のデータを取得
        $records = Attendance::where('user_id', $user->id)
            ->whereBetween('timestamp', [$startOfMonth, $endOfMonth])
            ->orderBy('timestamp')
            ->get();

        // 日別データに変換
        $attendances = $this->formatAttendanceData($records);

        // 前月・翌月情報
        $prevMonth = $startOfMonth->copy()->subMonth()->format('Y-m');
        $nextMonth = $startOfMonth->copy()->addMonth()->format('Y-m');

        return view('staff.attendance.list', compact('attendances', 'month', 'prevMonth', 'nextMonth','startOfMonth'));
    }

    // 詳細画面
    public function detail($id)
    {
        // まず AttendanceApplication を探す
        $application = AttendanceApplication::with('attendance')->find($id);

        if ($application) {
            // 申請データが見つかった場合（申請詳細画面）
            $record = $application->attendance;
            $viewType = 'application'; // 申請の場合
        } else {
            // 申請データがない場合は Attendance を探す
            $record = Attendance::with('user')->findOrFail($id);
            $application = null; // 申請データなし
            $viewType = 'attendance'; // 勤怠の場合
        }

        // 出勤、退勤、休憩開始・終了のデータ取得
        $clockIn = Attendance::where('user_id', $record->user_id)
                            ->whereDate('timestamp', $record->timestamp->toDateString())
                            ->where('type', 'clock_in')
                            ->first()->timestamp ?? null;

        $clockOut = Attendance::where('user_id', $record->user_id)
                            ->whereDate('timestamp', $record->timestamp->toDateString())
                            ->where('type', 'clock_out')
                            ->first()->timestamp ?? null;

        $breakStart = Attendance::where('user_id', $record->user_id)
                                ->whereDate('timestamp', $record->timestamp->toDateString())
                                ->where('type', 'break_start')
                                ->first()->timestamp ?? null;

        $breakEnd = Attendance::where('user_id', $record->user_id)
                                ->whereDate('timestamp', $record->timestamp->toDateString())
                                ->where('type', 'break_end')
                                ->first()->timestamp ?? null;

        // 同じビューを使用し、application か record を渡す
        return view('staff.attendance.detail', compact(
            'record',
            'application',
            'clockIn',
            'clockOut',
            'breakStart',
            'breakEnd',
            'viewType'
        ));
    }

    public function update(Request $request, $id)
    {
        // 申請データを確認
        $application = AttendanceApplication::where('attendance_id', $id)
                        ->where('user_id', auth()->id())
                        ->first();

        // 勤怠データの取得
        $record = Attendance::with('user')->findOrFail($id);

        // バリデーションルール
        $request->validate([
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i|after_or_equal:clock_in',
            'break_start' => 'nullable|date_format:H:i|after_or_equal:clock_in',
            'break_end' => 'nullable|date_format:H:i|after:break_start',
            'note' => 'nullable|string|max:255',
        ]);

        // 修正申請データの更新・作成
        if ($application) {
            // 既存の申請がある場合は更新
            $application->update([
                'clock_in' => $request->clock_in,
                'clock_out' => $request->clock_out,
                'break_start' => $request->break_start,
                'break_end' => $request->break_end,
                'note' => $request->note,
                'status' => '承認待ち',
            ]);
        } else {
            // 申請が存在しない場合は新規作成
            AttendanceApplication::create([
                'attendance_id' => $record->id,
                'user_id' => auth()->id(),
                'type' => '修正申請',
                'old_time' => $record->timestamp->format('Y-m-d H:i:s'),
                'new_time' => now()->format('Y-m-d H:i:s'),
                'note' => $request->note,
                'status' => '承認待ち',
            ]);
        }

        return redirect()->route('attendance.detail', ['id' => $record->id])
            ->with('success', '修正申請が完了しました。');
    }

    //申請一覧
    public function applicationindex(Request $request)
    {
        // タブの切り替え判定（デフォルトは「承認待ち」）
        $status = $request->input('status', '承認待ち');

        // 自分の申請データのみ取得
        $applications = AttendanceApplication::with('attendance')
            ->where('user_id', auth()->id())
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($application) {
                return \Carbon\Carbon::parse($application->created_at)->format('Y-m-d');
        });

        return view('staff.attendance.applicationlist', compact('applications', 'status'));
    }

    // データのフォーマット処理
    private function formatAttendanceData($records)
    {
        $attendances = [];

        foreach ($records as $record) {
            // 文字列の timestamp を Carbon インスタンスに変換
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

            //　日ごとのデータを整形し配列に出力
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

                //　対応するbreak_startがある場合
                case 'break_end':
                    if (isset($attendances[$date]['break_start'])) {
                        $breakDuration = $timestamp->diff($attendances[$date]['break_start']);
                        $attendances[$date]['break'] = $breakDuration->format('%H:%I');
                    }
                    break;
            }

            // 合計時間の計算
            if ($attendances[$date]['clock_in'] && $attendances[$date]['clock_out']) {
                $workDuration = Carbon::parse($attendances[$date]['clock_out'])
                    ->diffInMinutes(Carbon::parse($attendances[$date]['clock_in']));
                $attendances[$date]['total'] = floor($workDuration / 60) . ':' . str_pad($workDuration % 60, 2, '0', STR_PAD_LEFT);
            }
        }

        return $attendances;
    }

}
