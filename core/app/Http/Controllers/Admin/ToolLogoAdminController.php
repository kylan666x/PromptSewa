<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToolLogo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Admin AI-tool registry: name + optional logo upload. Cards and detail
 * pages render these logos instead of text chips.
 */
class ToolLogoAdminController extends Controller
{
    public function index()
    {
        return view('admin.tool-logos', [
            'tools' => ToolLogo::query()->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', 'unique:tool_logos,name'],
            'logo' => ['nullable', 'image', 'max:512'],
        ]);

        ToolLogo::query()->create([
            'name' => trim((string) $validated['name']),
            'logo_path' => $this->storeLogo($request),
            'position' => (int) ToolLogo::max('position') + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Tool added.');
    }

    public function update(Request $request, ToolLogo $toolLogo)
    {
        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'max:512'],
            'is_active' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $attributes = [
            'name' => trim((string) $validated['name']),
            'is_active' => $request->boolean('is_active'),
        ];

        $logoPath = $this->storeLogo($request);
        if ($logoPath !== null) {
            $attributes['logo_path'] = $logoPath;
        }

        $toolLogo->fill($attributes)->save();

        return back()->with('success', "Tool \"{$toolLogo->name}\" updated.");
    }

    public function destroy(ToolLogo $toolLogo)
    {
        $toolLogo->delete();

        return back()->with('success', 'Tool removed.');
    }

    private function storeLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        $file = $request->file('logo');

        return $file->storeAs('tool-logos', Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::random(6).'.'.$file->getClientOriginalExtension(), 'public');
    }
}
