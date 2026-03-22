<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Documentation;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\RouteInfo;

/**
 * Extensão do Scramble para aplicar segurança Sanctum automaticamente.
 *
 * Analisa middlewares das rotas e adiciona o requisito `Bearer`
 * na documentação OpenAPI quando `auth:sanctum` estiver presente.
 *
 * @author David Marques
 */
final class SanctumSecurityOperationExtension extends OperationExtension
{
    /**
     * Processa a operação OpenAPI e injeta segurança conforme middleware.
     *
     * @param Operation $operation Operação OpenAPI em construção.
     * @param RouteInfo $routeInfo Metadados da rota analisada.
     * @return void
     *
     * @example
     * Para rota com middleware `auth:sanctum`, o schema recebe:
     * security:
     *   - sanctum: []
     *
     * @author David Marques
     */
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $middlewares = method_exists($routeInfo->route, 'gatherMiddleware')
            ? $routeInfo->route->gatherMiddleware()
            : [];

        foreach ($middlewares as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }

            if ($middleware === 'auth:sanctum' || str_starts_with($middleware, 'auth:sanctum,')) {
                $operation->addSecurity(new SecurityRequirement(['sanctum' => []]));

                return;
            }
        }
    }
}
