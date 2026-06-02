<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Períodos fiscales para cierre contable.
 *
 * Una vez cerrado un período, el motor contable rechaza nuevos
 * asientos con fecha dentro de ese período.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');       // 1–12
            $table->string('name', 50);                 // ej: "Enero 2026"
            $table->string('status', 20)->default('open'); // open | closed
            $table->uuid('closed_by')->nullable();      // FK → users
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index(['year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
    }
};
