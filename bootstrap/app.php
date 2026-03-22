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
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
                } elseif ($exception instanceof AuthenticationException) {
                    $status = 401;
                    $code = 'UNAUTHENTICATED';
                    $message = 'Usuário não autenticado.';
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
                } elseif ($exception instanceof HttpExceptionInterface) {
                    $status = $exception->getStatusCode();
                    $code = match ($status) {
                        401 => 'UNAUTHENTICATED',
                        403 => 'FORBIDDEN',
                        404 => 'NOT_FOUND',
                        409 => 'CONFLICT',
                        422 => 'VALIDATION_ERROR',
                        429 => 'TOO_MANY_REQUESTS',
                        default => 'HTTP_ERROR',
                    };
                    $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'Erro HTTP.';
                } elseif ($exception instanceof HttpResponseException) {
                    $response = $exception->getResponse();
                    $status = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 500;
                    $code = match ($status) {
                        401 => 'UNAUTHENTICATED',
                        403 => 'FORBIDDEN',
                        404 => 'NOT_FOUND',
                        409 => 'CONFLICT',
                        422 => 'VALIDATION_ERROR',
                        429 => 'TOO_MANY_REQUESTS',
                        default => 'HTTP_ERROR',
                    };
                    $message = $status === 401 ? 'Usuário não autenticado.' : 'Erro HTTP.';
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
