<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\UpdateUserRolesInputDTO;

/**
 * Contrato de caso de uso para atualização de papéis de usuário.
 *
 * @author David Marques
 */
interface UpdateUserRolesUseCase
{
    /**
     * @param UpdateUserRolesInputDTO $input Dados do executor, alvo e papéis.
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @throws \DomainException Quando o executor não tiver permissão.
     * @throws \RuntimeException Quando o usuário alvo não existir.
     *
     * @author David Marques
     */
    public function execute(UpdateUserRolesInputDTO $input): array;
}
