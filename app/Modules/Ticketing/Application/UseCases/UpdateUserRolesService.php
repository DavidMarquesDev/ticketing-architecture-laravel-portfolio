<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\UpdateUserRolesInputDTO;
use App\Modules\Ticketing\Application\Ports\In\UpdateUserRolesUseCase;
use App\Modules\Ticketing\Application\Ports\Out\UserRoleManagementRepositoryPort;
use DomainException;
use RuntimeException;

/**
 * Serviço de aplicação para atualização segura de papéis de usuário.
 *
 * @author David Marques
 */
final class UpdateUserRolesService implements UpdateUserRolesUseCase
{
    private const ADMIN_ROLE = 'admin';

    /**
     * @param UserRoleManagementRepositoryPort $userRoleManagementRepository
     *
     * @author David Marques
     */
    public function __construct(
        private readonly UserRoleManagementRepositoryPort $userRoleManagementRepository
    ) {
    }

    /**
     * @param UpdateUserRolesInputDTO $input
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @throws DomainException
     * @throws RuntimeException
     *
     * @author David Marques
     */
    public function execute(UpdateUserRolesInputDTO $input): array
    {
        if (!$this->userRoleManagementRepository->hasRole($input->actorUserId, self::ADMIN_ROLE)) {
            throw new DomainException('Usuário sem permissão para atualizar papéis.');
        }

        if (!$this->userRoleManagementRepository->existsById($input->targetUserId)) {
            throw new RuntimeException('Usuário alvo não encontrado.');
        }

        return $this->userRoleManagementRepository->updateRoles($input->targetUserId, $input->roles);
    }
}
