<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Controllers;

use App\Modules\Ticketing\Application\DTOs\UpdateUserRolesInputDTO;
use App\Modules\Ticketing\Application\Ports\In\UpdateUserRolesUseCase;
use App\Modules\Ticketing\Interface\Http\Requests\UpdateUserRolesRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Controller responsável por atualização de papéis de usuários.
 *
 * @tags Usuários
 *
 * @author David Marques
 */
final class UserRoleController
{
    /**
     * @param UpdateUserRolesUseCase $updateUserRolesUseCase
     *
     * @author David Marques
     */
    public function __construct(
        private readonly UpdateUserRolesUseCase $updateUserRolesUseCase
    ) {
    }

    /**
     * Atualiza papéis de um usuário alvo.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Requer autenticação via Sanctum.
     * - ✅ Requer permissão de administrador.
     *
     * @param UpdateUserRolesRequest $request
     * @param int $userId
     * @return array<string, mixed>|JsonResponse
     *
     * @example Request
     * PATCH /api/users/10/roles
     * {
     *   "roles": ["agent"]
     * }
     *
     * @author David Marques
     */
    public function update(UpdateUserRolesRequest $request, int $userId): array|JsonResponse
    {
        $traceId = bin2hex(random_bytes(8));
        $authenticatedUserId = $this->authenticatedUserId($request);

        if ($authenticatedUserId <= 0) {
            return $this->errorResponse('UNAUTHENTICATED', 'Usuário não autenticado.', 401, $traceId);
        }

        if (!$this->authorizeAbility($request, 'user.roles.update')) {
            return $this->errorResponse('FORBIDDEN', 'Usuário sem permissão para atualizar papéis.', 403, $traceId);
        }

        $payload = method_exists($request, 'validated') ? $request->validated() : [];
        $roles = is_array($payload['roles'] ?? null) ? array_values(array_map('strval', $payload['roles'])) : [];

        try {
            $updated = $this->updateUserRolesUseCase->execute(
                new UpdateUserRolesInputDTO(
                    actorUserId: $authenticatedUserId,
                    targetUserId: $userId,
                    roles: $roles
                )
            );
        } catch (DomainException $exception) {
            return $this->errorResponse('FORBIDDEN', $exception->getMessage(), 403, $traceId);
        } catch (RuntimeException $exception) {
            return $this->errorResponse('USER_NOT_FOUND', $exception->getMessage(), 404, $traceId);
        }

        return $this->responsePayload([
            'data' => $updated,
        ]);
    }

    /**
     * @param object $request
     * @return int
     *
     * @author David Marques
     */
    private function authenticatedUserId(object $request): int
    {
        if (!method_exists($request, 'user')) {
            return 0;
        }

        $user = $request->user();

        if ($user === null) {
            return 0;
        }

        if (is_array($user)) {
            return (int) ($user['id'] ?? 0);
        }

        if (method_exists($user, 'getAuthIdentifier')) {
            return (int) $user->getAuthIdentifier();
        }

        if (property_exists($user, 'id')) {
            return (int) $user->id;
        }

        return 0;
    }

    /**
     * @param object $request
     * @param string $ability
     * @return bool
     *
     * @author David Marques
     */
    private function authorizeAbility(object $request, string $ability): bool
    {
        if (!method_exists($request, 'user')) {
            return false;
        }

        $user = $request->user();

        if ($user === null) {
            return false;
        }

        $gateFacade = '\Illuminate\Support\Facades\Gate';

        if (!class_exists($gateFacade)) {
            return false;
        }

        try {
            $gate = $gateFacade::forUser($user);
        } catch (\Throwable) {
            return false;
        }

        if (!is_object($gate) || !method_exists($gate, 'allows')) {
            return false;
        }

        return (bool) $gate->allows($ability);
    }

    /**
     * @param array<string, mixed> $payload
     * @param int $status
     * @return array<string, mixed>|JsonResponse
     *
     * @author David Marques
     */
    private function responsePayload(array $payload, int $status = 200): array|JsonResponse
    {
        if (class_exists(JsonResponse::class) && function_exists('response')) {
            return response()->json($payload, $status);
        }

        if (function_exists('http_response_code')) {
            http_response_code($status);
        }

        return $payload;
    }

    /**
     * @param string $code
     * @param string $message
     * @param int $status
     * @param string $traceId
     * @return array<string, mixed>|JsonResponse
     *
     * @author David Marques
     */
    private function errorResponse(string $code, string $message, int $status, string $traceId): array|JsonResponse
    {
        return $this->responsePayload([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'trace_id' => $traceId,
            ],
        ], $status);
    }
}
