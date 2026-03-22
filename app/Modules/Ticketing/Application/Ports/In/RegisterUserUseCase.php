<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\RegisterUserInputDTO;

/**
 * Contrato da aplicação para cadastro de usuários.
 *
 * Define o caso de uso responsável por registrar novos usuários
 * e retornar os dados normalizados para a camada de interface.
 *
 * @author David Marques
 */
interface RegisterUserUseCase
{
    /**
     * Executa o fluxo de cadastro de usuário.
     *
     * ## 📥 Entrada
     * - Dados validados de nome, e-mail e senha.
     *
     * ## 📤 Saída
     * - Estrutura normalizada com id, nome, e-mail e perfis.
     *
     * @param RegisterUserInputDTO $input Dados de cadastro já validados.
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @throws \DomainException Quando o e-mail já existir.
     *
     * @example
     * $result = $useCase->execute(new RegisterUserInputDTO('Nome', 'email@example.com', 'password123'));
     *
     * @author David Marques
     */
    public function execute(RegisterUserInputDTO $input): array;
}
