<?php

use App\Support\SchemaInspector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

const COMPLETION = '2026_09_28_121000_make_license_grants_comp_capable';
const REPAIR = '2026_09_28_120999_repair_license_grants_preconditions';

/** Drop the unique index only if present — post-121100 it no longer exists. */
function dropUniqueIndexIfPresent(): void
{
    if (app(SchemaInspector::class)->hasUniqueIndex('license_grants', ['order_item_id'])) {
        Schema::table('license_grants', function (Blueprint $table) {
            $table->dropUnique(['order_item_id']);
        });
    }
}

function schemaFingerprint(): string
{
    $inspector = app(SchemaInspector::class);
    $indexes = $inspector->indexes('license_grants');
    $columns = Schema::getColumnListing('license_grants');

    sort($columns);
    usort($indexes, fn ($a, $b) => strcmp($a['name'], $b['name']));

    return md5(serialize([
        'columns' => $columns,
        'indexes' => array_map(fn ($i) => ['name' => $i['name'], 'unique' => $i['unique'], 'columns' => $i['columns']], $indexes),
        'notnull_order_item' => (function () {
            foreach (DB::select("PRAGMA table_info('license_grants')") as $c) {
                if ($c->name === 'order_item_id') {
                    return (int) $c->notnull;
                }
            }

            return null;
        })(),
    ]));
}

function rerunRepair(): void
{
    // Execute the repair migration file directly (idempotency probe).
    $path = __DIR__.'/../../database/migrations/2026_09_28_120999_repair_license_grants_preconditions.php';
    $migration = require $path;
    $migration->up();
}

test('fresh DB: repair no-ops and 121000 stands applied', function () {
    // RefreshDatabase has run every migration including 120999 + 121000.
    expect(DB::table('migrations')->where('migration', COMPLETION)->exists())->toBeTrue()
        ->and(DB::table('migrations')->where('migration', REPAIR)->exists())->toBeTrue()
        // 121000's post-state: order_item_id nullable, comp columns present.
        ->and(app(SchemaInspector::class)->hasColumn('license_grants', 'issued_by'))->toBeTrue()
        ->and(app(SchemaInspector::class)->hasColumn('license_grants', 'issue_reason'))->toBeTrue();

    // The fingerprint captured BEFORE re-running the repair must equal the
    // one AFTER (no schema drift from a replay).
    $before = schemaFingerprint();
    rerunRepair();
    expect(schemaFingerprint())->toBe($before, 'repair replay drifted the schema on a healthy DB');
});

test('simulated partial state: repair normalizes so 121000 can complete', function () {
    // Simulate prod's interrupted 121000: partial DDL applied (unique index
    // dropped, comp columns partially added) while NOTHING was deleted from
    // the migrations table — i.e. 121000 unrecorded, 120999 recorded.
    //
    // On the test DB 121000 HAS run, so to reconstruct the interrupted
    // pre-state we rewind its recorded-but-partial effects: drop what it
    // already added (keeping only 'issued_by' to model a PARTIAL column
    // add — the interruption point), then drop the unique index.
    Schema::table('license_grants', function (Blueprint $table) {
        $table->dropColumn(['issue_reason']);
    });
    dropUniqueIndexIfPresent();

    // Un-record 121000 (as an interrupted run would leave it).
    DB::table('migrations')->where('migration', COMPLETION)->delete();

    // Pre-repair sanity: the precondition is broken (no unique index,
    // one stray comp column from the partial add).
    $inspector = app(SchemaInspector::class);
    expect($inspector->hasUniqueIndex('license_grants', ['order_item_id']))->toBeFalse()
        ->and($inspector->hasColumn('license_grants', 'issued_by'))->toBeTrue();

    rerunRepair();

    // Post-repair: exact pre-121000 precondition restored.
    expect($inspector->hasUniqueIndex('license_grants', ['order_item_id']))->toBeTrue('unique index not restored')
        ->and($inspector->hasColumn('license_grants', 'issued_by'))->toBeFalse('stray comp column not dropped')
        ->and($inspector->hasColumn('license_grants', 'issue_reason'))->toBeFalse('comp column not dropped');
});

test('recorded post-state: repair is a strict no-op (schema hash unchanged)', function () {
    // Healthy recorded state; repair must not touch a thing.
    $before = schemaFingerprint();
    rerunRepair();

    expect(schemaFingerprint())->toBe($before, 'repair mutated a healthy recorded schema');
});

test('repair fails loud when duplicate order_item_id blocks the unique index', function () {
    dropUniqueIndexIfPresent();
    DB::table('migrations')->where('migration', COMPLETION)->delete();

    // Forge duplicates: seed an order item + two grants on the same line.
    $buyer = \App\Models\User::factory()->create();
    $creator = \App\Models\User::factory()->create(['role' => \App\Models\User::ROLE_CREATOR]);
    $prompt = \App\Models\Prompt::factory()->published()->for($creator, 'creator')->create();
    $order = \App\Models\Order::factory()->for($buyer, 'buyer')->create();
    $item = \App\Models\OrderItem::factory()->create(['prompt_id' => $prompt->id]);
    $order->items()->save($item);

    foreach (['DUP-A', 'DUP-B'] as $code) {
        \App\Models\LicenseGrant::create([
            'user_id' => $buyer->id,
            'order_item_id' => $item->id,
            'prompt_id' => $prompt->id,
            'license_tier' => 'personal',
            'grant_code' => $code,
            'status' => 'active',
        ]);
    }

    rerunRepair();

    // The repair must have refused to re-create the index over duplicates…
    expect(app(SchemaInspector::class)->hasUniqueIndex('license_grants', ['order_item_id']))->toBeFalse('index re-created over duplicates — idempotency contract corrupted');
})->throws(RuntimeException::class, 'duplicate order_item_id');
