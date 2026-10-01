<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * G2 (v1.7.0) — badge CRUD + manual award (audited via awarded_by/reason).
 * Badge uploads go through the alpha-preserving 'badge' variant — a JPEG
 * source is rejected outright (G1).
 */
class BadgeAdminController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $images,
        private readonly GamificationService $gamification,
    ) {}

    public function index(\Illuminate\Http\Request $request)
    {
        // P1b (v1.7.1): admin-ONLY door — moderators are 403 here even
        // though the staff middleware lets them past the admin perimeter.
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.badges', [
            'badges' => Badge::query()->withCount('userBadges')->orderBy('name')->get(),
            'users' => User::query()->orderBy('name')->limit(200)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateBadge($request);

        $badge = Badge::query()->create($validated + ['is_active' => $request->boolean('is_active')]);

        if ($request->hasFile('image')) {
            $badge->update(['image_path' => $this->images->store($request->file('image'), 'badge')]);
        }

        return back()->with('success', "Badge \"{$badge->name}\" created.");
    }

    public function update(Request $request, Badge $badge)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validateBadge($request, $badge);

        $badge->update($validated + ['is_active' => $request->boolean('is_active')]);

        if ($request->hasFile('image')) {
            $this->images->delete($badge->image_path);
            $badge->update(['image_path' => $this->images->store($request->file('image'), 'badge')]);
        }

        return back()->with('success', "Badge \"{$badge->name}\" updated.");
    }

    /** Delete is allowed only before the first award (content with history stays). */
    public function destroy(Request $request, Badge $badge)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($badge->userBadges()->exists()) {
            // Soft-off: deactivate instead — earned rows are historical record.
            $badge->update(['is_active' => false]);

            return back()->with('success', "Badge \"{$badge->name}\" has awards — deactivated instead of deleted.");
        }

        $this->images->delete($badge->image_path);
        $badge->delete();

        return back()->with('success', 'Badge deleted.');
    }

    /** Manual award with a mandatory reason (audited: awarded_by + reason). */
    public function award(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'badge_id' => ['required', 'exists:badges,id'],
            'user_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:4', 'max:255'],
        ]);

        $badge = Badge::query()->findOrFail($validated['badge_id']);
        $user = User::query()->findOrFail($validated['user_id']);

        $result = $this->gamification->awardBadge($user, $badge, $request->user(), $validated['reason']);

        return $result === null
            ? back()->withErrors(['award' => "{$user->name} already holds \"{$badge->name}\"."])
            : back()->with('success', "\"{$badge->name}\" awarded to {$user->name}.");
    }

    private function validateBadge(Request $request, ?Badge $badge = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('badges', 'slug')->ignore($badge?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'criterion' => ['required', Rule::in(Badge::CRITERIA)],
            'image' => ['nullable', 'image', 'max:2048'], // 2 MB ceiling (G1)
        ]);
    }
}
