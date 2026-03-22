<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\In;

use App\Modules\Ticketing\Application\DTOs\LoginInputDTO;

interface AuthenticateUserUseCase
{
    /**
     * @return array{token:string,token_type:string,user:array{id:int,name:string,email:string,roles:array<int,string>}}
     */
    public function execute(LoginInputDTO $input): array;
}
