<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database-driven merchandise catalog.
 *
 * product_types  – merchandising groups (T-shirts, Hoodies, Mugs ...)
 * products       – a manufacturable blank (e.g. "Classic T-shirt") with a base cost
 * product_variants – colour × size combinations with an optional extra cost and SKU
 * print_areas    – physical printable regions of a blank (front, back ...)
 * product_mockups  – preview images and where the print area sits on each image
 * pricing_rules  – platform margin and artist-markup limits (see docs/PRICING.md)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestampsTz();
        });

        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_type_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->bigInteger('base_cost_amount');
            $table->char('currency', 3);
            $table->unsignedSmallInteger('production_days')->default(3);
            $table->unsignedInteger('weight_grams')->default(250);
            $table->boolean('is_shippable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->string('mockup_template', 50)->comment('Vector mockup template key in resources/mockups.');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->char('hex', 7);
            $table->boolean('is_dark')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('label', 20);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['product_id', 'label']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_color_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_size_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->bigInteger('extra_cost_amount')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

        });

        DB::statement("CREATE UNIQUE INDEX product_variants_product_color_size_unique ON product_variants (product_id, product_color_id, COALESCE(product_size_id, 0))");

        Schema::create('print_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key', 30);
            $table->string('label', 50);
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->unsignedSmallInteger('min_dpi')->default(150);
            $table->unsignedSmallInteger('recommended_dpi')->default(300);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['product_id', 'key']);
        });

        Schema::create('product_mockups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_color_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('Null means the mockup is a colourisable template used for every colour.');
            $table->string('view', 20)->default('front');
            $table->string('image_path')->nullable()->comment('Optional photographic mockup; falls back to the vector template.');
            $table->decimal('area_x', 6, 4);
            $table->decimal('area_y', 6, 4);
            $table->decimal('area_width', 6, 4);
            $table->decimal('area_height', 6, 4);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

        });

        DB::statement("CREATE UNIQUE INDEX product_mockups_product_area_color_view_unique ON product_mockups (product_id, print_area_id, COALESCE(product_color_id, 0), view)");

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedInteger('platform_margin_bps')->default(0);
            $table->bigInteger('platform_fixed_amount')->default(0);
            $table->unsignedInteger('artist_markup_min_bps')->default(0);
            $table->unsignedInteger('artist_markup_max_bps')->default(10000);
            $table->unsignedInteger('artist_markup_default_bps')->default(2000);
            $table->bigInteger('artist_fixed_max_amount')->nullable()->comment('Null disables fixed-amount artist markups.');
            $table->unsignedInteger('rounding_increment')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_base_cost_check CHECK (base_cost_amount >= 0)');
        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_extra_cost_check CHECK (extra_cost_amount >= 0)');
        DB::statement("ALTER TABLE product_colors ADD CONSTRAINT product_colors_hex_check CHECK (hex ~ '^#[0-9A-Fa-f]{6}$')");
        DB::statement('ALTER TABLE print_areas ADD CONSTRAINT print_areas_dpi_check CHECK (min_dpi > 0 AND recommended_dpi >= min_dpi AND width_mm > 0 AND height_mm > 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE product_mockups ADD CONSTRAINT product_mockups_area_check CHECK (
                area_x >= 0 AND area_y >= 0 AND area_width > 0 AND area_height > 0
                AND area_x + area_width <= 1 AND area_y + area_height <= 1
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE pricing_rules ADD CONSTRAINT pricing_rules_bounds_check CHECK (
                artist_markup_min_bps <= artist_markup_default_bps
                AND artist_markup_default_bps <= artist_markup_max_bps
                AND platform_fixed_amount >= 0
                AND rounding_increment > 0
                AND (product_id IS NULL OR product_type_id IS NULL)
                AND (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('product_mockups');
        Schema::dropIfExists('print_areas');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('product_colors');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_types');
        Schema::dropIfExists('categories');
    }
};
