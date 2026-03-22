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

        $schemaClass::create('ticket_comments', static function (object $table): void {
            if (!method_exists($table, 'string')) {
                return;
            }

            $table->string('id')->primary();
            $table->string('ticket_id');
            $table->unsignedBigInteger('author_id');
            $table->text('message');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (!class_exists('\Illuminate\Support\Facades\Schema')) {
            return;
        }

        $schemaClass = '\Illuminate\Support\Facades\Schema';
        $schemaClass::dropIfExists('ticket_comments');
    }
};
