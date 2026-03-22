# Ticketing Architecture Laravel Portfolio

[🇺🇸 English](./README.en.md) | 🇧🇷 Português

API REST de tickets desenvolvida como projeto de portfólio para demonstrar arquitetura de software com Laravel.

## Sumário

- [Visão Geral](#visão-geral)
- [Objetivo da API](#objetivo-da-api)
- [Arquitetura e Padrões](#arquitetura-e-padrões)
- [Tecnologias e Bibliotecas](#tecnologias-e-bibliotecas)
- [Estrutura de Pastas](#estrutura-de-pastas)
- [Módulos e Capacidades](#módulos-e-capacidades)
- [Fluxo de Requisição](#fluxo-de-requisição)
- [Autenticação e Autorização](#autenticação-e-autorização)
- [Endpoints](#endpoints)
- [Como Rodar o Projeto](#como-rodar-o-projeto)
- [Comandos Úteis](#comandos-úteis)
- [Testes e Qualidade](#testes-e-qualidade)
- [Troubleshooting](#troubleshooting)

## Visão Geral

O projeto implementa uma API em `/api` com foco em:

- Clean Architecture e abordagem Hexagonal;
- separação por camadas (Interface, Application, Domain e Infrastructure);
- CQRS leve com handlers de comando e consulta;
- autenticação com Sanctum;
- autorização por políticas e abilities;
- observabilidade com logs estruturados e telemetria de consultas;
- cache e fila com Redis.

## Objetivo da API

Fornecer uma base robusta para operações de atendimento e gestão de tickets, cobrindo:

- cadastro e autenticação de usuários;
- abertura, listagem, detalhamento e comentários de tickets;
- atribuição, resposta e fechamento com regras de negócio;
- promoção de papéis de usuário por endpoint protegido;
- processamento assíncrono de eventos de ciclo de vida de tickets.

## Arquitetura e Padrões

### Camadas

- **Interface:** Controllers, FormRequests, Policies, Resources e documentação de API.
- **Application:** Use Cases, DTOs, Command Handlers, Query Handlers e Ports.
- **Domain:** Entidades, enums, eventos de domínio e exceções de negócio.
- **Infrastructure:** Repositórios Eloquent/InMemory, cache/fila, autenticação e observabilidade.

### Padrões aplicados

- **Service/Use Case Layer**
- **Repository Pattern com Ports**
- **DTO Pattern**
- **Event-Driven interno com listeners e jobs**
- **Dependency Inversion com binding em Service Provider**

## Tecnologias e Bibliotecas

- PHP 8.2+
- Laravel 11
- Laravel Sanctum
- Redis (cache e fila)
- DeDoc Scramble (OpenAPI)
- PHPStan
- PHP_CodeSniffer

Observação de escopo atual:

- RabbitMQ aparece como direção arquitetural, mas o runtime atual usa Redis para mensageria/fila.

## Estrutura de Pastas

```text
app/
├─ Models/
├─ Modules/
│  └─ Ticketing/
│     ├─ Application/
│     ├─ Domain/
│     ├─ Infrastructure/
│     └─ Interface/
└─ Providers/

config/
routes/
database/
tests/
```

## Módulos e Capacidades

### Auth

- registro de usuário;
- login com emissão de token;
- papéis iniciais de usuário.

### Ticketing

- criação e consulta de tickets;
- comentários por ticket;
- operações de atribuição, resposta e fechamento;
- rate limit por endpoint e método;
- publicação assíncrona de jobs de auditoria e integração.

### Gestão de Papéis

- endpoint protegido para atualizar roles de usuários;
- acesso restrito a administradores.

## Fluxo de Requisição

Fluxo padrão de endpoint protegido:

1. rota recebe request com `auth:sanctum` e `throttle:ticketing`;
2. FormRequest valida entrada;
3. Controller coordena a operação e monta DTO;
4. Use Case aplica regra de negócio;
5. Port de saída delega à infraestrutura;
6. Repository persiste/consulta com Eloquent;
7. Controller retorna payload padronizado.

## Autenticação e Autorização

### Autenticação

- Bearer Token com Sanctum.
- Header esperado:

```bash
Authorization: Bearer {seu_token}
```

### Autorização

- Policies/Gate para abilities de ticket e gestão de usuários.
- Exemplo de ability crítica:
  - `user.roles.update`

## Endpoints

Base URL local sugerida: `http://127.0.0.1:8000/api`

### Públicos

- `POST /auth/register`
- `POST /auth/login`

### Protegidos

- `GET /tickets`
- `GET /tickets/{ticketId}`
- `GET /tickets/{ticketId}/comments`
- `POST /tickets`
- `PATCH /tickets/{ticketId}/assign`
- `POST /tickets/{ticketId}/reply`
- `PATCH /tickets/{ticketId}/close`
- `PATCH /users/{userId}/roles`

## Como Rodar o Projeto

### Pré-requisitos

- PHP 8.2+
- Composer 2+
- MySQL 8+
- Redis 7+ (local ou container)
- Docker e Docker Compose (opcional)

### Instalação local

1) Instalar dependências:

```bash
composer install
```

2) Criar `.env`:

```bash
cp .env.example .env
```

No Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

3) Gerar chave:

```bash
php artisan key:generate
```

4) Rodar migrações:

```bash
php artisan migrate
```

5) Popular usuários de teste:

```bash
php artisan db:seed --class=Database\\Seeders\\ManualTestUsersSeeder
```

6) Subir API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

7) Subir worker da fila:

```bash
php artisan queue:work --sleep=1 --tries=3
```

### Setup com Docker

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=Database\\Seeders\\ManualTestUsersSeeder
```

## Comandos Úteis

- listar rotas:

```bash
php artisan route:list
```

- validar sintaxe dos arquivos principais:

```bash
composer run lint:files
```

- análise estática:

```bash
composer run phpstan
```

- executar suíte de testes:

```bash
composer test
```

## Testes e Qualidade

- suíte customizada em `tests/RunAllTests.php`;
- testes unitários e de integração para camadas de aplicação e infraestrutura;
- validação estática com PHPStan;
- validação de sintaxe com scripts de lint.

## Troubleshooting

### Redis não conecta em `127.0.0.1:6379`

- subir Redis localmente ou via Docker;
- verificar `QUEUE_CONNECTION=redis` no `.env`;
- confirmar `REDIS_HOST` e `REDIS_PORT`.

### Docker Desktop no Windows com erro de Virtual Machine Platform

- habilitar o recurso do Windows:

```powershell
Enable-WindowsOptionalFeature -Online -FeatureName VirtualMachinePlatform -All
```

- reiniciar a máquina antes de abrir o Docker Desktop.

### Erro 401/403 nas rotas protegidas

- validar Bearer Token;
- validar role do usuário autenticado;
- revisar abilities/policies aplicadas ao endpoint.
