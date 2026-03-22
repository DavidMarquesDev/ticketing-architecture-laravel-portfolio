<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface UserReadRepositoryPort
{
    public function existsById(int $userId): bool;

    /**
     * @param array<int, string> $roles
     */
    public function hasAnyRole(int $userId, array $roles): bool;
}
