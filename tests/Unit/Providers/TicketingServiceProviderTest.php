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
];

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

    if ($value === null) {
        return 'null';
    }

    if (is_array($value)) {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    return (string) $value;
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
