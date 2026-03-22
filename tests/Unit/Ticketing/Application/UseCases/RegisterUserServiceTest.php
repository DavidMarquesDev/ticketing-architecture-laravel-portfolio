<?php

declare(strict_types=1);

use App\Modules\Ticketing\Application\DTOs\RegisterUserInputDTO;
use App\Modules\Ticketing\Application\Ports\Out\UserRegistrationRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\RegisterUserService;

require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/DTOs/RegisterUserInputDTO.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/Ports/In/RegisterUserUseCase.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/Ports/Out/UserRegistrationRepositoryPort.php';
require_once __DIR__ . '/../../../../../app/Modules/Ticketing/Application/UseCases/RegisterUserService.php';

$tests = [
    'register_user_service_creates_customer_user_when_email_is_available' => static function (): void {
        $repository = new FakeUserRegistrationRepository(false);
        $service = new RegisterUserService($repository);

        $result = $service->execute(
            new RegisterUserInputDTO(
                name: 'Cliente Novo',
                email: 'novo@example.com',
                password: 'password123'
            )
        );

        assertSame('Cliente Novo', $repository->capturedName, 'Cadastro deve encaminhar nome para repositório.');
        assertSame('novo@example.com', $repository->capturedEmail, 'Cadastro deve encaminhar email para repositório.');
        assertSame('password123', $repository->capturedPassword, 'Cadastro deve encaminhar senha para repositório.');
        assertSame(['customer'], $repository->capturedRoles, 'Cadastro deve definir role padrão customer.');
        assertSame(101, $result['id'], 'Cadastro deve retornar usuário criado.');
    },
    'register_user_service_throws_domain_exception_when_email_already_exists' => static function (): void {
        $service = new RegisterUserService(new FakeUserRegistrationRepository(true));

        assertThrows(
            static fn (): array => $service->execute(
                new RegisterUserInputDTO('Cliente Existente', 'existente@example.com', 'password123')
            ),
            DomainException::class,
            'Cadastro deve lançar DomainException para e-mail já cadastrado.'
        );
    },
];

final class FakeUserRegistrationRepository implements UserRegistrationRepositoryPort
{
    public string $capturedName = '';

    public string $capturedEmail = '';

    public string $capturedPassword = '';

    public array $capturedRoles = [];

    /**
     * @param bool $emailAlreadyExists
     *
     * @author David Marques
     */
    public function __construct(
        private readonly bool $emailAlreadyExists
    ) {
    }

    /**
     * @param string $email
     * @return bool
     *
     * @author David Marques
     */
    public function existsByEmail(string $email): bool
    {
        return $this->emailAlreadyExists;
    }

    /**
     * @param string $name
     * @param string $email
     * @param string $password
     * @param array<int, string> $roles
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @author David Marques
     */
    public function create(string $name, string $email, string $password, array $roles): array
    {
        $this->capturedName = $name;
        $this->capturedEmail = $email;
        $this->capturedPassword = $password;
        $this->capturedRoles = $roles;

        return [
            'id' => 101,
            'name' => $name,
            'email' => $email,
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
