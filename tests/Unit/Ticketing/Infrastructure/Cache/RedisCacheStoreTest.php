<?php

declare(strict_types=1);

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

use App\Modules\Ticketing\Infrastructure\Cache\RedisCacheStore;

$tests = [
    'redis_cache_store_put_and_get_by_key' => static function (): void {
        $cache = new RedisCacheStore();
        $cache->clear();

        $cache->putByKey('ticket:test:key', ['id' => 't-1'], 60);
        $value = $cache->getByKey('ticket:test:key');

        assertTrue(is_array($value), 'Valor cacheado deve ser um array.');
        assertSame('t-1', $value['id'] ?? null, 'Valor cacheado deve manter dados.');
    },
    'redis_cache_store_forget_by_key' => static function (): void {
        $cache = new RedisCacheStore();
        $cache->clear();

        $cache->putByKey('ticket:test:forget', 'value', 60);
        $cache->forgetByKey('ticket:test:forget');

        assertSame(null, $cache->getByKey('ticket:test:forget'), 'Chave removida deve retornar null.');
    },
    'redis_cache_store_clear_removes_all_keys' => static function (): void {
        $cache = new RedisCacheStore();
        $cache->clear();

        $cache->putByKey('ticket:test:clear:1', 'a', 60);
        $cache->putByKey('ticket:test:clear:2', 'b', 60);
        $cache->clear();

        assertSame(null, $cache->getByKey('ticket:test:clear:1'), 'Clear deve remover primeira chave.');
        assertSame(null, $cache->getByKey('ticket:test:clear:2'), 'Clear deve remover segunda chave.');
    },
];

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
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

function formatValue(mixed $value): string
{
    if (is_object($value)) {
        return $value::class;
    }

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
    } catch (Throwable $throwable) {
        $failures[] = sprintf('FAIL %s: %s', $name, $throwable->getMessage());
    }
}

foreach ($failures as $failure) {
    echo $failure . PHP_EOL;
}

exit(count($failures) === 0 ? 0 : 1);
