<?php

declare(strict_types=1);

/**
 * Lista de providers bootstrap da aplicação.
 *
 * ## 🎯 Objetivo
 * - Registrar providers essenciais do módulo Ticketing.
 * - Habilitar autenticação Sanctum e documentação OpenAPI.
 *
 * @return array<int, class-string>
 *
 * @author David Marques
 */
return [
    Laravel\Sanctum\SanctumServiceProvider::class,
    Dedoc\Scramble\ScrambleServiceProvider::class,
    App\Providers\ScrambleDocumentationServiceProvider::class,
    App\Providers\TicketingLaravelServiceProvider::class,
];
