<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * F6 (v1.7.8) — the buyer-facing half of notifications.
 *
 * cPanel has no websockets, so the navbar bell polls `unread()` every 60
 * seconds (auth + throttle:30,1 — the 60-second cadence leaves a wide
 * margin). `readAll()` and `open()` are the two mark-read doors; open()
 * doubles as the click-through redirect so a read notification can never
 * link somewhere stale.
 */
class NotificationController extends Controller
{
    /** Poll endpoint: unread count + newest 20 for the dropdown. */
    public function unread(Request $request)
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return response()->json([
            'count' => $user->notifications()->unread()->count(),
            'items' => $this->items($user->notifications()->orderByDesc('id')->limit(20)->get()),
        ]);
    }

    /** "Mark all read" — idempotent; re-posting marks zero rows. */
    public function readAll(Request $request)
    {
        $updated = $request->user()->notifications()->unread()->update(['read_at' => now()]);

        return back()->with('success', $updated === 0
            ? 'You are all caught up.'
            : "{$updated} notification(s) marked read.");
    }

    /** Per-item click-through: mark read (once) then land on the subject. */
    public function open(Request $request, Notification $notification)
    {
        abort_unless($request->user()?->id === $notification->user_id, 403);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return redirect()->to($notification->targetUrl());
    }

    /**
     * Shared JSON shape for the poll response and the SSR payload: the
     * view layers must never disagree about a field name.
     *
     * @param  \Illuminate\Support\Collection<int, Notification>  $notifications
     * @return list<array{id: int, type: string, message: string, created_at: string, read_at: ?string, url: string}>
     */
    public function items($notifications): array
    {
        return $notifications->map(fn (Notification $notification) => [
            'id' => $notification->id,
            'type' => $notification->type,
            'message' => $notification->message,
            'created_at' => $notification->created_at?->format('M j, H:i'),
            'read_at' => $notification->read_at?->toIso8601String(),
            // Click-through goes THROUGH open() so the row is marked read
            // before landing on the subject (never straight to targetUrl).
            'url' => route('notifications.open', $notification),
        ])->values()->all();
    }
}
