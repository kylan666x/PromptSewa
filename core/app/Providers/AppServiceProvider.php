<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\SettingsService;
use Illuminate\Pagination\Paginator;
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
            $view->with('brandFaviconPath', (string) $settings->get('brand_favicon_path', ''));
            $view->with('contactEmail', (string) $settings->get('contact_email', ''));
            $view->with('supportEmail', (string) $settings->get('support_email', ''));
        });
    }
}
