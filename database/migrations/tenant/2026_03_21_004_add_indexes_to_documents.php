<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Índice para consultas de reintentos DIAN y filtro por estado
            $table->index('dian_status', 'idx_documents_dian_status');

            // Índice compuesto para el dashboard: ventas del mes por tipo
            $table->index(['type_document_operation_id', 'issue_date', 'annulled'], 'idx_documents_type_date_annulled');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_dian_status');
            $table->dropIndex('idx_documents_type_date_annulled');
        });
    }
};
