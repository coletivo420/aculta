# Arquitetura do aculta_forum_lms

`aculta_forum_lms` adapta as discussões do Fórum ao contexto de turma do LMS.
Ele não é dono de tópicos, memberships ou permissões globais de Fórum.

## Decisões vigentes

- **2026-10-20 — Acesso contextual é fail-closed e deriva do Group da turma.**
  Situação: vigente. Motivo: acesso direto não pode inferir autorização quando
  o vínculo entre discussão, turma ou curso é ausente ou ambíguo. Origem:
  `0.1.0-dev.1` e `0.1.0-dev.2`,
  [histórico arquivado](../history/changelog/aculta_forum_lms.md).
  O provider atual rejeita Group/curso inválidos e delega a autorização ao
  `Group::takeAccess()` ou à permissão contextual do educador, com cacheability
  e revalidação. O contrato transversal está em
  [ADR-025](../decisions/ADR-025-acesso-e-moderacao-forum-lms.md), incluindo a
  limitação da moderação contextual a `view` e `approve`.

## Fronteiras

- Forum mantém entidades e ciclo de vida das discussões.
- LMS/Group mantém turma, curso, membership e papel contextual de educador.
- O adapter traduz contexto e acesso entre esses donos; não concede grants
  globais nem cria uma segunda fonte de memberships.
