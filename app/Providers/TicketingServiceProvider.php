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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Provider para bindings iniciais do módulo Ticketing.
 *
 * @author David Marques
 */
final class TicketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TicketRepositoryPort::class, InMemoryTicketRepository::class);
        $this->app->bind(TicketListCachePort::class, RedisTicketListCache::class);
        $this->app->bind(DistributedLockPort::class, RedisDistributedLock::class);
        $this->app->bind(EventDispatcherPort::class, LaravelEventDispatcher::class);
        $this->app->bind(QueueDispatcherPort::class, RedisQueueDispatcher::class);
        $this->app->bind(CreateTicketUseCase::class, CreateTicketService::class);
        $this->app->bind(ListTicketsUseCase::class, ListTicketsService::class);
        $this->app->bind(AssignTicketUseCase::class, AssignTicketService::class);
    }

    public function boot(): void
    {
        Event::listen(TicketCreated::class, HandleTicketCreated::class);
    }
}
