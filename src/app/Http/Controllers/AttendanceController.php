<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;

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
        $latest = Attendance::where('user_id', $user->id)
                            ->orderBy('timestamp', 'desc')
                            ->first();

        if (!$latest) {
            return '勤務外';
        }

        return match ($latest->type) {
            'clock_in' => '出勤中',
            'break_start' => '休憩中',
            'clock_out' => '退勤済',
            'break_end' => $this->checkPreviousStatus($user),
            default => '勤務外',
        };
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

    public function store(Request $request)
    {
        $user = auth()->user();
        $now = now();

        $request->validate([
            'type' => 'required|in:clock_in,break_start,break_end,clock_out',
        ]);

        $type = $request->type;
        $status = $this->getStatus($user);

        // 出勤・退勤・休憩のバリデーション
        if ($type === 'clock_in' && $status === '出勤中') {
            return back()->with('error', 'すでに出勤しています。');
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

        if ($type === 'clock_out') {
            auth()->logout(); // 退勤後はログアウト
        }

        $status = $this->getStatus($user);
        $message = match ($type) {
            'clock_in' => '出勤しました。',
            'break_start' => '休憩を開始しました。',
            'break_end' => '休憩終了しました。',
            'clock_out' => '退勤しました。お疲れさまでした！',
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
        $record = Attendance::with('user')->findOrFail($id);

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

        return view('staff.attendance.detail', compact(
            'record',
            'clockIn',
            'clockOut',
            'breakStart',
            'breakEnd'
        ));
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
