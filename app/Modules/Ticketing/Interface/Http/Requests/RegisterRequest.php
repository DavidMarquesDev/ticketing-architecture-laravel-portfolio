<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de validação para cadastro de usuário.
 *
 * Centraliza regras de entrada do endpoint público de registro.
 *
 * @author David Marques
 */
final class RegisterRequest extends FormRequest
{
    /**
     * Define autorização do request de cadastro.
     *
     * ## 🔐 Regras de Acesso
     * - ✅ Permitido para usuários autenticados e não autenticados.
     *
     * @return bool
     *
     * @author David Marques
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação de entrada para cadastro.
     *
     * ## 📥 Campos Esperados
     * - `name`: obrigatório, string, máximo de 120 caracteres.
     * - `email`: obrigatório, e-mail válido, único em `users`.
     * - `password`: obrigatória, string, entre 8 e 120 caracteres.
     *
     * @return array<string, array<int, string>|string>
     *
     * @author David Marques
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:120'],
        ];
    }

    /**
     * Mensagens de validação específicas para cadastro público.
     *
     * @return array<string, string>
     *
     * @author David Marques
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'name.string' => 'O nome deve ser um texto válido.',
            'name.max' => 'O nome deve ter no máximo 120 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail deve ter no máximo 190 caracteres.',
            'email.unique' => 'Este e-mail já está cadastrado.',
            'password.required' => 'A senha é obrigatória.',
            'password.string' => 'A senha deve ser um texto válido.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.max' => 'A senha deve ter no máximo 120 caracteres.',
        ];
    }
}
