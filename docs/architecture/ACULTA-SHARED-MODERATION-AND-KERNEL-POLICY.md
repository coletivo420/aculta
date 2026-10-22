# ACULTA — Política transversal de serviços compartilhados e moderação

**Decisão arquitetural aprovada · 2026-10-22 · Estado:** contrato e migração planejada, não refatoração executada.

## Regra geral para todos os submódulos ACULTA

**Todo código genuinamente transversal entre dois ou mais subkernels deve ser extraído para o kernel central `aculta_portal`, com interfaces/serviços neutros, testes de contrato e consumidores explícitos.** Evitar que Wiki importe implementações do Fórum, que Fórum dependa de Wiki, ou que a duplicação se espalhe entre LMS, Revista, Fórum, Wiki e Commerce.

A centralização é **seletiva**, não automática: só migrar comportamentos repetidos com semântica realmente comum, e só após auditoria de consumidores. Contratos de acesso e moderação dependentes de tipo de entidade, workflow, Domain, Group e configurações permanecem nos respectivos submódulos. Não centralizar Twig visual, SDC, CSS, configurações de negócio ou storage de domínio.

### Padrão de camadas

1. **Drupal Core/contrib:** Node, Content Moderation, Entity Access, Form API, Revision, Cacheability e respectivas APIs.
2. **`aculta_portal` central:** interfaces e serviços transversais de decisão (primeira contribuição), identidade de contexto, políticas de fila, cache, auditoria e eventos neutros quando se provar necessidade de compartilhamento.
3. **Subkernels:** `aculta_wiki`, `aculta_forum`, LMS e Revista mantêm adapters, seus próprios workflows, content types, queries, moderação específica, acesso e ciclo de vida.
4. **`aculta_user`:** conta, perfis e confirmação, mantendo papéis/capacidades já existentes e serviço próprio de participação enquanto sua migração é planejada.
5. **`aculta420`:** somente apresentação Core SDC + UI Suite (UI Patterns/UI Styles); não decide autorização nem moderação.

## Caso inicial: primeira participação moderada

**Fórum:** `aculta_user/src/ParticipationRules.php` já expõe `requiresFirstDiscussionModeration()`, com base em participação publicada em discussões, regras configuráveis e isenções. O Fórum consome essa política; esta política não pode ser reutilizada cegamente para Wiki, pois comentários ≠ verbetes.

**Wiki:** `email_confirmed` cria verbetes, edita os próprios e envia à revisão. **O primeiro verbete de cada colaborador exige revisão explícita de um content editor antes da publicação**, como a primeira participação no Fórum. A contagem de primeira contribuição da Wiki usa o histórico de verbetes **efetivamente publicados** do próprio usuário no domínio/purpose pertinente e ignora rascunhos, envios pendentes e sugestões alheias. O workflow `wiki_editorial` já tem `draft → review → published`. Para preservar a decisão anterior, **todas as sugestões e alterações de terceiros também continuam moderadas**, mesmo depois da primeira publicação. Nunca conceder a `email_confirmed` a transição de publicação.

**Política de elegibilidade:** 'primeira publicação pendente' deve ser derivada de fonte oficial, auditável, sem contador JS ou variável de sessão. Ao não existir prova segura de primeira publicação, manter revisão obrigatória (fail closed). Qualquer exceção de administradores ou revisores será explicitamente descrita por capacidade, não inferida da política Fórum.

### Contrato neutro candidato — ainda não implementado

- `FirstContributionPolicyInterface`: pergunta se contexto/conta precisa de revisão inicial, recebe contexto tipado e retorna decisão cacheável e justificativa.
- `ModerationQueueItem`/adaptador neutro de projeção: identificador de origem, entidade/revisão, estado oficial, direito de acesso e ações realmente permitidas; **não** cria entidade universal de moderação.
- `ModerationDecision`/orquestração: somente quando houver consumidores reais; decisões de publicação executadas pelo kernel proprietário via Content Moderation.
- Cada subkernel implementa `PublishedParticipationLookupInterface` ou adaptação específica para suas próprias entidades; não transportar queries Wiki ou Fórum ao core central.
- Configurações transversais: default conservador, cache tags/contexts, logs sem conteúdo pessoal desnecessário, sem sobrescrever políticas de Domain/Group.

A lista é desenho **candidato**, não nomes de serviços/API definitivos. Antes de criar PHP: mapear os consumidores e testar a compatibilidade semântica com LMS (cuja política de discussões difere da pública), Revista e fórum contextual da Wiki.

## Roteiro de migração sem regressão

| Fase | Trabalho | Gate |
| --- | --- | --- |
| SHARED-0.1-A | inventário dos consumidores da participação/moderação e ownership atual | mapa de dependências e riscos |
| SHARED-0.1-B | ADR: contratos comuns, isolamentos, cache e política de primeira publicação por contexto | desenho aprovado |
| SHARED-0.1-C | serviços neutros no Portal com testes unitários/Kernel e adaptadores preservando comportamento do Fórum | diff funcional mínimo e nenhum bypass |
| SHARED-0.1-D | adaptar Wiki ao serviço central, suas próprias queries/workflow e fila; testes por papel e versão | sem publicação antes de aprovação |
| SHARED-0.1-E | avaliar outros subkernels individualmente, sem generalização forçada | adoção com consumidor real |
| SHARED-0.1-R | regressão homelab, cache, propósito, roles, auditoria e UI | evidências Runtime |

**Proibições:** não mover código de `ParticipationRules` diretamente sem preservar suas dependências e contratos; não dar `edit any` ou `publish` a `email_confirmed`; não duplicar a fila de moderação do Fórum dentro da Wiki; não acoplar o domínio Wiki à classe `ForumPostModerationAccess`; não criar nova engine de revisão.
