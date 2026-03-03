<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transferencias de ítems entre bodegas.
     */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();

            $table->uuid('warehouse_origin_id');
            $table->foreign('warehouse_origin_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->uuid('warehouse_destination_id');
            $table->foreign('warehouse_destination_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->enum('status', ['draft', 'in_transit', 'received', 'cancelled'])->default('draft');
            $table->date('transfer_date');
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);

            $table->timestamps();
        });

        // Ítems incluidos en la transferencia
        Schema::create('items_transfer', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('transfer_id');
            $table->foreign('transfer_id')->references('id')->on('transfers')->cascadeOnDelete();
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('cost', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4)->default(0);
            $table->timestamps();
        });

        // Auditoría de cambios en la transferencia
        Schema::create('transfer_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('transfer_id');
            $table->foreign('transfer_id')->references('id')->on('transfers')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action', 100);
            $table->text('notes')->nullable();
            $table->datetime('action_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_histories');
        Schema::dropIfExists('items_transfer');
        Schema::dropIfExists('transfers');
    }
};
