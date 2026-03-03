<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas auxiliares: eventos DIAN, operaciones temporales,
     * buzón tributario y seguimiento de compras en progreso.
     */
    public function up(): void
    {
        // Eventos de documentos DIAN (acuse, reclamo, aceptación, endoso...)
        Schema::create('events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->string('cufe', 200)->nullable();          // CUFE del documento original
            // Códigos de evento DIAN (030=acuse, 031=reclamo, 032=aceptación, 033=rechazo, 034=endoso)
            $table->string('cude_030', 200)->nullable();
            $table->string('cude_031', 200)->nullable();
            $table->string('cude_032', 200)->nullable();
            $table->string('cude_033', 200)->nullable();
            $table->string('cude_034', 200)->nullable();
            $table->tinyInteger('status')->default(0);        // estado del evento
            $table->timestamps();
        });

        // Documentos en proceso de creación (borradores guardados automáticamente)
        Schema::create('temporary_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('type_document_operation_id'); // FK → public
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->json('data');                             // datos del documento en proceso
            $table->tinyInteger('state')->default(0);        // 0=borrador, 1=listo para procesar
            $table->timestamps();
        });

        // Buzón tributario: documentos electrónicos recibidos de proveedores via DIAN
        Schema::create('tax_mailboxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('type_document_id')->nullable(); // FK → public.type_documents
            $table->uuid('document_id')->nullable();          // documento interno si fue procesado
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();

            // Datos del emisor (proveedor externo)
            $table->string('identification_number_provider', 20)->nullable();
            $table->string('business_name_provider', 255)->nullable();

            // Datos del documento recibido
            $table->string('subject', 500)->nullable();
            $table->string('xml_file_name', 255)->nullable();
            $table->string('pdf_file_name', 255)->nullable();
            $table->datetime('date')->nullable();
            $table->string('cufe', 200)->nullable();
            $table->decimal('tax_inclusive_amount', 15, 4)->nullable();

            // Contenido del documento (base64 para almacenamiento)
            $table->json('base64_attacheddocument')->nullable();
            $table->json('events')->nullable();               // eventos aplicados al documento

            // Flags de referencia
            $table->boolean('has_order_reference')->default(false);
            $table->json('order_reference')->nullable();
            $table->json('payment_form')->nullable();

            $table->timestamps();
        });

        // Seguimiento de facturas de compra en proceso (desde buzón tributario)
        Schema::create('purchase_in_progress_mail_boxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->uuid('document_id')->nullable();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
            $table->uuid('tax_mailbox_id')->nullable();
            $table->foreign('tax_mailbox_id')->references('id')->on('tax_mailboxes')->nullOnDelete();
            $table->uuid('purchase_order_id')->nullable();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->json('data')->nullable();                 // datos en proceso
            $table->boolean('status')->default(false);        // false=pendiente, true=procesado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_in_progress_mail_boxes');
        Schema::dropIfExists('tax_mailboxes');
        Schema::dropIfExists('temporary_operations');
        Schema::dropIfExists('events');
    }
};
