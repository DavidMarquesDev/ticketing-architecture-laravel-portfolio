<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Modules\Ticketing\Application\Ports\Out\UserReadRepositoryPort;
use Traversable;

final class AuthenticatedUserReadRepository implements UserReadRepositoryPort
{
    public function existsById(int $userId): bool
    {
        $user = $this->authenticatedUser();

        if ($user === null) {
            return false;
        }

        return $this->extractUserId($user) === $userId;
    }

    public function hasAnyRole(int $userId, array $roles): bool
    {
        if (!$this->existsById($userId)) {
            return false;
        }

        $user = $this->authenticatedUser();

        if ($user === null) {
            return false;
        }

        $userRoles = array_map('strtolower', $this->extractUserRoles($user));
        $allowedRoles = array_map('strtolower', $roles);

        return array_intersect($userRoles, $allowedRoles) !== [];
    }

    private function authenticatedUser(): object|array|null
    {
        if (!function_exists('auth')) {
            return null;
        }

        $auth = call_user_func('auth');

        if (!is_object($auth) || !method_exists($auth, 'user')) {
            return null;
        }

        $user = $auth->user();

        if (is_object($user) || is_array($user)) {
            return $user;
        }

        return null;
    }

    private function extractUserId(object|array $user): int
    {
        if (is_array($user)) {
            return (int) ($user['id'] ?? 0);
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            return (int) $user->getAuthIdentifier();
        }

        if (method_exists($user, 'getKey')) {
            return (int) $user->getKey();
        }

        if (property_exists($user, 'id')) {
            return (int) $user->id;
        }

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function extractUserRoles(object|array $user): array
    {
        if (is_array($user)) {
            return $this->normalizeRoles($user['roles'] ?? []);
        }

        if (method_exists($user, 'roles')) {
            return $this->normalizeRoles($user->roles());
        }

        if (method_exists($user, 'getRoleNames')) {
            return $this->normalizeRoles($user->getRoleNames());
        }

        if (property_exists($user, 'roles')) {
            return $this->normalizeRoles($user->roles);
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (!is_array($roles) && !$roles instanceof Traversable) {
            return [];
        }

        $normalized = [];

        foreach ($roles as $role) {
            if (is_string($role) && trim($role) !== '') {
                $normalized[] = trim($role);
                continue;
            }

            if (is_object($role) && method_exists($role, '__toString')) {
                $normalized[] = (string) $role;
                continue;
            }

            if (is_object($role) && property_exists($role, 'name')) {
                $normalized[] = (string) $role->name;
            }
        }

        return array_values(array_unique($normalized));
    }
}
