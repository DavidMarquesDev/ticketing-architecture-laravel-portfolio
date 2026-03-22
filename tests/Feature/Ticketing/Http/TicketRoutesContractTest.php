<?php

declare(strict_types=1);

namespace Illuminate\Support\Facades {

    if (!class_exists(Route::class)) {
        /**
         * Double de facade de rotas para contrato HTTP em ambiente de teste.
         *
         * @author David Marques
         */
        final class Route
        {
            public static array $middlewares = [];

            public static array $routes = [];

            /**
             * Registra middlewares do grupo de rotas.
             *
             * @param array<int, string> $middlewares Lista de middlewares aplicados.
             * @return object
             *
             * @author David Marques
             */
            public static function middleware(array $middlewares): object
            {
                self::$middlewares = $middlewares;

                return new class {
                    /**
                     * Executa callback de agrupamento de rotas.
                     *
                     * @param callable $callback Callback com definição das rotas.
                     * @return void
                     *
                     * @author David Marques
                     */
                    public function group(callable $callback): void
                    {
                        $callback();
                    }
                };
            }

            /**
             * Registra rota GET no array de contrato.
             *
             * @param string $uri URI da rota.
             * @param array<int, mixed> $action Action da rota.
             * @return void
             *
             * @author David Marques
             */
            public static function get(string $uri, array $action): void
            {
                self::$routes[] = ['GET', $uri, $action];
            }

            /**
             * Registra rota POST no array de contrato.
             *
             * @param string $uri URI da rota.
             * @param array<int, mixed> $action Action da rota.
             * @return void
             *
             * @author David Marques
             */
            public static function post(string $uri, array $action): void
            {
                self::$routes[] = ['POST', $uri, $action];
            }

            /**
             * Registra rota PATCH no array de contrato.
             *
             * @param string $uri URI da rota.
             * @param array<int, mixed> $action Action da rota.
             * @return void
             *
             * @author David Marques
             */
            public static function patch(string $uri, array $action): void
            {
                self::$routes[] = ['PATCH', $uri, $action];
            }
        }
    }
}

namespace {

    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $baseDir = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    });

    use Illuminate\Support\Facades\Route;

    $tests = [
        'http_routes_are_registered_with_expected_contract' => static function (): void {
            Route::$routes = [];
            Route::$middlewares = [];
            require dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';

            assertSame(['auth:sanctum', 'throttle:ticketing'], Route::$middlewares, 'Middlewares de autenticação e throttle devem ser aplicados.');
            assertSame(9, count(Route::$routes), 'Deve registrar cadastro, login e os sete endpoints principais de ticket.');
            assertSame(['POST', '/auth/register'], [Route::$routes[0][0], Route::$routes[0][1]], 'Primeira rota deve cadastrar usuário.');
            assertSame(['POST', '/auth/login'], [Route::$routes[1][0], Route::$routes[1][1]], 'Segunda rota deve autenticar usuário.');
            assertSame(['GET', '/tickets'], [Route::$routes[2][0], Route::$routes[2][1]], 'Terceira rota deve listar tickets.');
            assertSame(['GET', '/tickets/{ticketId}'], [Route::$routes[3][0], Route::$routes[3][1]], 'Quarta rota deve detalhar ticket.');
            assertSame(['GET', '/tickets/{ticketId}/comments'], [Route::$routes[4][0], Route::$routes[4][1]], 'Quinta rota deve listar comentários do ticket.');
            assertSame(['POST', '/tickets'], [Route::$routes[5][0], Route::$routes[5][1]], 'Sexta rota deve criar ticket.');
            assertSame(['PATCH', '/tickets/{ticketId}/assign'], [Route::$routes[6][0], Route::$routes[6][1]], 'Sétima rota deve atribuir ticket.');
            assertSame(['POST', '/tickets/{ticketId}/reply'], [Route::$routes[7][0], Route::$routes[7][1]], 'Oitava rota deve responder ticket.');
            assertSame(['PATCH', '/tickets/{ticketId}/close'], [Route::$routes[8][0], Route::$routes[8][1]], 'Nona rota deve fechar ticket.');
        },
    ];

    /**
     * Compara valores estritamente e lança exceção com mensagem amigável.
     *
     * @param mixed $expected Valor esperado.
     * @param mixed $actual Valor atual.
     * @param string $message Mensagem de contexto para falha.
     * @return void
     *
     * @throws \RuntimeException Quando os valores diferirem.
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
     * Normaliza valor para string de depuração em mensagens de falha.
     *
     * @param mixed $value Valor a ser serializado.
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
