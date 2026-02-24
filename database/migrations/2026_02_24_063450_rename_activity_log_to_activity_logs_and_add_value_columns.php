<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('activity_log', 'activity_logs');

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->json('old_values')->nullable()->after('properties');
            $table->json('new_values')->nullable()->after('old_values');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropColumn(['old_values', 'new_values']);
        });

        Schema::rename('activity_logs', 'activity_log');
    }
};
