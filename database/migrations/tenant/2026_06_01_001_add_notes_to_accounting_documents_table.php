<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounting_documents', 'notes')) {
            Schema::table('accounting_documents', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounting_documents', 'notes')) {
            Schema::table('accounting_documents', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
