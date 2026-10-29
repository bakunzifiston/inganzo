<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Artists, their source artwork and the product configurations built from it.
 *
 * An artwork is the reusable source design. Each upload is an immutable
 * artwork_version (original file + derived previews). An artist_product links an
 * artwork to a blank product, and artist_product_placements store how the design
 * sits inside each print area of that blank. One artwork can feed many products
 * without duplicating the source file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('display_name', 80);
            $table->string('slug', 80)->unique();
            $table->string('tagline', 160)->nullable();
            $table->text('bio')->nullable();
            $table->string('location', 100)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('website_url')->nullable();
            $table->string('instagram_handle', 60)->nullable();
            $table->string('x_handle', 60)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('status_reason')->nullable();
            $table->timestampTz('agreement_accepted_at')->nullable();
            $table->string('agreement_version', 20)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->timestampsTz();
        });

        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_profile_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30);
            $table->string('account_name', 120);
            $table->text('account_number')->comment('Encrypted at rest by the application.');
            $table->string('account_last4', 4);
            $table->string('bank_name', 120)->nullable();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->boolean('is_default')->default(true);
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestampTz('rights_confirmed_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('published_at')->nullable()->index();
            $table->boolean('is_mature')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->timestampsTz();

            $table->index(['artist_profile_id', 'status']);
        });

        // A trigger instead of a generated column keeps this working on PostgreSQL < 12.
        DB::statement('ALTER TABLE artworks ADD COLUMN search_vector tsvector');
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION artworks_search_vector_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector :=
                    setweight(to_tsvector('simple', coalesce(NEW.title, '')), 'A') ||
                    setweight(to_tsvector('simple', coalesce(NEW.description, '')), 'B');
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER artworks_search_vector_trigger
                BEFORE INSERT OR UPDATE OF title, description ON artworks
                FOR EACH ROW EXECUTE PROCEDURE artworks_search_vector_update()
        SQL);
        DB::statement('CREATE INDEX artworks_search_vector_idx ON artworks USING GIN (search_vector)');

        Schema::create('artwork_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('disk', 30);
            $table->string('original_path');
            $table->string('preview_path')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename');
            $table->string('mime_type', 50);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('file_size');
            $table->boolean('has_alpha')->default(false);
            $table->char('sha256', 64);
            $table->string('status', 20)->default('processing');
            $table->text('error')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['artwork_id', 'version_number']);
            $table->index('sha256');
        });

        Schema::table('artworks', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('artwork_versions')->nullOnDelete();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 60)->unique();
            $table->timestampsTz();
        });

        Schema::create('artwork_tag', function (Blueprint $table) {
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['artwork_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('moderation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artwork_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 20);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['artwork_id', 'created_at']);
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_profile_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('Null for platform-curated collections.');
            $table->string('slug', 120)->unique();
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['collection_id', 'artwork_id']);
        });

        Schema::create('artist_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('markup_type', 10)->default('percent');
            $table->unsignedInteger('markup_bps')->nullable();
            $table->bigInteger('markup_amount')->nullable();
            $table->foreignId('default_color_id')->nullable()->constrained('product_colors')->nullOnDelete();
            $table->bigInteger('price_from_amount')->nullable()->comment('Cached lowest variant price for listing and filtering. Never used for charging.');
            $table->char('currency', 3)->nullable();
            $table->string('print_check_status', 10)->default('pending');
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->timestampsTz();

            $table->unique(['artwork_id', 'product_id']);
            $table->index(['product_id', 'is_enabled']);
            $table->index('price_from_amount');
        });

        Schema::create('artist_product_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_area_id')->constrained()->restrictOnDelete();
            $table->foreignId('artwork_version_id')->constrained()->restrictOnDelete();
            $table->decimal('offset_x', 7, 6)->comment('Left edge of the design as a fraction of print-area width.');
            $table->decimal('offset_y', 7, 6)->comment('Top edge of the design as a fraction of print-area height.');
            $table->decimal('scale', 7, 6)->comment('Design bounding-box width as a fraction of print-area width.');
            $table->smallInteger('rotation')->default(0);
            $table->unsignedInteger('effective_dpi')->nullable();
            $table->string('print_check_status', 10)->default('pending');
            $table->string('print_check_message')->nullable();
            $table->timestampsTz();

            $table->unique(['artist_product_id', 'print_area_id']);
        });

        Schema::create('artist_product_colors', function (Blueprint $table) {
            $table->foreignId('artist_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_color_id')->constrained()->cascadeOnDelete();
            $table->primary(['artist_product_id', 'product_color_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artwork_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['user_id', 'artwork_id']);
        });

        Schema::create('copyright_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name', 120);
            $table->string('reporter_email');
            $table->string('rights_holder', 160);
            $table->string('original_work_url')->nullable();
            $table->text('claim_details');
            $table->boolean('good_faith_confirmed')->default(false);
            $table->string('status', 20)->default('open')->index();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE artist_products ADD CONSTRAINT artist_products_markup_check CHECK (
            (markup_type = 'percent' AND markup_bps IS NOT NULL) OR (markup_type = 'fixed' AND markup_amount IS NOT NULL AND markup_amount >= 0)
        )");
        DB::statement(<<<'SQL'
            ALTER TABLE artist_product_placements ADD CONSTRAINT artist_product_placements_bounds_check CHECK (
                offset_x >= 0 AND offset_y >= 0 AND offset_x <= 1 AND offset_y <= 1
                AND scale > 0 AND scale <= 1
                AND rotation IN (0, 90, 180, 270)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('copyright_reports');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('artist_product_colors');
        Schema::dropIfExists('artist_product_placements');
        Schema::dropIfExists('artist_products');
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('moderation_reviews');
        Schema::dropIfExists('artwork_tag');
        Schema::dropIfExists('tags');
        Schema::table('artworks', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('artwork_versions');
        Schema::dropIfExists('artworks');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('artist_profiles');
    }
};
