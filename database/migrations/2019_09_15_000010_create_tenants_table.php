<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // stancl/tenancy usa string UUID como PK
            $table->string('id')->primary();

            // Datos del tenant visible al super-admin
            $table->string('name');                                                      // nombre de la empresa
            $table->string('email')->unique();                                           // email del admin principal
            $table->enum('status', ['trial', 'active', 'suspended', 'cancelled'])
                  ->default('trial');                                                    // estado del tenant

            // Plan activo (FK hacia plans, nullable en el periodo de trial inicial)
            $table->uuid('plan_id')->nullable();                                         // plan actual contratado
            $table->timestamp('trial_ends_at')->nullable();                              // fin del periodo trial

            // Metadata serializada (stancl la usa para datos extra del tenant)
            $table->json('data')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
