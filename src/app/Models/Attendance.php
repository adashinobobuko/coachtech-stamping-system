<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',        
        'type',           // 打刻の種類（clock_in, break_start, break_end, clock_out）
        'timestamp',      // 打刻日時
        'break_start2',
        'break_end2',
    ];

    protected $casts = [
    'timestamp' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
