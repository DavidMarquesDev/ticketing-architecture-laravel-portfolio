<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

final class ManualTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsertUser('Admin Teste', 'admin@example.com', ['admin']);
        $this->upsertUser('Agente Teste', 'agent@example.com', ['agent']);
        $this->upsertUser('Cliente Teste', 'customer@example.com', ['customer']);
    }

    private function upsertUser(string $name, string $email, array $roles): void
    {
        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password123',
                'roles' => $roles,
            ]
        );
    }
}
