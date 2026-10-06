<?php

use App\Models\Order;
use App\Models\Payout;
use App\Models\Product;
use App\Models\Prompt;
use App\Models\SikkaTransaction;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\ImageUploadService;
use App\Services\SettingsService;
use App\Services\SikkaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * S9 (v1.8.0) — the Sikka unit mark.
 *
 * The founder's approved art ships by UPLOAD (Admin → Brand), never inside
 * the release zip. This battery locks: the alpha-preserving 'sikka' ingest
 * variant (PNG/WebP, JPEG refused, <=512px), the Brand form's paper + ink
 * preview swatches, the <x-sikka> component contract (color mark when set,
 * mono variant for mail, honest bordered chip when unset, integers only),
 * and the arch ban that mirrors the money_npr rule — no blade view may echo
 * a raw Sikka amount outside the component.
 */
function sikkaIconAdmin(): User
{
    return User::factory()->create([
        'name' => 'Sikka Brand Admin',
        'username' => 'sikka-brand-admin',
        'email' => 'sikka-brand-admin@promptsewa.test',
        'role' => User::ROLE_ADMIN,
    ]);
}

/** A real transparent PNG: empty alpha canvas + a saffron block. */
function sikkaIconPngUpload(int $size = 1024): UploadedFile
{
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 40, 40, $size - 40, $size - 40, imagecolorallocate($image, 245, 197, 24));

    ob_start();
    imagepng($image);
    $binary = (string) ob_get_clean();
    imagedestroy($image);

    $tempPath = sys_get_temp_dir().'/sikka-mark-'.bin2hex(random_bytes(4)).'.png';
    file_put_contents($tempPath, $binary);

    return new UploadedFile($tempPath, 'sikka-mark.png', 'image/png', null, true);
}

/** A real transparent WebP built the same way. */
function sikkaIconWebpUpload(int $size = 1024): UploadedFile
{
    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 40, 40, $size - 40, $size - 40, imagecolorallocate($image, 245, 197, 24));

    $tempPath = sys_get_temp_dir().'/sikka-mark-'.bin2hex(random_bytes(4)).'.webp';
    imagewebp($image, $tempPath, 90);
    imagedestroy($image);

    return new UploadedFile($tempPath, 'sikka-mark.webp', 'image/webp', null, true);
}

/** A flat JPEG — no alpha channel at all. */
function sikkaIconJpegUpload(): UploadedFile
{
    $image = imagecreatetruecolor(400, 400);
    imagefilledrectangle($image, 0, 0, 399, 399, imagecolorallocate($image, 200, 120, 40));

    $tempPath = sys_get_temp_dir().'/sikka-mark-'.bin2hex(random_bytes(4)).'.jpg';
    imagejpeg($image, $tempPath, 90);
    imagedestroy($image);

    return new UploadedFile($tempPath, 'sikka-mark.jpg', 'image/jpeg', null, true);
}

/** 7-bit alpha of a pixel — 127 is fully transparent, 0 fully opaque. */
function sikkaIconAlpha(int $rgba): int
{
    return ($rgba >> 24) & 0x7F;
}

test('the sikka variant preserves alpha for PNG and WebP and stays at or below 512px', function () {
    Storage::fake('public');
    $service = new ImageUploadService;

    foreach ([sikkaIconPngUpload(), sikkaIconWebpUpload()] as $upload) {
        $path = $service->store($upload, 'sikka');

        expect(str_starts_with($path, 'sikka/'))->toBeTrue();

        $binary = Storage::disk('public')->get($path);
        $image = imagecreatefromstring($binary);

        expect(imagesx($image))->toBeLessThanOrEqual(512)
            ->and(imagesy($image))->toBeLessThanOrEqual(512)
            // The W2 rule (blending OFF + save-alpha ON) — a black corner
            // would read alpha 0; transparency must survive the re-encode.
            ->and(sikkaIconAlpha(imagecolorat($image, 0, 0)))->toBeGreaterThan(100);

        // The alpha-carrying formats are never re-encoded to JPEG.
        expect($path)->toEndWith($upload->getClientOriginalExtension() === 'webp' ? '.webp' : '.png');

        imagedestroy($image);
    }
});

test('the sikka variant refuses JPEG sources', function () {
    Storage::fake('public');
    $service = new ImageUploadService;

    expect(fn () => $service->store(sikkaIconJpegUpload(), 'sikka'))
        ->toThrow(RuntimeException::class, 'PNG or WebP');
});

test('the Brand form ingests both Sikka marks and refuses JPEG with a field error', function () {
    Storage::fake('public');
    $admin = sikkaIconAdmin();

    $this->actingAs($admin)
        ->put(route('admin.brand.update'), [
            'site_name' => 'PromptSewa',
            'sikka_icon' => sikkaIconPngUpload(),
            'sikka_icon_mono' => sikkaIconWebpUpload(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = app(SettingsService::class);
    $color = (string) $settings->get('sikka-icon-path', '');
    $mono = (string) $settings->get('sikka-icon-mono-path', '');

    expect($color)->toStartWith('sikka/')
        ->and($mono)->toStartWith('sikka/')
        ->and($mono)->toEndWith('.webp'); // WebP sources keep WebP, alpha and all

    Storage::disk('public')->assertExists($color);
    Storage::disk('public')->assertExists($mono);

    // JPEG is refused at the desk (and again inside the service, above).
    $this->actingAs($admin)
        ->from(route('admin.brand.edit'))
        ->put(route('admin.brand.update'), [
            'site_name' => 'PromptSewa',
            'sikka_icon' => sikkaIconJpegUpload(),
        ])
        ->assertSessionHasErrors('sikka_icon');

    // A save without new files never clears the uploaded marks.
    $this->actingAs($admin)->put(route('admin.brand.update'), ['site_name' => 'PromptSewa'])->assertRedirect();

    expect($settings->get('sikka-icon-path'))->toBe($color);
});

test('the served Brand form previews both marks on paper and ink grounds', function () {
    Storage::fake('public');
    $admin = sikkaIconAdmin();
    $settings = app(SettingsService::class);
    $settings->set('sikka-icon-path', 'sikka/color-mark.png');
    $settings->set('sikka-icon-mono-path', 'sikka/mono-mark.png');

    $html = $this->actingAs($admin)->get(route('admin.brand.edit'))->assertOk()->getContent();

    expect($html)->toContain('name="sikka_icon"')
        ->and($html)->toContain('name="sikka_icon_mono"')
        ->and($html)->toContain('accept="image/png,image/webp"')
        // Two swatches per mark — paper AND ink.
        ->and($html)->toContain('data-sikka-preview="paper"')
        ->and($html)->toContain('data-sikka-preview="ink"');

    // Count INSIDE the swatch spans: the served page also carries the navbar
    // wallet chip, which renders the same uploaded mark.
    preg_match_all(
        '~data-sikka-preview="(?<preview>mono-)?(?<ground>paper|ink)"[^>]*>\s*<img src="(?<src>[^"]+)"~',
        $html,
        $swatches,
        PREG_SET_ORDER,
    );

    $rendered = [];

    foreach ($swatches as $swatch) {
        $rendered[$swatch['preview'].$swatch['ground']] = $swatch['src'];
    }

    expect($rendered)->toBe([
        'paper' => asset('storage/sikka/color-mark.png'),
        'ink' => asset('storage/sikka/color-mark.png'),
        'mono-paper' => asset('storage/sikka/mono-mark.png'),
        'mono-ink' => asset('storage/sikka/mono-mark.png'),
    ]);
});

test('x-sikka renders the color mark when set, the mono variant for mail, and the chip fallback when unset', function () {
    $settings = app(SettingsService::class);
    $settings->set('sikka-icon-path', 'sikka/color-mark.png');
    $settings->set('sikka-icon-mono-path', 'sikka/mono-mark.png');

    $color = Blade::render('<x-sikka :amount="1234" :word="true" />');
    expect($color)->toContain('storage/sikka/color-mark.png')
        ->and($color)->toContain('>Sikka</span>') // first mention pairs the word
        ->and($color)->toContain('>1,234</span>'); // grouped integer, no decimals

    $mono = Blade::render('<x-sikka :amount="7" :mono="true" />');
    expect($mono)->toContain('storage/sikka/mono-mark.png');

    // No mono uploaded → the color mark covers ink; no mono path leaks.
    $settings->set('sikka-icon-mono-path', '');
    $monoFallback = Blade::render('<x-sikka :amount="7" :mono="true" />');
    expect($monoFallback)->toContain('storage/sikka/color-mark.png');

    // Nothing uploaded → an honest bordered chip, never a broken image.
    $settings->set('sikka-icon-path', '');
    $chip = Blade::render('<x-sikka :amount="7" />');
    expect($chip)->toContain('Sikka')
        ->and($chip)->toContain('>7</span>')
        ->and($chip)->not->toContain('<img');

    // Zero is a legitimate amount and still an integer.
    expect(Blade::render('<x-sikka :amount="0" />'))->toContain('>0</span>');
});

test('card, detail, checkout, earnings and finance render integer Sikka amounts through the component', function () {
    Storage::fake('public');
    $settings = app(SettingsService::class);
    $settings->set('sikka_enabled', '1');
    $settings->set('sikka-icon-path', 'sikka/color-mark.png');
    // Keep the buyer's balance exact: the daily-visit engagement reward is
    // a separate concern (EngagementRewardTest) and would add +1 here.
    $settings->set('engage_daily_sikka', '0');

    $creator = User::factory()->create(['role' => User::ROLE_CREATOR]);
    $prompt = Prompt::factory()->sikkaPriced(249)->hasVersion()->create([
        'user_id' => $creator->id,
        'status' => Prompt::STATUS_PUBLISHED,
        'visibility' => Prompt::VISIBILITY_PUBLIC,
    ]);

    // Card grid + detail page: 249 credits — the exact span content is the
    // decimal-point ban (a float would render ">249.00</span>").
    $library = $this->get(route('library.index'))->assertOk()->getContent();
    expect($library)->toContain('storage/sikka/color-mark.png')
        ->and($library)->toContain('>249</span>');

    $detail = $this->get(route('prompts.show', $prompt))->assertOk()->getContent();
    expect($detail)->toContain('storage/sikka/color-mark.png')
        ->and($detail)->toContain('>249</span>');

    // Checkout: a funded buyer sees the spendable balance and the total.
    $buyer = User::factory()->create();
    SikkaTransaction::query()->create([
        'user_id' => $buyer->id,
        'type' => SikkaTransaction::TYPE_TOPUP,
        'amount_sikka' => 1234,
        'cashout_eligible' => true,
        'idempotency_key' => 'sikka_icon:fund',
        'meta' => ['reason' => 'icon ingest fixture'],
        'created_at' => now(),
    ]);

    $product = Product::factory()->create(['prompt_id' => $prompt->id, 'price_paisa' => 24_900]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);
    $order->items()->create([
        'product_id' => $product->id, 'prompt_id' => $prompt->id,
        'price_paisa' => 24_900, 'currency' => 'npr', 'quantity' => 1,
    ]);

    $checkout = $this->actingAs($buyer)->get(route('checkout.show', $order))->assertOk()->getContent();
    expect($checkout)->toContain('storage/sikka/color-mark.png')
        ->and($checkout)->toContain('>1,234</span>')  // spendable, grouped
        ->and($checkout)->toContain('>249</span>');   // order total

    // Earnings: the Sikka cards + ledger browser.
    $earnings = $this->actingAs($buyer)->get(route('dashboard.earnings'))->assertOk()->getContent();
    expect($earnings)->toContain('storage/sikka/color-mark.png')
        ->and($earnings)->toContain('>1,234</span>');

    // Finance desk: a Sikka payout in the queue carries the currency chip
    // and the integer amount.
    $payout = app(SikkaService::class)->requestPayout($buyer, 750, Payout::METHOD_ESEWA_WALLET, '9800000000');
    $admin = sikkaIconAdmin();

    $finance = $this->actingAs($admin)->get(route('admin.finance'))->assertOk()->getContent();
    expect($finance)->toContain('storage/sikka/color-mark.png')
        ->and($finance)->toContain('SIKKA')
        ->and($finance)->toContain('>750</span>');

    expect($payout->sikka_amount)->toBe(750);
});

test('the reset mail header carries the mono mark, and none when unset or while the economy is off', function () {
    Storage::fake('public');
    $settings = app(SettingsService::class);
    $user = User::factory()->create();

    // S1 (v1.9.0): the economy ships ON, so the off state is now an explicit
    // legacy '0' row (no UI writes one any more) — even then a stored mono
    // path never leaks into mail.
    $settings->set('sikka-icon-mono-path', 'sikka/mono-mark.png');
    $settings->set('sikka_enabled', '0');
    $off = (new ResetPasswordNotification('token-off'))->toMail($user)->render();
    expect($off)->not->toContain('storage/sikka');

    $settings->set('sikka_enabled', '1');
    $on = (new ResetPasswordNotification('token-on'))->toMail($user)->render();
    expect($on)->toContain('storage/sikka/mono-mark.png')
        ->and($on)->not->toContain('storage/sikka/color'); // mono only on ink

    $settings->set('sikka-icon-mono-path', '');
    $unset = (new ResetPasswordNotification('token-unset'))->toMail($user)->render();
    expect($unset)->not->toContain('storage/sikka');
});

test('blade views never echo a raw Sikka amount outside the x-sikka component', function () {
    $viewDir = realpath(__DIR__.'/../../resources/views');
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        $relative = str_replace('\\', '/', substr((string) $file->getPathname(), strlen($viewDir) + 1));

        // 1. A simple echo (bare, or a non-input attribute) of a Sikka-named
        //    expression — the money_npr ban's mirror. Form number inputs are
        //    exempt: an admin edits raw integers there, they are not rendered
        //    amounts. Asset URLs/paths/booleans are not amounts either.
        if (preg_match_all('/\{\{\s*(\$[a-zA-Z_]+(?:\s*->\s*[a-zA-Z_]+)*)\s*\}\}/', $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $i => $capture) {
                $expr = $capture[0];
                $offset = (int) $m[0][$i][1];

                if (! preg_match('/(sikka)/i', $expr)) {
                    continue;
                }

                // Only AMOUNTS are banned — a mark's name/slug/URL/flag is not
                // a number and never renders through <x-sikka>.
                $terminal = preg_match('/->\s*([a-zA-Z_]+)$/', $expr, $tail) ? $tail[1] : substr($expr, 1);

                if (preg_match('/(name|slug|label|title|url|path|icon|enabled|status|type|id)$/i', $terminal)) {
                    continue;
                }

                if (preg_match('/value="$/', substr($content, max(0, $offset - 10), 10))) {
                    continue; // an editable form field, not a rendered amount
                }

                $violations[] = $relative.': raw Sikka echo '.$expr.' — render through <x-sikka>';
            }
        }
        // 2. Formatting a Sikka integer anywhere but the component itself.
        if (preg_match('/number_format\([^)]*sikka/i', $content)) {
            $violations[] = $relative.': number_format on a Sikka amount — SikkaFormat belongs in <x-sikka>';
        }

        // 3. The formatter is the component's private door.
        if ($relative !== 'components/sikka.blade.php' && str_contains($content, 'SikkaFormat::render(')) {
            $violations[] = $relative.': SikkaFormat::render outside <x-sikka>';
        }
    }

    expect($violations)->toBe([], implode("\n", $violations));
});
