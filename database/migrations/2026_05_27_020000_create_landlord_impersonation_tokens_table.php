<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landlord_impersonation_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token_hash', 64)->unique();
            $table->string('tenant_id');
            $table->string('tenant_domain');
            $table->uuid('admin_user_id')->nullable();
            $table->string('admin_name')->nullable();
            $table->string('admin_email')->nullable();
            $table->uuid('tenant_user_id');
            $table->string('tenant_user_name')->nullable();
            $table->string('tenant_user_email')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->ipAddress('created_ip')->nullable();
            $table->ipAddress('consumed_ip')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'expires_at']);
            $table->index(['tenant_user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landlord_impersonation_tokens');
    }
};
