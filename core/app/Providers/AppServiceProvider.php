<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\User;
use App\Services\SettingsService;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
