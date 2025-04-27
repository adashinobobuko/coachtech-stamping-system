<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminModificationsTable extends Migration
{
    public function up()
    {
        Schema::create('admin_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->onDelete('cascade');
            $table->string('field'); // clock_in / break_start_1 / note など
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('modified_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_modifications');
    }
}

