<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\UseCases;

use App\Modules\Ticketing\Application\DTOs\LoginInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AuthenticateUserUseCase;
use App\Modules\Ticketing\Application\Ports\Out\UserAuthenticationRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\UserTokenIssuerPort;
use DomainException;

final class AuthenticateUserService implements AuthenticateUserUseCase
{
    public function __construct(
        private readonly UserAuthenticationRepositoryPort $userAuthenticationRepository,
        private readonly UserTokenIssuerPort $userTokenIssuer
    ) {
    }

    public function execute(LoginInputDTO $input): array
    {
        $user = $this->userAuthenticationRepository->findByEmail($input->email);

        if ($user === null || !password_verify($input->password, $user['password_hash'])) {
            throw new DomainException('Credenciais inválidas.');
        }

        $token = $this->userTokenIssuer->issue($user['id'], 'manual-test-token');

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'roles' => $user['roles'],
            ],
        ];
    }
}
