<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Policies;

use Traversable;

final class TicketPolicy
{
    public function assign(mixed $user): bool
    {
        return $this->hasAnyRole($user, ['admin', 'agent']);
    }

    public function close(mixed $user): bool
    {
        return $this->hasAnyRole($user, ['admin', 'agent']);
    }

    public function reply(mixed $user): bool
    {
        return $this->extractUserId($user) > 0;
    }

    /**
     * @param array<int, string> $allowedRoles
     */
    private function hasAnyRole(mixed $user, array $allowedRoles): bool
    {
        $roles = array_map('strtolower', $this->extractRoles($user));

        foreach ($allowedRoles as $role) {
            if (in_array(strtolower($role), $roles, true)) {
                return true;
            }
        }

        return false;
    }

    private function extractUserId(mixed $user): int
    {
        if (is_array($user)) {
            return (int) ($user['id'] ?? 0);
        }

        if (!is_object($user)) {
            return 0;
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
    private function extractRoles(mixed $user): array
    {
        if (is_array($user)) {
            return $this->normalizeRoles($user['roles'] ?? []);
        }

        if (!is_object($user)) {
            return [];
        }

        if (method_exists($user, 'roles')) {
            return $this->normalizeRoles($user->roles());
        }

        if (method_exists($user, 'getRoleNames')) {
            return $this->normalizeRoles($user->getRoleNames());
        }

        if (method_exists($user, 'getAttribute')) {
            return $this->normalizeRoles($user->getAttribute('roles'));
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

            if (is_object($role) && property_exists($role, 'name')) {
                $normalized[] = (string) $role->name;
                continue;
            }

            if (is_object($role) && method_exists($role, '__toString')) {
                $normalized[] = (string) $role;
            }
        }

        return array_values(array_unique($normalized));
    }
}
