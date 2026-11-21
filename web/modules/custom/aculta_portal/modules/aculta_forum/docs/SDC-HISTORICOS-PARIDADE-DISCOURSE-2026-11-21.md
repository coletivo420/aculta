# ACULTA Fórum - SDC, UI Patterns, stories e históricos (21/11/2026)

## Escopo e metodologia

Snapshot de leitura: `8fa522b63da739ad6092169fd01156ead4c9b40a` na `feat/forum-paridade-discourse`. Inventário dos SDC do tema
`aculta420_v2/components`, dos stories UI Patterns Library, das rotas de
revisão de `ForumPost`, de `TopicPollRenderHooks`, `ForumPollVoteForm`,
`TopicComposerFormHooks` e da matriz FDC. Referência do Discourse: navegação,
edições revisionáveis, enquetes e Review Queue. **Paridade de comportamento,
não cópia do código do Discourse**.

O catálogo contém componentes existentes para tópicos, lista, posts, citações,
reações, composer, navegação por abas, ações de moderação e interfaces Marley.
Os componentes de comunidade encontrados possuíam ao menos uma story
`.story.yml`; a falta era de responsabilidades visuais específicas, não de
um catálogo alternativo.

## Entrega deste pacote

| Componente | Status | Consumer / fronteira |
| --- | --- | --- |
| `forum-poll-card` | ligado | `TopicPollRenderHooks::nodeView()` envolve Form API real; formulário acessível, CSRF e POST mantidos |
| `forum-poll-results` | ligado | `ForumPollVoteForm::buildForm()` somente após `PollResults::resultsVisible`; percentuais calculados pelo owner |
| `forum-revision-history` | ligado | `ForumPostRevisionHistoryController::history()` mantém consulta paginada, ACL de rota, comparação e tabela construídos pelo Drupal |
| `forum-reading-timeline` | SDC e story | Provider de primeiro não lido / links por grant ainda por integrar; a story **não** é contador persistido |
| `forum-draft-status` | SDC e stories | Sem serviço de autosave/retomar; estados `saved` e `saving` **não podem** ser emitidos sem gravação comprovada |

Histórico: esta entrega compõe o histórico **revisionável atual** de `ForumPost`,
não converte nem reconta as mensagens do acervo histórico Growroom.
O histórico de decisão CGU pertence ao `aculta_cgu` e será exibido no
Marley/Fórum apenas após contrato de audit trail e ACL.

## Continuação: construtor de enquete + leitura com History

- O SDC `forum-poll-builder` usa slots para receber o checkbox e os campos
  **já processados** pelo Form API. `afterBuildPollBuilder` registra a
  conversão apenas para `#pre_render`: `#parents`, `#name`,
  `#value`, `#states`, #validate e #submit permanecem no Drupal
  durante processamento/validação. O Twig não reimplementa formulário.
- `ForumReadingTimelineHooks` registra `node_view` somente para
  `aculta_forum_topic` publicado e autorizado do purpose Forum. A
  contagem usa `accessCheck(TRUE)` de ForumPost e o recibo
  `ForumPostUnreadManager` (History). Links: início, primeiro não lido
  com fragmento `forum-post-{id}` e pager 1, última página. Cache por
  usuário/domínio/grants e max-age 0.
- O percentual é **estimativa das publicações desde a última visita**.
  Não mede posição do scroll no navegador e não substitui timeline
  paginada visual de todas as respostas. O History considera apenas a
  janela temporal suportada pelo módulo Core.
- Contratos de regressão verificam slotting pós-Form API e o cálculo
  percentual com limites; Browser/Kernel e a prévia Library são DEFERRED.

## Lacunas de paridade ainda abertas

| Frente | Estado e condição de integração |
| --- | --- |
| `forum-poll-builder` | SDC integrado com campos Form API no #pre_render; falta validar envio HTMX e sem JS em Browser/Kernel antes de homologar |
| Timeline de tópico e primeiro não lido | Primeira integração baseada em History e pager 1 concluída; falta provar acesso/âncora em Browser, progresso de scroll real e consumo fora dos tópicos públicos |
| Rascunho recuperável | Persistência/retomada com retenção, finalidade, segurança e concorrência ainda não aprovadas |
| Tracking e menções | Antes de SDC, configurar serviço de preferências Messenger/Flag e busca autorizada, evitando divulgar contas privadas |
| Review Queue / timeline CGU | Reusar `studio-report-item`/CGU, não duplicar storage de denúncias no Fórum; notas internas requerem trilha auditável com ACL |
| Menu administrativo | `topic-admin-controls` e `moderation-action` já existem; revisar consumidores em vez de clonar SDC |
| Enquetes: moderação, resultados e consistência | Autorização na rota e ações do manager devem ser auditadas em Browser/Kernel; não inferir conclusão pelos componentes |
| Discourse Solved/Assign/Topic Voting/Bookmarks | Motor, permissões e consumer pendentes, decidir fonte de verdade antes de adicionar botões de fachada |
| Revisões comparadas no frontend | Página administrativa existente é restrita a administrador; migração para popup frontend exige nova política de leitura e comandos |
| Story UI Patterns Library | Catálogo descobre manifests/stories; confirmar realmente no Runtime, sem criar rota paralela |

## Segurança e qualidade

- Nenhum componente decide `access`, faz EntityQuery, serializa usuário ou
  emite URL de moderação não autorizada.
- Resultados de poll só existem no render **depois** da política
  `resultsVisible`. `ForumPollVoteForm` mantém voto único, validação,
  CSRF, encerramento e fallback sem JS.
- Contratos estritos JSON Schema, props simples, slots de render, tokens do
  tema e stories exclusivamente sintéticas.
- Resultados de múltipla escolha usam pessoas votantes como denominador:
  soma de percentuais pode ultrapassar 100%.
- Sem alteração de rotas, permissões, banco, configuração Runtime, acervo
  histórico ou bypass de autenticação.
- A alteração do markup de histórico mantém a tabela e pager Core, e o
  Kernel focal foi adaptado para validar o slot do SDC.

## Gates e aceite

- **Revisão estática no connector:** arquivos e contratos comparados com o
  snapshot da branch; commit e push são verificáveis no remoto.
- **DEFERRED:** PHPUnit Unit e Kernel, PHPStan nível 10 + debt guard, PHPCS,
  validações `validate-aculta420-sdc-library-contract.php`,
  `validate-aculta420-sdc-schemas.php`, UI Patterns Library,
  Browser light/dark, 320/390, teclado, zoom e leitor de tela.
- O agente local deve executar gates sobre o diff com banco de teste
  isolado, em sua worktree, sem acionar o Runtime compartilhado.

**Próxima fase técnica:** validar Kernel/Browser para enquetes, navegação
e ACL, e concluir Review Queue no Marley com trilha CGU. Não anunciar
paridade Discourse integral sem esses gates.
