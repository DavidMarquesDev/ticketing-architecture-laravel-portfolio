<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

interface UserAuthenticationRepositoryPort
{
    /**
     * @return array{id:int,name:string,email:string,roles:array<int,string>,password_hash:string}|null
     */
    public function findByEmail(string $email): ?array;
}
