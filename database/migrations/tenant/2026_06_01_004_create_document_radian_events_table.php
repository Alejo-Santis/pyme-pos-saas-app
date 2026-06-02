<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_radian_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('event_key', 20);    // 'accuse' | 'claim' | 'received' | 'acceptance'
            $table->string('event_code', 10);   // '030' | '031' | '032' | '033'
            $table->string('event_name', 150);  // descripción humana DIAN
            $table->string('cude', 120)->nullable();     // código único del evento (respuesta DIAN)
            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('error_message')->nullable();
            $table->json('response_api')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'event_code']);       // un registro por tipo de evento
            $table->index(['document_id', 'status']);
            $table->index(['status', 'attempts']);                // para el comando de reintento
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_radian_events');
    }
};
