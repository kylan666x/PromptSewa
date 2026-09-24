<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin brand settings: site name, tagline, contact emails, logo and
 * favicon. Files land on the public disk (storage/app/public) so they are
 * served through the storage symlink on cPanel without any special config.
 */
class BrandSettingsAdminController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function edit()
    {
        return view('admin.brand', [
            'siteName' => $this->settings->siteName(),
            'siteTagline' => (string) $this->settings->get('site_tagline', ''),
            'contactEmail' => (string) $this->settings->get('contact_email', ''),
            'supportEmail' => (string) $this->settings->get('support_email', ''),
            'logoPath' => (string) $this->settings->get('brand_logo_path', ''),
            'faviconPath' => (string) $this->settings->get('brand_favicon_path', ''),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can change brand settings.');

        $validated = $request->validate([
            'site_name' => ['required', 'string', 'min:2', 'max:60'],
            'site_tagline' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'logo' => ['nullable', 'image', 'max:512'],
            'favicon' => ['nullable', 'max:256', 'mimes:ico,png,svg'],
        ]);

        $this->settings->set('site_name', trim((string) $validated['site_name']));
        $this->settings->set('site_tagline', trim((string) ($validated['site_tagline'] ?? '')));
        $this->settings->set('contact_email', strtolower(trim((string) ($validated['contact_email'] ?? ''))));
        $this->settings->set('support_email', strtolower(trim((string) ($validated['support_email'] ?? ''))));

        $logoPath = $this->storeUpload($request, 'logo', 'brand');
        if ($logoPath !== null) {
            $this->settings->set('brand_logo_path', $logoPath);
        }

        $faviconPath = $this->storeUpload($request, 'favicon', 'brand');
        if ($faviconPath !== null) {
            $this->settings->set('brand_favicon_path', $faviconPath);
        }

        return back()->with('success', 'Brand settings saved.');
    }

    /**
     * Store an uploaded image on the public disk and return the relative
     * path (what gets saved in settings), or null when no file arrived.
     */
    private function storeUpload(Request $request, string $field, string $directory): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);

        return $file->storeAs(
            $directory,
            Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(6).'.'.$file->getClientOriginalExtension(),
            'public',
        );
    }
}
