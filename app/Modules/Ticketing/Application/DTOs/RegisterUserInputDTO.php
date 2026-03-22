<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

/**
 * DTO de entrada para cadastro de usuário.
 *
 * Centraliza os dados já validados na camada HTTP para o caso de uso
 * de registro, mantendo tipagem explícita entre camadas.
 *
 * @example
 * $input = new RegisterUserInputDTO(
 *     name: 'Novo Cliente',
 *     email: 'novo.cliente@example.com',
 *     password: 'password123'
 * );
 *
 * @author David Marques
 */
final class RegisterUserInputDTO
{
    /**
     * Constrói o DTO de cadastro com os dados necessários para o serviço.
     *
     * @param string $name Nome completo do usuário.
     * @param string $email E-mail do usuário.
     * @param string $password Senha em texto plano validada na camada HTTP.
     *
     * @example
     * $dto = new RegisterUserInputDTO('Cliente', 'cliente@example.com', 'password123');
     *
     * @author David Marques
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ) {
    }
}
