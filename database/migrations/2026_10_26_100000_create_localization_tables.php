<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Countries, currencies, exchange rates and delivery geography.
 *
 * Money convention used across the schema: every `*_amount` column is a signed
 * bigint holding integer minor units of the row's currency. The number of minor
 * units per major unit is `10 ^ currencies.minor_unit` (RWF = 0, KES/UGX/TZS = 0 or 2
 * as configured, USD = 2). Floating point is never used for money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->string('symbol', 10);
            $table->unsignedSmallInteger('minor_unit')->default(0);
            $table->boolean('symbol_first')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso2', 2)->unique();
            $table->string('name');
            $table->char('currency_code', 3);
            $table->string('phone_code', 6);
            $table->string('phone_pattern')->comment('Regex for the national significant number (no leading zero).');
            $table->string('default_locale', 5)->default('en');
            $table->unsignedSmallInteger('tax_rate_bps')->default(0)->comment('VAT in basis points, prices are tax inclusive.');
            $table->boolean('is_active')->default(false);
            $table->boolean('cod_enabled')->default(false);
            $table->timestampsTz();

            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 20, 10);
            $table->timestampTz('effective_at');
            $table->string('source')->nullable();
            $table->timestampsTz();

            $table->foreign('base_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('quote_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['base_currency', 'quote_currency', 'effective_at']);
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->bigInteger('fee_amount');
            $table->bigInteger('free_over_amount')->nullable()->comment('Order subtotal from which delivery is free.');
            $table->unsignedSmallInteger('min_days');
            $table->unsignedSmallInteger('max_days');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();

            $table->unique(['country_id', 'name']);
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_zone_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('province')->nullable();
            $table->timestampsTz();

            $table->unique(['country_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('phone')->constrained()->nullOnDelete();
        });

        DB::statement('ALTER TABLE delivery_zones ADD CONSTRAINT delivery_zones_days_check CHECK (min_days <= max_days AND fee_amount >= 0)');
        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_rate_check CHECK (rate > 0)');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
        });
        Schema::dropIfExists('districts');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
    }
};
