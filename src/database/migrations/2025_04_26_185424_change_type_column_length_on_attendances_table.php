<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeTypeColumnLengthOnAttendancesTable extends Migration
{
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('type', 50)->change(); // ← 50文字に変更
        });
    }

    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('type', 10)->change(); // ← 元に戻す
        });
    }
}
