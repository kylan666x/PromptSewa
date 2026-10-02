<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\BookmarkedIds;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);

        // H4 (v1.7.4): the viewer's bookmarked prompt ids, resolved ONCE per
        // request so every heart surface can pre-flip server-side (the bug:
        // only the library grid knew the saved state, so a heart saved on the
        // home grid rendered unsaved after refresh).
        $this->app->singleton('bookmarked.ids', fn (): array => BookmarkedIds::resolve());

        // T5 (v1.7.3): the merged disposable-domain set (curated bundle +
        // admin's blocked_domains_extra) resolved once per request.
        $this->app->singleton('disposable.domains', function () {
            $settings = app(SettingsService::class);

            $extra = collect(preg_split('/[\r\n,]+/', (string) $settings->get('blocked_domains_extra', '')) ?: [])
                ->map(fn (string $d) => strtolower(trim($d)))
                ->filter()
                ->unique();

            return collect(config('disposable-domains', []))
                ->map(fn (string $d) => strtolower(trim($d)))
                ->merge($extra)
                ->unique()
                ->values();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // G2/G4 (v1.7.0): gamification + feed emission points. Observers
        // fire INSIDE the triggering transaction; controllers never emit.
        \App\Models\Prompt::observe(\App\Observers\PromptObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\UserBadge::observe(\App\Observers\UserBadgeObserver::class);
        \App\Models\Rating::observe(\App\Observers\RatingObserver::class);
        \App\Models\Pack::observe(\App\Observers\PackObserver::class);
        Paginator::defaultView('pagination::tailwind');

        // /creators/{creator} binds by the public handle (username) with a
        // display-name fallback for accounts that never picked one. Scoped
        // to this parameter only — admin {user} routes keep id binding.
        Route::bind('creator', function (string $value) {
            return User::query()
                ->where(fn ($q) => $q
                    ->where('username', $value)
                    ->orWhere('name', $value))
                ->whereNull('deleted_at')
                ->first();
        });

        // Every full page gets the navbar's category list without each
        // controller repeating the query (UI-001 navbar requirement).
        view()->composer('components.app-layout', function (View $view) {
            $settings = app(SettingsService::class);

            $view->with('navCategories', Category::query()
                ->active()
                ->orderBy('position')
                ->get(['id', 'name', 'slug', 'icon']));
            $view->with('siteName', $settings->siteName());
            $view->with('siteTagline', (string) $settings->get('site_tagline', ''));
            $view->with('brandLogoPath', (string) $settings->get('brand_logo_path', ''));
            $view->with('brandMarkPath', (string) $settings->get('brand_mark_path', ''));
            $view->with('brandFaviconPath', (string) $settings->get('brand_favicon_path', ''));
            $view->with('contactEmail', (string) $settings->get('contact_email', ''));
            $view->with('supportEmail', (string) $settings->get('support_email', ''));
        });
    }
}
