# Análise Arquitetural e Proposta de Evolução — Ticketing

## 1. Objetivo da análise

Este documento analisa a proposta em `docs/arquitetura-clean-hexagonal-cqrs-leve.md` e apresenta melhorias para manter a solução:

- simples na execução do dia a dia;
- sólida internamente para demonstrar maturidade técnica;
- aderente a Clean Code, SOLID, DDD tático e boas práticas Laravel.

---

## 2. Diagnóstico da proposta atual

## 2.1 Pontos fortes

- Combinação correta de **Clean Architecture + Hexagonal + CQRS leve**.
- Boa separação inicial entre **escrita (commands)** e **leitura (queries)**.
- Preocupação explícita com **baixo acoplamento** e **testabilidade**.
- Fluxos funcionais essenciais já definidos (criar, atribuir, responder, fechar).
- Direcionamento para eventos de domínio e processamento assíncrono.

## 2.2 Riscos de implementação

- Complexidade estrutural pode crescer rápido se todos os conceitos forem aplicados ao mesmo tempo.
- Fronteira entre **Application** e **Domain** pode ficar difusa sem contratos claros.
- CQRS pode virar duplicação de código se não houver critério objetivo para separar read/write.
- Falta de definição de convenções transversais (erros, logs, idempotência, transação) pode gerar inconsistência.

---

## 3. Melhorias recomendadas

## 3.1 Diretriz macro: modular monolith orientado a domínio

Manter monólito modular, com isolamento por contexto de negócio, evita complexidade prematura e preserva caminho de evolução.

Contextos recomendados:

- **IAM** (identidade, autenticação e autorização);
- **Ticketing** (ciclo de vida do ticket);
- **Collaboration** (comentários e interação);
- **Notification** (notificações assíncronas).

## 3.2 Camadas e responsabilidades (regra de ouro)

- **Interface/Presentation**: Controller recebe request, mapeia para DTO, retorna Resource.
- **Application**: Use Cases/Handlers coordenam regra de negócio, autorização de aplicação e transações.
- **Domain**: Entidades, Value Objects, Domain Services, regras invariantes e eventos de domínio.
- **Infrastructure**: Implementações de portas (repositórios, cache, mensageria, fila, integrações externas).

Regra obrigatória: dependências sempre do externo para o interno via abstrações.

## 3.3 CQRS leve com critério explícito

Aplicar separação de leitura/escrita somente quando houver pelo menos um destes gatilhos:

- consulta com múltiplos filtros e paginação avançada;
- volume alto com necessidade de cache dedicado;
- necessidade de projeção diferente do modelo transacional.

Se não houver gatilho, manter query no mesmo módulo com repositório de leitura simples.

## 3.4 Governança de contratos

Definir contratos mínimos para estabilidade:

- **Input DTOs** por caso de uso;
- **Output DTOs** para respostas internas;
- **Resource** para contrato HTTP;
- **Ports Out** para persistência/eventos/cache.

Evitar arrays livres entre camadas.

## 3.5 Consistência transacional e eventos

Para comandos de escrita:

1. validar invariantes;
2. persistir em transação;
3. registrar evento de domínio;
4. publicar evento de integração após commit.

Padrão recomendado: **Outbox Pattern** para garantir confiabilidade de publicação sem perder simplicidade.

## 3.6 Tratamento de erros padronizado

Padronizar exceções por tipo:

- `ValidationException` -> 422;
- `AuthorizationException` -> 403;
- `DomainConflictException` -> 409;
- `EntityNotFoundException` -> 404.

Retornar payload único de erro com `code`, `message`, `details`, `trace_id`.

## 3.7 Observabilidade mínima desde o início

- Logs estruturados com `trace_id`, `user_id`, `ticket_id`.
- Métricas por caso de uso (latência, taxa de erro, taxa de conflito).
- Auditoria assíncrona para mudanças críticas de status.

---

## 4. Estrutura de pastas proposta (simples e escalável)

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
        Services/
      Application/
        DTOs/
        UseCases/
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
          Repositories/
        Messaging/
        Cache/
        Providers/
      Interface/
        Http/
          Controllers/
          Requests/
          Resources/
    IAM/
    Collaboration/
    Notification/
```

Observação prática: para MVP, `IAM` e `Collaboration` podem iniciar dentro de `Ticketing` e serem extraídos depois.

---

## 5. Definição de domínios e responsabilidades

## 5.1 Ticketing (núcleo)

Responsável por:

- abrir ticket;
- atribuir atendente;
- controlar transição de status;
- fechar ticket.

Regras centrais:

- transições válidas de status;
- integridade de atribuição;
- proteção contra concorrência otimista.

## 5.2 Collaboration

Responsável por:

- resposta/comentário em ticket;
- histórico de interações;
- atualização de `last_reply_at`.

## 5.3 IAM

Responsável por:

- autenticação e emissão de token;
- autorização por policy/ability;
- contexto de usuário autenticado.

## 5.4 Notification

Responsável por:

- listeners de eventos de domínio;
- envio assíncrono de e-mail/webhook/notificação interna.

---

## 6. Padrões de projeto recomendados

Aplicar apenas onde agrega clareza:

- **Repository Pattern**: persistência desacoplada por interface.
- **Service Layer / Use Case**: regras de negócio fora do Controller.
- **Factory Pattern**: criação de entidades complexas com invariantes.
- **Observer/Event-Driven**: reação assíncrona a mudança de estado.
- **Specification (opcional)**: filtros complexos em leitura.

Evitar abstrações desnecessárias em operações CRUD triviais.

---

## 7. Estratégia de testes

## 7.1 Pirâmide recomendada

- **Domínio (unitário puro):** valida invariantes e máquina de estados.
- **Aplicação (unitário com mocks):** handlers e orquestração de portas.
- **Infraestrutura (integração):** repositórios Eloquent, índices, eager loading e paginação.
- **Interface (feature):** contrato HTTP, autenticação, autorização, validação e payload Resource.

## 7.2 Cenários mínimos obrigatórios

- fluxo feliz completo por caso de uso;
- transição de estado inválida;
- tentativa sem permissão;
- conflito de concorrência;
- validação de entrada inválida.

## 7.3 Critérios de qualidade

- cobertura orientada a risco, não a percentual absoluto;
- testes de serviço e domínio como prioridade;
- testes de integração para adapters críticos;
- execução de linter e análise estática no CI.

---

## 8. Trade-offs da arquitetura proposta

## 8.1 Benefícios

- alta legibilidade e baixo acoplamento;
- manutenção previsível;
- extensão por novos casos de uso sem quebrar fluxo existente;
- evolução para escala horizontal com risco controlado.

## 8.2 Custos

- mais arquivos e contratos para operações simples;
- necessidade de disciplina de equipe;
- onboarding inicial mais técnico.

## 8.3 Mitigação

- aplicar arquitetura completa somente no domínio crítico;
- manter CRUD simples com menos camadas quando não houver regra de negócio;
- usar roadmap incremental por fases.

---

## 9. Plano de adoção incremental

## Fase 1 — Base sólida

- estruturar módulos e camadas;
- implantar comandos e queries essenciais;
- padronizar erros e Resources;
- cobrir fluxo principal com testes.

## Fase 2 — Escala controlada

- incluir eventos/listeners assíncronos;
- adicionar cache nas consultas de maior volume;
- implantar outbox em operações críticas.

## Fase 3 — Evolução orientada por métrica

- otimizar read models conforme gargalo real;
- separar contextos em módulos mais independentes;
- reforçar observabilidade e governança de arquitetura.

---

## 10. Stack tecnológica para portfólio (foco em demonstração)

Como o objetivo principal é evidenciar domínio técnico para recrutadores, a estratégia recomendada é demonstrar as tecnologias em cenários reais, mesmo sem produção.

## 10.1 Tecnologias recomendadas

- **API e domínio:** Laravel 12 + PHP 8.2.
- **Banco transacional:** PostgreSQL.
- **Cache:** Redis.
- **Fila assíncrona inicial:** Laravel Queue com driver Redis.
- **Mensageria avançada para portfólio:** RabbitMQ em fluxos selecionados.
- **Observabilidade:** logs estruturados + métricas básicas.

## 10.2 Como demonstrar Redis no projeto

- cache de listagens paginadas com invalidação por evento;
- rate limit em endpoints públicos;
- lock distribuído para evitar corrida em operações críticas;
- fila de jobs para notificações e auditoria assíncrona.

## 10.3 Como demonstrar RabbitMQ sem complexidade excessiva

- publicar evento de integração em casos de uso críticos;
- criar pelo menos dois consumidores independentes;
- mostrar retry e dead-letter queue;
- documentar claramente quando usar Redis Queue e quando usar RabbitMQ.

## 10.4 Critério de leitura para recrutadores

O projeto deve deixar explícito:

- decisão arquitetural por fase;
- trade-off entre simplicidade e robustez;
- evidência de uso de cache, fila e mensageria;
- testes cobrindo fluxos de sucesso e falha;
- documentação de fluxo ponta a ponta.

## 10.5 Docker para ambiente de estudo e demonstração

Sim, a recomendação é usar Docker neste projeto de portfólio.

Motivos principais:

- padronização do ambiente para qualquer avaliador técnico;
- reprodução simples de Redis, RabbitMQ e banco relacional;
- demonstração prática de organização de infraestrutura;
- redução de ruído de configuração local em entrevistas técnicas.

Stack mínima sugerida de containers:

- `app` (PHP-FPM);
- `nginx`;
- `postgres`;
- `redis`;
- `rabbitmq`;
- `queue-worker`.

## 10.6 O que o recrutador deve conseguir executar com Docker

- subir ambiente completo com um único comando;
- executar migrations e seeders;
- validar endpoint com cache ativo;
- validar processamento assíncrono em fila;
- validar fluxo de publicação e consumo em RabbitMQ.

---

## 11. Evidências de portfólio (checklist técnico)

Esta seção serve para facilitar a leitura técnica de recrutadores em poucos minutos.

## 11.1 Evidências de arquitetura

- `app/Modules` organizado por domínio, com camadas separadas;
- Controllers sem regra de negócio;
- Services/Use Cases centralizando regras e orquestração;
- Repositories por interface e implementação desacoplada.

## 11.2 Evidências de cache e fila (Redis)

- endpoint de listagem com cache e invalidação por evento;
- configuração de rate limit em rotas públicas;
- lock distribuído em operação crítica de escrita;
- job assíncrono processado por worker com driver Redis.

## 11.3 Evidências de mensageria (RabbitMQ)

- publicação de evento de integração em caso de uso de escrita;
- dois consumidores independentes para o mesmo evento;
- estratégia de retry e dead-letter documentada;
- comparação objetiva entre Redis Queue e RabbitMQ no contexto do projeto.

## 11.4 Evidências de qualidade de engenharia

- testes unitários de domínio e aplicação;
- testes de integração de repositório com filtros/paginação;
- testes de feature cobrindo autenticação, autorização e erros;
- documentação de decisões arquiteturais e trade-offs.

## 11.5 Roteiro de demonstração rápida (5 minutos)

- apresentar a estrutura modular por domínio;
- executar fluxo de criação e fechamento de ticket;
- mostrar fila assíncrona em execução e processamento de job;
- evidenciar cache aplicado em listagem;
- demonstrar publicação e consumo de evento via RabbitMQ.

---

## 12. Plano de implementação por commits

Objetivo: gerar um histórico de Git legível, progressivo e técnico para avaliação de recrutadores.

## Commit 1 — Base arquitetural e domínio inicial

- estruturar `app/Modules` por contexto;
- criar camadas Controller, Service/UseCase, Repository e DTO;
- implementar fluxo básico de ticket com validação via FormRequest;
- padronizar Resources e respostas de erro.

## Commit 2 — Cache e fila com Redis

- configurar Redis para cache e queue;
- aplicar cache em listagens com invalidação por evento;
- adicionar lock distribuído em operação crítica;
- implementar job assíncrono para notificação/auditoria.

## Commit 3 — Mensageria com RabbitMQ

- configurar integração com RabbitMQ;
- publicar evento de integração após operação de escrita;
- criar ao menos dois consumidores independentes;
- documentar estratégia de retry e dead-letter queue.

## Commit 4 — Testes e qualidade de engenharia

- adicionar testes unitários de domínio e aplicação;
- adicionar testes de integração de repositório;
- adicionar testes de feature para fluxos críticos e erros;
- validar padrões de código e garantir documentação das decisões.

## Commit 5 — Demonstração e fechamento de portfólio

- revisar README técnico com arquitetura e trade-offs;
- adicionar passo a passo de execução via Docker;
- incluir roteiro de demonstração de 5 minutos;
- registrar evidências de funcionamento (exemplos de request/response e fluxos assíncronos).

---

## 13. Conclusão

A proposta original é tecnicamente consistente e já está acima da média para portfólio.  
As melhorias aqui sugeridas fortalecem governança arquitetural, previsibilidade de evolução e robustez operacional sem perder simplicidade de execução.

Resultado esperado:

- solução enxuta para entregar;
- arquitetura madura para demonstrar capacidade sênior;
- base preparada para crescimento com baixo retrabalho.
