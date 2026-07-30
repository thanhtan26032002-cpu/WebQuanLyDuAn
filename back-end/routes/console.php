<?php

use App\Models\User;
use App\Services\AutomationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => AutomationService::sendDeadlineReminders())
    ->name('ringnet-deadline-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Artisan::command('ringnet:create-test-accounts', function () {
    if (app()->environment('production')) {
        $this->error('Lệnh tạo tài khoản thử nghiệm bị khóa trong production.');

        return 1;
    }

    $definitions = [
        ['admin@example.com', 'Quản trị viên RingNet', 'admin', 'Quản trị hệ thống', 'Ban điều hành', '0900000001', 'violet'],
        ['manager@example.com', 'Quản lý dự án', 'project_manager', 'Project Manager', 'Quản lý dự án', '0900000002', 'indigo'],
        ['employee@example.com', 'Nhân viên kiểm thử', 'member', 'Nhân viên', 'Phát triển sản phẩm', '0900000003', 'emerald'],
    ];
    $credentials = [];

    foreach ($definitions as [$email, $name, $role, $jobTitle, $department, $phone, $color]) {
        $password = Str::password(16);
        User::updateOrCreate(
            ['user_email' => $email],
            [
                'user_name' => $name,
                'user_password' => Hash::make($password),
                'user_role' => $role,
                'user_job_title' => $jobTitle,
                'user_department' => $department,
                'user_phone' => $phone,
                'user_color' => $color,
                'user_join_date' => now()->toDateString(),
                'user_online' => true,
                'user_weekly_capacity_hours' => 40,
                'user_profile_completed_at' => now(),
            ]
        );
        $credentials[] = [$role, $email, $password];
    }

    $this->table(['Vai trò', 'Email', 'Mật khẩu một lần'], $credentials);

    return 0;
})->purpose('Tạo lại ba tài khoản kiểm thử phân quyền trong môi trường local/testing');
