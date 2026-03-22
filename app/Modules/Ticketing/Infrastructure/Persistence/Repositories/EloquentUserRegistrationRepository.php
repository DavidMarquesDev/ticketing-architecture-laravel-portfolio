<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Models\User;
use App\Modules\Ticketing\Application\Ports\Out\UserRegistrationRepositoryPort;

/**
 * Repositório Eloquent para operações de cadastro de usuários.
 *
 * Implementa o contrato de persistência de cadastro usando Eloquent
 * e retorna dados normalizados para a camada de aplicação.
 *
 * @author David Marques
 */
final class EloquentUserRegistrationRepository implements UserRegistrationRepositoryPort
{
    /**
     * Verifica existência de usuário por e-mail.
     *
     * @param string $email E-mail para validação de existência.
     * @return bool
     *
     * @author David Marques
     */
    public function existsByEmail(string $email): bool
    {
        if (!class_exists(User::class)) {
            return false;
        }

        return User::query()->where('email', $email)->exists();
    }

    /**
     * Persiste usuário e normaliza payload de retorno.
     *
     * @param string $name Nome completo do usuário.
     * @param string $email E-mail do usuário.
     * @param string $password Senha em texto plano, convertida por cast hashed do Model.
     * @param array<int, string> $roles Perfis de acesso do usuário.
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @example
     * $repository->create('Cliente', 'cliente@example.com', 'password123', ['customer']);
     *
     * @author David Marques
     */
    public function create(string $name, string $email, string $password, array $roles): array
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'roles' => $roles,
        ]);

        $userRolesRaw = $user->getAttribute('roles');
        $userRoles = is_array($userRolesRaw) ? array_values(array_map('strval', $userRolesRaw)) : [];
        $userId = (int) $user->getAttribute('id');
        $userName = (string) $user->getAttribute('name');
        $userEmail = (string) $user->getAttribute('email');

        return [
            'id' => $userId,
            'name' => $userName,
            'email' => $userEmail,
            'roles' => $userRoles,
        ];
    }
}
