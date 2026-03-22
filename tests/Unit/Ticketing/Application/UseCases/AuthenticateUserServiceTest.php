<?php

declare(strict_types=1);

use App\Modules\Ticketing\Application\DTOs\LoginInputDTO;
use App\Modules\Ticketing\Application\Ports\Out\UserAuthenticationRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\UserTokenIssuerPort;
use App\Modules\Ticketing\Application\UseCases\AuthenticateUserService;

require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/DTOs/LoginInputDTO.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/Ports/In/AuthenticateUserUseCase.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/Ports/Out/UserAuthenticationRepositoryPort.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/Ports/Out/UserTokenIssuerPort.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/UseCases/AuthenticateUserService.php';

$tests = [
    'authenticate_user_service_returns_token_for_valid_credentials' => static function (): void {
        $service = new AuthenticateUserService(
            new InMemoryUserAuthRepository(
                [
                    [
                        'id' => 10,
                        'name' => 'Admin',
                        'email' => 'admin@example.com',
                        'roles' => ['admin'],
                        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    ],
                ]
            ),
            new FakeUserTokenIssuer()
        );

        $result = $service->execute(new LoginInputDTO('admin@example.com', 'password123'));

        assertSame('fake-token-10', $result['token'], 'Deve gerar token para usuário válido.');
        assertSame('Bearer', $result['token_type'], 'Deve retornar tipo Bearer.');
        assertSame(10, $result['user']['id'], 'Deve retornar usuário autenticado.');
    },
    'authenticate_user_service_throws_for_invalid_credentials' => static function (): void {
        $service = new AuthenticateUserService(
            new InMemoryUserAuthRepository(
                [
                    [
                        'id' => 11,
                        'name' => 'Agent',
                        'email' => 'agent@example.com',
                        'roles' => ['agent'],
                        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    ],
                ]
            ),
            new FakeUserTokenIssuer()
        );

        assertThrows(
            static fn (): array => $service->execute(new LoginInputDTO('agent@example.com', 'senha-errada')),
            DomainException::class,
            'Deve lançar DomainException para credenciais inválidas.'
        );
    },
];

final class InMemoryUserAuthRepository implements UserAuthenticationRepositoryPort
{
    /**
     * @param array<int, array{id:int,name:string,email:string,roles:array<int,string>,password_hash:string}> $users
     */
    public function __construct(private readonly array $users)
    {
    }

    public function findByEmail(string $email): ?array
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }

        return null;
    }
}

final class FakeUserTokenIssuer implements UserTokenIssuerPort
{
    public function issue(int $userId, string $tokenName): string
    {
        return sprintf('fake-token-%d', $userId);
    }
}

/**
 * @throws RuntimeException
 */
function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
        );
    }
}

/**
 * @throws RuntimeException
 */
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
