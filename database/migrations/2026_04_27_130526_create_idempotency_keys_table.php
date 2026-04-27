<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tenant_id')->nullable();
            // SHA-256 of (tenant_id|user_id|route_action|idempotency_key) — avoids NULL uniqueness issues
            $table->char('scope_hash', 64)->unique();
            $table->string('idempotency_key', 255);
            $table->string('route_action', 255);
            $table->char('request_hash', 64);
            $table->string('status', 20)->default('processing'); // processing | completed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'tenant_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
