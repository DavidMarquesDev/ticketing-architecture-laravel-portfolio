<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\DTOs\LoginInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AuthenticateUserUseCase;
use App\Modules\Ticketing\Interface\Http\Requests\LoginRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

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
     * @return array<string, mixed>|JsonResponse
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
    public function login(LoginRequest $request): array|JsonResponse
    {
        $traceId = bin2hex(random_bytes(8));
        $payload = method_exists($request, 'validated') ? $request->validated() : [];

        try {
            $authenticated = $this->authenticateUserUseCase->execute(
                new LoginInputDTO(
                    email: (string) ($payload['email'] ?? ''),
                    password: (string) ($payload['password'] ?? '')
                )
            );
        } catch (DomainException $exception) {
            return $this->responsePayload([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => $exception->getMessage(),
                    'details' => [],
                    'trace_id' => $traceId,
                ],
            ], 401);
        }

        return $this->responsePayload([
            'data' => $authenticated,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|JsonResponse
     */
    private function responsePayload(array $payload, int $status = 200): array|JsonResponse
    {
        if (function_exists('response') && class_exists(JsonResponse::class)) {
            return response()->json($payload, $status);
        }

        http_response_code($status);

        return $payload;
    }
}
