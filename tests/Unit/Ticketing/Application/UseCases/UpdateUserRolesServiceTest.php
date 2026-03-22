<?php

declare(strict_types=1);

use App\Modules\Ticketing\Application\DTOs\UpdateUserRolesInputDTO;
use App\Modules\Ticketing\Application\Ports\Out\UserRoleManagementRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\UpdateUserRolesService;

require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/DTOs/UpdateUserRolesInputDTO.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/Ports/In/UpdateUserRolesUseCase.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/Ports/Out/UserRoleManagementRepositoryPort.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/UseCases/UpdateUserRolesService.php';

$tests = [
    'update_user_roles_service_updates_roles_when_actor_is_admin' => static function (): void {
        $repository = new FakeUserRoleManagementRepository(actorIsAdmin: true, targetExists: true);
        $service = new UpdateUserRolesService($repository);

        $result = $service->execute(
            new UpdateUserRolesInputDTO(
                actorUserId: 1,
                targetUserId: 10,
                roles: ['agent']
            )
        );

        assertSame(10, $repository->capturedTargetUserId, 'Deve encaminhar usuário alvo para o repositório.');
        assertSame(['agent'], $repository->capturedRoles, 'Deve encaminhar papéis normalizados.');
        assertSame('agent', $result['roles'][0] ?? null, 'Deve retornar os papéis atualizados.');
    },
    'update_user_roles_service_throws_domain_exception_when_actor_is_not_admin' => static function (): void {
        $repository = new FakeUserRoleManagementRepository(actorIsAdmin: false, targetExists: true);
        $service = new UpdateUserRolesService($repository);

        assertThrows(
            static fn (): array => $service->execute(
                new UpdateUserRolesInputDTO(
                    actorUserId: 2,
                    targetUserId: 10,
                    roles: ['agent']
                )
            ),
            DomainException::class,
            'Deve lançar DomainException quando usuário autenticado não for admin.'
        );
    },
    'update_user_roles_service_throws_runtime_exception_when_target_does_not_exist' => static function (): void {
        $repository = new FakeUserRoleManagementRepository(actorIsAdmin: true, targetExists: false);
        $service = new UpdateUserRolesService($repository);

        assertThrows(
            static fn (): array => $service->execute(
                new UpdateUserRolesInputDTO(
                    actorUserId: 1,
                    targetUserId: 999,
                    roles: ['customer']
                )
            ),
            RuntimeException::class,
            'Deve lançar RuntimeException quando usuário alvo não existir.'
        );
    },
];

final class FakeUserRoleManagementRepository implements UserRoleManagementRepositoryPort
{
    public int $capturedTargetUserId = 0;

    public array $capturedRoles = [];

    public function __construct(
        private readonly bool $actorIsAdmin,
        private readonly bool $targetExists
    ) {
    }

    public function hasRole(int $userId, string $role): bool
    {
        return $this->actorIsAdmin && $userId > 0 && $role === 'admin';
    }

    public function existsById(int $userId): bool
    {
        return $this->targetExists && $userId > 0;
    }

    public function updateRoles(int $userId, array $roles): array
    {
        $this->capturedTargetUserId = $userId;
        $this->capturedRoles = $roles;

        return [
            'id' => $userId,
            'name' => 'Usuário Alvo',
            'email' => 'alvo@example.com',
            'roles' => $roles,
        ];
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
        );
    }
}

function assertThrows(callable $callback, string $expectedClass, string $message): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $expectedClass) {
            return;
        }

        throw new RuntimeException(
            sprintf('%s Classe recebida: %s', $message, $throwable::class)
        );
    }

    throw new RuntimeException($message);
}

function formatValue(mixed $value): string
{
    if (is_scalar($value) || $value === null) {
        return var_export($value, true);
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[valor não serializável]';
}

$failures = [];

foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS {$name}" . PHP_EOL;
    } catch (Throwable $throwable) {
        $failures[] = sprintf('FAIL %s: %s', $name, $throwable->getMessage());
    }
}

foreach ($failures as $failure) {
    echo $failure . PHP_EOL;
}

exit(count($failures) === 0 ? 0 : 1);
