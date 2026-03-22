<?php

declare(strict_types=1);


use App\Modules\Ticketing\Interface\Http\Controllers\AuthController;
use App\Modules\Ticketing\Interface\Http\Controllers\TicketController;

$routeFacade = '\Illuminate\Support\Facades\Route';

if (class_exists($routeFacade)) {
    $routeFacade::post('/auth/login', [AuthController::class, 'login']);

    $routeFacade::middleware(['auth:sanctum', 'throttle:ticketing'])->group(function () use ($routeFacade): void {
        $routeFacade::get('/tickets', [TicketController::class, 'index']);
        $routeFacade::get('/tickets/{ticketId}', [TicketController::class, 'show']);
        $routeFacade::get('/tickets/{ticketId}/comments', [TicketController::class, 'comments']);
        $routeFacade::post('/tickets', [TicketController::class, 'store']);
        $routeFacade::patch('/tickets/{ticketId}/assign', [TicketController::class, 'assign']);
        $routeFacade::post('/tickets/{ticketId}/reply', [TicketController::class, 'reply']);
        $routeFacade::patch('/tickets/{ticketId}/close', [TicketController::class, 'close']);
    });
}
