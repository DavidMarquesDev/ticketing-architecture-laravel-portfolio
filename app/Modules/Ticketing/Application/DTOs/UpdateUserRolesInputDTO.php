<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

/**
 * DTO de entrada para atualização de papéis de um usuário.
 *
 * @author David Marques
 */
final readonly class UpdateUserRolesInputDTO
{
    /**
     * @param int $actorUserId Identificador do usuário autenticado executor.
     * @param int $targetUserId Identificador do usuário alvo da atualização.
     * @param array<int, string> $roles Papéis finais do usuário alvo.
     *
     * @author David Marques
     */
    public function __construct(
        public int $actorUserId,
        public int $targetUserId,
        public array $roles
    ) {
    }
}
