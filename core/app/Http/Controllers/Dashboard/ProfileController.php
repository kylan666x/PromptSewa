<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Facebook-style full profile editing: identity (name/bio), avatar and
 * cover banner. Images are GD-compressed and re-encoded (strip metadata,
 * downscale) before storage — see ImageUploadService.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $this->abortIfOfficialAndNotAdmin($request->user());

        // G3 (v1.7.0): the frame picker — active frames only, none = clear.
        return view('dashboard.profile', [
            'frames' => \App\Models\Frame::query()->where('is_active', true)->orderBy('name')->get(),
            'user' => $request->user(),
        ]);
    }

    /**
     * T1 (v1.5.0): the official PromptSewa house account is founder-controlled.
     * Only admins may edit it — moderators (and everyone else) get 403.
     *
     * Two doors lead into the official account's profile: logging in as it,
     * or admin impersonation (T3) landing a session on it. Both are closed
     * for non-admins: the guard checks the EDITED account (the session user)
     * AND, when an impersonation is active, the real driver behind it.
     */
    private function abortIfOfficialAndNotAdmin(User $user): void
    {
        $drivers = collect([$user]);

        $impersonatorId = session('impersonator_id');
        if ($impersonatorId !== null && (int) $impersonatorId !== $user->id) {
            $drivers->push(User::query()->find((int) $impersonatorId));
        }

        if ($user->is_official && $drivers->filter()->contains(fn (User $driver) => ! $driver->isAdmin())) {
            abort(403, 'The official PromptSewa account can only be edited by admins.');
        }
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $this->abortIfOfficialAndNotAdmin($user);

        $validated = $request->validate([
            // G3 (v1.7.0): nullable frame id — an inactive/foreign id 404s via exists.
            'active_frame_id' => ['nullable', 'exists:frames,id'],
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'username' => [
                'required',
                'string',
                'max:30',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'bio' => ['nullable', 'string', 'max:400'],
            'avatar' => ['nullable', 'image', 'max:8192'],
            'banner' => ['nullable', 'image', 'max:8192'],
            'remove_avatar' => ['nullable', 'boolean'],
            'remove_banner' => ['nullable', 'boolean'],
        ]);

        $uploader = app(ImageUploadService::class);

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            $uploader->delete($user->avatar_path);
            $user->avatar_path = null;
        }
        if ($request->hasFile('avatar')) {
            try {
                $new = $uploader->store($request->file('avatar'), 'avatar');
            } catch (\RuntimeException $e) {
                return back()->withErrors(['avatar' => $e->getMessage()]);
            }
            $uploader->delete($user->avatar_path);
            $user->avatar_path = $new;
        }

        if ($request->boolean('remove_banner') && $user->banner_path) {
            $uploader->delete($user->banner_path);
            $user->banner_path = null;
        }
        if ($request->hasFile('banner')) {
            try {
                $new = $uploader->store($request->file('banner'), 'banner');
            } catch (\RuntimeException $e) {
                return back()->withErrors(['banner' => $e->getMessage()]);
            }
            $uploader->delete($user->banner_path);
            $user->banner_path = $new;
        }

        $user->name = trim($validated['name']);

        // Handle: store lowercase-normalized or null (clearing it falls the
        // profile URL back to the display name).
        $username = strtolower(trim((string) ($validated['username'] ?? '')));
        $user->username = $username !== '' ? $username : null;

        $bio = trim((string) ($validated['bio'] ?? ''));
        $user->bio = $bio !== '' ? $bio : null;

        // G3 (v1.7.0): frame selection — a missing/empty checkbox clears it.
        $user->active_frame_id = $validated['active_frame_id'] ?? null;

        $user->save();

        return back()->with('success', 'Profile updated.');
    }
}
