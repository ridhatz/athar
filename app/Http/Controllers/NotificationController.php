<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in user's own in-app notifications. Always scoped to the caller, so one person
 * can never read or mark another's.
 */
class NotificationController extends Controller
{
    /** API: newest first, with the unread count. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return NotificationResource::collection($user->notifications()->paginate(20))
            ->additional(['unread_count' => $user->unreadNotifications()->count()]);
    }

    /** API: mark one read. */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['data' => NotificationResource::make($notification->fresh())]);
    }

    /** API: mark everything read. */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['unread_count' => 0]);
    }

    /** Web: mark one read, then go to the request it is about. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $number = $notification->data['request_number'] ?? null;

        return $number ? redirect()->route('requests.show', $number) : redirect()->route('requests.index');
    }

    /** Web: mark everything read. */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
