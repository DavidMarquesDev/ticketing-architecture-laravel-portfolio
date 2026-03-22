<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', static function (Blueprint $table): void {
                if (!Schema::hasColumn('users', 'name')) {
                    $table->string('name');
                }

                if (!Schema::hasColumn('users', 'email')) {
                    $table->string('email');
                }

                if (!Schema::hasColumn('users', 'password')) {
                    $table->string('password');
                }

                if (!Schema::hasColumn('users', 'roles')) {
                    $table->json('roles')->nullable();
                }

                if (!Schema::hasColumn('users', 'remember_token')) {
                    $table->rememberToken();
                }

                if (!Schema::hasColumn('users', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }

                if (!Schema::hasColumn('users', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            return;
        }

        Schema::create('users', static function (Blueprint $table): void {
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
        Schema::dropIfExists('users');
    }
};
