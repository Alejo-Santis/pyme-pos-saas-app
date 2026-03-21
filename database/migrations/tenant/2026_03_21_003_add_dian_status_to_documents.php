<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega seguimiento del estado de transmisión DIAN al documento.
     *
     * Estados:
     *   pending    → aún no enviado a la DIAN
     *   processing → job en cola, enviando
     *   sent       → aceptado por DIAN (electronic=true)
     *   failed     → falló la transmisión (se puede reintentar)
     *   rejected   → rechazado por DIAN (requiere corrección del documento)
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('dian_status', 20)->default('pending')->after('electronic');
            $table->text('dian_error')->nullable()->after('dian_status');
            $table->timestamp('dian_sent_at')->nullable()->after('dian_error');
            $table->unsignedTinyInteger('dian_attempts')->default(0)->after('dian_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['dian_status', 'dian_error', 'dian_sent_at', 'dian_attempts']);
        });
    }
};
