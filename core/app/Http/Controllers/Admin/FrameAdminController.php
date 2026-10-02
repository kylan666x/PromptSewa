<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Models\User;
use App\Models\UserFrameUnlock;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

/**
 * G3 (v1.7.0) / W3+W4 (v1.7.3) — frame CRUD, animation select, criterion
 * select and the manual award panel. Frame uploads go through the
 * alpha-preserving 'frame' variant — animated GIF/WebP take the
 * byte-identical pass-through lane (ImageUploadService). Delete nulls
 * users.active_frame_id via nullOnDelete (never breaks a user row).
 */
class FrameAdminController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $images,
    ) {}

    public function index(\Illuminate\Http\Request $request)
    {
        // P1b (v1.7.1): admin-ONLY door — moderators are 403 here even
        // though the staff middleware lets them past the admin perimeter.
        abort_unless($request->user()?->isAdmin(), 403);

        $unlocks = UserFrameUnlock::query()
            ->with(['user', 'frame', 'grantor'])
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.frames', [
            'frames' => Frame::query()->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->limit(500)->get(['id', 'name', 'username', 'email']),
            'unlocks' => $unlocks,
            'criteria' => \App\Services\CriterionEvaluator::CRITERIA,
            'animations' => Frame::ANIMATIONS,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['required', 'image', 'max:2048'],
            'criterion' => ['nullable', 'string', 'in:'.implode(',', \App\Services\CriterionEvaluator::CRITERIA)],
            'animation' => ['nullable', 'string', 'in:'.implode(',', Frame::ANIMATIONS)],
        ]);

        $frame = Frame::query()->create([
            'name' => $validated['name'],
            'image_path' => $this->images->store($request->file('image'), 'frame'),
            'is_active' => $request->boolean('is_active', true),
            'criterion' => $validated['criterion'] ?? null,
            'animation' => $validated['animation'] ?? 'none',
        ]);

        return back()->with('success', "Frame \"{$frame->name}\" created.");
    }

    public function update(Request $request, Frame $frame)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:2048'],
            'criterion' => ['nullable', 'string', 'in:'.implode(',', \App\Services\CriterionEvaluator::CRITERIA)],
            'animation' => ['nullable', 'string', 'in:'.implode(',', Frame::ANIMATIONS)],
        ]);

        $frame->update([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
            // Empty string = explicitly free (null); missing = unchanged.
            'criterion' => array_key_exists('criterion', $validated) && $validated['criterion'] !== ''
                ? $validated['criterion']
                : ($request->has('criterion') ? null : $frame->criterion),
            'animation' => $validated['animation'] ?? $frame->animation,
        ]);

        if ($request->hasFile('image')) {
            $this->images->delete($frame->image_path);
            $frame->update(['image_path' => $this->images->store($request->file('image'), 'frame')]);
        }

        return back()->with('success', "Frame \"{$frame->name}\" updated.");
    }

    public function destroy(Request $request, Frame $frame)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $this->images->delete($frame->image_path);
        $frame->delete(); // nullOnDelete clears users.active_frame_id

        return back()->with('success', 'Frame deleted — users wearing it fell back to no frame.');
    }

    /**
     * W4: manual award — audited (granted_by + MANDATORY reason), idempotent
     * through the UNIQUE(user, frame) constraint. Admin-only.
     */
    public function award(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'frame_id' => ['required', 'exists:frames,id'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $frame = Frame::query()->findOrFail($validated['frame_id']);

        $created = UserFrameUnlock::query()->firstOrCreate(
            ['user_id' => $user->id, 'frame_id' => $frame->id],
            [
                'source' => UserFrameUnlock::SOURCE_MANUAL,
                'granted_by' => $request->user()->id,
                'reason' => trim($validated['reason']),
            ],
        );

        $message = $created->wasRecentlyCreated
            ? "Frame \"{$frame->name}\" granted to {$user->name}."
            : "{$user->name} already holds \"{$frame->name}\" — nothing changed.";

        return back()->with('success', $message);
    }

    /** W4: confirm-gated revoke — the unlock row is removed, the equip clears. */
    public function revoke(Request $request, UserFrameUnlock $unlock)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $frameName = $unlock->frame?->name ?? 'frame';
        $userName = $unlock->user?->name ?? 'user';

        $unlock->delete();

        return back()->with('success', "Revoked \"{$frameName}\" from {$userName} — they can no longer equip it.");
    }
}
