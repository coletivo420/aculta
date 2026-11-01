# ADR-023 — Associado é uma situação da conta, não um papel

**Status:** vigente desde 2026-10-21, por decisão do responsável.  
**Escopo:** identidade, papéis e participação em todo o projeto.  
**Origem:** F-D19 em [histórico arquivado do Fórum](../history/changelog/aculta_forum.md) e `0.4.0-dev.239` em [histórico arquivado do Portal](../history/changelog/aculta_portal.md).

## Contexto

Representar adesão à associação como papel global misturava uma situação
administrativa com permissões herdáveis e criava dependências entre funções de
conta, Fórum e LMS.

## Decisão

1. `associado` é situação administrativa da conta, com pedido, status e data de
   adesão; não é papel Drupal nem elo da hierarquia de permissões.
2. Concessão, convite, suspensão e encerramento seguem o fluxo próprio de
   associados em `aculta_user`.
3. A hierarquia de papéis permanece independente da situação de associado.

## Evidência vigente

`config/sync/aculta_portal.role_hierarchy.yml` não inclui `associado`;
`aculta_user/docs/ASSOCIADO.md` e `HIERARQUIA-DE-FUNCOES.md` descrevem a situação
e a hierarquia em vigor.
