<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->string('status', 30)->index();
            $table->string('schema_name', 63)->unique();
            $table->timestampsTz();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('provisioning_started_at')->nullable();
            $table->timestampTz('provisioned_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
