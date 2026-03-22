<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'author_id' => ['required', 'integer', 'min:1'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
