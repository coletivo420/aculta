# ADR-026 — Ownership da moderação de propostas entre Wiki e Studio

**Status:** vigente desde 2026-10-22.  
**Escopo:** propostas editoriais da Wiki e sua apresentação no ACULTA Studio.  
**Origem:** `0.1.0-dev.18` e `0.1.0-dev.19`, [histórico arquivado da Wiki](../history/changelog/aculta_wiki.md).

## Contexto

Propostas precisam preservar revisões, decisões e acesso privado, enquanto a
experiência editorial deve reunir as tarefas dos colaboradores sem transferir
para o Studio o ownership das entidades ou do workflow.

## Decisão

1. `aculta_wiki` é dono das propostas, revisões, acesso e transições editoriais.
2. As decisões são privadas e revisionáveis, verificando permissão, estado e
   revisão-base; a autoria recebe a devolutiva pela experiência privada.
3. A fila de moderação é apresentada na WikiTab existente do ACULTA Studio. O
   Studio consome as operações autorizadas da Wiki e não cria storage nem uma
   segunda fonte de decisões.

## Evidência vigente

`WikiProposal` e `WikiProposalDecision` implementam revisão e decisão no módulo
Wiki; a integração WikiTab do Studio apresenta as ações autorizadas. Contrato
local: [aculta_wiki.md](../architecture/aculta_wiki.md).
