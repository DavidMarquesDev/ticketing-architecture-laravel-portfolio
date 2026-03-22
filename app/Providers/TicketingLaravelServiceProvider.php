<?php

declare(strict_types=1);


namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Bridge para registrar o provider do módulo ticketing no runtime Laravel.
 *
 * @author David Marques
 */
final class TicketingLaravelServiceProvider extends ServiceProvider
{
    /**
     * Registra bindings do módulo ticketing.
     *
     * @return void
     */
    public function register(): void
    {
        (new TicketingServiceProvider($this->app))->register();
    }

    /**
     * Inicializa listeners, autorização e rate limits do módulo ticketing.
     *
     * @return void
     */
    public function boot(): void
    {
        (new TicketingServiceProvider($this->app))->boot();
    }
}
