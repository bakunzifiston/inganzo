<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carts, orders, payments and refunds.
 *
 * Orders carry three independent status fields:
 *  - status              commercial lifecycle (pending_payment, processing, completed, cancelled)
 *  - payment_status      money state (unpaid, pending, paid, partially_refunded, refunded, failed)
 *  - fulfillment_status  physical progress (unfulfilled ... delivered)
 * Production and shipping have their own tables and statuses.
 *
 * Prices are VAT inclusive: total = subtotal - discount + delivery. tax_amount is the
 * VAT portion contained in the total, recorded for reporting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable();
            $table->string('recipient_name', 120);
            $table->string('phone', 20);
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sector', 80)->nullable();
            $table->string('street_address');
            $table->string('landmark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->string('discount_code', 40)->nullable();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX carts_one_per_user ON carts (user_id) WHERE user_id IS NOT NULL');

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestampsTz();

            $table->unique(['cart_id', 'artist_product_id', 'product_variant_id']);
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('type', 10);
            $table->unsignedInteger('percent_bps')->nullable();
            $table->bigInteger('amount')->nullable();
            $table->char('currency', 3);
            $table->bigInteger('min_subtotal_amount')->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_per_customer')->default(1);
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name', 120);
            $table->string('email');
            $table->string('phone', 20);
            $table->char('currency', 3);
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_zone_id')->constrained()->restrictOnDelete();
            $table->string('locale', 5)->default('en');
            $table->string('status', 20)->default('pending_payment')->index();
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->string('fulfillment_status', 20)->default('unfulfilled')->index();
            $table->string('payment_method', 30);
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('delivery_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->unsignedSmallInteger('tax_rate_bps')->default(0);
            $table->bigInteger('total_amount');
            $table->bigInteger('refunded_amount')->default(0);
            $table->foreignId('discount_code_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('tracking_token', 64)->unique();
            $table->text('customer_note')->nullable();
            $table->timestampTz('placed_at');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['user_id', 'placed_at']);
            $table->index('placed_at');
            $table->index('paid_at');
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('recipient_name', 120);
            $table->string('phone', 20);
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('district', 80)->nullable();
            $table->string('sector', 80)->nullable();
            $table->string('street_address');
            $table->string('landmark')->nullable();
            $table->timestampsTz();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('artwork_id')->constrained()->restrictOnDelete();
            $table->foreignId('artwork_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('artist_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('product_name');
            $table->string('color_name', 50);
            $table->char('color_hex', 7);
            $table->string('size_label', 20)->nullable();
            $table->string('sku', 64);
            $table->unsignedSmallInteger('quantity');
            $table->bigInteger('unit_price_amount');
            $table->bigInteger('unit_base_cost_amount');
            $table->bigInteger('unit_artist_earning_amount');
            $table->bigInteger('unit_platform_margin_amount');
            $table->bigInteger('line_total_amount');
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('refunded_amount')->default(0);
            $table->string('fulfillment_status', 20)->default('pending')->index();
            $table->jsonb('placement_snapshot')->comment('Frozen print specification at time of purchase.');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->index(['artist_profile_id', 'created_at']);
        });

        Schema::create('discount_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->bigInteger('amount');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['discount_code_id', 'email']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('status', 20)->default('pending')->index();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('payer_phone', 20)->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->string('provider_reference', 120)->nullable();
            $table->string('provider_status', 60)->nullable();
            $table->string('failure_reason')->nullable();
            $table->bigInteger('fee_amount')->default(0);
            $table->jsonb('meta')->nullable();
            $table->timestampTz('initiated_at')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['provider', 'provider_reference']);
        });
        // An order can have many payment attempts but at most one successful payment.
        DB::statement("CREATE UNIQUE INDEX payments_one_success_per_order ON payments (order_id) WHERE status = 'succeeded'");

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('type', 30);
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->boolean('signature_valid')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('result', 60)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['provider', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('reason', 30);
            $table->string('status', 20)->default('requested')->index();
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('category', 30);
            $table->string('subject', 160);
            $table->text('message');
            $table->string('status', 20)->default('open')->index();
            $table->text('staff_response')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body')->nullable();
            $table->string('status', 20)->default('published');
            $table->timestampsTz();

            $table->index(['artist_product_id', 'status']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 30)->default('home_banner');
            $table->jsonb('title');
            $table->jsonb('subtitle')->nullable();
            $table->jsonb('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('image_path')->nullable();
            $table->string('theme', 20)->default('ink');
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_check CHECK (quantity BETWEEN 1 AND 99)');
        DB::statement("ALTER TABLE discount_codes ADD CONSTRAINT discount_codes_value_check CHECK (
            (type = 'percent' AND percent_bps BETWEEN 1 AND 10000) OR (type = 'fixed' AND amount > 0)
        )");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_totals_check CHECK (
            subtotal_amount >= 0 AND discount_amount >= 0 AND delivery_amount >= 0 AND tax_amount >= 0
            AND discount_amount <= subtotal_amount
            AND total_amount = subtotal_amount - discount_amount + delivery_amount
            AND refunded_amount BETWEEN 0 AND total_amount
        )');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_amounts_check CHECK (
            quantity > 0
            AND unit_price_amount = unit_base_cost_amount + unit_artist_earning_amount + unit_platform_margin_amount
            AND line_total_amount = unit_price_amount * quantity
            AND discount_amount BETWEEN 0 AND line_total_amount
            AND refunded_amount BETWEEN 0 AND line_total_amount
        )');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0 AND fee_amount >= 0)');
        DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_check CHECK (amount > 0)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('discount_redemptions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('customer_addresses');
    }
};
