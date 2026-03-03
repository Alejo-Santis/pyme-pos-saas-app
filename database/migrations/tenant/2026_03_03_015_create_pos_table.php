<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de Punto de Venta (POS):
     * terminales, turnos, cierres de caja, retiros, recibos de caja y pago.
     */
    public function up(): void
    {
        // Usuarios asignados a terminales POS con saldo de caja
        Schema::create('pos_terminal_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->uuid('establishment_id')->nullable();
            $table->foreign('establishment_id')->references('id')->on('establishments')->nullOnDelete();
            $table->uuid('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->decimal('initial_balance', 15, 4)->default(0);  // efectivo inicial del turno
            $table->decimal('current_balance', 15, 4)->default(0);  // saldo actual
            $table->boolean('active_shift')->default(false);         // turno activo
            $table->string('cashier_session_key', 100)->nullable();  // clave de sesión activa
            $table->timestamps();
        });

        // Cierres de caja por turno
        Schema::create('cash_register_counts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('establishment_id')->nullable();
            $table->foreign('establishment_id')->references('id')->on('establishments')->nullOnDelete();
            $table->uuid('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->datetime('opening_date')->nullable();
            $table->datetime('closing_date')->nullable();
            $table->decimal('cash_amount', 15, 4)->default(0);      // efectivo contado al cierre
            $table->decimal('mismatch_amount', 15, 4)->default(0);  // diferencia (faltante/sobrante)
            $table->decimal('surplus_amount', 15, 4)->default(0);   // sobrante
            $table->json('details')->nullable();                      // desglose de denominaciones
            $table->json('calculated_payment_methods')->nullable();  // totales por medio de pago
            $table->timestamps();
        });

        // Retiros de efectivo de caja (gastos, traslados)
        Schema::create('cash_with_drawals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->decimal('amount', 15, 4);
            $table->text('reason')->nullable();
            $table->string('destination_cash', 100)->nullable();
            $table->timestamps();
        });

        // Conceptos de ingresos y gastos (para recibos de caja)
        Schema::create('income_and_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->string('internal_code', 50)->nullable();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('type_document_operation_id')->nullable(); // FK → public
            $table->string('accountable_id')->nullable();
            $table->string('accountable_type')->nullable();
            $table->unsignedBigInteger('account_nature_id')->nullable();           // FK → public.accounting_natures
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        // Recibos de caja (ingresos — abonos de clientes, anticipos)
        Schema::create('cash_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->string('internal_code', 50)->nullable();
            $table->unsignedBigInteger('type_document_operation_id'); // FK → public
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('third_party_id')->nullable();
            $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            $table->uuid('income_and_expense_id')->nullable();
            $table->foreign('income_and_expense_id')->references('id')->on('income_and_expenses')->nullOnDelete();
            $table->uuid('resolution_id')->nullable();
            $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
            $table->string('prefix', 10)->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('amount_received', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('annulled')->default(false);
            $table->date('issue_date');
            $table->timestamps();
        });

        // Detalle de medios de pago en recibos de caja
        Schema::create('cash_receipt_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->uuid('cash_receipt_id');
            $table->foreign('cash_receipt_id')->references('id')->on('cash_receipts')->cascadeOnDelete();
            $table->uuid('document_id')->nullable();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            $table->unsignedBigInteger('rate_holding_tax_id')->nullable(); // FK → public.rate_holding_taxes
            $table->string('transaction_reference', 100)->nullable();
            $table->decimal('withholdings_tax', 15, 4)->default(0);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('payment_form_id')->nullable();   // FK → public.payment_forms
            $table->unsignedBigInteger('currency_id')->nullable();        // FK → public.type_currencies
            $table->decimal('amount', 15, 4)->default(0);
            $table->timestamps();
        });

        // Comprobantes de pago (pagos a proveedores, egresos)
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->string('internal_code', 50)->nullable();
            $table->unsignedBigInteger('type_document_operation_id'); // FK → public
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('third_party_id')->nullable();
            $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            $table->uuid('income_and_expense_id')->nullable();
            $table->foreign('income_and_expense_id')->references('id')->on('income_and_expenses')->nullOnDelete();
            $table->uuid('resolution_id')->nullable();
            $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
            $table->string('prefix', 10)->nullable();
            $table->unsignedInteger('number')->nullable();
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->decimal('amount_received', 15, 4)->default(0);
            $table->boolean('is_paid_credit')->default(false);         // ¿pago de factura a crédito?
            $table->json('payment_forms')->nullable();
            $table->date('issue_date');
            $table->timestamps();
        });

        // Detalle de medios de pago en comprobantes de pago
        Schema::create('payment_receipts_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->uuid('payment_receipt_id');
            $table->foreign('payment_receipt_id')->references('id')->on('payment_receipts')->cascadeOnDelete();
            $table->uuid('document_id')->nullable();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            $table->unsignedBigInteger('rate_holding_tax_id')->nullable();
            $table->string('transaction_reference', 100)->nullable();
            $table->decimal('withholdings_tax', 15, 4)->default(0);
            $table->decimal('quantity', 15, 4)->default(0);
            $table->unsignedBigInteger('payment_form_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->decimal('amount', 15, 4)->default(0);
            $table->decimal('refunded_amount', 15, 4)->default(0);    // monto revertido/devuelto
            $table->timestamps();
        });

        // Relación entre comprobantes de pago y documentos (para cruce de cartera)
        Schema::create('document_payment_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->uuid('payment_receipt_id');
            $table->foreign('payment_receipt_id')->references('id')->on('payment_receipts')->cascadeOnDelete();
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->uuid('payment_receipt_detail_id')->nullable();
            $table->decimal('amount', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_payment_receipts');
        Schema::dropIfExists('payment_receipts_details');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('cash_receipt_details');
        Schema::dropIfExists('cash_receipts');
        Schema::dropIfExists('income_and_expenses');
        Schema::dropIfExists('cash_with_drawals');
        Schema::dropIfExists('cash_register_counts');
        Schema::dropIfExists('pos_terminal_users');
    }
};
