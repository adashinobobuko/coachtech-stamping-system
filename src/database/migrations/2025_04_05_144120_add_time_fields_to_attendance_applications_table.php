<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimeFieldsToAttendanceApplicationsTable extends Migration
{
    public function up()
    {
        Schema::table('attendance_applications', function (Blueprint $table) {
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
        });
    }

    public function down()
    {
        Schema::table('attendance_applications', function (Blueprint $table) {
            $table->dropColumn(['clock_in', 'clock_out', 'break_start', 'break_end']);
        });
    }
}
