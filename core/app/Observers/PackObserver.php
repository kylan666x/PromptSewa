<?php

namespace App\Observers;

use App\Models\FeedEvent;
use App\Models\Pack;
use App\Models\User;

/**
 * G4 (v1.7.0) — pack_created feed emission. Packs are admin-owned
 * merchandising, so the actor is the creating admin (resolved from the
 * auth user at creation time; system-created packs attribute to the
 * official account when present).
 */
class PackObserver
{
    public function created(Pack $pack): void
    {
        $actor = auth()->user()
            ?? User::query()->where('is_official', true)->first();

        if ($actor === null) {
            return;
        }

        FeedEvent::query()->create([
            'type' => FeedEvent::TYPE_PACK_CREATED,
            'actor_id' => $actor->id,
            'subject_type' => $pack::class,
            'subject_id' => $pack->id,
            'meta' => [
                'name' => $pack->name,
                'slug' => $pack->slug,
                'price_npr' => intdiv($pack->price_paisa, 100),
            ],
            'dedupe_key' => "pack_created:{$pack->id}",
            'created_at' => now(),
        ]);
    }
}
