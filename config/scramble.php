<?php

declare(strict_types=1);

/**
 * Configuração da documentação de API via Scramble.
 *
 * ## 🔐 Segurança
 * - A extensão `SanctumSecurityOperationExtension` injeta requisitos
 *   de autenticação nas rotas protegidas por middleware Sanctum.
 *
 * @return array{
 *     api_path:string,
 *     middleware:array<int,string>,
 *     extensions:array<int,class-string>
 * }
 *
 * @author David Marques
 */
return [
    'api_path' => 'api',
    'middleware' => [
        'api',
    ],
    'extensions' => [
        App\Modules\Ticketing\Interface\Http\Documentation\SanctumSecurityOperationExtension::class,
    ],
];
