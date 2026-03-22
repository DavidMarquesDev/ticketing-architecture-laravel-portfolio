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

        $schemaClass::create('personal_access_tokens', static function (object $table): void {
            if (!method_exists($table, 'id')) {
                return;
            }

            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        if (!class_exists('\Illuminate\Support\Facades\Schema')) {
            return;
        }

        $schemaClass = '\Illuminate\Support\Facades\Schema';
        $schemaClass::dropIfExists('personal_access_tokens');
    }
};
