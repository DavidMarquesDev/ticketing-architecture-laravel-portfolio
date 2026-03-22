<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\DTOs;

final class LoginInputDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {
    }
}
