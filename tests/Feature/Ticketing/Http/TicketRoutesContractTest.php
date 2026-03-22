<?php

declare(strict_types=1);

namespace Illuminate\Support\Facades {

    if (!class_exists(Route::class)) {
        final class Route
        {
            public static array $middlewares = [];

            public static array $routes = [];

            public static function middleware(array $middlewares): object
            {
                self::$middlewares = $middlewares;

                return new class {
                    public function group(callable $callback): void
                    {
                        $callback();
                    }
                };
            }

            public static function get(string $uri, array $action): void
            {
                self::$routes[] = ['GET', $uri, $action];
            }

            public static function post(string $uri, array $action): void
            {
                self::$routes[] = ['POST', $uri, $action];
            }

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

            assertSame(['auth:sanctum'], Route::$middlewares, 'Middleware de autenticação deve ser aplicado.');
            assertSame(6, count(Route::$routes), 'Deve registrar os seis endpoints principais de ticket.');
            assertSame(['GET', '/tickets'], [Route::$routes[0][0], Route::$routes[0][1]], 'Primeira rota deve listar tickets.');
            assertSame(['GET', '/tickets/{ticketId}'], [Route::$routes[1][0], Route::$routes[1][1]], 'Segunda rota deve detalhar ticket.');
            assertSame(['POST', '/tickets'], [Route::$routes[2][0], Route::$routes[2][1]], 'Terceira rota deve criar ticket.');
            assertSame(['PATCH', '/tickets/{ticketId}/assign'], [Route::$routes[3][0], Route::$routes[3][1]], 'Quarta rota deve atribuir ticket.');
            assertSame(['POST', '/tickets/{ticketId}/reply'], [Route::$routes[4][0], Route::$routes[4][1]], 'Quinta rota deve responder ticket.');
            assertSame(['PATCH', '/tickets/{ticketId}/close'], [Route::$routes[5][0], Route::$routes[5][1]], 'Sexta rota deve fechar ticket.');
        },
    ];

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
