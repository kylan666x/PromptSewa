<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S1 (v1.8.0) — the Sikka ledger: INSERT-ONLY, the exact sibling of
 * wallet_transactions (AGENTS.md financial invariant #2).
 *
 *   - no updated_at column — rows are immutable facts;
 *   - balances are SUM(amount_sikka) computed under lockForUpdate(),
 *     never a cached column;
 *   - idempotency_key UNIQUE makes every credit/spend/hold/release
 *     replay-proof (double approvals credit once);
 *   - cashout_eligible is written at INSERT time: top-ups, bonuses, sale
 *     credits and stipends are withdrawable; engagement rewards and admin
 *     grants are spend-only by default (an admin may flip one grant with a
 *     mandatory reason, audited in meta + the admin log).
 *
 * amount_sikka is SIGNED: credits positive, spends/holds negative.
 * Sikka is a credit, never a currency — no decimals, integers only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sikka_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // topup | topup_bonus | sale_credit | spend | membership_stipend
            // | engagement_reward | admin_grant | payout_hold | payout_release
            $table->string('type', 32);
            // Signed BIGINT Sikka — credits positive, spends/holds negative.
            $table->bigInteger('amount_sikka');
            $table->boolean('cashout_eligible')->default(false);
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            // spend:{order_id}:{item_id} | topup:{order}:{item} | stipend:{membership_id}:{period}
            $table->string('idempotency_key')->unique();
            $table->json('meta')->nullable();
            // Insert-only: created_at is the ONLY timestamp.
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'cashout_eligible']);
        });
    }

    public function down(): void
    {
        // Financial tables are never dropped in the wild; down() exists for
        // test database symmetry only.
        Schema::dropIfExists('sikka_transactions');
    }
};
