<?php

declare(strict_types=1);


use App\Modules\Ticketing\Interface\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/tickets', [TicketController::class, 'store']);
});
