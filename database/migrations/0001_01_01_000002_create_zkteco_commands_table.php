<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zkteco_commands', function (Blueprint $table): void {
            $table->id();
            $table->string('serial_number');
            $table->text('command');
            $table->string('status')->default('pending');
            $table->integer('return_code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['serial_number', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_commands');
    }
};
