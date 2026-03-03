<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos de tipos de documentos y operaciones DIAN.
     * Viven en schema public — compartidos por todos los tenants.
     */
    public function up(): void
    {
        // Tipos de documento DIAN (Factura, NC, ND, Nómina, etc.)
        Schema::create('type_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('code', 10)->unique();           // código DIAN (ej: "01"=FEV, "91"=NC, "92"=ND)
            $table->string('cufe_algorithm', 20)->nullable(); // SHA-256, SHA-384, etc.
            $table->string('prefix', 10)->nullable();        // prefijo por defecto del documento
            $table->timestamps();
        });

        // Tipos de operación de documento (uso interno + DIAN)
        // Ej: Factura VE, NC por devolución, ND por intereses, Remisión, Ajuste inventario...
        Schema::create('type_document_operations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('code', 10)->unique();            // código interno (ej: "FEV", "NC", "ND", "REM")
            $table->string('name', 150);                     // nombre amigable
            $table->string('dian_name', 150)->nullable();    // nombre oficial DIAN en XML
            $table->string('prefix', 10)->nullable();        // prefijo de numeración sugerido
            $table->string('type_print', 50)->nullable();    // plantilla de impresión a usar
            $table->boolean('operation')->default(true);     // genera movimiento de inventario
            $table->timestamps();
        });

        // Respuestas de discrepancia para notas crédito (Devolución parcial, Anulación, etc.)
        Schema::create('credit_note_discrepancy_responses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('code', 10)->unique(); // código DIAN (ej: "1"=Devolución parcial)
            $table->timestamps();
        });

        // Respuestas de discrepancia para notas débito (Intereses, Gastos, etc.)
        Schema::create('debit_note_discrepancy_responses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('code', 10)->unique();
            $table->timestamps();
        });

        // Tipos de eventos DIAN (acuse de recibo, reclamo, aceptación...)
        Schema::create('type_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('code', 10)->unique(); // código DIAN
            $table->timestamps();
        });

        // Naturalezas contables (Débito / Crédito)
        Schema::create('accounting_natures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50);
            $table->string('key', 10)->unique(); // 'D' o 'C'
            $table->timestamps();
        });

        // Tipos de cuenta contable (Clase, Grupo, Cuenta, Subcuenta, Auxiliar, Detalle)
        Schema::create('type_operation_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Centros de costo (ventas, administración, producción, distribución)
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('internal_code', 20)->nullable();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Tipos de terceros (Cliente, Proveedor, Usuario, Otro)
        Schema::create('type_thirds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->timestamps();
        });

        // Cargos directivos (para contactos de empresa)
        Schema::create('management_positions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        // Clasificación de ítems (Producto, Servicio, Otro)
        Schema::create('item_clasification', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->timestamps();
        });

        // Categorías tributarias de ítems (Gravado, Excluido, Exento, No aplica)
        Schema::create('item_tax_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->timestamps();
        });

        // Conceptos de inventario (Entrada/Salida por ajuste, devolución, pérdida...)
        Schema::create('inventory_concepts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('type_document_operation_id')->nullable();
            $table->foreign('type_document_operation_id')
                ->references('id')->on('type_document_operations')->nullOnDelete();
            $table->string('code', 20)->nullable();
            $table->string('name', 150);
            $table->enum('type', ['input', 'output']); // entrada o salida de inventario
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_concepts');
        Schema::dropIfExists('item_tax_categories');
        Schema::dropIfExists('item_clasification');
        Schema::dropIfExists('management_positions');
        Schema::dropIfExists('type_thirds');
        Schema::dropIfExists('cost_centers');
        Schema::dropIfExists('type_operation_accounts');
        Schema::dropIfExists('accounting_natures');
        Schema::dropIfExists('type_events');
        Schema::dropIfExists('debit_note_discrepancy_responses');
        Schema::dropIfExists('credit_note_discrepancy_responses');
        Schema::dropIfExists('type_document_operations');
        Schema::dropIfExists('type_documents');
    }
};
