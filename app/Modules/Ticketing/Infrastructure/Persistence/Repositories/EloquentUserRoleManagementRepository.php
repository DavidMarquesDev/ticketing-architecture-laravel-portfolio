<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Models\User;
use App\Modules\Ticketing\Application\Ports\Out\UserRoleManagementRepositoryPort;
use RuntimeException;

/**
 * Repositório Eloquent para gestão de papéis de usuário.
 *
 * @author David Marques
 */
final class EloquentUserRoleManagementRepository implements UserRoleManagementRepositoryPort
{
    /**
     * @param int $userId
     * @param string $role
     * @return bool
     *
     * @author David Marques
     */
    public function hasRole(int $userId, string $role): bool
    {
        $user = User::query()
            ->select(['id', 'roles'])
            ->find($userId);

        if ($user === null) {
            return false;
        }

        $roles = $this->normalizeRoles($user->getAttribute('roles'));

        return in_array(strtolower($role), array_map('strtolower', $roles), true);
    }

    /**
     * @param int $userId
     * @return bool
     *
     * @author David Marques
     */
    public function existsById(int $userId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->exists();
    }

    /**
     * @param int $userId
     * @param array<int, string> $roles
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @author David Marques
     */
    public function updateRoles(int $userId, array $roles): array
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'roles'])
            ->find($userId);

        if ($user === null) {
            throw new RuntimeException('Usuário alvo não encontrado.');
        }

        $user->setAttribute('roles', $this->normalizeRoles($roles));
        $user->save();

        return [
            'id' => (int) $user->getAttribute('id'),
            'name' => (string) $user->getAttribute('name'),
            'email' => (string) $user->getAttribute('email'),
            'roles' => $this->normalizeRoles($user->getAttribute('roles')),
        ];
    }

    /**
     * @param mixed $roles
     * @return array<int, string>
     *
     * @author David Marques
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (!is_array($roles)) {
            return [];
        }

        $normalized = [];

        foreach ($roles as $role) {
            if (!is_string($role)) {
                continue;
            }

            $trimmed = trim($role);

            if ($trimmed === '') {
                continue;
            }

            $normalized[] = strtolower($trimmed);
        }

        return array_values(array_unique($normalized));
    }
}
