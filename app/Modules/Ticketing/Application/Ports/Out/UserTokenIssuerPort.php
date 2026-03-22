<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface UserTokenIssuerPort
{
    public function issue(int $userId, string $tokenName): string;
}
