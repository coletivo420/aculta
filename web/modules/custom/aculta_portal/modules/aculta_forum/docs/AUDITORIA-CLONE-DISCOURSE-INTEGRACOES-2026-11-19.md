# Política ACULTA Forum: clone funcional Drupal do Discourse

Decisão de produto aprovada em 2026-11-19. **Paridade funcional e de experiência com Discourse**, implementação nativa sobre Drupal 11/12, identidade ACULTA e UI Suite/SDC como arquitetura visual obrigatória. O `aculta_forum` é o **motor único de discussões e comentários de todos os módulos do site**. Isso **não** significa transformar qualquer comentário editorial, pedagógico ou restrito em tópico público; cada host preserva entidade, acesso, workflow e propósito. A moderação de denúncias e os casos usam `aculta_cgu` como dono, com adapters de ações por contexto.

## Auditoria consolidada de integrações (base dev: `182ab6d9ad283c7c1e0776d9be48f6aaf4e33457`)

**Método:** leitura estática no GitHub das árvores, services YAML, entidades, hooks, providers, Config Sync, README/ROADMAPs, testes existentes e contratos de temas. Não foram executados PHPUnit, PHPStan, browser autenticado nem Runtime; versões e status no código são evidências documentais, não homologação. Os caminhos abaixo são relativos a `web/modules/custom/aculta_portal/modules/` ou a `web/themes/custom/aculta420_v2/`.

### Inventário por consumer

| Consumer / owner | Contrato constatado | Limites e correção exigida |
| --- | --- | --- |
| `aculta_forum` Growroom | Node `aculta_forum_topic`, `aculta_forum_post` revisionável, `aculta_discussion_post` legado; `DiscussionContextResolver` | Unificar experiência e novos writes no post revisionável, preservar URLs/revisões/anexos na migração |
| `aculta_wiki` | `field_aculta_discussion` em `wiki_entry` no Config Sync, provider `WikiDiscussionContextProvider` registrado no Fórum | O verbete é o host, não gerar tópico público; cache e escrita seguem autorização do verbete |
| `aculta_magazine` (Revista) | `field_aculta_discussion` em `revista`; `PublicationDiscussionHooks`, `EditorialCommentAccessHooks`, `EditorialCommentModerationForm` | Manter publicação como host e `EditorialCapability::COMMENTS`; retirar apenas duplicações de UI/regra comum |
| `aculta_lms` discussões | `field_aculta_discussion` no `aculta_lms_topic`; provider LMS com Group/matrícula/Educador; `LmsForumModerationManager` | Nunca conceder acesso por cargo global de fórum; Group e relações turma/curso são obrigatórios |
| `aculta_lms` respostas avaliativas | `aculta_lms_answer.comments` usa bundle `aculta_lms_answer_comment` e campo `comments` próprios | **Lacuna de universalidade**: inventariar se é feedback avaliativo ou conversa; plugar renderer/compositor/moderação do Fórum sem alterar semântica avaliativa ou acesso; migrar bundle somente com prova de compatibilidade |
| `aculta_cgu` | `aculta_cgu_report`, `CguReportQueue`, `ReportService`, `ModerationScope`; bridge Flagging no Fórum | Owner da fila e ciclo de denúncias; risco de classificação errada de Comment e de decisão sem execução sobre alvo |
| `aculta_index` | Tag `aculta_index.provider`: `ForumContentIndexProvider` suporta tópico/ForumPost/Comment legado do **Growroom**; AccessFilter revalida no purpose `forum` | Não confundir catálogo transversal com indexação textual Search API e nem assumir cobertura de Comments na Wiki/Revista/LMS |
| `aculta_statistics` | `ForumMetricsProvider` via tag, subscribers `ForumActivitySubscriber`, `ForumReactionSubscriber`, `ForumSearchSubscriber`; catálogo central | Eventos/estoques FST públicos e agregados de CGU; consolidar métricas por contexto evitando inferência de dados pessoais/restritos |
| `aculta_index` x `aculta_statistics` | `ContentIndexStatisticsBridge` e `AuthorizedEntityMetricAggregator` permitem `forum.replies.created` por tópico autorizado | Não é métricas por qualquer Comment; limites de dimensão, sensibilidade e `view content-level statistics` são obrigatórios |
| `aculta_messenger` e `aculta_mailer` | Avisos e eventos do Fórum; templates de Forum/Wiki/Revista/LMS no Messenger | Reusar única entrega/digest; assinatura não concede acesso; revalidar na entrega; prevenir eventos duplos na migração |
| `aculta_user`, `aculta_game` | Card de autor, `ForumModerationHooks`, `ForumParticipationPolicy` | Identidade, participações e concessões continuam com seus owners |
| `aculta_editor`, Media, `aculta_image`, Gallery | Formato `forum`, upload e políticas do Portal | Não duplicar editor/uploader; acesso a embeds privados e limpeza após exclusão precisam de testes |
| Tema `aculta420_v2` | 151 diretórios SDC; família forum/discussion (list, topic, post, reply, quote, tabs, sidebar, reaction etc.), UI Skins/Styles | Catálogo não prova consumer ativo; Config Sync da View de categorias mistura displays com row `fields` e `ui_patterns` |
| Outros módulos ACULTA | Eventos, FAQ, Galeria, documentos e Commerce não apresentam campo de comentário próprio nas árvores examinadas | Entrada futura obrigatória por contrato de provider, **não** ativar comentários automaticamente em entidades sem política de publicação e acesso |

### Achados técnicos priorizados

| ID | P | Tipo | Achado verificável ou risco para reprodução |
| --- | --- | --- | --- |
| AF-01 | P0 | código + risco | `aculta_forum/src/Controller/DiscussionReplyController.php:100-110`: carregar `?citar`/ `?citacao` não demonstra ACL de origem/host/tombstone, embora `ForumQuoteHooks` seja mais restritivo; teste cruzado LMS/Forum/Wiki/Revista |
| AF-02 | P0 | código | `aculta_forum.routing.yml` permite GET/HEAD/POST na decisão de exclusão; `TopicDeletionQueueController` modifica estado com token na query; proibir mutação em GET/HEAD |
| AF-03 | P1 | código + risco | `TopicDeletionModerationManager`: despublica e insere fila em passos independentes; aprova/apaga e rejeita/republica sem transação única, estado original e idempotência |
| AF-04 | P1 | código + docs | `CommentRouteSubscriber` reescreve canonical para `/resposta/{comment}`; `DiscussionReactionEntityViewHooks` usa literal `/comment/{cid}`; D-65 histórico menciona 404. Testar Router e definir política única |
| AF-05 | P1 | código | `DiscussionReactionController` utiliza `destination` ou `Referer` para `RedirectResponse` sem checar URL local no trecho; corrigir fallback |
| AF-06 | P1 | risco | `DiscussionReplyFormHooks` recebe `pid` de query/POST e reatribui ao Comment; verificar vínculo com host, acesso e relações em todos os contexts na validação final |
| AF-07 | P1 | risco | `DiscussionReplyIdempotencyManager` consulta duplicidade por texto e 30s; não assegura concorrência exatamente-uma-vez, pode bloquear repetição legítima |
| AF-08 | P1 | risco | Tombstones de Comment em KeyValue: testar invalidação de cache, busca, citações, reações, avisos, anexos e histórico |
| AF-09 | P1 | risco | `DiscussionReactionAccess`: acesso do hospedeiro não substitui `comment->access('view')` para alvos com ACL própria |
| AF-10 | P2 | risco | `DiscussionReactionEntityViewHooks` consulta irmãos e percorre pais por resposta; `ForumPostUnreadManager` tem subqueries correlacionadas; perfilar carga real e N+1 |
| AI-01 | P1 | código | `ForumContentIndexProvider` reconhece `comment:aculta_discussion_post` mas `isIndexable()` só aceita host `aculta_forum_topic`; portabilidade transversal exige contrato por host, sem publicar LMS/Revista/Wiki em índice público |
| AI-02 | P1 | docs/código | Search API do Fórum cobre tópico + ForumPost, não corpo de Comments legados segundo `docs/SEARCH.md`; catálogo `aculta_index` não substitui índice textual |
| AS-01 | P1 | código | `ForumMetricsProvider` mistura Comment legado + ForumPost público intencionalmente; migrar sem contagem dupla e assegurar mesmo escopo na reconciliação |
| AS-02 | P1 | código | `ForumActivitySubscriber` registra tópico/Comment/ForumPost; `ForumSearchSubscriber` só registra se habilitado; FST ledger e eventos devem distinguir publicação lógica de origem migrada/replay |
| AC-01 | P0 | código | `aculta_cgu/Service/ReportService::resolvePurpose()` classifica todo tipo contendo `comment` como `forum`; errado em Comments vinculados à Revista/Wiki e possivelmente LMS. Resolver pelo host/provider, fail closed para contexto desconhecido |
| AC-02 | P1 | código | `ReportService::decideReport()` altera status da denúncia, mas não aplica ação editorial ao conteúdo; integrar adapters de ação e auditoria sem automatismo implícito |
| AC-03 | P1 | código | Bridge `ForumReportCguBridgeHooks` + reconciliação `CguReportQueue` coexistem; garantir uma denúncia lógica por flagging e idempotência de migração |
| UI-01 | P1 | código | A View `views.view.aculta_forum_categories.yml` contém row UI Patterns num display e row Fields noutro; UI Patterns Library possui componentes que podem não ter consumer. Cutover por View/display e prova de render |
| UI-02 | P1 | docs | `docs/operations/status/aculta_forum.yml` informa CI/Runtime e dark/zoom/leitor de tela pendentes e cutover de Views condicionado ao post-update Portal autorizado |

### Evidências positivas a preservar

- Group/LMS por membership e Educador, publicação revisionável Core, grants no Search API e acesso no Content Index antes da entrega dos resultados.
- Ledger FST, subscribers sem payload pessoal, métricas CGU sem denunciante, `ContentIndexStatisticsBridge` com checagem independente da métrica.
- SDCs já disponíveis, UI Skins por tokens e separação Portal (contrato) / tema (render).

### Limites

Os achados são base para testes de exploração, não declarações de comprometimento ou de falha reproduzida no Runtime. Ver [matriz de aceite](MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md) antes de alterar status. Nenhuma escrita de dados ou mudança de configuração foi autorizada por esta documentação.

### Fontes primárias da referência Discourse / UI Suite

- https://www.discourse.org/features
- https://meta.discourse.org/t/introducing-the-unified-new-view-for-the-topic-list/404728 (2026; distinguir New Topics e Unread Replies)
- https://meta.discourse.org/t/discourse-moderation-guide-part-3-managing-content/406266 (2026; fila, flags, notas, timeline)
- https://meta.discourse.org/t/a-new-review-queue-layout-with-all-new-features/388194 (padrões de revisão, release/flag devem ser verificados)
- https://www.drupal.org/project/ui_patterns (SDC e integração)
- https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/ui-patterns/use-patterns-with-views
- https://www.drupal.org/project/ui_patterns_field_group
- https://www.drupal.org/sa-contrib-2026-075 (verificar versão corrigida do UI Patterns no Runtime)
