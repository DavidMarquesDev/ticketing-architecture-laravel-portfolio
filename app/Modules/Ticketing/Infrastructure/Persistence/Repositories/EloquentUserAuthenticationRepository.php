<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Models\User;
use App\Modules\Ticketing\Application\Ports\Out\UserAuthenticationRepositoryPort;

final class EloquentUserAuthenticationRepository implements UserAuthenticationRepositoryPort
{
    public function findByEmail(string $email): ?array
    {
        if (!class_exists(User::class)) {
            return null;
        }

        $user = User::query()
            ->select(['id', 'name', 'email', 'password', 'roles'])
            ->where('email', $email)
            ->first();

        if ($user === null) {
            return null;
        }

        $roles = is_array($user->roles) ? array_values(array_map('strval', $user->roles)) : [];

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'roles' => $roles,
            'password_hash' => (string) $user->password,
        ];
    }
}
