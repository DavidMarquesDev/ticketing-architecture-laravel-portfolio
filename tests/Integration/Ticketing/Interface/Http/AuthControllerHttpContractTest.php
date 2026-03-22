<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Http {

    if (!class_exists(FormRequest::class)) {
        class FormRequest
        {
            /**
             * @return array<string, mixed>
             */
            public function validated(): array
            {
                $payload = $GLOBALS['ticketing_auth_payload'] ?? [];

                return is_array($payload) ? $payload : [];
            }
        }
    }
}

namespace App\Modules\Ticketing\Interface\Http\Controllers {

    if (!function_exists(__NAMESPACE__ . '\http_response_code')) {
        function http_response_code(?int $code = null): int|bool
        {
            if ($code !== null) {
                $GLOBALS['ticketing_http_status_code'] = $code;

                return true;
            }

            return (int) ($GLOBALS['ticketing_http_status_code'] ?? 200);
        }
    }
}

namespace {

    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $baseDir = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    });

    use App\Modules\Ticketing\Application\DTOs\LoginInputDTO;
    use App\Modules\Ticketing\Application\Ports\In\AuthenticateUserUseCase;
    use App\Modules\Ticketing\Interface\Http\Controllers\AuthController;
    use App\Modules\Ticketing\Interface\Http\Requests\LoginRequest;

    $tests = [
        'auth_controller_login_returns_data_for_valid_credentials' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_auth_payload'] = [
                'email' => 'admin@example.com',
                'password' => 'password123',
            ];

            $controller = new AuthController(
                new class implements AuthenticateUserUseCase {
                    /**
                     * @return array<string, mixed>
                     */
                    public function execute(LoginInputDTO $input): array
                    {
                        $GLOBALS['captured_login_input'] = $input;

                        return [
                            'token' => 'token-1',
                            'token_type' => 'Bearer',
                            'user' => [
                                'id' => 1,
                                'name' => 'Admin',
                                'email' => 'admin@example.com',
                                'roles' => ['admin'],
                            ],
                        ];
                    }
                }
            );

            $response = $controller->login(new LoginRequest());
            $captured = $GLOBALS['captured_login_input'];

            assertSame('admin@example.com', $captured->email, 'Login deve mapear email para DTO.');
            assertSame('password123', $captured->password, 'Login deve mapear password para DTO.');
            assertSame('token-1', $response['data']['token'] ?? null, 'Login deve retornar token no contrato.');
        },
        'auth_controller_login_returns_401_for_invalid_credentials_with_trace_id' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_auth_payload'] = [
                'email' => 'admin@example.com',
                'password' => 'senha-invalida',
            ];

            $controller = new AuthController(
                new class implements AuthenticateUserUseCase {
                    /**
                     * @return array<string, mixed>
                     */
                    public function execute(LoginInputDTO $input): array
                    {
                        throw new \DomainException('Credenciais inválidas.');
                    }
                }
            );

            $response = $controller->login(new LoginRequest());

            assertSame(401, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Login inválido deve responder 401.');
            assertSame('UNAUTHENTICATED', $response['error']['code'] ?? null, 'Login inválido deve retornar código padronizado.');
            assertSame(16, strlen((string) ($response['error']['trace_id'] ?? '')), 'Login inválido deve retornar trace_id.');
        },
    ];

    function resetHttpContext(): void
    {
        unset($GLOBALS['ticketing_http_status_code'], $GLOBALS['ticketing_auth_payload'], $GLOBALS['captured_login_input']);
    }

    function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
            );
        }
    }

    function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    $failures = [];

    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "PASS {$name}" . PHP_EOL;
        } catch (\Throwable $throwable) {
            $failures[] = sprintf('FAIL %s: %s', $name, $throwable->getMessage());
        }
    }

    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }

    exit(count($failures) === 0 ? 0 : 1);
}
