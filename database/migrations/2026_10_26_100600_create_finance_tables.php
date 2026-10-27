<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable financial ledger and artist payouts.
 *
 * Every recorded money movement is one append-only ledger row. Rows are never
 * updated or deleted (enforced by trigger); corrections are new adjustment rows.
 * entry_key is a deterministic idempotency key (e.g. "sale:artist:order_item:42")
 * so the same business event can never be posted twice.
 *
 * Accounts and buckets:
 *  artist  / held       – earnings on paid orders not yet eligible for payout
 *  artist  / available  – earnings eligible for payout (minus payouts requested)
 *  platform / revenue   – platform margin, delivery income, discounts, fees
 *  partner / payable    – production cost owed to printing partners
 *  processor / fees     – payment processing fees
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('artist_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('payout_account_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 20)->default('requested')->index();
            $table->string('method', 30);
            $table->string('account_display', 160);
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });
        DB::statement("CREATE UNIQUE INDEX payout_requests_one_open_per_artist ON payout_requests (artist_profile_id) WHERE status IN ('requested', 'approved')");

        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_request_id')->constrained()->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('reference', 120);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('executed_at');
            $table->timestampsTz();

            $table->unique(['provider', 'reference']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_key', 191)->unique();
            $table->string('account_type', 20);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('bucket', 20);
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payout_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_job_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['account_type', 'account_id', 'bucket']);
            $table->index(['type', 'created_at']);
        });

        DB::statement('ALTER TABLE payout_requests ADD CONSTRAINT payout_requests_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_account_check CHECK (
            (account_type IN ('artist', 'partner') AND account_id IS NOT NULL) OR account_type IN ('platform', 'processor')
        )");
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER ledger_entries_append_only
                BEFORE UPDATE OR DELETE ON ledger_entries
                FOR EACH ROW EXECUTE FUNCTION prevent_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_append_only ON ledger_entries');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payout_transactions');
        Schema::dropIfExists('payout_requests');
    }
};
