<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('notif_user_code', $request->user()->user_code)
            ->orderByDesc('notif_created_at');

        if ($request->boolean('paginate')) {
            $notifications = $query->paginate(min(100, max(10, $request->integer('per_page', 30))));
            $notifications->getCollection()->transform(fn (Notification $notification) => $this->formatNotification($notification));

            return response()->json($notifications);
        }

        $notifications = $query->limit(50)->get()->map(fn (Notification $notification) => $this->formatNotification($notification));

        return response()->json($notifications);
    }

    public function markAsRead(Request $request, string $code)
    {
        $notification = Notification::where('notif_user_code', $request->user()->user_code)->findOrFail($code);
        $notification->update(['notif_is_read' => true]);

        return response()->json(['message' => 'Đã đánh dấu là đã đọc.']);
    }

    public function markAllAsRead(Request $request)
    {
        Notification::where('notif_user_code', $request->user()->user_code)
            ->where('notif_is_read', false)
            ->update(['notif_is_read' => true]);

        return response()->json(['message' => 'Đã đánh dấu tất cả là đã đọc.']);
    }

    public function preferences(Request $request)
    {
        return response()->json($request->user()->user_notification_preferences ?? $this->defaults());
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'assignment' => 'required|boolean',
            'deadline' => 'required|boolean',
            'comments' => 'required|boolean',
            'mentions' => 'required|boolean',
            'blocked' => 'required|boolean',
        ]);
        $request->user()->update(['user_notification_preferences' => $validated]);

        return response()->json(['message' => 'Đã lưu tùy chọn thông báo.', 'preferences' => $validated]);
    }

    private function defaults(): array
    {
        return [
            'assignment' => true,
            'deadline' => true,
            'comments' => true,
            'mentions' => true,
            'blocked' => true,
        ];
    }

    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->notif_code,
            'title' => $notification->notif_title,
            'message' => $notification->notif_message,
            'type' => $notification->notif_type,
            'targetType' => $notification->notif_target_type,
            'targetCode' => $notification->notif_target_code,
            'read' => $notification->notif_is_read,
            'createdAt' => $notification->notif_created_at,
        ];
    }
}
