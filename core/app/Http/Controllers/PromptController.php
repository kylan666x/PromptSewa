<?php

namespace App\Http\Controllers;

use App\Models\LicenseGrant;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Public prompt detail (UI-002).
 *
 * Visibility contract: public+published listings render for everyone;
 * anything else 404s unless the viewer owns (or moderates) the prompt.
 * Full prompt bodies are the product: guests see free prompt bodies and a
 * locked teaser on paid prompts; owners, moderators, and buyers holding an
 * active license grant see everything.
 */
class PromptController extends Controller
{
    public function show(Request $request, Prompt $prompt)
    {
        abort_unless($prompt->isViewableBy($request->user()), 404);

        $viewer = $request->user();
        $prompt->load(['category', 'creator', 'latestVersion']);

        $isOwner = $viewer !== null && $viewer->id === $prompt->user_id;

        // Staff moderation preview moved to GET /admin/prompts/{prompt}/preview
        // (PromptPreviewController) — the old ?preview=1 + referer heuristic
        // broke behind privacy extensions and Referrer-Policy headers.

        return view('prompts.show', [
            'prompt' => $prompt,
            'canEdit' => $viewer !== null && Gate::forUser($viewer)->allows('update', $prompt),
            'canViewFullBody' => $this->canViewFullBody($viewer, $prompt, $isOwner),
            'previewMode' => false,
        ]);
    }

    /**
     * Public version history (B6): metadata only — version label, changelog,
     * author, timestamp, oldest → newest. No body fragments and no variable
     * lists for paid prompts; the paywall lives on the detail page.
     */
    public function versions(Request $request, Prompt $prompt)
    {
        abort_unless($prompt->isViewableBy($request->user()), 404);

        $prompt->load(['category', 'creator', 'versions.author']);

        $viewer = $request->user();
        $isOwner = $viewer !== null && $viewer->id === $prompt->user_id;
        $isStaff = $viewer !== null && ($viewer->isAdmin() || $viewer->isModerator());
        $canRestore = $viewer !== null && Gate::forUser($viewer)->allows('update', $prompt);

        // T7 (v1.5.0): full snapshots (body + variables + tools) render on
        // the history page only for the owner, staff, and active license
        // holders — the paywall contract is inherited from the detail page.
        // Everyone else keeps metadata-only rows; pre-v1.5.0 rows show an
        // honest "snapshot not captured" chip instead of a fabricated body.
        $fullBodyAllowed = $this->canViewFullBody($viewer, $prompt, $isOwner) || $isStaff;

        $versions = $prompt->versions
            ->sortBy('version_number')
            ->values()
            ->map(fn ($version) => [
                'label' => $version->label(),
                'changelog' => (string) ($version->changelog ?? ''),
                'author' => $version->author?->name ?? 'Unknown',
                'created_at' => $version->created_at,
                'has_snapshot' => $version->hasSnapshot(),
                'body' => $fullBodyAllowed && $version->hasSnapshot() ? (string) $version->body : null,
                'variables' => $fullBodyAllowed && $version->hasSnapshot() ? $version->variableNames() : [],
                'tools' => $fullBodyAllowed && $version->hasSnapshot() ? $version->toolList() : [],
                'can_restore' => $canRestore && $version->hasSnapshot(),
            ]);

        return view('prompts.versions', [
            'prompt' => $prompt,
            'versions' => $versions,
            'isPaid' => $prompt->price_cents > 0,
            'fullBodyAllowed' => $fullBodyAllowed,
        ]);
    }

    /**
     * The prompt body is the product: it renders in full for free listings,
     * the owner, and verified buyers. Everyone else gets a locked teaser on
     * paid listings — INCLUDING staff. Staff moderation happens through the
     * admin panel (which uses the full-body pipeline), never by browsing the
     * storefront for free.
     */
    private function canViewFullBody(?object $viewer, Prompt $prompt, bool $isOwner): bool
    {
        if ($prompt->price_cents === 0 || $isOwner) {
            return true;
        }

        if ($viewer === null) {
            return false;
        }

        return LicenseGrant::query()
            ->where('user_id', $viewer->id)
            ->where('prompt_id', $prompt->id)
            ->where('status', LicenseGrant::STATUS_ACTIVE)
            ->exists();
    }
}
