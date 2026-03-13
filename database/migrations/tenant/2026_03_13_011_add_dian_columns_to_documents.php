<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas JSON requeridas por el builder UBL 2.1 de la DIAN.
 * En xedoc estos campos almacenan el payload listo para Nextpyme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // JSON para el builder DIAN (líneas enriquecidas con códigos DIAN)
            if (! Schema::hasColumn('documents', 'invoice_lines')) {
                $table->json('invoice_lines')->nullable()->after('taxes');
            }
            // Totales monetarios legales UBL 2.1
            if (! Schema::hasColumn('documents', 'legal_monetary_totals')) {
                $table->json('legal_monetary_totals')->nullable()->after('invoice_lines');
            }
            // Retenciones aplicadas al documento
            if (! Schema::hasColumn('documents', 'withholdings_tax')) {
                $table->json('withholdings_tax')->nullable()->after('legal_monetary_totals');
            }
            // Nota general del documento
            if (! Schema::hasColumn('documents', 'note')) {
                $table->text('note')->nullable()->after('withholdings_tax');
            }
            // Referencia de orden de compra asociada
            if (! Schema::hasColumn('documents', 'order_reference')) {
                $table->json('order_reference')->nullable()->after('note');
            }
            // UUID público del documento (para URLs y DIAN)
            if (! Schema::hasColumn('documents', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
            // Número de sistema (consecutivo independiente del prefijo DIAN)
            if (! Schema::hasColumn('documents', 'system_number')) {
                $table->unsignedInteger('system_number')->nullable()->after('number');
            }
            // Documento referenciado (para NC/ND indica la factura original)
            if (! Schema::hasColumn('documents', 'reference_id')) {
                $table->uuid('reference_id')->nullable()->after('resolution_id');
                $table->foreign('reference_id')->references('id')->on('documents')->nullOnDelete();
            }
        });

        // Agregar description y annulled a documents_details si faltan
        if (Schema::hasTable('documents_details')) {
            Schema::table('documents_details', function (Blueprint $table) {
                if (! Schema::hasColumn('documents_details', 'description')) {
                    $table->string('description')->nullable()->after('item_id');
                }
                if (! Schema::hasColumn('documents_details', 'item_note')) {
                    $table->text('item_note')->nullable()->after('description');
                }
                if (! Schema::hasColumn('documents_details', 'state')) {
                    $table->boolean('state')->default(true)->after('annulled');
                }
            });
        }

        // Agregar description y annulled a item_stocktakings si faltan
        if (Schema::hasTable('item_stocktakings')) {
            Schema::table('item_stocktakings', function (Blueprint $table) {
                if (! Schema::hasColumn('item_stocktakings', 'description')) {
                    $table->string('description')->nullable()->after('new_average');
                }
                if (! Schema::hasColumn('item_stocktakings', 'type_moviment')) {
                    $table->string('type_moviment', 10)->nullable()->after('description');
                }
                if (! Schema::hasColumn('item_stocktakings', 'annulled')) {
                    $table->boolean('annulled')->default(false)->after('type_moviment');
                }
                if (! Schema::hasColumn('item_stocktakings', 'state')) {
                    $table->boolean('state')->default(true)->after('annulled');
                }
                if (! Schema::hasColumn('item_stocktakings', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('documents', 'invoice_lines')        ? 'invoice_lines'        : null,
                Schema::hasColumn('documents', 'legal_monetary_totals')? 'legal_monetary_totals': null,
                Schema::hasColumn('documents', 'withholdings_tax')     ? 'withholdings_tax'     : null,
                Schema::hasColumn('documents', 'note')                 ? 'note'                 : null,
                Schema::hasColumn('documents', 'order_reference')      ? 'order_reference'      : null,
                Schema::hasColumn('documents', 'system_number')        ? 'system_number'        : null,
            ]));
        });
    }
};
