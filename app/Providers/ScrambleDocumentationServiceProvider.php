<?php

declare(strict_types=1);


namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

/**
 * Provider responsável por customizar documentação OpenAPI.
 *
 * Registra esquema de segurança `sanctum` para padronizar
 * autenticação Bearer nos endpoints protegidos.
 *
 * @author David Marques
 */
final class ScrambleDocumentationServiceProvider extends ServiceProvider
{
    /**
     * Inicializa hooks de geração de documentação.
     *
     * @return void
     *
     * @author David Marques
     */
    public function boot(): void
    {
        Scramble::afterOpenApiGenerated(static function (OpenApi $openApi): void {
            $openApi->components->addSecurityScheme(
                'sanctum',
                SecurityScheme::http('bearer')->as('sanctum')
            );
        });
    }
}
