<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mejora las tablas de bancos y caja existentes:
     * - Agrega columnas faltantes a banks (third_party_id, default_bank_account_id, internal_code)
     * - Agrega columnas faltantes a bank_account_movements (debit/credit, user_id, etc.)
     * - Agrega columnas faltantes a cash_movements (debit/credit, third_party_id, document_id, etc.)
     */
    public function up(): void
    {
        // ── banks: agregar columnas faltantes ─────────────────────────────────
        Schema::table('banks', function (Blueprint $table) {
            if (! Schema::hasColumn('banks', 'internal_code')) {
                $table->string('internal_code', 30)->nullable()->after('id');
            }
            if (! Schema::hasColumn('banks', 'third_party_id')) {
                $table->uuid('third_party_id')->nullable()->after('internal_code');
                $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('banks', 'default_bank_account_id')) {
                $table->uuid('default_bank_account_id')->nullable()->after('third_party_id');
            }
        });

        // ── bank_account_movements: reemplazar por estructura completa ─────────
        // Si la tabla existe con estructura antigua (amount/type_document_operation_id), la mejoramos
        Schema::table('bank_account_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_account_movements', 'debit')) {
                $table->decimal('debit', 15, 2)->default(0)->after('bank_account_id');
            }
            if (! Schema::hasColumn('bank_account_movements', 'credit')) {
                $table->decimal('credit', 15, 2)->default(0)->after('debit');
            }
            if (! Schema::hasColumn('bank_account_movements', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('credit');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('bank_account_movements', 'third_party_id')) {
                $table->uuid('third_party_id')->nullable()->after('user_id');
                $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('bank_account_movements', 'document_id')) {
                $table->uuid('document_id')->nullable()->after('third_party_id');
                $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('bank_account_movements', 'internal_code')) {
                $table->string('internal_code', 30)->nullable()->after('id');
            }
            if (! Schema::hasColumn('bank_account_movements', 'movementable_id')) {
                $table->uuid('movementable_id')->nullable();
                $table->string('movementable_type', 100)->nullable();
            }
            if (! Schema::hasColumn('bank_account_movements', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // ── cash_movements: agregar columnas faltantes ────────────────────────
        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'internal_code')) {
                $table->string('internal_code', 30)->nullable()->after('id');
            }
            if (! Schema::hasColumn('cash_movements', 'third_party_id')) {
                $table->uuid('third_party_id')->nullable();
                $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_movements', 'document_id')) {
                $table->uuid('document_id')->nullable();
                $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_movements', 'movementable_id')) {
                $table->uuid('movementable_id')->nullable();
                $table->string('movementable_type', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn(['internal_code', 'third_party_id', 'default_bank_account_id']);
        });
    }
};
