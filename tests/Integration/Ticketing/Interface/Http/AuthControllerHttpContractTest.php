<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Http {

    if (!class_exists(FormRequest::class)) {
        /**
         * Stub mínimo de FormRequest para contrato HTTP em teste isolado.
         *
         * @author David Marques
         */
        class FormRequest
        {
            /**
             * Retorna payload validado injetado no contexto global de teste.
             *
             * @return array<string, mixed>
             *
             * @author David Marques
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
        /**
         * Simula função nativa de status HTTP no namespace do controller.
         *
         * @param int|null $code Código de status opcional.
         * @return int|bool
         *
         * @author David Marques
         */
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
    use App\Modules\Ticketing\Application\DTOs\RegisterUserInputDTO;
    use App\Modules\Ticketing\Application\Ports\In\AuthenticateUserUseCase;
    use App\Modules\Ticketing\Application\Ports\In\RegisterUserUseCase;
    use App\Modules\Ticketing\Interface\Http\Controllers\AuthController;
    use App\Modules\Ticketing\Interface\Http\Requests\LoginRequest;
    use App\Modules\Ticketing\Interface\Http\Requests\RegisterRequest;

    $tests = [
        'auth_controller_login_returns_data_for_valid_credentials' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_auth_payload'] = [
                'email' => 'admin@example.com',
                'password' => 'password123',
            ];

            $controller = makeAuthController(
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
                },
                noopRegisterUseCase()
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

            $controller = makeAuthController(
                new class implements AuthenticateUserUseCase {
                    /**
                     * @return array<string, mixed>
                     */
                    public function execute(LoginInputDTO $input): array
                    {
                        throw new \DomainException('Credenciais inválidas.');
                    }
                },
                noopRegisterUseCase()
            );

            $response = $controller->login(new LoginRequest());

            assertSame(401, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Login inválido deve responder 401.');
            assertSame('UNAUTHENTICATED', $response['error']['code'] ?? null, 'Login inválido deve retornar código padronizado.');
            assertSame(16, strlen((string) ($response['error']['trace_id'] ?? '')), 'Login inválido deve retornar trace_id.');
        },
        'auth_controller_register_returns_201_for_valid_payload' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_auth_payload'] = [
                'name' => 'Cliente Novo',
                'email' => 'new.customer@example.com',
                'password' => 'password123',
            ];

            $controller = makeAuthController(
                noopAuthenticateUseCase(),
                new class implements RegisterUserUseCase {
                    /**
                     * @return array{id:int,name:string,email:string,roles:array<int,string>}
                     */
                    public function execute(RegisterUserInputDTO $input): array
                    {
                        $GLOBALS['captured_register_input'] = $input;

                        return [
                            'id' => 99,
                            'name' => $input->name,
                            'email' => $input->email,
                            'roles' => ['customer'],
                        ];
                    }
                }
            );

            $response = $controller->register(new RegisterRequest());
            $captured = $GLOBALS['captured_register_input'];

            assertSame(201, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Cadastro válido deve responder 201.');
            assertSame('Cliente Novo', $captured->name, 'Cadastro deve mapear nome para DTO.');
            assertSame('new.customer@example.com', $captured->email, 'Cadastro deve mapear email para DTO.');
            assertSame('password123', $captured->password, 'Cadastro deve mapear senha para DTO.');
            assertSame(99, $response['data']['id'] ?? null, 'Cadastro deve retornar id do usuário criado.');
            assertSame('customer', $response['data']['roles'][0] ?? null, 'Cadastro deve retornar role padrão.');
        },
        'auth_controller_register_returns_409_for_existing_email' => static function (): void {
            resetHttpContext();
            $GLOBALS['ticketing_auth_payload'] = [
                'name' => 'Cliente Existente',
                'email' => 'existing.customer@example.com',
                'password' => 'password123',
            ];

            $controller = makeAuthController(
                noopAuthenticateUseCase(),
                new class implements RegisterUserUseCase {
                    /**
                     * @return array{id:int,name:string,email:string,roles:array<int,string>}
                     */
                    public function execute(RegisterUserInputDTO $input): array
                    {
                        throw new \DomainException('E-mail já cadastrado.');
                    }
                }
            );

            $response = $controller->register(new RegisterRequest());

            assertSame(409, (int) ($GLOBALS['ticketing_http_status_code'] ?? 200), 'Cadastro duplicado deve responder 409.');
            assertSame('USER_ALREADY_EXISTS', $response['error']['code'] ?? null, 'Cadastro duplicado deve retornar código padronizado.');
            assertSame(16, strlen((string) ($response['error']['trace_id'] ?? '')), 'Cadastro duplicado deve retornar trace_id.');
        },
    ];

    /**
     * Cria controller de autenticação para testes de contrato.
     *
     * @param AuthenticateUserUseCase $authenticate Double do caso de uso de login.
     * @param RegisterUserUseCase $register Double do caso de uso de cadastro.
     * @return AuthController
     *
     * @author David Marques
     */
    function makeAuthController(AuthenticateUserUseCase $authenticate, RegisterUserUseCase $register): AuthController
    {
        return new AuthController($authenticate, $register);
    }

    /**
     * Fornece caso de uso de login sem efeito colateral.
     *
     * @return AuthenticateUserUseCase
     *
     * @author David Marques
     */
    function noopAuthenticateUseCase(): AuthenticateUserUseCase
    {
        return new class implements AuthenticateUserUseCase {
            /**
             * @return array<string, mixed>
             */
            public function execute(LoginInputDTO $input): array
            {
                return [];
            }
        };
    }

    /**
     * Fornece caso de uso de cadastro sem efeito colateral.
     *
     * @return RegisterUserUseCase
     *
     * @author David Marques
     */
    function noopRegisterUseCase(): RegisterUserUseCase
    {
        return new class implements RegisterUserUseCase {
            /**
             * @return array{id:int,name:string,email:string,roles:array<int,string>}
             */
            public function execute(RegisterUserInputDTO $input): array
            {
                return [];
            }
        };
    }

    /**
     * Limpa estado global de testes HTTP.
     *
     * @return void
     *
     * @author David Marques
     */
    function resetHttpContext(): void
    {
        unset(
            $GLOBALS['ticketing_http_status_code'],
            $GLOBALS['ticketing_auth_payload'],
            $GLOBALS['captured_login_input'],
            $GLOBALS['captured_register_input']
        );
    }

    /**
     * Compara valores e falha com mensagem detalhada em divergências.
     *
     * @param mixed $expected Valor esperado.
     * @param mixed $actual Valor atual.
     * @param string $message Contexto da asserção.
     * @return void
     *
     * @throws \RuntimeException Quando valores não coincidirem.
     *
     * @author David Marques
     */
    function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
            );
        }
    }

    /**
     * Converte valor para string legível nas mensagens de falha.
     *
     * @param mixed $value Valor a converter.
     * @return string
     *
     * @author David Marques
     */
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
