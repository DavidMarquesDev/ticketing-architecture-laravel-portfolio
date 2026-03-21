<?php

declare(strict_types=1);


namespace App\Providers;

use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\UseCases\CreateTicketService;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\InMemoryTicketRepository;
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
        $this->app->bind(TicketRepositoryPort::class, InMemoryTicketRepository::class);
        $this->app->bind(CreateTicketUseCase::class, CreateTicketService::class);
    }
}
