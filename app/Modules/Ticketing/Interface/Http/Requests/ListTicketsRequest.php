<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
