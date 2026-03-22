<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para validação de atualização de papéis de usuário.
 *
 * @author David Marques
 */
final class UpdateUserRolesRequest extends FormRequest
{
    /**
     * @return bool
     *
     * @author David Marques
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     *
     * @author David Marques
     */
    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'in:admin,agent,customer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     *
     * @author David Marques
     */
    public function messages(): array
    {
        return [
            'roles.required' => 'Os papéis são obrigatórios.',
            'roles.array' => 'Os papéis devem ser enviados em uma lista.',
            'roles.min' => 'Informe ao menos um papel.',
            'roles.*.required' => 'Cada papel da lista é obrigatório.',
            'roles.*.string' => 'Cada papel deve ser um texto válido.',
            'roles.*.in' => 'Papel inválido. Valores permitidos: admin, agent, customer.',
            'roles.*.distinct' => 'Não repita papéis na lista.',
        ];
    }
}
