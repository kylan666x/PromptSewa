<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;

/**
 * G3 (v1.7.0) — frame CRUD. Frame uploads go through the alpha-preserving
 * 'frame' variant — JPEG sources rejected (G1). Delete nulls
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

        return view('admin.frames', [
            'frames' => Frame::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['required', 'image', 'max:2048'],
        ]);

        $frame = Frame::query()->create([
            'name' => $validated['name'],
            'image_path' => $this->images->store($request->file('image'), 'frame'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Frame \"{$frame->name}\" created.");
    }

    public function update(Request $request, Frame $frame)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $frame->update([
            'name' => $validated['name'],
            'is_active' => $request->boolean('is_active'),
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
}
