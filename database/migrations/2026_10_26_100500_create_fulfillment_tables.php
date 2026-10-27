<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Printing partners, production jobs and shipments.
 *
 * Each order item is produced by a production_job at one partner. A failed or
 * cancelled job may be followed by a reprint (new job, reprint_of_id set); at most
 * one job per order item may be active at any time. Shipments group order items
 * so that orders can be partially fulfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printing_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('legal_name', 160)->nullable();
            $table->string('email');
            $table->string('phone', 20);
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('city', 80);
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('daily_capacity')->default(50);
            $table->unsignedSmallInteger('priority')->default(100)->comment('Lower is preferred during automatic assignment.');
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('partner_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printing_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('operator');
            $table->timestampsTz();
        });

        Schema::create('printing_partner_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printing_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->bigInteger('unit_cost_amount')->comment('What the partner charges the platform per unit.');
            $table->unsignedSmallInteger('production_days')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['printing_partner_id', 'product_id']);
        });

        Schema::create('partner_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('printing_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->cascadeOnDelete()
                ->comment('Null covers every zone in the country.');
            $table->timestampsTz();

            $table->unique(['printing_partner_id', 'country_id', 'delivery_zone_id'])->nullsNotDistinct();
        });

        Schema::create('production_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('printing_partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('reprint_of_id')->nullable()->constrained('production_jobs')->nullOnDelete();
            $table->string('status', 24)->default('assigned')->index();
            $table->unsignedSmallInteger('quantity');
            $table->bigInteger('unit_cost_amount');
            $table->char('currency', 3);
            $table->string('qc_result', 10)->nullable();
            $table->text('qc_notes')->nullable();
            $table->boolean('has_issue')->default(false);
            $table->string('failure_reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestampTz('due_at')->index();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->index(['printing_partner_id', 'status']);
        });
        DB::statement("CREATE UNIQUE INDEX production_jobs_one_active_per_item ON production_jobs (order_item_id) WHERE status NOT IN ('failed', 'cancelled')");

        Schema::create('production_job_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('printing_partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('carrier', 80);
            $table->string('tracking_number', 80)->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('status', 20)->default('dispatched')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->unique(['carrier', 'tracking_number']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->timestampsTz();

            $table->unique(['shipment_id', 'order_item_id']);
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->string('location', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE production_jobs ADD CONSTRAINT production_jobs_quantity_check CHECK (quantity > 0 AND unit_cost_amount >= 0)');
        DB::statement('ALTER TABLE shipment_items ADD CONSTRAINT shipment_items_quantity_check CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('production_job_events');
        Schema::dropIfExists('production_jobs');
        Schema::dropIfExists('partner_service_areas');
        Schema::dropIfExists('printing_partner_services');
        Schema::dropIfExists('partner_members');
        Schema::dropIfExists('printing_partners');
    }
};
