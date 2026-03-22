<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\DTOs\LoginInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AuthenticateUserUseCase;
use App\Modules\Ticketing\Interface\Http\Requests\LoginRequest;
use DomainException;

/**
 * Controller responsável por autenticação para testes manuais da API.
 *
 * @author David Marques
 */
final class AuthController
{
    public function __construct(
        private readonly AuthenticateUserUseCase $authenticateUserUseCase
    ) {
    }

    /**
     * Realiza autenticação e retorna token de acesso.
     *
     * @param LoginRequest $request
     * @return array<string, mixed>
     * @throws DomainException
     *
     * @example Request
     * POST /api/auth/login
     * {
     *   "email": "admin@example.com",
     *   "password": "password123"
     * }
     *
     * @example Response 200
     * {
     *   "data": {
     *     "token": "1|token...",
     *     "token_type": "Bearer",
     *     "user": {
     *       "id": 1,
     *       "name": "Admin",
     *       "email": "admin@example.com",
     *       "roles": ["admin"]
     *     }
     *   }
     * }
     */
    public function login(LoginRequest $request): array
    {
        $payload = method_exists($request, 'validated') ? $request->validated() : [];

        try {
            $authenticated = $this->authenticateUserUseCase->execute(
                new LoginInputDTO(
                    email: (string) ($payload['email'] ?? ''),
                    password: (string) ($payload['password'] ?? '')
                )
            );
        } catch (DomainException $exception) {
            http_response_code(401);

            return [
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => $exception->getMessage(),
                    'details' => [],
                ],
            ];
        }

        return [
            'data' => $authenticated,
        ];
    }
}
