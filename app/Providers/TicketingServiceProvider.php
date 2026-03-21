<?php

declare(strict_types=1);


namespace App\Providers;

use App\Modules\Ticketing\Application\Listeners\HandleTicketCreated;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\AssignTicketService;
use App\Modules\Ticketing\Application\UseCases\CreateTicketService;
use App\Modules\Ticketing\Application\UseCases\ListTicketsService;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Infrastructure\Cache\RedisTicketListCache;
use App\Modules\Ticketing\Infrastructure\Events\LaravelEventDispatcher;
use App\Modules\Ticketing\Infrastructure\Lock\RedisDistributedLock;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\InMemoryTicketRepository;
use App\Modules\Ticketing\Infrastructure\Queue\RedisQueueDispatcher;

/**
 * Provider para bindings iniciais do módulo Ticketing.
 *
 * @author David Marques
 */
final class TicketingServiceProvider
{
    public function register(): void
    {
        $container = $this->container();

        if ($container === null) {
            return;
        }

        if (method_exists($container, 'singleton')) {
            $container->singleton(TicketRepositoryPort::class, InMemoryTicketRepository::class);
        }

        if (method_exists($container, 'bind')) {
            $container->bind(TicketListCachePort::class, RedisTicketListCache::class);
            $container->bind(DistributedLockPort::class, RedisDistributedLock::class);
            $container->bind(EventDispatcherPort::class, LaravelEventDispatcher::class);
            $container->bind(QueueDispatcherPort::class, RedisQueueDispatcher::class);
            $container->bind(CreateTicketUseCase::class, CreateTicketService::class);
            $container->bind(ListTicketsUseCase::class, ListTicketsService::class);
            $container->bind(AssignTicketUseCase::class, AssignTicketService::class);
        }
    }

    public function boot(): void
    {
        $container = $this->container();

        if ($container === null || !method_exists($container, 'make')) {
            return;
        }

        $dispatcher = $container->make('events');

        if (is_object($dispatcher) && method_exists($dispatcher, 'listen')) {
            $dispatcher->listen(TicketCreated::class, HandleTicketCreated::class);
        }
    }

    private function container(): ?object
    {
        if (!function_exists('app')) {
            return null;
        }

        $container = call_user_func('app');

        return is_object($container) ? $container : null;
    }
}
