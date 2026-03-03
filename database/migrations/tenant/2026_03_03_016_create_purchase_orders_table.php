<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo de órdenes de compra: OC, ítems, historial y archivos adjuntos.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();

            $table->uuid('third_party_id')->nullable();       // proveedor
            $table->foreign('third_party_id')->references('id')->on('third_parties')->nullOnDelete();
            $table->uuid('user_id')->nullable();              // usuario que creó la OC
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('approver_user_id')->nullable();     // usuario que aprobó
            $table->foreign('approver_user_id')->references('id')->on('users')->nullOnDelete();
            $table->uuid('document_id')->nullable();          // factura de compra generada
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();

            $table->string('internal_code', 50)->nullable();
            $table->string('reference', 100)->nullable();     // referencia del proveedor

            $table->decimal('amount', 15, 4)->default(0);
            $table->date('issue_date');
            $table->text('notes')->nullable();

            $table->enum('status', ['draft', 'pending', 'approved', 'partial', 'received', 'cancelled'])
                  ->default('draft');
            $table->boolean('approved')->default(false);
            $table->boolean('annulled')->default(false);

            $table->timestamps();
        });

        // Ítems de la orden de compra
        Schema::create('items_purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->uuid('item_id')->nullable();
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
            $table->decimal('invoice_quantity', 15, 4);       // cantidad solicitada
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->json('tax')->nullable();                  // impuestos de la línea
            $table->decimal('line_extension_amount', 15, 4)->default(0);
            $table->timestamps();
        });

        // Historial de cambios en la OC
        Schema::create('purchase_order_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->datetime('history_issue_date');
            $table->text('notes')->nullable();
            $table->text('history');
            $table->timestamps();
        });

        // Archivos adjuntos de la OC (facturas físicas del proveedor, etc.)
        Schema::create('document_file_purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('purchase_order_id');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedBigInteger('size')->nullable();   // tamaño en bytes
            $table->string('type', 100)->nullable();          // mime type
            $table->string('path', 500);                      // ruta en storage
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_file_purchase_orders');
        Schema::dropIfExists('purchase_order_histories');
        Schema::dropIfExists('items_purchase_orders');
        Schema::dropIfExists('purchase_orders');
    }
};
