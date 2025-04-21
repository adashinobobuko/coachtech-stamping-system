<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'user_id',
        'type',
        'event_type',
        'old_time',
        'new_time',
        'clock_in',
        'clock_out',
        'break_start',
        'break_end',
        'note',
        'status',
    ];

    // 勤怠情報とのリレーション
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    // ユーザー情報とのリレーション
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
