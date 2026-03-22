<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Persistence\Repositories;

use App\Models\User;
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

    /**
     * @return object|array<string, mixed>|null
     */
    private function authenticatedUser(): object|array|null
    {
        if (function_exists('request')) {
            $request = request();

            if (is_object($request) && method_exists($request, 'user')) {
                /** @var mixed $requestUser */
                $requestUser = $request->user();

                if (is_object($requestUser) || is_array($requestUser)) {
                    return $requestUser;
                }
            }
        }

        if (function_exists('auth')) {
            $sanctumAuth = call_user_func('auth', 'sanctum');

            if (is_object($sanctumAuth) && method_exists($sanctumAuth, 'user')) {
                /** @var mixed $sanctumUser */
                $sanctumUser = $sanctumAuth->user();

                if (is_object($sanctumUser) || is_array($sanctumUser)) {
                    return $sanctumUser;
                }
            }

            $defaultAuth = call_user_func('auth');

            if (is_object($defaultAuth) && method_exists($defaultAuth, 'user')) {
                /** @var mixed $defaultUser */
                $defaultUser = $defaultAuth->user();

                if (is_object($defaultUser) || is_array($defaultUser)) {
                    return $defaultUser;
                }
            }
        }

        if (array_key_exists('ticketing_authenticated_user', $GLOBALS)) {
            $fallbackUser = $GLOBALS['ticketing_authenticated_user'];

            if (is_object($fallbackUser) || is_array($fallbackUser)) {
                return $fallbackUser;
            }
        }

        return null;
    }

    /**
     * @param object|array<string, mixed> $user
     */
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
     * @param object|array<string, mixed> $user
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

        if (method_exists($user, 'getAttribute')) {
            return $this->normalizeRoles($user->getAttribute('roles'));
        }

        if (method_exists($user, 'toArray')) {
            $attributes = $user->toArray();

            if (is_array($attributes)) {
                return $this->normalizeRoles($attributes['roles'] ?? []);
            }
        }

        if (property_exists($user, 'roles')) {
            return $this->normalizeRoles($user->roles);
        }

        $userId = $this->extractUserId($user);

        if ($userId > 0 && class_exists(User::class)) {
            try {
                $persistedUser = User::query()->find($userId);

                if ($persistedUser !== null) {
                    return $this->normalizeRoles($persistedUser->getAttribute('roles'));
                }
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            $trimmed = trim($roles);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return $this->normalizeRoles($decoded);
            }

            return [$trimmed];
        }

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
