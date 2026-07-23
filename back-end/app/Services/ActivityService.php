<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    /**
     * Ghi nhận một hoạt động vào hệ thống.
     */
    public static function log($userCode, $action, $targetType, $targetCode, $detail = null)
    {
        return Activity::create([
            'user_code'   => $userCode ?? 'US0001',
            'action'      => $action,
            'target_type' => $targetType,
            'target_code' => $targetCode,
            'detail'      => $detail,
        ]);
    }

    /**
     * Gửi thông báo cho một thành viên.
     */
    public static function notify($userCode, $title, $message, $type = 'info')
    {
        return Notification::create([
            'user_code'   => $userCode,
            'title'       => $title,
            'message'     => $message,
            'type'        => $type,
            'is_read'     => false,
        ]);
    }
}
