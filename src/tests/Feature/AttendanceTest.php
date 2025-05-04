<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AttendanceTest extends TestCase
{
    public function test_check_database_connection(): void
    {
        dump(DB::getDatabaseName()); // データベース名を出力

        $this->assertEquals('demo_test', DB::getDatabaseName());
    }
}
