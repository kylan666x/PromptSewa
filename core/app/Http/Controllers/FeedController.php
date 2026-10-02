<?php

namespace App\Http\Controllers;

use App\Models\FeedEvent;
use Illuminate\Http\Request;

/**
 * G4 (v1.7.0) — the public news pulse (/feed, paper world).
 *
 * Paginated 20/page; the FIRST page is file-cached 5 minutes (TTL honest —
 * invalidate-on-write not required per the directive). Banned actors are
 * excluded at query level (FeedEvent::scopePublicStream). noindex: stream
 * content, avoid duplicate-content dilution.
 */
class FeedController extends Controller
{
    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', '1'));

        // Cache only page 1 (the hot surface); deep pages hit live.
        $events = $page === 1
            ? cache()->remember('feed:page:1', now()->addMinutes(5), fn () => $this->streamQuery())
            : $this->streamQuery();

        return view('feed.index', [
            'events' => $events,
            'page' => $page,
        ]);
    }

    private function streamQuery()
    {
        // LengthAwarePaginator over the public stream, 20/page.
        return FeedEvent::query()
            ->publicStream()
            ->with(['actor', 'actor.activeFrame', 'subject'])
            ->paginate(20)
            ->withQueryString();
    }
}
