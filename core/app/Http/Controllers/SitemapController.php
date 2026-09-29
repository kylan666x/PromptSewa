<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Pack;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * T9 (v1.5.0) — dynamic sitemap.xml + robots.txt.
 *
 * Sitemap lists published public prompts, public creator profiles, active
 * categories, and active packs. Cached for 60 minutes; auth/dashboard/
 * checkout surfaces are deliberately excluded (they are noindex anyway).
 */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function (): string {
            $urls = [];

            $urls[] = ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'daily'];

            Prompt::query()
                ->publicListing()
                ->select('slug', 'updated_at')
                ->orderBy('updated_at')
                ->get()
                ->each(function (Prompt $prompt) use (&$urls): void {
                    $urls[] = [
                        'loc' => route('prompts.show', $prompt),
                        'lastmod' => optional($prompt->updated_at)->toAtomString(),
                        'priority' => '0.8',
                        'changefreq' => 'weekly',
                    ];
                });

            User::query()
                ->whereNotNull('username')
                ->where('banned_at', null)
                ->whereExists(function ($query): void {
                    // Raw builder here — publicListing is an Eloquent scope.
                    $query->selectRaw(1)
                        ->from('prompts')
                        ->whereColumn('prompts.user_id', 'users.id')
                        ->where('prompts.status', Prompt::STATUS_PUBLISHED)
                        ->where('prompts.visibility', Prompt::VISIBILITY_PUBLIC)
                        ->whereNull('prompts.deleted_at');
                })
                ->select('username')
                ->get()
                ->each(function (User $creator) use (&$urls): void {
                    $urls[] = ['loc' => route('creators.show', $creator), 'priority' => '0.6', 'changefreq' => 'weekly'];
                });

            Category::query()
                ->active()
                ->select('slug')
                ->get()
                ->each(function (Category $category) use (&$urls): void {
                    $urls[] = ['loc' => route('library.category', $category), 'priority' => '0.6', 'changefreq' => 'weekly'];
                });

            Pack::query()
                ->active()
                ->select('slug')
                ->get()
                ->each(function (Pack $pack) use (&$urls): void {
                    $urls[] = ['loc' => route('packs.show', $pack), 'priority' => '0.7', 'changefreq' => 'weekly'];
                });

            $urls[] = ['loc' => route('pages.about'), 'priority' => '0.3', 'changefreq' => 'yearly'];
            $urls[] = ['loc' => route('packs.index'), 'priority' => '0.5', 'changefreq' => 'weekly'];
            $urls[] = ['loc' => route('library.index'), 'priority' => '0.9', 'changefreq' => 'daily'];

            $lines = array_map(
                fn (array $url): string => '  <url>'
                   .'<loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>'
                   .(isset($url['lastmod']) ? '<lastmod>'.$url['lastmod'].'</lastmod>' : '')
                    .(isset($url['changefreq']) ? '<changefreq>'.$url['changefreq'].'</changefreq>' : '')
                    .(isset($url['priority']) ? '<priority>'.$url['priority'].'</priority>' : '')
                    .'</url>',
                $urls,
            );

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $lines)."\n"
                .'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /purchases',
            'Disallow: /checkout',
            'Disallow: /admin',
            'Disallow: /search/preview',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
