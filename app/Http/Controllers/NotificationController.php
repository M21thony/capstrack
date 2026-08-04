<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        $notifications = Notification::where('student_id', $studentId)
            ->latest('created_at')
            ->take(20)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'link' => $n->link,
                'is_read' => $n->is_read,
                'time' => $n->created_at->diffForHumans(),
                'created_at' => $n->created_at->toISOString(),
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    public function count(Request $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        $count = Notification::where('student_id', $studentId)
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $studentId = $request->user()->student_id;

        if ($notification->student_id !== $studentId) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $studentId = $request->user()->student_id;

        Notification::where('student_id', $studentId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markSeen(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! empty($ids)) {
            $seen = session('student_seen_notification_ids', []);
            $seen = array_unique(array_merge($seen, $ids));
            session(['student_seen_notification_ids' => $seen]);
        }

        return response()->json(['success' => true]);
    }
}
