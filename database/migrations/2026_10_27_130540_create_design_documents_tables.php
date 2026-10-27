<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design Studio: editable compositions kept separately from flattened print files.
 *
 * design_documents  – one editable document per artwork (validated, versioned JSON).
 *                     Image layers reference design_assets / artwork_versions by id;
 *                     no image data is ever stored in the JSON.
 * design_assets     – images an artist uploads into the studio (private disk).
 * artwork_versions  – gains source_dpi_limit / design_dpi so an export that contains
 *                     enlarged low-resolution photos is still judged by the photos' real
 *                     resolution, not by the export's nominal pixel count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_profile_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 50);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->timestampsTz();

            $table->index(['artist_profile_id', 'created_at']);
        });

        Schema::create('design_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artwork_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('print_area_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->unsignedSmallInteger('design_dpi')->default(300);
            $table->jsonb('data');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('min_raster_dpi')->nullable();
            $table->foreignId('last_exported_version_id')->nullable()->constrained('artwork_versions')->nullOnDelete();
            $table->timestampTz('exported_at')->nullable();
            $table->timestampsTz();
        });

        Schema::table('artwork_versions', function (Blueprint $table) {
            $table->unsignedInteger('source_dpi_limit')->nullable()->after('has_alpha')
                ->comment('Lowest real DPI of raster layers in a studio export, at the designed size.');
            $table->unsignedSmallInteger('design_dpi')->nullable()->after('source_dpi_limit')
                ->comment('Nominal DPI the studio exported at; null for direct uploads.');
        });
    }

    public function down(): void
    {
        Schema::table('artwork_versions', function (Blueprint $table) {
            $table->dropColumn(['source_dpi_limit', 'design_dpi']);
        });
        Schema::dropIfExists('design_documents');
        Schema::dropIfExists('design_assets');
    }
};
