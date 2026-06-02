<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_documents', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('annulled');
            }

            if (! Schema::hasColumn('accounting_documents', 'reversed_by_accounting_document_id')) {
                $table->uuid('reversed_by_accounting_document_id')->nullable()->after('reversed_at');
                $table->foreign('reversed_by_accounting_document_id', 'acct_docs_reversed_by_fk')
                    ->references('id')->on('accounting_documents')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table) {
            if (Schema::hasColumn('accounting_documents', 'reversed_by_accounting_document_id')) {
                $table->dropForeign('acct_docs_reversed_by_fk');
                $table->dropColumn('reversed_by_accounting_document_id');
            }

            if (Schema::hasColumn('accounting_documents', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
        });
    }
};
