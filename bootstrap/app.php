<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Modules\Ticketing\Domain\Exceptions\TicketNotFoundException;
use App\Modules\Ticketing\Domain\Exceptions\TicketStateException;
use Illuminate\Auth\Access\AuthorizationException;

if (!class_exists(Application::class)) {
    throw new RuntimeException('Dependências do Laravel não instaladas. Execute composer install.');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->statefulApi();
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->render(
            static function (\Throwable $exception, Request $request): ?JsonResponse {
                $traceId = (string) ($request->header('X-Trace-Id') ?? bin2hex(random_bytes(8)));
                $status = 500;
                $code = 'INTERNAL_ERROR';
                $message = 'Erro interno do servidor.';
                $details = [];

                if ($exception instanceof ValidationException) {
                    $status = 422;
                    $code = 'VALIDATION_ERROR';
                    $message = 'Dados de entrada inválidos.';
                    $details = $exception->errors();
                } elseif ($exception instanceof AuthorizationException) {
                    $status = 403;
                    $code = 'FORBIDDEN';
                    $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'Usuário sem permissão.';
                } elseif ($exception instanceof TicketNotFoundException) {
                    $status = 404;
                    $code = 'TICKET_NOT_FOUND';
                    $message = $exception->getMessage();
                } elseif ($exception instanceof TicketStateException) {
                    $status = 409;
                    $code = 'TICKET_CONFLICT';
                    $message = $exception->getMessage();
                }

                return response()->json(
                    [
                        'error' => [
                            'code' => $code,
                            'message' => $message,
                            'details' => $details,
                            'trace_id' => $traceId,
                        ],
                    ],
                    $status
                );
            }
        );
    })
    ->create();
