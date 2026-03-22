<?php

declare(strict_types=1);


namespace App\Modules\Ticketing\Application\Ports\Out;

/**
 * Contrato de persistência para operações de cadastro de usuário.
 *
 * Abstrai a camada de infraestrutura para manter o caso de uso
 * desacoplado da tecnologia de banco de dados.
 *
 * @author David Marques
 */
interface UserRegistrationRepositoryPort
{
    /**
     * Verifica se já existe usuário com o e-mail informado.
     *
     * ## 🎯 Objetivo
     * - Evitar conflito de unicidade antes da escrita.
     *
     * @param string $email E-mail a ser consultado.
     * @return bool
     *
     * @author David Marques
     */
    public function existsByEmail(string $email): bool;

    /**
     * Cria um novo usuário e retorna os dados normalizados.
     *
     * ## 📥 Entrada
     * - Nome, e-mail, senha e perfis do usuário.
     *
     * ## 📤 Saída
     * - Estrutura normalizada para retorno ao serviço de aplicação.
     *
     * @param string $name Nome completo do usuário.
     * @param string $email E-mail do usuário.
     * @param string $password Senha em texto plano para persistência com cast hashed.
     * @param array<int, string> $roles
     * @return array{id:int,name:string,email:string,roles:array<int,string>}
     *
     * @author David Marques
     */
    public function create(string $name, string $email, string $password, array $roles): array;
}
