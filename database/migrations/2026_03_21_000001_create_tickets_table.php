<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', static function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->string('status', 20);
            $table->string('title', 180);
            $table->text('description');
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'assignee_id']);
            $table->index(['requester_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
