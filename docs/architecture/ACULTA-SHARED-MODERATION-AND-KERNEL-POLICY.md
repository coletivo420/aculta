# ACULTA — Política de participação e moderação compartilhada no aculta_user

**Decisão revisada · 2026-10-22 · Implementação:** USER-PART 0.1-C parcial em 2026-10-24; sem alteração de permissões ou Runtime.

> **Esta decisão substitui a proposta anterior de mover as regras de primeira participação para o núcleo aculta_portal.** O dono definitivo da política conjunta Fórum + Wiki + demais contextos de contribuição é o **subkernel aculta_user**. Centralização é por responsabilidade, não por posição na árvore de módulos.

## Responsabilidades

| Camada | Responsabilidade |
| --- | --- |
| Drupal Core/contrib | Entity Access, Node/Comment/Revision, Form API, Content Moderation, cache e autenticação |
| **aculta_user** | Política compartilhada de primeira contribuição/publicação, confiança/isenções, participação por conta, interface neutra para consulta de histórico publicado e decisões cacheáveis; não cria workflows dos produtos |
| aculta_forum | Adaptador de histórico de discussões, fila de moderação do Fórum, comentários e posts próprios |
| aculta_wiki | Adaptador de histórico de verbetes publicados, workflow wiki_editorial, propostas de alteração e fila editorial WE SATIVA |
| aculta_portal | Integração/domain/purpose e serviços transversais do Portal que não pertençam a identidade/participação; **não hospeda a política conjunta de primeira moderação** |
| aculta420 | Apresentação mobile-first por SDC Core e UI Suite; sem consultas, regras de acesso ou decisões de moderação |

## Política vigente e reuso correto

O código já existente em `web/modules/custom/aculta_portal/modules/aculta_user/src/ParticipationRules.php` contém `requiresFirstDiscussionModeration()`, ligado à contagem de participações públicas em discussões e às configurações do usuário. **Preservar esse comportamento e seus testes.**

A Wiki usa uma regra correlata, mas distinta: uma conta `email_confirmed` pode criar verbetes e editar os próprios; **o primeiro verbete precisa de revisão de content_editor antes da publicação**, como primeira participação moderada. A existência de um verbete efetivamente publicado do autor identifica que a primeira publicação já ocorreu. **Todas as contribuições e sugestões continuam dependentes do workflow de publicação; a primeira aprovação não concede permissão de publicar diretamente.** O papel `email_confirmed` não obtém `review_to_published`.

A função Fórum `requiresFirstDiscussionModeration()` **não deve ser chamada para verbetes**, porque comentários/posts não são evidência de verbete Wiki publicado. A extensão ficará no próprio `aculta_user` com métodos/contratos explícitos por contexto, preservando a semântica.

## Desenho de extensão no aculta_user (nomes candidatos)

- `ParticipationRules::requiresFirstWikiEntryModeration(AccountInterface $account)` (ou política delegada equivalente), baseado em um adaptador de verbetes Wiki publicados.
- `PublishedContributionLookupInterface`: consulta de participação publicada por contexto (`discussion`, `wiki_entry`), sem transportar detalhes de entidade ao mecanismo de decisão.
- Adapters isolados por domínio e testes: Fórum continua com queries atuais; Wiki consulta Node `wiki_entry`, revisões efetivamente publicadas, autoria e purpose com critérios acordados.
- `ParticipationModerationDecision`: dados neutros de `requires_review`, motivo e metadados de cache somente se necessário; **não** guarda drafts, propostas, tickets, mensagens ou informação pessoal sensível.
- As configurações partilhadas devem permanecer no domínio de `aculta_user` se fizer sentido e ser explicitamente versionadas; não reutilizar automaticamente as flags de primeiro comentário como flag de primeiro verbete.

Nomes acima são **propostas**, não APIs implementadas. Antes de modificar `ParticipationRules`, verificar DI, configurações, cache invalidation e todas as chamadas existentes. O subkernel Wiki **não deve importar implementações internas do Fórum** e o Fórum **não deve depender do código da Wiki**.

## Onde ficará cada parte da moderação

**aculta_user** pode centralizar toda a camada **conjunta**: políticas de primeira contribuição, critérios comuns, isenções, histórico de confiança, protocolos de decisão, contratos de auditoria e projeções neutras de participação. **aculta_forum/aculta_wiki** continuam donos das suas entidades, grants, fila, transições, query de conteúdo, moderação específica e armazenamento de proposta. Só migrar funcionalidades comprovadamente compartilháveis depois de inventário e testes; não unificar dados de Fórum e Wiki à força.

O mecanismo de sugestões de alteração em verbetes de terceiros é uma responsabilidade editorial Wiki: a política de confiança pode ser fornecida por `aculta_user`, mas o storage/revision base e aplicação das propostas pertencem a `aculta_wiki`.

## Migração em fases, sem regressão

| Etapa | Trabalho | Evidência necessária |
| --- | --- | --- |
| USER-PART 0.1-A | mapear `ParticipationRules`, chamadas, DI e testes Fórum; requisitos da Wiki | inventário dos consumidores |
| 0.1-B | ADR e contrato de extensão dentro de `aculta_user`; política e lookup por domínio | review de segurança e ownership |
| XK 0.1-C1 | caracterizar políticas e hooks compartilhados antes de refatorar | concluído; evidência histórica em [USER-PART C1](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/USER-PART-0.1-C1-CHARACTERIZATION.md) |
| 0.1-C — etapa 1 | lookup de evidência publicada por contexto e adapter Wiki | implementado e testado (User Unit 123/439, Wiki Kernel 1/45, Fórum Kernel 1/39) |
| 0.1-C — etapa 2 | ligar decisão de primeira publicação ao fluxo Wiki, preservando Fórum | pendente; nenhuma permissão ou transição foi alterada |
| 0.1-D | integrar adapter/acesso Wiki, fila e Content Moderation | nenhuma publicação antecipada; grants/cache |
| 0.1-E | compartilhar outros recursos de participação com contexto real | contratos testados |
| 0.1-R | homelab, testes de concorrência, roles, Domain e revisão | Runtime comprovado |

Autosave Form permanece futuro. Institution Bar + Domain Header preservados; toda UI compartilha apenas SDC/UI Suite.
