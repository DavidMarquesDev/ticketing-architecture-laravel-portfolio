<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

/**
 * Contrato de persistência para gestão de papéis de usuário.
 *
 * @author David Marques
 */
interface UserRoleManagementRepositoryPort
{
    /**
     * @param int $userId
     * @param string $role
     * @return bool
     *
     * @author David Marques
     */
    public function hasRole(int $userId, string $role): bool;

    /**
     * @param int $userId
     * @return bool
     *
     * @author David Marques
     */
    public function existsById(int $userId): bool;

    /**
     * @param int $userId
     * @param array<int, string> $roles
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @author David Marques
     */
    public function updateRoles(int $userId, array $roles): array;
}
