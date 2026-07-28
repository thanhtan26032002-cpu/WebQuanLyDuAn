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
            'activity_user_code'   => $userCode ?? 'US0001',
            'activity_action'      => $action,
            'activity_target_type' => $targetType,
            'activity_target_code' => $targetCode,
            'activity_detail'      => $detail,
        ]);
    }

    /**
     * Gửi thông báo cho một thành viên.
     */
    public static function notify($userCode, $title, $message, $type = 'info')
    {
        return Notification::create([
            'notif_user_code'   => $userCode,
            'notif_title'       => $title,
            'notif_message'     => $message,
            'notif_type'        => $type,
            'notif_is_read'     => false,
        ]);
    }
}
