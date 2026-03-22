# Ticketing Architecture Laravel Portfolio

🇺🇸 English | [🇧🇷 Português](./README.pt-BR.md)

REST API for ticket management built as a portfolio project to demonstrate software architecture with Laravel.

## Summary

- [Overview](#overview)
- [API Goal](#api-goal)
- [Architecture and Patterns](#architecture-and-patterns)
- [Technologies and Libraries](#technologies-and-libraries)
- [Folder Structure](#folder-structure)
- [Modules and Capabilities](#modules-and-capabilities)
- [Request Flow](#request-flow)
- [Authentication and Authorization](#authentication-and-authorization)
- [Endpoints](#endpoints)
- [How to Run the Project](#how-to-run-the-project)
- [Useful Commands](#useful-commands)
- [Testing and Quality](#testing-and-quality)
- [Troubleshooting](#troubleshooting)

## Overview

The project exposes an API under `/api` focused on:

- Clean Architecture with a Hexagonal approach;
- layered separation (Interface, Application, Domain, and Infrastructure);
- lightweight CQRS with command/query handlers;
- Sanctum-based authentication;
- policy/ability-based authorization;
- structured logging and query telemetry;
- Redis for cache and queue.

## API Goal

Provide a robust foundation for support and ticket operations, including:

- user registration and authentication;
- ticket creation, listing, details, and comments;
- assignment, reply, and close actions with business rules;
- protected user role updates;
- asynchronous processing of ticket lifecycle events.

## Architecture and Patterns

### Layers

- **Interface:** Controllers, FormRequests, Policies, Resources, and API documentation.
- **Application:** Use Cases, DTOs, Command Handlers, Query Handlers, and Ports.
- **Domain:** Entities, enums, domain events, and business exceptions.
- **Infrastructure:** Eloquent/InMemory repositories, cache/queue, authentication, and observability.

### Applied patterns

- **Service/Use Case Layer**
- **Repository Pattern with Ports**
- **DTO Pattern**
- **Internal Event-Driven flow with listeners/jobs**
- **Dependency Inversion via Service Provider bindings**

## Technologies and Libraries

- PHP 8.2+
- Laravel 11
- Laravel Sanctum
- Redis (cache and queue)
- DeDoc Scramble (OpenAPI)
- PHPStan
- PHP_CodeSniffer

Current scope note:

- RabbitMQ exists as an architectural direction, but the current runtime uses Redis for messaging/queue.

## Folder Structure

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

## Modules and Capabilities

### Auth

- user registration;
- login with token issuance;
- initial user roles.

### Ticketing

- ticket creation and querying;
- ticket comments;
- assign/reply/close operations;
- endpoint-aware rate limiting;
- asynchronous audit/integration job publishing.

### Role Management

- protected endpoint for user role updates;
- restricted access for administrators.

## Request Flow

Standard protected endpoint flow:

1. route receives request with `auth:sanctum` and `throttle:ticketing`;
2. FormRequest validates input;
3. Controller coordinates and builds DTO;
4. Use Case applies business rules;
5. output Port delegates to infrastructure;
6. Repository persists/queries using Eloquent;
7. Controller returns standardized payload.

## Authentication and Authorization

### Authentication

- Sanctum Bearer token.
- Expected header:

```bash
Authorization: Bearer {your_token}
```

### Authorization

- Policies/Gate for ticket and user management abilities.
- Critical ability example:
  - `user.roles.update`

## Endpoints

Suggested local base URL: `http://127.0.0.1:8000/api`

### Public

- `POST /auth/register`
- `POST /auth/login`

### Protected

- `GET /tickets`
- `GET /tickets/{ticketId}`
- `GET /tickets/{ticketId}/comments`
- `POST /tickets`
- `PATCH /tickets/{ticketId}/assign`
- `POST /tickets/{ticketId}/reply`
- `PATCH /tickets/{ticketId}/close`
- `PATCH /users/{userId}/roles`

## How to Run the Project

### Prerequisites

- PHP 8.2+
- Composer 2+
- MySQL 8+
- Redis 7+ (local or container)
- Docker and Docker Compose (optional)

### Local setup

1) Install dependencies:

```bash
composer install
```

2) Create `.env`:

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

3) Generate app key:

```bash
php artisan key:generate
```

4) Run migrations:

```bash
php artisan migrate
```

5) Seed manual test users:

```bash
php artisan db:seed --class=Database\\Seeders\\ManualTestUsersSeeder
```

6) Start API:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

7) Start queue worker:

```bash
php artisan queue:work --sleep=1 --tries=3
```

### Docker setup

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=Database\\Seeders\\ManualTestUsersSeeder
```

## Useful Commands

- list routes:

```bash
php artisan route:list
```

- validate syntax of main files:

```bash
composer run lint:files
```

- static analysis:

```bash
composer run phpstan
```

- run test suite:

```bash
composer test
```

## Testing and Quality

- custom test suite in `tests/RunAllTests.php`;
- unit and integration tests for application/infrastructure layers;
- static analysis with PHPStan;
- syntax validation through lint scripts.

## Troubleshooting

### Redis not connecting to `127.0.0.1:6379`

- start Redis locally or via Docker;
- check `QUEUE_CONNECTION=redis` in `.env`;
- verify `REDIS_HOST` and `REDIS_PORT`.

### Docker Desktop on Windows with Virtual Machine Platform error

- enable Windows feature:

```powershell
Enable-WindowsOptionalFeature -Online -FeatureName VirtualMachinePlatform -All
```

- restart the machine before opening Docker Desktop.

### 401/403 on protected routes

- validate Bearer token;
- validate authenticated user role;
- review endpoint abilities/policies.
