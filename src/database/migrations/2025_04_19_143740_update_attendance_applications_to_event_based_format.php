<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateAttendanceApplicationsToEventBasedFormat extends Migration
{
    public function up()
    {
        Schema::table('attendance_applications', function (Blueprint $table) {
            // 不要なカラムを削除（既に存在していれば）
            if (Schema::hasColumn('attendance_applications', 'clock_in')) {
                $table->dropColumn('clock_in');
            }
            if (Schema::hasColumn('attendance_applications', 'clock_out')) {
                $table->dropColumn('clock_out');
            }
            if (Schema::hasColumn('attendance_applications', 'break_start')) {
                $table->dropColumn('break_start');
            }
            if (Schema::hasColumn('attendance_applications', 'break_end')) {
                $table->dropColumn('break_end');
            }
            if (Schema::hasColumn('attendance_applications', 'break_start2')) {
                $table->dropColumn('break_start2');
            }
            if (Schema::hasColumn('attendance_applications', 'break_end2')) {
                $table->dropColumn('break_end2');
            }

            // `event_type` を追加（新しい打刻の種別）
            if (!Schema::hasColumn('attendance_applications', 'event_type')) {
                $table->string('event_type')->after('type');
            }
        });
    }

    public function down()
    {
        Schema::table('attendance_applications', function (Blueprint $table) {
            // カラム復元（nullable）
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->time('break_start2')->nullable();
            $table->time('break_end2')->nullable();

            // `event_type` の削除
            $table->dropColumn('event_type');
        });
    }
}
