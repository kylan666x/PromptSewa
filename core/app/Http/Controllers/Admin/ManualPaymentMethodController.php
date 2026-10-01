<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualPaymentMethod;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * C3 (v1.4.4): admin CRUD for manual payment methods.
 *
 * - Create/edit: name (required), instructions, QR upload (PNG/WebP alpha
 *   preserved — scannability), active toggle, position (drag-free input).
 * - Delete: hard delete only before first use; once any order references
 *   the method name, "delete" deactivates so order history stays readable.
 * - Orders snapshot the NAME at checkout; later edits never rewrite it.
 */
class ManualPaymentMethodController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $images,
    ) {}

    public function index()
    {
        return view('admin.manual-methods', [
            'methods' => ManualPaymentMethod::query()->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can manage payment methods.');

        $validated = $this->validateMethod($request);

        $method = new ManualPaymentMethod();
        $method->name = trim($validated['name']);
        $method->kind = $validated['kind'] ?? ManualPaymentMethod::KIND_OTHER;
        $method->instructions = $validated['instructions'] ?? null;
        $method->position = (int) ($validated['position'] ?? 0);
        $method->active = $request->boolean('active');
        $method->qr_path = $this->storeQr($request);
        $method->save();

        return back()->with('success', "Manual method \"{$method->name}\" created.");
    }

    public function update(Request $request, ManualPaymentMethod $manualMethod)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can manage payment methods.');

        $validated = $this->validateMethod($request);

        $manualMethod->name = trim($validated['name']);
        $manualMethod->kind = $validated['kind'] ?? $manualMethod->kind;
        $manualMethod->instructions = $validated['instructions'] ?? null;
        $manualMethod->position = (int) ($validated['position'] ?? 0);
        $manualMethod->active = $request->boolean('active');

        if ($request->boolean('remove_qr')) {
            $this->images->delete($manualMethod->qr_path);
            $manualMethod->qr_path = null;
        } else {
            $newQr = $this->storeQr($request);
            if ($newQr !== null) {
                $this->images->delete($manualMethod->qr_path);
                $manualMethod->qr_path = $newQr;
            }
        }

        $manualMethod->save();

        return back()->with('success', "Manual method \"{$manualMethod->name}\" saved.");
    }

    public function destroy(Request $request, ManualPaymentMethod $manualMethod)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only admins can manage payment methods.');

        if ($manualMethod->isUsedByOrders()) {
            // History references this name — deactivate, never destroy.
            $manualMethod->active = false;
            $manualMethod->save();

            return back()->with('success', "\"{$manualMethod->name}\" is referenced by existing orders — deactivated instead of deleted.");
        }

        $this->images->delete($manualMethod->qr_path);
        $manualMethod->delete();

        return back()->with('success', "Manual method \"{$manualMethod->name}\" deleted.");
    }

    /** @return array{name: string, kind?: string, instructions?: string, position?: int} */
    private function validateMethod(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(ManualPaymentMethod::KINDS)],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'qr' => ['nullable', 'image', 'max:4096', 'mimes:png,webp,jpeg,jpg'],
            'remove_qr' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function storeQr(Request $request): ?string
    {
        if (! $request->hasFile('qr')) {
            return null;
        }

        try {
            return $this->images->store($request->file('qr'), 'qr');
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }
    }
}
