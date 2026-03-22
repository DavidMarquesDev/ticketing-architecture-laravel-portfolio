<?php

declare(strict_types=1);


use App\Modules\Ticketing\Interface\Http\Controllers\AuthController;
use App\Modules\Ticketing\Interface\Http\Controllers\TicketController;
use App\Modules\Ticketing\Interface\Http\Controllers\UserRoleController;

$routeFacade = '\Illuminate\Support\Facades\Route';

if (class_exists($routeFacade)) {
    /**
     * 🔓 Rotas públicas de autenticação.
     *
     * @example
     * POST /api/auth/register
     * POST /api/auth/login
     *
     * @author David Marques
     */
    $routeFacade::post('/auth/register', [AuthController::class, 'register']);
    $routeFacade::post('/auth/login', [AuthController::class, 'login']);

    /**
     * 🔐 Rotas protegidas por Sanctum e rate limiting.
     *
     * ## Regras de Acesso
     * - Requer autenticação: `auth:sanctum`
     * - Requer limitação de requisições: `throttle:ticketing`
     *
     * @author David Marques
     */
    $routeFacade::middleware(['auth:sanctum', 'throttle:ticketing'])->group(function () use ($routeFacade): void {
        $routeFacade::get('/tickets', [TicketController::class, 'index']);
        $routeFacade::get('/tickets/{ticketId}', [TicketController::class, 'show']);
        $routeFacade::get('/tickets/{ticketId}/comments', [TicketController::class, 'comments']);
        $routeFacade::post('/tickets', [TicketController::class, 'store']);
        $routeFacade::patch('/tickets/{ticketId}/assign', [TicketController::class, 'assign']);
        $routeFacade::post('/tickets/{ticketId}/reply', [TicketController::class, 'reply']);
        $routeFacade::patch('/tickets/{ticketId}/close', [TicketController::class, 'close']);
        $routeFacade::patch('/users/{userId}/roles', [UserRoleController::class, 'update']);
    });
}
