<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Requests;

final class CloseTicketRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
