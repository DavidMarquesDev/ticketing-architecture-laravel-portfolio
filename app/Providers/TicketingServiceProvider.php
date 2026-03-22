<?php

declare(strict_types=1);


namespace App\Providers;

use App\Modules\Ticketing\Application\Listeners\HandleTicketCreated;
use App\Modules\Ticketing\Application\Listeners\HandleTicketClosed;
use App\Modules\Ticketing\Application\Listeners\HandleTicketReplied;
use App\Modules\Ticketing\Application\Listeners\HandleTicketAssigned;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\AssignTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\CloseTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\CreateTicketUseCase;
use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsQueryHandler as GetTicketDetailsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\GetTicketDetailsUseCase;
use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsQueryHandler as ListTicketCommentsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\ListTicketCommentsUseCase;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsQueryHandler as ListTicketsQueryHandlerPort;
use App\Modules\Ticketing\Application\Ports\In\ListTicketsUseCase;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketCommandHandler;
use App\Modules\Ticketing\Application\Ports\In\ReplyTicketUseCase;
use App\Modules\Ticketing\Application\QueryHandlers\GetTicketDetailsQueryHandler as GetTicketDetailsQueryHandlerService;
use App\Modules\Ticketing\Application\QueryHandlers\ListTicketCommentsQueryHandler as ListTicketCommentsQueryHandlerService;
use App\Modules\Ticketing\Application\QueryHandlers\ListTicketsQueryHandler as ListTicketsQueryHandlerService;
use App\Modules\Ticketing\Application\Ports\Out\CachePort;
use App\Modules\Ticketing\Application\Ports\Out\DistributedLockPort;
use App\Modules\Ticketing\Application\Ports\Out\EventBusPort;
use App\Modules\Ticketing\Application\Ports\Out\EventDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\IntegrationEventPublisherPort;
use App\Modules\Ticketing\Application\Ports\Out\QueryTelemetryPort;
use App\Modules\Ticketing\Application\Ports\Out\QueueDispatcherPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketCommentRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\TicketListCachePort;
use App\Modules\Ticketing\Application\Ports\Out\TicketRepositoryPort;
use App\Modules\Ticketing\Application\Ports\Out\UserReadRepositoryPort;
use App\Modules\Ticketing\Application\CommandHandlers\AssignTicketCommandHandler as AssignTicketCommandHandlerService;
use App\Modules\Ticketing\Application\CommandHandlers\CloseTicketCommandHandler as CloseTicketCommandHandlerService;
use App\Modules\Ticketing\Application\CommandHandlers\CreateTicketCommandHandler as CreateTicketCommandHandlerService;
use App\Modules\Ticketing\Application\CommandHandlers\ReplyTicketCommandHandler as ReplyTicketCommandHandlerService;
use App\Modules\Ticketing\Application\UseCases\AssignTicketService;
use App\Modules\Ticketing\Application\UseCases\CloseTicketService;
use App\Modules\Ticketing\Application\UseCases\CreateTicketService;
use App\Modules\Ticketing\Application\UseCases\ListTicketsService;
use App\Modules\Ticketing\Application\UseCases\ReplyTicketService;
use App\Modules\Ticketing\Domain\Events\TicketClosed;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Events\TicketReplied;
use App\Modules\Ticketing\Domain\Events\TicketAssigned;
use App\Modules\Ticketing\Interface\Http\Policies\TicketPolicy;
use App\Modules\Ticketing\Infrastructure\Cache\RedisCacheStore;
use App\Modules\Ticketing\Infrastructure\Cache\RedisTicketListCache;
use App\Modules\Ticketing\Infrastructure\Events\LaravelEventDispatcher;
use App\Modules\Ticketing\Infrastructure\Lock\RedisDistributedLock;
use App\Modules\Ticketing\Infrastructure\Observability\ErrorLogQueryTelemetry;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\AuthenticatedUserReadRepository;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\EloquentTicketCommentRepository;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\EloquentTicketRepository;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\InMemoryTicketCommentRepository;
use App\Modules\Ticketing\Infrastructure\Persistence\Repositories\InMemoryTicketRepository;
use App\Modules\Ticketing\Infrastructure\Queue\QueueIntegrationEventPublisher;
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
            $container->singleton(
                TicketRepositoryPort::class,
                $this->supportsEloquent() ? EloquentTicketRepository::class : InMemoryTicketRepository::class
            );
            $container->singleton(
                TicketCommentRepositoryPort::class,
                $this->supportsEloquent() ? EloquentTicketCommentRepository::class : InMemoryTicketCommentRepository::class
            );
            $container->singleton(UserReadRepositoryPort::class, AuthenticatedUserReadRepository::class);
        }

        if (method_exists($container, 'bind')) {
            $container->bind(TicketListCachePort::class, RedisTicketListCache::class);
            $container->bind(CachePort::class, RedisCacheStore::class);
            $container->bind(DistributedLockPort::class, RedisDistributedLock::class);
            $container->bind(EventDispatcherPort::class, LaravelEventDispatcher::class);
            $container->bind(EventBusPort::class, LaravelEventDispatcher::class);
            $container->bind(QueueDispatcherPort::class, RedisQueueDispatcher::class);
            $container->bind(IntegrationEventPublisherPort::class, QueueIntegrationEventPublisher::class);
            $container->bind(QueryTelemetryPort::class, ErrorLogQueryTelemetry::class);
            $container->bind(CreateTicketUseCase::class, CreateTicketService::class);
            $container->bind(CreateTicketCommandHandler::class, CreateTicketCommandHandlerService::class);
            $container->bind(ListTicketsUseCase::class, ListTicketsService::class);
            $container->bind(ListTicketsQueryHandlerPort::class, ListTicketsQueryHandlerService::class);
            $container->bind(GetTicketDetailsUseCase::class, GetTicketDetailsQueryHandlerService::class);
            $container->bind(GetTicketDetailsQueryHandlerPort::class, GetTicketDetailsQueryHandlerService::class);
            $container->bind(ListTicketCommentsUseCase::class, ListTicketCommentsQueryHandlerService::class);
            $container->bind(ListTicketCommentsQueryHandlerPort::class, ListTicketCommentsQueryHandlerService::class);
            $container->bind(AssignTicketUseCase::class, AssignTicketService::class);
            $container->bind(AssignTicketCommandHandler::class, AssignTicketCommandHandlerService::class);
            $container->bind(CloseTicketUseCase::class, CloseTicketService::class);
            $container->bind(CloseTicketCommandHandler::class, CloseTicketCommandHandlerService::class);
            $container->bind(ReplyTicketUseCase::class, ReplyTicketService::class);
            $container->bind(ReplyTicketCommandHandler::class, ReplyTicketCommandHandlerService::class);
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
            $dispatcher->listen(TicketClosed::class, HandleTicketClosed::class);
            $dispatcher->listen(TicketReplied::class, HandleTicketReplied::class);
            $dispatcher->listen(TicketAssigned::class, HandleTicketAssigned::class);
        }

        $this->registerAuthorization();
        $this->registerRateLimiter();
    }

    private function container(): ?object
    {
        if (!function_exists('app')) {
            return null;
        }

        $container = call_user_func('app');

        return is_object($container) ? $container : null;
    }

    private function supportsEloquent(): bool
    {
        return class_exists('Illuminate\Database\Eloquent\Model');
    }

    private function registerAuthorization(): void
    {
        $gateFacade = '\Illuminate\Support\Facades\Gate';

        if (!class_exists($gateFacade) || !method_exists($gateFacade, 'define')) {
            return;
        }

        $policy = new TicketPolicy();

        $gateFacade::define('ticket.assign', static fn (mixed $user): bool => $policy->assign($user));
        $gateFacade::define('ticket.close', static fn (mixed $user): bool => $policy->close($user));
        $gateFacade::define('ticket.reply', static fn (mixed $user): bool => $policy->reply($user));
    }

    private function registerRateLimiter(): void
    {
        $rateLimiter = '\Illuminate\Support\Facades\RateLimiter';
        $limitClass = '\Illuminate\Cache\RateLimiting\Limit';

        if (!class_exists($rateLimiter) || !class_exists($limitClass) || !method_exists($rateLimiter, 'for')) {
            return;
        }

        $readLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_READ', 120);
        $indexLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_INDEX', 90);
        $showLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_SHOW', 180);
        $commentsLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_COMMENTS', 100);
        $writeLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_WRITE', 40);
        $createLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_CREATE', 40);
        $assignLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_ASSIGN', 25);
        $closeLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_CLOSE', 25);
        $replyLimit = $this->resolveRateLimit('TICKETING_RATE_LIMIT_REPLY', 35);

        $rateLimiter::for(
            'ticketing',
            static function (object $request) use (
                $limitClass,
                $readLimit,
                $indexLimit,
                $showLimit,
                $commentsLimit,
                $writeLimit,
                $createLimit,
                $assignLimit,
                $closeLimit,
                $replyLimit
            ): object {
                $resolvedUser = null;

                if (method_exists($request, 'user')) {
                    $resolvedUser = $request->user();
                }

                $resolvedUserId = 0;

                if (is_array($resolvedUser)) {
                    $resolvedUserId = (int) ($resolvedUser['id'] ?? 0);
                } elseif (is_object($resolvedUser) && method_exists($resolvedUser, 'getAuthIdentifier')) {
                    $resolvedUserId = (int) $resolvedUser->getAuthIdentifier();
                } elseif (is_object($resolvedUser) && property_exists($resolvedUser, 'id')) {
                    $resolvedUserId = (int) $resolvedUser->id;
                }

                $requestMethod = method_exists($request, 'getMethod')
                    ? strtoupper((string) $request->getMethod())
                    : '';
                $rawPath = '';

                if (method_exists($request, 'path')) {
                    $rawPath = (string) $request->path();
                } elseif (method_exists($request, 'getPathInfo')) {
                    $rawPath = (string) $request->getPathInfo();
                } elseif (method_exists($request, 'server')) {
                    $rawPath = (string) $request->server('REQUEST_URI', '');
                }

                $normalizedPath = strtolower(trim(strtok($rawPath, '?') ?: '', '/'));
                $normalizedPath = preg_replace('/^api\//', '', $normalizedPath) ?? $normalizedPath;
                $normalizedPath = preg_replace('/^tickets\/[^\/]+\/assign$/', 'tickets/{ticketId}/assign', $normalizedPath) ?? $normalizedPath;
                $normalizedPath = preg_replace('/^tickets\/[^\/]+\/close$/', 'tickets/{ticketId}/close', $normalizedPath) ?? $normalizedPath;
                $normalizedPath = preg_replace('/^tickets\/[^\/]+\/reply$/', 'tickets/{ticketId}/reply', $normalizedPath) ?? $normalizedPath;
                $normalizedPath = preg_replace('/^tickets\/[^\/]+\/comments$/', 'tickets/{ticketId}/comments', $normalizedPath) ?? $normalizedPath;
                $normalizedPath = preg_replace('/^tickets\/[^\/]+$/', 'tickets/{ticketId}', $normalizedPath) ?? $normalizedPath;

                $endpointBucket = 'tickets:read';
                $limitPerMinute = $readLimit;

                if ($requestMethod === 'GET' && $normalizedPath === 'tickets') {
                    $endpointBucket = 'tickets:index';
                    $limitPerMinute = $indexLimit;
                } elseif ($requestMethod === 'GET' && $normalizedPath === 'tickets/{ticketId}') {
                    $endpointBucket = 'tickets:show';
                    $limitPerMinute = $showLimit;
                } elseif ($requestMethod === 'GET' && $normalizedPath === 'tickets/{ticketId}/comments') {
                    $endpointBucket = 'tickets:comments';
                    $limitPerMinute = $commentsLimit;
                } elseif ($requestMethod === 'POST' && $normalizedPath === 'tickets') {
                    $endpointBucket = 'tickets:create';
                    $limitPerMinute = $createLimit;
                } elseif ($requestMethod === 'PATCH' && $normalizedPath === 'tickets/{ticketId}/assign') {
                    $endpointBucket = 'tickets:assign';
                    $limitPerMinute = $assignLimit;
                } elseif ($requestMethod === 'PATCH' && $normalizedPath === 'tickets/{ticketId}/close') {
                    $endpointBucket = 'tickets:close';
                    $limitPerMinute = $closeLimit;
                } elseif ($requestMethod === 'POST' && $normalizedPath === 'tickets/{ticketId}/reply') {
                    $endpointBucket = 'tickets:reply';
                    $limitPerMinute = $replyLimit;
                } elseif (in_array($requestMethod, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
                    $endpointBucket = 'tickets:write';
                    $limitPerMinute = $writeLimit;
                }

                $ip = method_exists($request, 'ip') ? (string) $request->ip() : 'cli';
                $key = sprintf(
                    'ticketing:%s:%s:%s',
                    $resolvedUserId > 0 ? (string) $resolvedUserId : 'guest',
                    $ip,
                    $endpointBucket
                );

                return $limitClass::perMinute($limitPerMinute)->by($key);
            }
        );
    }

    private function resolveRateLimit(string $envKey, int $default): int
    {
        $value = null;

        if ($value === null || $value === false || $value === '') {
            $value = getenv($envKey);
        }

        if (($value === null || $value === false || $value === '') && array_key_exists($envKey, $_ENV)) {
            $value = $_ENV[$envKey];
        }

        if (!is_numeric($value)) {
            return $default;
        }

        $resolved = (int) $value;

        return $resolved > 0 ? $resolved : $default;
    }
}
