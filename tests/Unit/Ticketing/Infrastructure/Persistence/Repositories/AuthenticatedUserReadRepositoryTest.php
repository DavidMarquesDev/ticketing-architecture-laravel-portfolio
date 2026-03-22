<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__, 6) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

if (!function_exists('auth')) {
    function auth(): object
    {
        return new class {
            public function user(): object|array|null
            {
                return $GLOBALS['ticketing_test_authenticated_user'] ?? null;
            }
        };
    }
}

use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\AuthenticatedUserReadRepository;

$tests = [
    'authenticated_user_read_repository_exists_by_id_for_object_user' => static function (): void {
        $GLOBALS['ticketing_test_authenticated_user'] = new class {
            public int $id = 77;
            public array $roles = ['agent'];
        };

        $repository = new AuthenticatedUserReadRepository();

        assertTrue($repository->existsById(77), 'Repositório deve encontrar usuário autenticado por id.');
        assertTrue(!$repository->existsById(88), 'Repositório não deve confirmar outro id.');
    },
    'authenticated_user_read_repository_has_any_role_for_array_user' => static function (): void {
        $GLOBALS['ticketing_test_authenticated_user'] = [
            'id' => 45,
            'roles' => ['admin', 'support'],
        ];

        $repository = new AuthenticatedUserReadRepository();

        assertTrue($repository->hasAnyRole(45, ['guest', 'admin']), 'Repositório deve validar interseção de roles.');
        assertTrue(!$repository->hasAnyRole(45, ['guest']), 'Repositório deve negar role ausente.');
    },
    'authenticated_user_read_repository_returns_false_when_user_not_authenticated' => static function (): void {
        $GLOBALS['ticketing_test_authenticated_user'] = null;
        $repository = new AuthenticatedUserReadRepository();

        assertTrue(!$repository->existsById(1), 'Sem autenticação, existsById deve retornar false.');
        assertTrue(!$repository->hasAnyRole(1, ['admin']), 'Sem autenticação, hasAnyRole deve retornar false.');
    },
];

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
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
