<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CloseTicketRequest extends FormRequest
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
