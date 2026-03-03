<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de contabilidad: comprobantes, plan de cuentas, conceptos.
     */
    public function up(): void
    {
        // Conceptos contables configurables (para automatizar asientos)
        Schema::create('accounting_concepts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->string('internal_code', 50)->nullable();
            $table->string('name', 150);
            $table->string('type_concept', 50)->nullable();    // tipo de concepto
            $table->string('accountable_id')->nullable();      // cuenta contable asociada
            $table->string('accountable_type')->nullable();    // tipo de modelo contable
            $table->unsignedBigInteger('account_nature_id')->nullable(); // FK → public.accounting_natures
            $table->unsignedBigInteger('cost_center_id')->nullable();    // FK → public.cost_centers
            $table->boolean('is_tax_concept')->default(false);
            $table->timestamps();
        });

        // Comprobantes contables (asientos)
        Schema::create('accounting_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->string('internal_code', 50)->nullable();

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('third_party_id')->nullable();
            $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            $table->uuid('document_id')->nullable();          // documento comercial que origina el asiento
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();

            $table->unsignedBigInteger('type_document_operation_id'); // FK → public.type_document_operations

            $table->string('prefix', 10)->nullable();
            $table->unsignedInteger('number')->nullable();

            $table->decimal('debit', 15, 4)->default(0);
            $table->decimal('credit', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);

            $table->date('issue_date');
            $table->boolean('annulled')->default(false);

            $table->timestamps();
        });

        // Líneas del comprobante contable
        Schema::create('accounting_documents_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('accounting_document_id');
            $table->foreign('accounting_document_id')
                ->references('id')->on('accounting_documents')->cascadeOnDelete();

            // Cuenta contable (polimórfico — puede ser string de código de cuenta)
            $table->string('accountable_id')->nullable();
            $table->string('accountable_type')->nullable();

            $table->uuid('third_party_id')->nullable();
            $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            $table->unsignedBigInteger('cost_center_id')->nullable(); // FK → public.cost_centers

            $table->string('document_number', 50)->nullable();
            $table->decimal('taxable_amount', 15, 4)->default(0);
            $table->decimal('debit', 15, 4)->default(0);
            $table->decimal('credit', 15, 4)->default(0);

            $table->date('issue_date')->nullable();
            $table->timestamps();
        });

        // Saldos iniciales contables por año
        Schema::create('initial_balance_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id')->nullable();
            $table->unsignedBigInteger('type_document_operation_id')->nullable();
            $table->uuid('document_id')->nullable();
            $table->bigInteger('accounting_documents_detail_id')->nullable();
            $table->string('accountable_id')->nullable();
            $table->string('accountable_type')->nullable();
            $table->uuid('third_party_id')->nullable();
            $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            $table->smallInteger('year');
            $table->decimal('debit', 15, 4)->default(0);
            $table->decimal('credit', 15, 4)->default(0);
            $table->decimal('taxable_amount', 15, 4)->default(0);
            $table->text('note')->nullable();
            $table->date('issue_date')->nullable();
            $table->boolean('annulled')->default(false);
            $table->boolean('accounting_closing')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('initial_balance_accounts');
        Schema::dropIfExists('accounting_documents_details');
        Schema::dropIfExists('accounting_documents');
        Schema::dropIfExists('accounting_concepts');
    }
};
