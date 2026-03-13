<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas críticas que faltaban en las migraciones iniciales.
 * Basado en el análisis del proyecto xedoc-laravel-svelte.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Terminales POS ────────────────────────────────────────────────
        if (! Schema::hasTable('pos_terminals')) {
            Schema::create('pos_terminals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->uuid('resolution_id')->nullable();
                $table->uuid('warehouse_id')->nullable();
                $table->uuid('establishment_id')->nullable();
                $table->string('serial_number')->unique()->nullable();
                $table->string('location')->nullable();
                $table->string('printer_name')->nullable();
                $table->string('printer_ip')->nullable();
                $table->string('printer_port')->nullable();
                $table->string('printer_type')->nullable();    // escpos, star, etc.
                $table->string('printer_start_with')->nullable();
                $table->boolean('is_usb')->default(false);
                $table->boolean('state')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        // ── Resumen de cierre de turno POS ────────────────────────────────
        if (! Schema::hasTable('cash_register_summaries')) {
            Schema::create('cash_register_summaries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('pos_terminal_id');
                $table->uuid('cash_register_count_id')->nullable();
                $table->decimal('total_sales', 15, 2)->default(0);
                $table->decimal('total_taxes', 15, 2)->default(0);
                $table->decimal('total_discounts', 15, 2)->default(0);
                $table->json('payment_summary')->nullable();   // resumen por medio de pago
                $table->timestamp('closing_date')->nullable();
                $table->decimal('difference', 15, 2)->default(0);
                $table->uuid('warehouse_id')->nullable();
                $table->uuid('establishment_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── Envíos electrónicos DIAN (tracking) ───────────────────────────
        if (! Schema::hasTable('sending_electronic_documents')) {
            Schema::create('sending_electronic_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('document_id');
                $table->uuid('event_id')->nullable();
                $table->json('error_message')->nullable();
                $table->boolean('is_valid')->default(false);
                $table->boolean('is_invoice')->default(false);
                $table->boolean('is_event')->default(false);
                $table->boolean('is_payroll')->default(false);
                $table->boolean('is_nc')->default(false);     // nota crédito
                $table->boolean('is_nd')->default(false);     // nota débito
                $table->boolean('is_ds')->default(false);     // documento soporte
                $table->boolean('is_nds')->default(false);    // NC de soporte
                $table->boolean('is_eqdocs')->default(false); // documento equivalente
                $table->string('status_code')->nullable();
                $table->string('status_description')->nullable();
                $table->text('status_message')->nullable();
                $table->string('xml_document_key')->nullable(); // CUFE / CUDE
                $table->text('qr_str')->nullable();
                $table->json('response_api')->nullable();
                $table->json('dian_validation_date_time')->nullable();
                $table->integer('attempts')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            });
        }

        // ── Centros de costo ──────────────────────────────────────────────
        if (! Schema::hasTable('cost_centers')) {
            Schema::create('cost_centers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->boolean('state')->default(true);
                $table->timestamps();
            });
        }

        // ── Parámetros contables (mapeo operación → cuenta PUC) ───────────
        if (! Schema::hasTable('accounting_parameters')) {
            Schema::create('accounting_parameters', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('type_document_operation_id');  // FK → public.type_document_operations
                $table->string('concept_slug');                            // ej: 'cxc', 'ingreso', 'iva_generado'
                $table->uuid('accountable_id');                            // FK polimórfica → minor_accounting_accounts o sub_accounts
                $table->string('accountable_type');                        // clase PHP
                $table->boolean('state')->default(true);
                $table->timestamps();
            });
        }

        // ── Bancos ────────────────────────────────────────────────────────
        if (! Schema::hasTable('banks')) {
            Schema::create('banks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('state')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── Cuentas bancarias ─────────────────────────────────────────────
        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('internal_code')->nullable();
                $table->string('name');
                $table->string('type')->nullable();              // Ahorro, Corriente
                $table->string('account_bank_number')->nullable();
                $table->uuid('bank_id')->nullable();
                $table->unsignedBigInteger('currency_id')->nullable(); // FK → public.type_currencies
                $table->uuid('accountable_id')->nullable();      // polimórfico
                $table->string('accountable_type')->nullable();
                $table->decimal('balance', 15, 2)->default(0);
                $table->boolean('has_gmf')->default(false);
                $table->boolean('state')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── Movimientos bancarios ─────────────────────────────────────────
        if (! Schema::hasTable('bank_account_movements')) {
            Schema::create('bank_account_movements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('bank_account_id');
                $table->unsignedBigInteger('type_document_operation_id')->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('description')->nullable();
                $table->timestamp('issue_date')->nullable();
                $table->string('reference')->nullable();
                $table->boolean('state')->default(true);
                $table->timestamps();

                $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->cascadeOnDelete();
            });
        }

        // ── Cajas de efectivo ─────────────────────────────────────────────
        if (! Schema::hasTable('cash_boxes')) {
            Schema::create('cash_boxes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('internal_code')->nullable();
                $table->string('name');
                $table->uuid('accountable_id')->nullable();  // polimórfico → minor_accounting_accounts
                $table->string('accountable_type')->nullable();
                $table->boolean('is_main')->default(false);
                $table->boolean('state')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── Movimientos de caja ───────────────────────────────────────────
        if (! Schema::hasTable('cash_movements')) {
            Schema::create('cash_movements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cash_box_id');
                $table->unsignedBigInteger('type_document_operation_id')->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('description')->nullable();
                $table->timestamp('issue_date')->nullable();
                $table->string('reference')->nullable();
                $table->boolean('state')->default(true);
                $table->timestamps();

                $table->foreign('cash_box_id')->references('id')->on('cash_boxes')->cascadeOnDelete();
            });
        }

        // ── Retenciones ───────────────────────────────────────────────────
        if (! Schema::hasTable('withholdings')) {
            Schema::create('withholdings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('document_id');
                $table->unsignedBigInteger('tax_id')->nullable();
                $table->uuid('third_party_id')->nullable();
                $table->uuid('holding_id')->nullable();
                $table->json('rate_holdings')->nullable();
                $table->decimal('base_value', 15, 2)->default(0);
                $table->decimal('fee', 15, 2)->default(0);
                $table->decimal('uvt', 15, 2)->default(0);
                $table->boolean('state')->default(true);
                $table->timestamps();

                $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            });
        }

        // ── Conceptos de gastos ───────────────────────────────────────────
        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->uuid('account_id')->nullable();   // FK → minor_accounting_accounts
                $table->boolean('state')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // ── UUID de transacciones DIAN ────────────────────────────────────
        if (! Schema::hasTable('document_transactions_id')) {
            Schema::create('document_transactions_id', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('document_id');
                $table->string('transaction_uuid')->nullable();
                $table->boolean('state')->default(true);
                $table->timestamp('issue_date')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            });
        }

        // ── Notificaciones del sistema ────────────────────────────────────
        if (! Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->string('title');
                $table->text('message')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // ── Columna dian_validation_date_time en documents (si no existe) ─
        if (Schema::hasTable('documents') && ! Schema::hasColumn('documents', 'dian_validation_date_time')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->json('dian_validation_date_time')->nullable()->after('cufe');
            });
        }

        // ── FK pos_terminal_id en documents (si no existe) ────────────────
        if (Schema::hasTable('documents') && ! Schema::hasColumn('documents', 'pos_terminal_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->uuid('pos_terminal_id')->nullable()->after('seller_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_transactions_id');
        Schema::dropIfExists('withholdings');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_boxes');
        Schema::dropIfExists('bank_account_movements');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('banks');
        Schema::dropIfExists('accounting_parameters');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('sending_electronic_documents');
        Schema::dropIfExists('cash_register_summaries');
        Schema::dropIfExists('pos_terminals');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('system_notifications');
    }
};
