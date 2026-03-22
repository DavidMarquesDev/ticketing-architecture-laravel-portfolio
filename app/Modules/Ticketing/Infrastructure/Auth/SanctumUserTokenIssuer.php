<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Infrastructure\Auth;

use App\Models\User;
use App\Modules\Ticketing\Application\Ports\Out\UserTokenIssuerPort;
use RuntimeException;

final class SanctumUserTokenIssuer implements UserTokenIssuerPort
{
    public function issue(int $userId, string $tokenName): string
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            throw new RuntimeException('Usuário não encontrado para emissão de token.');
        }

        if (!method_exists($user, 'createToken')) {
            throw new RuntimeException('Sanctum indisponível para emissão de token.');
        }

        return (string) $user->createToken($tokenName)->plainTextToken;
    }
}
