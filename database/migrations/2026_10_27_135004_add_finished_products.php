<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finished (ready-made) products: an artist's second way of selling, next to print on demand.
 *
 * finished_products          – the listing: title, description, price, shipping details, moderation status.
 * finished_product_variants  – colour/size options, each with its own SKU, optional price and stock.
 * finished_product_images    – product photos on the private artwork disk (served after a visibility check).
 *
 * Carts and orders are shared with print-on-demand items. cart_items and order_items gain a
 * finished_product_variant_id, the print-on-demand columns become nullable, and CHECK
 * constraints guarantee every row is exactly one of the two kinds. Stock can never go
 * negative (CHECK stock_quantity >= 0); checkout reserves it with a conditional decrement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finished_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->char('currency', 3);
            $table->bigInteger('price_amount');
            $table->bigInteger('sale_price_amount')->nullable();
            $table->bigInteger('price_from_amount')->nullable()->comment('Cached lowest current variant price for listing and sorting. Never used for charging.');
            $table->string('sku', 64)->nullable();
            $table->boolean('is_handmade')->default(false);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedSmallInteger('processing_days')->default(2);
            $table->string('ships_from', 80)->nullable();
            $table->string('shipping_note')->nullable();
            $table->unsignedSmallInteger('low_stock_threshold')->default(3);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->text('moderation_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['artist_profile_id', 'status']);
            $table->index('price_from_amount');
        });

        Schema::create('finished_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('color_name', 50)->nullable();
            $table->char('color_hex', 7)->nullable();
            $table->string('size_label', 20)->nullable();
            $table->bigInteger('price_amount')->nullable()->comment('Overrides the product price when set.');
            $table->bigInteger('sale_price_amount')->nullable()->comment('Overrides the product sale price when set.');
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

        });

        DB::statement("CREATE UNIQUE INDEX finished_product_variants_product_color_size_unique ON finished_product_variants (finished_product_id, COALESCE(color_name, ''), COALESCE(size_label, ''))");

        Schema::create('finished_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_product_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('preview_path');
            $table->string('thumbnail_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->index(['finished_product_id', 'position']);
        });

        DB::statement('ALTER TABLE finished_products ADD CONSTRAINT finished_products_price_check CHECK (
            price_amount > 0 AND (sale_price_amount IS NULL OR (sale_price_amount > 0 AND sale_price_amount < price_amount))
        )');
        DB::statement('ALTER TABLE finished_product_variants ADD CONSTRAINT finished_product_variants_check CHECK (
            stock_quantity >= 0
            AND (price_amount IS NULL OR price_amount > 0)
            AND (sale_price_amount IS NULL OR sale_price_amount > 0)
            AND (color_hex IS NULL OR color_hex ~ \'^#[0-9A-Fa-f]{6}$\')
        )');

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('artist_product_id')->nullable()->change();
            $table->foreignId('product_variant_id')->nullable()->change();
            $table->foreignId('finished_product_variant_id')->nullable()->after('product_variant_id')->constrained()->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX cart_items_finished_unique ON cart_items (cart_id, finished_product_variant_id) WHERE finished_product_variant_id IS NOT NULL');
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_kind_check CHECK (
            (artist_product_id IS NOT NULL AND product_variant_id IS NOT NULL AND finished_product_variant_id IS NULL)
            OR (artist_product_id IS NULL AND product_variant_id IS NULL AND finished_product_variant_id IS NOT NULL)
        )');

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('selling_type', 20)->default('print_on_demand')->after('order_id')->index();
            $table->foreignId('finished_product_id')->nullable()->after('artist_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('finished_product_variant_id')->nullable()->after('finished_product_id')->constrained()->restrictOnDelete();
            $table->timestampTz('stock_released_at')->nullable()->comment('When reserved stock was returned (cancellation or refund before shipping).');

            $table->foreignId('artist_product_id')->nullable()->change();
            $table->foreignId('artwork_id')->nullable()->change();
            $table->foreignId('artwork_version_id')->nullable()->change();
            $table->foreignId('product_id')->nullable()->change();
            $table->foreignId('product_variant_id')->nullable()->change();
            $table->string('color_name', 50)->nullable()->change();
            $table->char('color_hex', 7)->nullable()->change();
            $table->jsonb('placement_snapshot')->nullable()->change();
        });
        DB::statement("ALTER TABLE order_items ADD CONSTRAINT order_items_kind_check CHECK (
            (selling_type = 'print_on_demand' AND artist_product_id IS NOT NULL AND artwork_id IS NOT NULL AND artwork_version_id IS NOT NULL
                AND product_id IS NOT NULL AND product_variant_id IS NOT NULL AND placement_snapshot IS NOT NULL
                AND finished_product_variant_id IS NULL)
            OR (selling_type = 'finished' AND finished_product_id IS NOT NULL AND finished_product_variant_id IS NOT NULL
                AND artist_product_id IS NULL AND artwork_version_id IS NULL AND unit_base_cost_amount = 0)
        )");
    }

    public function down(): void
    {
        if (DB::table('order_items')->where('selling_type', 'finished')->exists()) {
            throw new RuntimeException('Orders for finished products exist; rolling back would delete order history.');
        }

        DB::statement('ALTER TABLE order_items DROP CONSTRAINT order_items_kind_check');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finished_product_variant_id');
            $table->dropConstrainedForeignId('finished_product_id');
            $table->dropColumn(['selling_type', 'stock_released_at']);

            $table->foreignId('artist_product_id')->nullable(false)->change();
            $table->foreignId('artwork_id')->nullable(false)->change();
            $table->foreignId('artwork_version_id')->nullable(false)->change();
            $table->foreignId('product_id')->nullable(false)->change();
            $table->foreignId('product_variant_id')->nullable(false)->change();
            $table->string('color_name', 50)->nullable(false)->change();
            $table->char('color_hex', 7)->nullable(false)->change();
            $table->jsonb('placement_snapshot')->nullable(false)->change();
        });

        DB::table('cart_items')->whereNotNull('finished_product_variant_id')->delete();
        DB::statement('ALTER TABLE cart_items DROP CONSTRAINT cart_items_kind_check');
        DB::statement('DROP INDEX cart_items_finished_unique');
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finished_product_variant_id');
            $table->foreignId('artist_product_id')->nullable(false)->change();
            $table->foreignId('product_variant_id')->nullable(false)->change();
        });

        Schema::dropIfExists('finished_product_images');
        Schema::dropIfExists('finished_product_variants');
        Schema::dropIfExists('finished_products');
    }
};
