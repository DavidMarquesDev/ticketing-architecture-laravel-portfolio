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

        $schemaClass::create('users', static function (object $table): void {
            if (!method_exists($table, 'id')) {
                return;
            }

            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->json('roles')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index('email');
        });
    }

    public function down(): void
    {
        if (!class_exists('\Illuminate\Support\Facades\Schema')) {
            return;
        }

        $schemaClass = '\Illuminate\Support\Facades\Schema';
        $schemaClass::dropIfExists('users');
    }
};
