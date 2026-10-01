<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M1 (v1.6.0) — Money Core ledger + payouts.
 *
 * wallet_transactions is the INSERT-ONLY financial ledger (AGENTS.md
 * invariant #2):
 *   - no updated_at column — rows are immutable facts;
 *   - balances are SUM(amount_paisa) computed under lockForUpdate(),
 *     never a cached column;
 *   - idempotency_key UNIQUE makes every credit/hold/release replay-proof
 *     (double webhooks credit once);
 *   - meta json carries the reason/admin context; nothing financial is
 *     derived from it.
 *
 * payouts is a state machine (requested → approved → settled, or
 * rejected/cancelled): state changes only, never deletes (AGENTS.md #5).
 * destination_encrypted is Crypt::encryptString'd at rest; plaintext
 * exists only inside the staff-gated decryption path.
 *
 * ledger_started_at is the cutover marker: paid orders BEFORE it are
 * pre-ledger revenue — visible in the Finance desk, never backfilled
 * into anyone's balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // sale_credit | withdrawal_hold | withdrawal_release | adjustment
            $table->string('type', 32);
            // Signed BIGINT paisa — credits positive, holds negative.
            $table->bigInteger('amount_paisa');
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            // sale:{order_id}:{item_id} | withdrawal_hold:{payout_id} | …
            $table->string('idempotency_key')->unique();
            $table->json('meta')->nullable();
            // Insert-only: created_at is the ONLY timestamp.
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'type']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_paisa');
            // requested | approved | settled | rejected | cancelled
            $table->string('status', 20)->default('requested')->index();
            // esewa_wallet | bank
            $table->string('method', 20);
            $table->text('destination_encrypted');
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Money settings defaults + the one-time cutover marker. The marker
        // is written ONLY if absent — the migration must stay idempotent.
        $defaults = [
            ['key' => 'commission_bps', 'value' => '2000'],
            ['key' => 'payout_min_paisa', 'value' => '50000'],
            ['key' => 'ledger_started_at', 'value' => now()->toIso8601String()],
        ];

        foreach ($defaults as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    public function down(): void
    {
        // Financial tables are never dropped in the wild; down() exists for
        // test database symmetry only.
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('wallet_transactions');

        DB::table('settings')->whereIn('key', ['commission_bps', 'payout_min_paisa', 'ledger_started_at'])->delete();
    }
};
