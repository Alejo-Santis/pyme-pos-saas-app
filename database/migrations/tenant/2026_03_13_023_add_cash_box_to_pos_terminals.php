<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula cada terminal POS a una caja registradora (cash_box).
     * Los pagos en efectivo de ventas POS se registran automáticamente en esa caja.
     */
    public function up(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_terminals', 'cash_box_id')) {
                $table->uuid('cash_box_id')->nullable()->after('establishment_id');
                $table->foreign('cash_box_id')
                      ->references('id')->on('cash_boxes')->nullOnDelete();
            }
        });

        // Agrega balance_in_shift a pos_terminal_users para el cuadre de caja
        Schema::table('pos_terminal_users', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_terminal_users', 'final_balance')) {
                $table->decimal('final_balance', 15, 2)->default(0)->after('current_balance');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'total_sales')) {
                $table->decimal('total_sales', 15, 2)->default(0)->after('final_balance');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'total_cash')) {
                $table->decimal('total_cash', 15, 2)->default(0)->after('total_sales');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'total_card')) {
                $table->decimal('total_card', 15, 2)->default(0)->after('total_cash');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'total_transfer')) {
                $table->decimal('total_transfer', 15, 2)->default(0)->after('total_card');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'shift_opened_at')) {
                $table->timestamp('shift_opened_at')->nullable()->after('total_transfer');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'shift_closed_at')) {
                $table->timestamp('shift_closed_at')->nullable()->after('shift_opened_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->dropForeign(['cash_box_id']);
            $table->dropColumn('cash_box_id');
        });
    }
};
