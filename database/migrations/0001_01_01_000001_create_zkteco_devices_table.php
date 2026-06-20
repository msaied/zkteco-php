<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('protocol_generation')->default('Legacy');
            $table->string('status')->default('pending')->index();
            $table->json('capabilities')->nullable();
            $table->json('stamps')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_devices');
    }
};
