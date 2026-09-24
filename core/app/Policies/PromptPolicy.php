<?php

namespace App\Policies;

use App\Models\Prompt;
use App\Models\User;

/**
 * Authorization contract for prompt listings.
 *
 * view:   public+published is world-readable; drafts/pending/private/rejected
 *         are visible to the owner and moderators only.
 * update: owner or moderator (edit form + new-version commits).
 */
class PromptPolicy
{
    public function view(?User $user, Prompt $prompt): bool
    {
        if ($prompt->status === Prompt::STATUS_PUBLISHED && $prompt->visibility === Prompt::VISIBILITY_PUBLIC) {
            return true;
        }

        return $user !== null
            && ($user->id === $prompt->user_id || $user->isModerator());
    }

    public function update(User $user, Prompt $prompt): bool
    {
        return $user->id === $prompt->user_id || $user->isModerator();
    }

    public function delete(User $user, Prompt $prompt): bool
    {
        return $user->id === $prompt->user_id || $user->isAdmin();
    }
}
