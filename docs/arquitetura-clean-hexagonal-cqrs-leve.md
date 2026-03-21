# Aplicação de Clean Architecture + Hexagonal + CQRS Leve

## 1. Objetivo

Definir um modelo arquitetural para um novo sistema de tickets em PHP, com foco em escalabilidade, testabilidade e baixo acoplamento.

Escopo funcional base:

- abrir ticket;
- listar e detalhar tickets;
- atribuir atendente;
- responder ticket;
- fechar ticket;
- autenticação e autorização.

## 2. Decisão Arquitetural

A estratégia recomendada é:

- **Clean Architecture** para organizar regras por camadas;
- **Hexagonal Architecture (Ports and Adapters)** para isolar o domínio de frameworks;
- **CQRS leve** para separar escrita e leitura sem entrar em complexidade excessiva.

Essa combinação permite começar simples e crescer sem reestruturações profundas.

## 3. Princípios que devem guiar a implementação

- Domínio não depende de Laravel, Eloquent, Redis ou HTTP.
- Casos de uso coordenam regras de aplicação e transações.
- Infraestrutura implementa contratos definidos pela aplicação.
- Controllers apenas traduzem HTTP para DTO e retornam Resource.
- Leitura e escrita possuem fluxos separados quando houver ganho real.

## 4. Estrutura de pastas sugerida

```text
app/
  Modules/
    Ticketing/
      Domain/
        Entities/
        ValueObjects/
        Enums/
        Exceptions/
        Events/
      Application/
        DTOs/
        Commands/
        CommandHandlers/
        Queries/
        QueryHandlers/
        Ports/
          In/
          Out/
      Infrastructure/
        Persistence/
          Eloquent/
          Mappers/
        Cache/
        Queue/
        Providers/
      Interface/
        Http/
          Controllers/
          Requests/
          Resources/
```

## 5. Responsabilidade por camada

### 5.1 Domain

- Entidades: `Ticket`, `TicketComment`.
- Regras puras: transição de status e invariantes.
- Eventos de domínio: `TicketCreated`, `TicketAssigned`, `TicketClosed`.
- Exceções de negócio: estado inválido, acesso inválido, conflito de transição.

### 5.2 Application

- Casos de uso orientados a ação.
- DTOs de entrada e saída.
- Ports de saída para persistência, cache e eventos.
- Orquestração de transação e políticas de autorização de aplicação.

### 5.3 Infrastructure

- Repositórios Eloquent implementando portas de saída.
- Publicação de eventos em fila.
- Cache Redis para consultas.
- Integração com notificações e auditoria.

### 5.4 Interface (HTTP)

- FormRequests para validação.
- Controllers finos delegando para Command/Query Handlers.
- API Resources para resposta padronizada.

## 6. CQRS leve na prática

Aplique separação apenas onde existe benefício.

### Escrita (Commands)

- `CreateTicketCommand`
- `AssignTicketCommand`
- `ReplyTicketCommand`
- `CloseTicketCommand`

Cada comando altera estado e pode disparar eventos.

### Leitura (Queries)

- `ListTicketsQuery`
- `GetTicketDetailsQuery`
- `ListTicketCommentsQuery`

Consultas podem usar projeções otimizadas e cache.

### Regra de ouro do CQRS leve

- Não duplicar modelo sem necessidade.
- Começar com um banco e uma base transacional.
- Introduzir read models dedicados somente quando houver gargalo real.

## 7. Fluxos recomendados

### 7.1 Criar ticket

Controller -> `CreateTicketCommandHandler` -> `TicketRepositoryPort` -> Event `TicketCreated`

### 7.2 Atribuir ticket

Controller -> `AssignTicketCommandHandler` -> regra de domínio de transição -> persistência -> Event `TicketAssigned`

### 7.3 Responder ticket

Controller -> `ReplyTicketCommandHandler` -> valida vínculo/permite resposta -> salva comentário -> Event `TicketReplied`

### 7.4 Fechar ticket

Controller -> `CloseTicketCommandHandler` -> valida estado -> fecha -> Event `TicketClosed`

### 7.5 Listagens

Controller -> `ListTicketsQueryHandler` -> projeção com filtros/paginação -> cache opcional.

## 8. Contratos essenciais (Ports)

### 8.1 Ports de saída

- `TicketRepositoryPort`
- `TicketCommentRepositoryPort`
- `UserReadRepositoryPort`
- `EventBusPort`
- `CachePort`

### 8.2 Ports de entrada

- `CreateTicketUseCase`
- `AssignTicketUseCase`
- `ReplyTicketUseCase`
- `CloseTicketUseCase`
- `ListTicketsUseCase`

## 9. Modelagem de dados inicial

### 9.1 Tabela `tickets`

- `id`
- `requester_id`
- `assignee_id`
- `status` (`open`, `pending`, `closed`)
- `title`
- `description`
- `last_reply_at`
- `closed_at`
- `created_at`
- `updated_at`

### 9.2 Tabela `ticket_comments`

- `id`
- `ticket_id`
- `author_id`
- `message`
- `created_at`
- `updated_at`

### 9.3 Índices obrigatórios

- `(status, assignee_id)`
- `(requester_id, created_at)`
- `(ticket_id, created_at)`

## 10. Docker, cache e mensageria

## 10.1 Docker

Recomendado desde o início:

- `app` (PHP-FPM)
- `nginx`
- `postgres` ou `mysql`
- `redis`
- `queue-worker`

## 10.2 Cache

Use Redis para:

- listagens com filtros recorrentes;
- throttling/rate limit;
- lock de atualização concorrente em ticket.

## 10.3 Mensageria

Para este estágio:

- fila Laravel com Redis já é suficiente.

Evolua para broker dedicado quando houver:

- múltiplos serviços consumidores;
- alta taxa de eventos;
- necessidade forte de replay e integração.

## 11. Estratégia de testes

- **Domínio:** testes unitários puros para regras de transição.
- **Aplicação:** testes de handlers com mocks dos ports.
- **Infraestrutura:** testes de integração dos adapters Eloquent.
- **Interface:** testes de feature para autenticação, autorização e contratos HTTP.

Cobertura mínima:

- fluxo feliz;
- transição inválida de status;
- tentativa sem permissão;
- conflito de concorrência.

## 12. Roadmap de implementação

### Fase 1

- estruturar pastas e contratos;
- implementar comandos críticos;
- entregar endpoints de escrita e leitura básica.

### Fase 2

- adicionar eventos/listeners assíncronos;
- incluir cache em consultas de maior volume;
- reforçar cobertura de testes em domínio e aplicação.

### Fase 3

- otimizar read side para consultas avançadas;
- aprimorar observabilidade;
- preparar extração de módulo como serviço independente, se necessário.

## 13. Benefícios e trade-offs

### Benefícios

- Alta testabilidade.
- Baixo acoplamento com framework.
- Evolução segura por contratos.
- Escalabilidade progressiva sem ruptura.

### Trade-offs

- Mais classes e maior disciplina de projeto.
- Curva de aprendizado inicial maior.
- Overhead desnecessário se o escopo for extremamente simples.

## 14. Conclusão

Para seu próximo projeto de portfólio, essa é a arquitetura com melhor custo-benefício técnico:

- mantém o sistema em formato prático de entrega;
- eleva claramente seu nível arquitetural;
- demonstra maturidade de engenharia para vagas de nível pleno/sênior.
