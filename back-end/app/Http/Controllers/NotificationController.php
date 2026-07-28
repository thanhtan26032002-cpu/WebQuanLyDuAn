<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Lấy danh sách thông báo cho member hiện tại.
     * (Sử dụng tạm MB0001 nếu chưa có hệ thống Auth hoàn chỉnh)
     */
    public function index(Request $request)
    {
        $userCode = $request->query('user_code', 'US0001');

        $notifications = Notification::where('notif_user_code', $userCode)
            ->orderBy('notif_created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($notif) {
                // Map lại để phù hợp với định dạng của frontend (camelCase)
                return [
                    'id' => $notif->notif_code,
                    'title' => $notif->notif_title,
                    'message' => $notif->notif_message,
                    'type' => $notif->notif_type,
                    'read' => $notif->notif_is_read,
                    'createdAt' => $notif->notif_created_at,
                ];
            });

        return response()->json($notifications);
    }

    /**
     * Đánh dấu 1 thông báo là đã đọc
     */
    public function markAsRead($code)
    {
        $notification = Notification::findOrFail($code);
        $notification->update(['notif_is_read' => true]);

        return response()->json(['message' => 'Đã đánh dấu là đã đọc']);
    }

    /**
     * Đánh dấu tất cả thông báo là đã đọc
     */
    public function markAllAsRead(Request $request)
    {
        $userCode = $request->input('user_code', 'US0001');
        
        Notification::where('notif_user_code', $userCode)
            ->where('notif_is_read', false)
            ->update(['notif_is_read' => true]);

        return response()->json(['message' => 'Đã đánh dấu tất cả là đã đọc']);
    }
}
