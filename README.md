# Ticketing Architecture Laravel Portfolio

Projeto de portfólio focado em arquitetura de software com Laravel, aplicando:

- Clean Architecture
- Hexagonal Architecture
- CQRS leve
- DDD tático
- SOLID
- Redis (cache e fila)
- RabbitMQ (mensageria)
- Docker (ambiente reprodutível)

## Pré-requisitos

- PHP 8.2+
- Composer 2+
- MySQL 8+
- Redis 7+
- Docker e Docker Compose (opcional)

## Setup local

1. Instale dependências:
   - `composer install`
2. Gere arquivo de ambiente:
   - copie `.env.example` para `.env`
3. Gere chave da aplicação:
   - `php artisan key:generate`
4. Execute migrações:
   - `php artisan migrate`
5. Popule usuários de teste manual:
   - `php artisan db:seed --class=Database\\Seeders\\ManualTestUsersSeeder`
6. Suba API:
   - `php artisan serve --host=0.0.0.0 --port=8000`
7. Suba worker de fila:
   - `php artisan queue:work --sleep=1 --tries=3`

## Setup com Docker

1. `docker compose up -d --build`
2. `docker compose exec app php artisan key:generate`
3. `docker compose exec app php artisan migrate`
4. `docker compose exec app php artisan db:seed --class=Database\\Seeders\\ManualTestUsersSeeder`

## Login para testes manuais

Endpoint público:

- `POST /api/auth/login`

Payload:

```json
{
    "email": "admin@example.com",
    "password": "password123"
}
```

Usuários seeded:

- `admin@example.com` com role `admin`
- `agent@example.com` com role `agent`
- `customer@example.com` com role `customer`
- senha para todos: `password123`

## Fluxo mínimo de teste manual

1. Autenticar em `/api/auth/login`.
2. Chamar `POST /api/tickets` com Bearer Token.
3. Chamar `GET /api/tickets` e validar `data` + `meta`.
4. Chamar `PATCH /api/tickets/{id}/assign` com usuário `agent/admin`.
5. Chamar `POST /api/tickets/{id}/reply`.
6. Chamar `PATCH /api/tickets/{id}/close`.
7. Chamar `GET /api/tickets/{id}/comments`.

## Qualidade e validação

- Lint dos arquivos principais:
  - `composer run lint:files`
- Suíte de testes existente:
  - `composer test`
- Análise estática:
  - `composer run phpstan`
