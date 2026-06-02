<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('system_notifications');
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();             // null = para todos los admins
            $table->string('type', 40);                     // low_stock|dian_rejection|receivable_due|payable_due|nes_failed
            $table->string('title', 120);
            $table->text('body');
            $table->string('icon', 40)->default('mdi-bell'); // clase MDI
            $table->string('color', 20)->default('blue');    // blue|amber|rose|emerald
            $table->json('data')->nullable();                // datos contextuales (id, url, etc.)
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
