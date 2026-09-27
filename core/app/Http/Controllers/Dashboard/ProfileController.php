<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

/**
 * Facebook-style full profile editing: identity (name/bio), avatar and
 * cover banner. Images are GD-compressed and re-encoded (strip metadata,
 * downscale) before storage — see ImageUploadService.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('dashboard.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
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
        $bio = trim((string) ($validated['bio'] ?? ''));
        $user->bio = $bio !== '' ? $bio : null;
        $user->save();

        return back()->with('success', 'Profile updated.');
    }
}
