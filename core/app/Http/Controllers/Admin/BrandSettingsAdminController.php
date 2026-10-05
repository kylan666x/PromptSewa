<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin brand settings: site name, tagline, contact emails, logo and
 * favicon. Files land on the public disk (storage/app/public) so they are
 * served through the storage symlink on cPanel without any special config.
 */
class BrandSettingsAdminController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ImageUploadService $images,
    ) {}

    public function edit()
    {
        return view('admin.brand', [
            'siteName' => $this->settings->siteName(),
            'siteTagline' => (string) $this->settings->get('site_tagline', ''),
            'contactEmail' => (string) $this->settings->get('contact_email', ''),
            'supportEmail' => (string) $this->settings->get('support_email', ''),
            'logoPath' => (string) $this->settings->get('brand_logo_path', ''),
            'markPath' => (string) $this->settings->get('brand_mark_path', ''),
            'faviconPath' => (string) $this->settings->get('brand_favicon_path', ''),
            // S9 (v1.8.0): the Sikka unit mark — color + mono variants,
            // admin-managed like every other brand asset.
            'sikkaIconPath' => (string) $this->settings->get('sikka-icon-path', ''),
            'sikkaIconMonoPath' => (string) $this->settings->get('sikka-icon-mono-path', ''),
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
            // S3: logo keeps transparency — PNG/WebP with alpha only, no
            // SVG (XSS vector), no JPEG (flattens transparency).
            'logo' => ['nullable', 'image', 'max:512', 'mimes:png,webp'],
            'mark' => ['nullable', 'image', 'max:512', 'mimes:png,webp'],
            'favicon' => ['nullable', 'max:256', 'mimes:ico,png,svg'],
            // S9 (v1.8.0): Sikka mark uploads — PNG/WebP with alpha only,
            // processed through the alpha-preserving 'sikka' variant.
            'sikka_icon' => ['nullable', 'image', 'max:2048', 'mimes:png,webp'],
            'sikka_icon_mono' => ['nullable', 'image', 'max:2048', 'mimes:png,webp'],
        ]);

        $this->settings->set('site_name', trim((string) $validated['site_name']));
        $this->settings->set('site_tagline', trim((string) ($validated['site_tagline'] ?? '')));
        $this->settings->set('contact_email', strtolower(trim((string) ($validated['contact_email'] ?? ''))));
        $this->settings->set('support_email', strtolower(trim((string) ($validated['support_email'] ?? ''))));

        $logoPath = $this->storeUpload($request, 'logo', 'brand');
        if ($logoPath !== null) {
            $this->settings->set('brand_logo_path', $logoPath);
        }

        $markPath = $this->storeUpload($request, 'mark', 'brand');
        if ($markPath !== null) {
            $this->settings->set('brand_mark_path', $markPath);
        }

        $faviconPath = $this->storeUpload($request, 'favicon', 'brand');
        if ($faviconPath !== null) {
            $this->settings->set('brand_favicon_path', $faviconPath);
        }

        // S9 (v1.8.0): the Sikka unit mark rides ImageUploadService's
        // alpha-preserving 'sikka' variant (PNG/WebP only, <=512px).
        $this->storeSikkaMark($request, 'sikka_icon', 'sikka-icon-path');
        $this->storeSikkaMark($request, 'sikka_icon_mono', 'sikka-icon-mono-path');

        return back()->with('success', 'Brand settings saved.');
    }

    /**
     * S9 (v1.8.0): process a Sikka mark upload through the alpha variant
     * and persist its settings key. A missing file is a no-op (upload a
     * new file to replace); processing failures surface as field errors.
     */
    private function storeSikkaMark(Request $request, string $field, string $key): void
    {
        if (! $request->hasFile($field)) {
            return;
        }

        try {
            $path = $this->images->store($request->file($field), 'sikka');
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        $this->settings->set($key, $path);
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
