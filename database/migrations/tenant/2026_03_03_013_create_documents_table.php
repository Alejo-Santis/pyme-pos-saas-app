<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos: facturas, notas crédito/débito, remisiones, etc.
     * Núcleo de la facturación electrónica.
     * FK a catálogos globales en public via search_path.
     */
    public function up(): void
    {
        // Control de consecutivos por resolución (sincroniza current_number en resolutions)
        Schema::create('sends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('resolution_id');
            $table->foreign('resolution_id')->references('id')->on('resolutions')->cascadeOnDelete();
            $table->unsignedBigInteger('type_document_id');   // FK → public.type_documents
            $table->unsignedInteger('next_consecutive');       // próximo número a emitir
            $table->timestamps();
        });

        // Documentos transaccionales (facturas, notas, remisiones...)
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();                   // UUID para DIAN y URLs públicas
            $table->string('internal_code', 50)->nullable();  // código interno correlativo

            // Quién emite y quién recibe
            $table->uuid('user_id')->nullable();              // usuario que creó el documento
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('third_party_id')->nullable();       // cliente/proveedor
            $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            $table->uuid('seller_id')->nullable();            // vendedor asignado
            $table->foreign('seller_id')->references('id')->on('users')->nullOnDelete();

            // Tipo de documento — FK a catálogos globales
            $table->unsignedBigInteger('type_document_id');           // FK → public.type_documents
            $table->unsignedBigInteger('type_document_operation_id'); // FK → public.type_document_operations

            // Resolución de numeración
            $table->uuid('resolution_id')->nullable();
            $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
            $table->string('prefix', 10)->nullable();         // prefijo del documento
            // Nullable: resolution_id es opcional (documentos internos sin numeración DIAN),
            // así que el consecutivo solo se asigna cuando el documento tiene una resolución.
            $table->unsignedInteger('number')->nullable();    // número consecutivo

            // Totales financieros
            $table->unsignedBigInteger('currency_id')->default(1); // FK → public.type_currencies (COP=1)
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('total_discount', 15, 4)->default(0);
            $table->decimal('total_tax', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('balance', 15, 4)->default(0);   // saldo pendiente de pago

            // Forma de pago (JSON para múltiples)
            $table->json('payment_forms')->nullable();        // [{payment_form_id, payment_method_id, amount}]

            // Impuestos detallados (JSON para UBL 2.1)
            $table->json('taxes')->nullable();                // [{tax_id, percent, amount}]

            // Fechas
            $table->date('issue_date');                       // fecha de emisión

            // Estado del documento
            $table->boolean('paid')->default(false);          // ¿está completamente pagado?
            $table->boolean('electronic')->default(false);    // ¿fue enviado a la DIAN?
            $table->boolean('accounted')->default(false);     // ¿fue contabilizado?
            $table->boolean('annulled')->default(false);      // ¿fue anulado?

            // DIAN FE
            $table->string('cufe', 100)->nullable();          // Código Único de Factura Electrónica
            $table->string('qr_code', 500)->nullable();       // código QR para impresión

            // POS
            $table->string('cashier_shift')->nullable();       // turno de caja que lo generó

            $table->timestamps();

            $table->index(['type_document_operation_id', 'issue_date']);
            $table->index(['third_party_id', 'paid']);
        });

        // Líneas del documento (ítems facturados)
        Schema::create('documents_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->uuid('item_id')->nullable();
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();

            // Cantidades y valores
            $table->decimal('amount', 15, 4);                 // cantidad
            $table->decimal('cost_value', 15, 4)->default(0); // costo unitario (compras/inventario)
            $table->decimal('sale_price', 15, 4);             // precio de venta unitario
            $table->decimal('discount', 8, 4)->default(0);    // descuento porcentual
            $table->decimal('taxable_amount', 15, 4)->default(0); // base imponible
            $table->decimal('tax_amount', 15, 4)->default(0);     // total impuestos

            // Impuestos detallados de la línea (JSON UBL 2.1)
            $table->json('taxes')->nullable();

            // Unidad de medida — FK a catálogo global
            $table->unsignedBigInteger('unit_measure_id')->nullable(); // FK → public.unit_measures

            // Movimiento de inventario
            $table->uuid('warehouse_out')->nullable();        // bodega origen (ventas/traslados)
            $table->foreign('warehouse_out')->references('id')->on('warehouses')->nullOnDelete();
            $table->uuid('warehouse_in')->nullable();         // bodega destino (compras/traslados)
            $table->foreign('warehouse_in')->references('id')->on('warehouses')->nullOnDelete();
            $table->enum('movement_type', ['IN', 'OUT', 'NONE'])->default('NONE');

            $table->boolean('annulled')->default(false);
            $table->timestamps();
        });

        // Historial de cambios del documento (auditoría)
        Schema::create('document_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->datetime('history_issue_date');
            $table->text('notes')->nullable();
            $table->text('history');                          // descripción del cambio
            $table->timestamps();
        });

        // Medios de pago usados en el documento
        Schema::create('document_payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->unsignedBigInteger('payment_method_id'); // FK → public.payment_methods
            $table->decimal('amount', 15, 4);
            $table->timestamps();
        });

        // Respuestas de discrepancia (notas crédito/débito que referencian otro doc)
        Schema::create('document_discrepancy_responses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->unsignedBigInteger('discrepancy_responsable_id');  // id del catálogo de respuesta
            $table->string('discrepancy_responsable_type');             // credit_note o debit_note
            $table->timestamps();
        });

        // Movimientos de inventario (kardex)
        Schema::create('item_stocktakings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->bigInteger('document_details_id')->nullable();
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_concept_id')->nullable(); // FK → public.inventory_concepts
            $table->decimal('input_quantity', 15, 4)->default(0);
            $table->decimal('output_quantity', 15, 4)->default(0);
            $table->decimal('purchase_price', 15, 4)->default(0);
            $table->decimal('new_average', 15, 4)->default(0);
            $table->uuid('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->string('description')->nullable();
            $table->string('type_moviment', 10)->nullable();   // IN / OUT — ver ToolTrait::updateItemStocktaking
            $table->boolean('annulled')->default(false);
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        // Control de existencias por empresa, ítem, bodega
        Schema::create('item_inventory_controls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('document_id')->nullable();
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->uuid('warehouse_id')->nullable();
            $table->decimal('last_existence', 15, 4)->default(0);
            $table->decimal('new_existence', 15, 4)->default(0);
            $table->decimal('last_average', 15, 4)->default(0);
            $table->decimal('new_average', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_inventory_controls');
        Schema::dropIfExists('item_stocktakings');
        Schema::dropIfExists('document_discrepancy_responses');
        Schema::dropIfExists('document_payment_methods');
        Schema::dropIfExists('document_histories');
        Schema::dropIfExists('documents_details');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('sends');
    }
};
