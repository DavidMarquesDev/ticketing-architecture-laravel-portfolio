<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\RegisterUserInputDTO;
use App\Modules\Ticketing\Application\Ports\In\RegisterUserUseCase;
use App\Modules\Ticketing\Application\Ports\Out\UserRegistrationRepositoryPort;
use DomainException;

/**
 * Serviço de aplicação responsável pelo cadastro de usuários.
 *
 * Centraliza as regras de negócio de registro, incluindo validação
 * de e-mail único e atribuição de perfil padrão.
 *
 * @author David Marques
 */
final class RegisterUserService implements RegisterUserUseCase
{
    private const DEFAULT_ROLE = 'customer';

    /**
     * Constrói o serviço de cadastro com dependência de persistência.
     *
     * @param UserRegistrationRepositoryPort $userRegistrationRepository Porta de escrita de usuário.
     *
     * @author David Marques
     */
    public function __construct(
        private readonly UserRegistrationRepositoryPort $userRegistrationRepository
    ) {
    }

    /**
     * Executa cadastro atribuindo perfil padrão de cliente.
     *
     * ## 🔐 Regras de Negócio
     * - Não permite cadastro com e-mail já existente.
     * - Atribui automaticamente a role `customer`.
     *
     * @param RegisterUserInputDTO $input Dados de cadastro.
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @throws DomainException Quando o e-mail já estiver cadastrado.
     *
     * @example
     * $data = $service->execute(new RegisterUserInputDTO('Nome', 'email@example.com', 'password123'));
     *
     * @author David Marques
     */
    public function execute(RegisterUserInputDTO $input): array
    {
        if ($this->userRegistrationRepository->existsByEmail($input->email)) {
            throw new DomainException('E-mail já cadastrado.');
        }

        return $this->userRegistrationRepository->create(
            name: $input->name,
            email: $input->email,
            password: $input->password,
            roles: [self::DEFAULT_ROLE]
        );
    }
}
