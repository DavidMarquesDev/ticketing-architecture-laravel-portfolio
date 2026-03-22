<?php

declare(strict_types=1);


return new class
{
    public function up(): void
    {
        if (!class_exists('\Illuminate\Support\Facades\Schema')) {
            return;
        }

        $schemaClass = '\Illuminate\Support\Facades\Schema';

        $schemaClass::create('tickets', static function (object $table): void {
            if (!method_exists($table, 'string')) {
                return;
            }

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
        if (!class_exists('\Illuminate\Support\Facades\Schema')) {
            return;
        }

        $schemaClass = '\Illuminate\Support\Facades\Schema';
        $schemaClass::dropIfExists('tickets');
    }
};
