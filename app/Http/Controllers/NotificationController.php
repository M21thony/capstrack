<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Support\NotificationTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Which column in `notifications` holds the person these belong to.
     *
     * A notification is owned by exactly one of student_id or expert_id.
     * ExpertNotificationController overrides these three and inherits the
     * rest, so the bell behaves identically on both sides of CapsTrack.
     */
    protected function ownerColumn(): string
    {
        return 'student_id';
    }

    protected function person(Request $request)
    {
        return $request->user();
    }

    protected function ownerId(Request $request): int
    {
        return (int) $this->person($request)->student_id;
    }

    /** Save this person's notification switches. */
    public function preferences(Request $request): JsonResponse
    {
        $person = $this->person($request);

        $validated = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'on' => ['array'],
            'on.*' => ['boolean'],
        ]);

        $person->saveNotificationPrefs(
            $validated['on'] ?? [],
            (bool) ($validated['all'] ?? true),
        );

        return response()->json([
            'success' => true,
            'prefs' => $person->notification_prefs,
            'types' => NotificationTypes::for($person->notificationAudience()),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where($this->ownerColumn(), $this->ownerId($request))
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
        $count = Notification::where($this->ownerColumn(), $this->ownerId($request))
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ((int) $notification->{$this->ownerColumn()} !== $this->ownerId($request)) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where($this->ownerColumn(), $this->ownerId($request))
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
