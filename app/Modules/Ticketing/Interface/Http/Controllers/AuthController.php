<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\DTOs\LoginInputDTO;
use App\Modules\Ticketing\Application\DTOs\RegisterUserInputDTO;
use App\Modules\Ticketing\Application\Ports\In\AuthenticateUserUseCase;
use App\Modules\Ticketing\Application\Ports\In\RegisterUserUseCase;
use App\Modules\Ticketing\Interface\Http\Requests\LoginRequest;
use App\Modules\Ticketing\Interface\Http\Requests\RegisterRequest;
use DomainException;
use Illuminate\Http\JsonResponse;

/**
 * Controller responsável por autenticação para testes manuais da API.
 *
 * Coordena endpoints públicos de login e cadastro, delegando regras
 * de negócio aos casos de uso da camada de aplicação.
 *
 * @tags Autenticação
 *
 * @author David Marques
 */
final class AuthController
{
    /**
     * Construtor do controller de autenticação.
     *
     * @param AuthenticateUserUseCase $authenticateUserUseCase Caso de uso de autenticação.
     * @param RegisterUserUseCase $registerUserUseCase Caso de uso de cadastro de usuário.
     *
     * @author David Marques
     */
    public function __construct(
        private readonly AuthenticateUserUseCase $authenticateUserUseCase,
        private readonly RegisterUserUseCase $registerUserUseCase
    ) {
    }

    /**
     * Realiza autenticação e retorna token de acesso.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Endpoint público sem token.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` em sucesso com token e dados do usuário.
     * - `error` em falha com `code`, `message`, `details` e `trace_id`.
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
     *
     * @example Response 401
     * {
     *   "error": {
     *     "code": "UNAUTHENTICATED",
     *     "message": "Credenciais inválidas.",
     *     "details": [],
     *     "trace_id": "abc123def4567890"
     *   }
     * }
     *
     * @author David Marques
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
     * Realiza cadastro público de usuário e retorna dados normalizados.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Endpoint público sem token.
     *
     * ## 📋 Estrutura da Resposta
     * - `data` em sucesso com usuário criado.
     * - `error` em conflito de e-mail com `trace_id`.
     *
     * @param RegisterRequest $request
     * @return array<string, mixed>|JsonResponse
     * @throws DomainException
     *
     * @example Request
     * POST /api/auth/register
     * {
     *   "name": "Novo Cliente",
     *   "email": "novo.cliente@example.com",
     *   "password": "password123"
     * }
     *
     * @example Response 201
     * {
     *   "data": {
     *     "id": 10,
     *     "name": "Novo Cliente",
     *     "email": "novo.cliente@example.com",
     *     "roles": ["customer"]
     *   }
     * }
     *
     * @example Response 409
     * {
     *   "error": {
     *     "code": "USER_ALREADY_EXISTS",
     *     "message": "E-mail já cadastrado.",
     *     "details": [],
     *     "trace_id": "abc123def4567890"
     *   }
     * }
     *
     * @author David Marques
     */
    public function register(RegisterRequest $request): array|JsonResponse
    {
        $traceId = bin2hex(random_bytes(8));
        $payload = method_exists($request, 'validated') ? $request->validated() : [];

        try {
            $created = $this->registerUserUseCase->execute(
                new RegisterUserInputDTO(
                    name: (string) ($payload['name'] ?? ''),
                    email: (string) ($payload['email'] ?? ''),
                    password: (string) ($payload['password'] ?? '')
                )
            );
        } catch (DomainException $exception) {
            return $this->responsePayload([
                'error' => [
                    'code' => 'USER_ALREADY_EXISTS',
                    'message' => $exception->getMessage(),
                    'details' => [],
                    'trace_id' => $traceId,
                ],
            ], 409);
        }

        return $this->responsePayload([
            'data' => $created,
        ], 201);
    }

    /**
     * Normaliza retorno para array em testes e JsonResponse em runtime.
     *
     * @param array<string, mixed> $payload
     * @param int $status Código HTTP da resposta.
     * @return array<string, mixed>|JsonResponse
     *
     * @author David Marques
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
