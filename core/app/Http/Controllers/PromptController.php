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

        // Staff preview escape hatch: ?preview=1 works only for moderators AND
        // only when arriving from the admin panel (referer check). Staff can
        // never browse the storefront into paid content otherwise.
        $staffPreview = $viewer !== null
            && $viewer->isModerator()
            && $request->boolean('preview')
            && str_contains((string) $request->header('referer'), '/admin');

        return view('prompts.show', [
            'prompt' => $prompt,
            'canEdit' => $viewer !== null && Gate::forUser($viewer)->allows('update', $prompt),
            'canViewFullBody' => $staffPreview || $this->canViewFullBody($viewer, $prompt, $isOwner),
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
