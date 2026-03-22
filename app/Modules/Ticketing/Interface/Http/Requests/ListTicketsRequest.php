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

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'in:open,pending,closed'],
            'requester_id' => ['sometimes', 'integer', 'min:1'],
            'assignee_id' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string', 'max:120'],
            'sort_by' => ['sometimes', 'string', 'in:id,status,title,requester_id,assignee_id'],
            'sort_dir' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
