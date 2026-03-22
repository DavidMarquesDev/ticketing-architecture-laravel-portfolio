<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

if (!function_exists('app')) {
    function app(): object
    {
        return $GLOBALS['ticketing_test_container'];
    }
}

use App\Modules\Ticketing\Application\Ports\Out\CachePort;
use App\Modules\Ticketing\Application\Ports\Out\EventBusPort;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\UserReadRepositoryPort;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsQueryHandler;
use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsQueryHandler;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsQueryHandler;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketCommandHandler;
use App\Modules\Ticketing\Application\CommandHandlers\AssignTicketCommandHandler as AssignTicketCommandHandlerService;
use App\Modules\Ticketing\Application\CommandHandlers\CloseTicketCommandHandler as CloseTicketCommandHandlerService;
use App\Modules\Ticketing\Application\CommandHandlers\CreateTicketCommandHandler as CreateTicketCommandHandlerService;
use App\Modules\Ticketing\Application\CommandHandlers\ReplyTicketCommandHandler as ReplyTicketCommandHandlerService;
use App\Modules\Ticketing\Application\QueryHandlers\GetTicketDetailsQueryHandler as GetTicketDetailsQueryHandlerService;
use App\Modules\Ticketing\Application\QueryHandlers\ListTicketCommentsQueryHandler as ListTicketCommentsQueryHandlerService;
use App\Modules\Ticketing\Application\QueryHandlers\ListTicketsQueryHandler as ListTicketsQueryHandlerService;
use App\Modules\Ticketing\Domain\Events\TicketClosed;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Events\TicketReplied;
use App\Modules\Ticketing\Domain\Events\TicketAssigned;
use App\Modules\Ticketing\Infrastructure\Cache\RedisCacheStore;
use App\Modules\Ticketing\Infrastructure\Cache\RedisTicketListCache;
use App\Modules\Ticketing\Infrastructure\Events\LaravelEventDispatcher;
use App\Modules\Ticketing\Infrastructure\Observability\ErrorLogQueryTelemetry;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\AuthenticatedUserReadRepository;
use App\Providers\TicketingServiceProvider;

$tests = [
    'ticketing_service_provider_register_binds_new_generic_ports' => static function (): void {
        $container = new FakeContainer();
        $GLOBALS['ticketing_test_container'] = $container;

        $provider = new TicketingServiceProvider();
        $provider->register();

        assertSame(RedisCacheStore::class, $container->bindings[CachePort::class] ?? null, 'Provider deve bindar CachePort.');
        assertSame(LaravelEventDispatcher::class, $container->bindings[EventBusPort::class] ?? null, 'Provider deve bindar EventBusPort.');
        assertSame(LaravelEventDispatcher::class, $container->bindings[EventDispatcherPort::class] ?? null, 'Provider deve manter binding EventDispatcherPort.');
        assertSame(RedisTicketListCache::class, $container->bindings[TicketListCachePort::class] ?? null, 'Provider deve manter binding TicketListCachePort.');
        assertSame(ErrorLogQueryTelemetry::class, $container->bindings[QueryTelemetryPort::class] ?? null, 'Provider deve bindar QueryTelemetryPort.');
        assertSame(AuthenticatedUserReadRepository::class, $container->singletons[UserReadRepositoryPort::class] ?? null, 'Provider deve registrar UserReadRepositoryPort.');
        assertSame(AssignTicketCommandHandlerService::class, $container->bindings[AssignTicketCommandHandler::class] ?? null, 'Provider deve bindar AssignTicketCommandHandler.');
        assertSame(CloseTicketCommandHandlerService::class, $container->bindings[CloseTicketCommandHandler::class] ?? null, 'Provider deve bindar CloseTicketCommandHandler.');
        assertSame(CreateTicketCommandHandlerService::class, $container->bindings[CreateTicketCommandHandler::class] ?? null, 'Provider deve bindar CreateTicketCommandHandler.');
        assertSame(ReplyTicketCommandHandlerService::class, $container->bindings[ReplyTicketCommandHandler::class] ?? null, 'Provider deve bindar ReplyTicketCommandHandler.');
        assertSame(ListTicketsQueryHandlerService::class, $container->bindings[ListTicketsQueryHandler::class] ?? null, 'Provider deve bindar ListTicketsQueryHandler.');
        assertSame(GetTicketDetailsQueryHandlerService::class, $container->bindings[GetTicketDetailsQueryHandler::class] ?? null, 'Provider deve bindar GetTicketDetailsQueryHandler.');
        assertSame(ListTicketCommentsQueryHandlerService::class, $container->bindings[ListTicketCommentsQueryHandler::class] ?? null, 'Provider deve bindar ListTicketCommentsQueryHandler.');
    },
    'ticketing_service_provider_boot_registers_domain_event_listeners' => static function (): void {
        $container = new FakeContainer();
        $GLOBALS['ticketing_test_container'] = $container;

        $provider = new TicketingServiceProvider();
        $provider->boot();

        assertSame(4, count($container->events->listeners), 'Provider deve registrar quatro listeners de domínio.');
        assertSame(TicketCreated::class, $container->events->listeners[0][0] ?? null, 'Primeiro listener deve ser de TicketCreated.');
        assertSame(TicketClosed::class, $container->events->listeners[1][0] ?? null, 'Segundo listener deve ser de TicketClosed.');
        assertSame(TicketReplied::class, $container->events->listeners[2][0] ?? null, 'Terceiro listener deve ser de TicketReplied.');
        assertSame(TicketAssigned::class, $container->events->listeners[3][0] ?? null, 'Quarto listener deve ser de TicketAssigned.');
    },
    'ticketing_service_provider_resolve_rate_limit_uses_env_and_default' => static function (): void {
        putenv('TICKETING_RATE_LIMIT_TEST=77');
        unset($_ENV['TICKETING_RATE_LIMIT_TEST']);

        $provider = new TicketingServiceProvider();
        $fromEnv = callPrivateMethod($provider, 'resolveRateLimit', ['TICKETING_RATE_LIMIT_TEST', 20]);
        assertSame(77, $fromEnv, 'Provider deve usar valor numérico do ambiente.');

        putenv('TICKETING_RATE_LIMIT_TEST=');
        unset($_ENV['TICKETING_RATE_LIMIT_TEST']);
        $fromDefault = callPrivateMethod($provider, 'resolveRateLimit', ['TICKETING_RATE_LIMIT_TEST', 20]);
        assertSame(20, $fromDefault, 'Provider deve aplicar default sem valor válido.');
    },
    'ticketing_service_provider_resolve_rate_window_enforces_bounds' => static function (): void {
        putenv('TICKETING_RATE_WINDOW_TEST=5');
        unset($_ENV['TICKETING_RATE_WINDOW_TEST']);

        $provider = new TicketingServiceProvider();
        $minBounded = callPrivateMethod($provider, 'resolveRateWindow', ['TICKETING_RATE_WINDOW_TEST', 60]);
        assertSame(10, $minBounded, 'Provider deve respeitar limite mínimo de janela.');

        putenv('TICKETING_RATE_WINDOW_TEST=7200');
        $maxBounded = callPrivateMethod($provider, 'resolveRateWindow', ['TICKETING_RATE_WINDOW_TEST', 60]);
        assertSame(3600, $maxBounded, 'Provider deve respeitar limite máximo de janela.');

        putenv('TICKETING_RATE_WINDOW_TEST=');
        unset($_ENV['TICKETING_RATE_WINDOW_TEST']);
        $fromDefault = callPrivateMethod($provider, 'resolveRateWindow', ['TICKETING_RATE_WINDOW_TEST', 45]);
        assertSame(45, $fromDefault, 'Provider deve aplicar default de janela.');
    },
    'ticketing_service_provider_logs_rate_limit_bucket_with_trace_context' => static function (): void {
        $logPath = createTempLogPath('rate-limiter');
        $previousLogPath = ini_get('error_log');
        ini_set('error_log', $logPath);

        try {
            $request = new FakeRateLimitRequest(
                method: 'PATCH',
                path: 'api/tickets/abc/assign',
                headers: [
                    'X-Trace-Id' => 'trace-test-1',
                    'X-Correlation-Id' => 'corr-test-1',
                ]
            );

            callPrivateStaticMethod(
                TicketingServiceProvider::class,
                'logRateLimitTelemetry',
                [$request, 'tickets:assign', 45, 30, 77, '127.0.0.1', 'ticketing:77:127.0.0.1:tickets:assign:30:123']
            );

            $logContent = (string) file_get_contents($logPath);
            assertTrue(str_contains($logContent, '"type":"rate_limit_bucket"'), 'Telemetria de rate limit deve registrar tipo esperado.');
            assertTrue(str_contains($logContent, '"endpoint_bucket":"tickets:assign"'), 'Telemetria de rate limit deve registrar endpoint bucket.');
            assertTrue(str_contains($logContent, '"window_seconds":30'), 'Telemetria de rate limit deve registrar janela em segundos.');
            assertTrue(str_contains($logContent, '"limit":45'), 'Telemetria de rate limit deve registrar limite configurado.');
            assertTrue(str_contains($logContent, '"user_id":77'), 'Telemetria de rate limit deve registrar usuário autenticado.');
            assertTrue(str_contains($logContent, '"trace_id":"trace-test-1"'), 'Telemetria de rate limit deve registrar trace_id recebido.');
            assertTrue(str_contains($logContent, '"correlation_id":"corr-test-1"'), 'Telemetria de rate limit deve registrar correlation_id recebido.');
        } finally {
            ini_set('error_log', is_string($previousLogPath) ? $previousLogPath : '');
            removeFileIfExists($logPath);
        }
    },
];

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            sprintf('%s Esperado: %s. Atual: %s.', $message, formatValue($expected), formatValue($actual))
        );
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function formatValue(mixed $value): string
{
    if (is_object($value)) {
        return $value::class;
    }

    if ($value === null) {
        return 'null';
    }

    if (is_array($value)) {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    return (string) $value;
}

function callPrivateMethod(object $instance, string $methodName, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($instance);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invokeArgs($instance, $arguments);
}

function callPrivateStaticMethod(string $className, string $methodName, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($className);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invokeArgs(null, $arguments);
}

function createTempLogPath(string $suffix): string
{
    $unique = str_replace('.', '', uniqid('ticketing_provider_', true));

    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $unique . '_' . $suffix . '.log';
}

function removeFileIfExists(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    }
}

final class FakeContainer
{
    public array $bindings = [];

    public array $singletons = [];

    public FakeEventDispatcher $events;

    public function __construct()
    {
        $this->events = new FakeEventDispatcher();
    }

    public function singleton(string $abstract, string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function make(string $abstract): object|null
    {
        if ($abstract === 'events') {
            return $this->events;
        }

        return null;
    }
}

final class FakeEventDispatcher
{
    public array $listeners = [];

    public function listen(string $eventClass, string $listenerClass): void
    {
        $this->listeners[] = [$eventClass, $listenerClass];
    }
}

final class FakeRateLimitRequest
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $headers
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function header(string $name): mixed
    {
        return $this->headers[$name] ?? null;
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
