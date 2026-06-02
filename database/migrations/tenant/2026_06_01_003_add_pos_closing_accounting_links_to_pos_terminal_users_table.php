<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminal_users', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_terminal_users', 'closing_cash_movement_id')) {
                $table->uuid('closing_cash_movement_id')->nullable()->after('close_notes');
                $table->foreign('closing_cash_movement_id', 'pos_shifts_closing_cash_movement_fk')
                    ->references('id')->on('cash_movements')->nullOnDelete();
            }

            if (! Schema::hasColumn('pos_terminal_users', 'closing_accounting_document_id')) {
                $table->uuid('closing_accounting_document_id')->nullable()->after('closing_cash_movement_id');
                $table->foreign('closing_accounting_document_id', 'pos_shifts_closing_accounting_document_fk')
                    ->references('id')->on('accounting_documents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_terminal_users', function (Blueprint $table) {
            if (Schema::hasColumn('pos_terminal_users', 'closing_accounting_document_id')) {
                $table->dropForeign('pos_shifts_closing_accounting_document_fk');
                $table->dropColumn('closing_accounting_document_id');
            }

            if (Schema::hasColumn('pos_terminal_users', 'closing_cash_movement_id')) {
                $table->dropForeign('pos_shifts_closing_cash_movement_fk');
                $table->dropColumn('closing_cash_movement_id');
            }
        });
    }
};
