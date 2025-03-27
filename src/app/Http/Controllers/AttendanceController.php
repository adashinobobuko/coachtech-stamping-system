<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;

class AttendanceController extends Controller
{
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
}
