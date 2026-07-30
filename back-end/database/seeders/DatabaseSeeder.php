<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure the current frontend always has a valid actor for foreign keys.
        // The random password deliberately makes this system user non-loginable.
        User::firstOrCreate([
            'user_email' => env('SYSTEM_USER_EMAIL', 'admin@example.com'),
        ], [
            'user_name' => env('SYSTEM_USER_NAME', 'Quản trị viên'),
            'user_role' => 'admin',
            'user_password' => Hash::make(Str::random(64)),
            'user_job_title' => 'Quản trị hệ thống',
            'user_department' => 'Ban điều hành',
            'user_phone' => '0900000001',
            'user_color' => 'violet',
            'user_join_date' => now()->toDateString(),
            'user_profile_completed_at' => now(),
        ]);
    }
}
