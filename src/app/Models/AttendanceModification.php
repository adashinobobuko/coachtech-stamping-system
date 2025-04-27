<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceModification extends Model
{
    use HasFactory;

    protected $table = 'admin_modifications';

    protected $fillable = [
        'admin_id',
        'user_id',
        'attendance_id',
        'field',
        'old_value',
        'new_value',
        'modified_at',
    ];

    public $timestamps = false;
}
