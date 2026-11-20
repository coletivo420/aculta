> **DECISÃO POSTERIOR 2026-11-20:** para **saldo geral de curtidas** de cada item, todos os emojis positivos atuais/antigos equivalem +1 e `dislike` equivale -1, sem necessidade de `like_reaction_ids` para essa finalidade. A proposta anterior de subset de emojis continua somente histórico de pesquisa. Discourse badges Nice/Good/Great usarão o **score líquido do mesmo item** com critérios Game próprios. [Implementação e DV-01..15](IMPLEMENTACAO-DESCURTIDAS-2026-11-20.md).

# Auditoria complementar - Discourse, Game, estatísticas e integrações ACULTA

**Data:** 2026-11-20. **Base:** `feat/forum-paridade-discourse` PR #394, commit `52e20d6b5a4f50ba8915625cbd7f6d269b11e05d` (snapshot de planejamento). **Status:** análise estática do código mais fontes oficiais do Discourse. Nenhum teste de integração, Runtime, benchmark ou implementação dessas novas funções foi executado nesta rodada.

**Políticas obrigatórias:** frontend operacional via `aculta_studio`; moderador comunitário só atua em Marley > Fórum e em popups contextuais nos tópicos; backend exclusivamente `administrator`; moderação pedagógica própria de GanjaLab/LMS, sem herança por `moderador` ou `content_editor`; acervo histórico Growroom `ARCHIVE` imutável, separado e **nunca convertido para ForumPost**. Não criar pontuação, ranking, XP, moeda, privilégio por troféu ou prêmio por mera quantidade sem decisão posterior.

## 1. Evidências extraídas do código, não da documentação apenas

| Fonte real | Existe no HEAD | Limite identificado / correção de desenho |
| --- | --- | --- |
| `aculta_game/src/Hook/BadgeEvaluationHooks.php` e `ForumParticipationPolicy.php` | Conquista por primeira resposta publicada ou aprovada: Comment contemporâneo ou `aculta_forum_post`; evita sync, arquivo, host não público | **Não** possui motor geral de quantidades de tópicos, curtidas ou 10/25/50 likes, nem gatilho de criação de tópico; precisa serviços de qualificação novos |
| `aculta_user/src/Badge/{BadgeCatalog,AchievementDefinitions,BadgeService}.php` | 7 conquistas iniciais, triggers configuráveis, concessões idempotentes com lock e `aculta_badge_award` | `TRIGGERS` é allowlist fixa; adicionar triggers e esquema/config com migração compatível; concessões automáticas não podem ser revogadas na regra vigente |
| `aculta_game/src/MissionProgress.php` | Missões unitárias com estado privado, sem novo storage/progresso | Para marcos quantitativos adicionar leitura de projeções elegíveis do owner, sem tabela de progresso paralela |
| `aculta_forum/src/Service/DiscussionReactionManager.php` | `aculta_discussion_reaction` e `aculta_discussion_reaction_user_stats`, `getUserStats` e `getAuthorPublicPostCount`; reactions permitidas/configuráveis | `getUserStats` mistura vários emojis e pode incluir Revista (`PUBLICATION`); `getAuthorPublicPostCount` agrega **tópicos + Comments + ForumPosts**, não serve como cada métrica isolada |
| `aculta_forum/src/Event/ForumReactionEvent.php` | Evento `aculta_forum.reaction_added` com somente `reaction` | **Não tem UID de autor, pessoa que reagiu ou target**, suficiente para métrica agregada por emoji, insuficiente para conceder conquistas ou detectar likes recebidos por post |
| `aculta_statistics/src/EventSubscriber/ForumReactionSubscriber.php` | Consome evento atual e grava `forum.reactions.added` por tipo | Evento de adição não representa saldo atual após remover/trocar reação; precisa snapshot/reconciliação e projeção de identidade de uso restrito |
| `aculta_statistics/src/Catalog/MetricCatalog.php` | `forum.topics.created`, `forum.comments.created`, `forum.posts.created`, `forum.replies.created`, `forum.reactions.added/active`, `forum.topics.views` etc | Contadores agregados por dia **não** são métricas pessoais nem ledger completo. Catálogo de métricas novas só após definir semântica e privacidade |
| `aculta_forum/src/Analytics/ForumMetricsProvider.php` | Estoques de tópicos e respostas PUBLIC CURRENT, categorias, sem resposta, participantes, primeiras respostas e reações | Confirmar filtros `CURRENT` e reconciliação em todos os queries; não somar histórico ou material editorial/pedagógico |
| `aculta_forum/src/Service/ForumReadMarker.php` + `ForumPostUnreadManager.php` | Marcar como lido e primeiro não lido, usando `history` Drupal | `marcar tudo como lido` não é prova de leitura real. `forum.topics.views` é **evento de visualização**, não necessariamente tópicos únicos abertos por pessoa; não atribuir badge baseado apenas nisso |
| `aculta_studio/src/Plugin/MarleyTab/ForumTab.php` | Aba de moderação do Fórum em Marley | Link antigo vai a `aculta_user.denuncias_forum` no backend. Mover fila para Studio frontend sem duplicar CGU |
| `aculta_game/aculta_game.routing.yml` | Config/admin de conquistas em `/painel-administrativo/game` | Somente administradores; apresentação e progressos de usuário via Conta/Studio e perfil `aculta_user` |

**Nota sobre dados existentes:** `Comment` contextual em Wiki/Revista/LMS é válido para discussão compartilhada, **não** para prêmio de Growroom; legado separado não entra em contadores Game. O módulo `aculta_game` é opcional; fórum, comentários, reações, Stats, login e Studio funcionam sem ele. Reutilizar a API de `BadgeService` e o `aculta_badge_award`, não inventar novo backend de troféus.

## 2. Métricas de perfil: significados distintos

| Indicador novo / aprimorado | Fonte de verdade / regra | Onde mostrar | Situação |
| --- | --- | --- | --- |
| Curtidas **dadas** | Cédulas ativas da `aculta_discussion_reaction` com tipo explicitamente equiparado a `like`, autor do gesto, em conteúdo `CURRENT` Growroom, não o total de emojis | Perfil próprio; público somente se visibilidade permitida | Não há projeção Fórum/like isolada - **planejar** |
| Curtidas **recebidas** | Mesma tabela, destinatário autor do tópico/ForumPost/Comment, ignorar própria curtida e conteúdo fora de escopo; por tipo e por post elegível | Perfil/autoria, cards e Game com permissão | **Planejar**, não usar `getUserStats` como total de curtidas |
| Outras reações dadas/recebidas | Tipos `broccoli/heart/thumbs_up` etc, agrupados explicitamente, sem tratar tudo automaticamente como `like` | Projeção separada, opt-in se houver perfil público | Agregados existentes, projeção por contexto não comprovada |
| Tópicos **criados** | Nós `aculta_forum_topic` por UID, origem `CURRENT`, publicados e pertencentes ao purpose Fórum; criação lógica única | Perfil e progresso de missão | Query atual é componente de contagem composta; **criar consulta própria** |
| Tópicos **abertos/visualizados** | Hits autenticados de tópicos elegíveis com deduplicação e política de privacidade; separar `views totais`, `tópicos distintos abertos`, `lidos` | Estatística privada da conta; pública só com decisão explícita | `forum.topics.views` catálogo existe; deduplicação pessoal e leitura validada **não comprovadas** |
| Comentários/respostas **publicados** | `aculta_forum_post` e Comment contemporâneo Growroom, apenas publicação efetiva; uma resposta lógica = um fato | Perfil, autor, progresso Game | Contagens de tópico e post existentes em fontes diferentes; **consolidar contrato** |
| Tópicos respondidos | `COUNT(DISTINCT topic)` de respostas publicadas por UID; não contar múltiplas respostas ao mesmo tópico nem próprias conforme indicador | Perfil, missões opcionais | **Planejar** |
| Curtidas em um tópico/resposta | `COUNT` de reações `like` vigentes no **mesmo alvo** (Node ou ForumPost/Comment CURRENT) | Cartão do conteúdo, badge Nice/Good/Great | Manager traz summary genérico; precisa separar `like` e origem do alvo |
| Engajamento de qualidade | Sinais de resposta reconhecida, aprovação e diversidade de pessoas que reagiram, com controle de abuso e acesso | Indicadores Stats e marcos de qualidade | **Planejar** |

**Regra de equivalência Like:** `aculta_forum.settings.default_reaction` é `broccoli` no snapshot e existem `heart`, `thumbs_up` e outros. A semântica Discourse Like **não é equivalente automaticamente** a todas as reações. Proposta: Config API `like_reaction_ids` com decisão de produto inicial `broccoli` (por ser a ação padrão do site), ou `broccoli + heart` mediante aceite; registrar a escolha e a versão. Para o MVP **não** somar emojis com semânticas diferentes, reações apagadas ou eventos históricos não ativos. Testar migração caso o mapeamento seja alterado depois. Nunca usar likes como fonte de papéis/moderação.

**Contrato pessoal:** preferir `ForumMemberActivitySummaryInterface::forUser(uid, viewer, scope)` provido pelo Fórum; `aculta_game` consulta o contrato via DI opcional e `aculta_statistics` registra séries anônimas agregadas. `aculta_user` conserva identidade e preferências de exposição; `aculta_studio` apresenta o painel. A consulta é eficiente, com índices e invalidação por usuário/tópico e Context/Domain.

## 3. Troféus reais do Discourse - estudo comparativo

Base: [Badges reference guide](https://meta.discourse.org/t/badges-reference-guide/310717), [default badge queries](https://meta.discourse.org/t/the-default-badge-queries/276812), [Understanding and using badges](https://meta.discourse.org/t/understanding-and-using-badges/32540). O Discourse oferece categorias Bronze/Prata/Ouro; seu plugin opcional **Gamification** acrescenta pontos/leaderboards, distintos das badges Core. O ACULTA adota os **marcos**, não a pontuação.

| Badge Discourse oficial | Critério oficial | Equivalente proposto no Game | Prioridade |
| --- | --- | --- | --- |
| First Like | Primeira curtida dada | `primeira_curtida_forum`; uma ação válida e publicada | P1 |
| Welcome | Primeira curtida recebida | `primeira_curtida_recebida_forum` | P1 |
| Nice Topic | 10 curtidas no mesmo tópico | `topico_legal_forum` | P1 |
| Good Topic | 25 curtidas no mesmo tópico | `topico_bom_forum` | P2 |
| Great Topic | 50 curtidas no mesmo tópico | `topico_excelente_forum` | P2 |
| Nice Reply | 10 curtidas na mesma resposta | `resposta_legal_forum` | P1 |
| Good Reply | 25 curtidas na mesma resposta | `resposta_boa_forum` | P2 |
| Great Reply | 50 curtidas na mesma resposta | `resposta_excelente_forum` | P2 |
| Appreciated | Pelo menos 1 curtida em cada um de 20 posts distintos | `reconhecido_forum` - diversidade de conteúdos, não 20 likes na mesma resposta | P2 |
| First Quote / First Mention | Primeira citação / menção em post | `primeira_citacao_forum` / `primeira_mencao_forum`, se houver eventos de domínio seguros | P2 |
| Reader | Ler todas as respostas de um tópico com mais de 100 respostas | **Não ativar** sem leitura real comprovável e consentimento; History/mark-all não prova leitura | P3 |
| Basic / Member / Regular / Leader | Trust levels, alguns conferem poderes | **Não reproduzir como autorização**: moderador é papel administrativo concedido, nunca troféu | Fora de escopo |

**Marcos específicos do ACULTA, NÃO badges padrão do Discourse:** `primeiro_topico_forum` (1 tópico criado e publicado), `participacao_constante_forum` (por exemplo 5 tópicos com respostas de pessoas diferentes em janela longa), `conversas_diversas_forum` (responder a tópicos distintos). **Limiar por volume é somente sugestão a homologar**, não prêmio automático ativo. **Preservar a badge existente** `primeiro_comentario_forum` sem alterar ID, trigger ou concessões. Nenhum replay/backfill cria troféu para conteúdo importado.

**Política antiabuso antes de ativar 10/25/50:** só curtida de contas elegíveis diferentes da autoria; estado atual acessível e publicado; excluir conteúdo ARCHIVE/sync, self-like, votos artificialmente repetidos, contas bloqueadas, material removido ou qualificado como spam; revisões e reações trocadas não geram segunda conquista. Definir se limiar vale pelo pico histórico ou saldo atual. Recomendação: **saldo atual sustentado + concessão permanente como marco histórico**, anotando critérios/versão/evidência, sem revogação automática (compatível com BadgeService). Se abuso comprovado, processo administrativo próprio e eventual revisão de política, não alteração furtiva de medalhas. Gatilhos que premiam denunciar, ler compulsivamente, comentar em massa, compra ou presença diária ficam fora do MVP.

**Três camadas:** (1) estatística operacional atual da pessoa ou item, (2) progresso privado de missão e (3) concessão histórica imutável no `aculta_badge_award`. Não confundir valores. Esconder conquistas pelo UserData/Profile não apaga fatos.

## 4. Contrato proposto Game + Forum + Statistics

```text
Node Topic / ForumPost / Comment CURRENT + ReactionManager
   -> ForumParticipationPolicy / ForumMemberActivitySummary (escopo, UID, purpose, ACL)
   -> transição/publicação/reação confirmada (sem persistir dados no evento público)
      -> GameAchievementEvaluator (consulta fonte real, avalia regras permitidas)
         -> aculta_user.badge_service (awardForTrigger / award) -> aculta_badge_award
            -> aculta_messenger (aviso idempotente, acesso revalidado)
      -> aculta_statistics (métricas agregadas anonimizadas e snapshots)
      -> aculta_index (índice/autocomplete/relacionados, nunca ACL de origem)
   -> aculta_studio + aculta_user (painel privado, cartões públicos conforme preferência)
```

A API de eventos precisa ser expandida **sem quebrar o subscriber Statistics atual**. O evento `ForumReactionEvent` existente informa somente o tipo de emoji; não enfiar UID, target, IP ou conteúdo sensível no fluxo público sem avaliação dos subscribers. Preferir **evento de domínio interno separado** ou após commit com IDs mínimos para avaliação Game, e manter o evento analítico atual sem identificadores; um consumer busca a entidade no Fórum com ACL e origin antes de conceder. Replay, alteração de reaction e concorrência necessitam chave estável/transaction + lock e ledger mínimo; reconciliador do Game corrige projeção, **não duplica concessões**.

**Catálogo Stats candidato**: `forum.likes.added` (eventos `like` por tipo se elegíveis), `forum.likes.active_current` (snapshot de likes atuais), `forum.topics.created_by_members_current` (estoque agregado, sem UID), `forum.replies.created_by_members_current` (estoque), `forum.topics.distinct_opened` (somente após desenho de coleta com privacidade), `forum.game.badges.granted` (agregado por código de troféu e período, não por UID), `forum.game.badges.active_current` (snapshot). Preservar `forum.reactions.added`, `forum.reactions.active`, `forum.topics.created` etc. **Não inserir métricas não implementadas como se estivessem no catálogo.** Event counters ≠ estoques atuais ≠ pessoas distintas. Sem nomes, UIDs, títulos, opções de enquetes, dados CGU/LMS/privados em métricas públicas. Reconciliar reação↔métrica↔conquista em dry-run e medir drift.

### Regras de performance / acesso

- API do Fórum para resumo `forUser(uid)` consulta dados reais por `UID, purpose, tipo, data`, separa `created` e `published_current`, expõe números ao proprietário e dados públicos apenas conforme preferências `aculta_user`.
- Escopo `CURRENT` deve ser verificado com `DiscussionOriginResolver` e `ForumAnalyticsScopeResolver`; `CONTEXTUAL` Revista/Wiki/LMS não qualifica Game Growroom, mesmo se reaction pública.
- Para métricas privadas de LMS/CGU, nunca incluir ID de turma, Group, eleitor ou denunciante nas dimensões públicas; feedback educacional não dá badge de popularidade.
- Cache tags por conta, tópico e award; contexts `user`, roles/permissions, site purpose e Group quando apropriado. Testar revogação entre GET e POST e cross-host.
- Criar índices para `reaction`, `author_uid`, `host_entity_id`, `uid` segundo EXPLAIN, sem table scan por cartão de post; avaliar 1k/10k/100k posts.
- `aculta_game` desinstalado: Fórum, Statistics, criação de tópicos, likes e renderizados devem permanecer funcionais; concessões existentes no User preservadas.

## 5. Outros módulos ACULTA a integrar ao Fórum - prioridades e ownership

| Módulo | Código/contrato existente localizado | Integração proposta sem duplicação |
| --- | --- | --- |
| **aculta_user** - P0 | BadgeCatalog, BadgeService, políticas de participação, identidade/cartões e preferências de perfil | Projeções de estatísticas e troféus por conta, permissão de exibição, avatar, autoria UID e regras de confiança **não administrativas** |
| **aculta_statistics** - P0 | MetricCatalog, ForumMetricsProvider, subscribers de criação/reação | Contratos de contador/estoque/leitura e badge, reconciliador, painel autorizado de saúde do Fórum |
| **aculta_game** - P0/P1 | BadgeEvaluationHooks e primeira resposta, missão e concessões existentes | Avaliador de milestones e badges 10/25/50, sem nova tabela/XP/ranking e sem obrigar Forum a depender de Game |
| **aculta_cgu** - P0 | Denúncias/casos/decisão por purpose; contrato Forum-CGU | Review Queue global comunitária no Studio Marley; ações auditadas e acesso contextual; LMS pedagógico fora |
| **aculta_studio** - P0 | Suite Marley com ForumTab, GanjaLab, WE Sativa, Green Stash, Culturas | Fila de moderação e ações operacionais, métricas e gestão de conquistas pessoais no frontend; painel admin só administrator |
| **aculta_index** - P0 | Providers Fórum/Wiki/Revista/LMS/Gallery/Activity e relações com access revalidado | Pesquisa relacionada de tópicos, backlinks, links de fontes/verbete/evento e filtros por conteúdo atual; sem vazar Group privado |
| **aculta_messenger** e **aculta_mailer** - P0 | Infra de avisos, seguir tópicos, menções, citações e conquistas do Game | Notificações de respostas, likes recebidas, conquistas, solução aceita e lembrete de bookmark (opt-in + dedup e acesso revalidado); Mailer só transporte |
| **aculta_forum_legacy** - P0 de isolamento | Addon histórico próprio e política ARCHIVE | Provedores de apresentação/memorial, **nunca** votos, likes, troféus, mutações, stats e indexação como atividade contemporânea |
| **aculta_wiki** - P1 | Discussões contextuais, revisões e propostas, WE Sativa | Linkagem bidirecional de verbetes e tópicos relacionados via Index, citar fonte/verbete no composer sem duplicar comentários; editoria própria |
| **aculta_magazine** - P1 | Comments da publicação, Studio Marley, workflow editorial | Comentários compartilhados, citação e fonte, perguntas relacionadas/links do Fórum para matéria, sem criar tópico automaticamente nem misturar contadores |
| **aculta_lms** - P1 | Discussões por Group e curso, feedback pedagógico, GanjaLab | Motor de respostas compartilhado com ACL Group e pedagógico exclusivo; sugestões de leitura pública quando autorizadas, sem badges Game Growroom por participação restrita |
| **aculta_gallery / aculta_image / aculta_document_viewer** - P1 | Media Gallery/Media e visualização de documentos | Anexar/visualizar mídia/arquivos via owners e filtros, permissão e integridade nos posts, sem duplicar storage de anexos |
| **aculta_linkweed** - P1 | Links curtos e métricas de cliques | Citar URLs curtas válidas, backlinks autorizados e tags de fonte; tracking e estatística pertencem ao Links/Statistics, sem prêmio por cliques |
| **aculta_events / aculta_calendar** - P2 | Events, programação, espaços/organizações, propostas e calendário | Compartilhar/fixar discussões referentes a evento por relação Index, sem criar automaticamente tópico, mantendo propósito e autorização do evento |
| **aculta_activity** - P2 | Conteúdo de atividade com adapter Index | Feed da Conta/Studio de contribuições recentes e marcos, sem publicar histórico privado ou criar feed paralelo de User |
| **aculta_maps** - P3 | Serviço de mapas de Eventos/Local | Prévia autorizada de local em tópicos que vinculam evento oficial, sem geocodificação arbitrária de texto |
| **aculta_commerce / aculta_donation** - integração indireta somente | Checkout/doação e eventos financeiros separados | Game pode reconhecer apoio já existente; **não** exibir compras, valor, pagamentos ou recompensar gasto/apoio como popularidade no Fórum |
| **aculta_portal / aculta_portal_sitemap** - P1 | DomainPurposeManager, indexação/sitemap e shell base | URLs e canonical por purpose, SEO/sitemap de tópicos públicos, excluir material não publicado, arquivo ou Group privado |

**Não incorporar funcionalidades sem contrato:** mensagens privadas e chat são exclusivamente Messenger; aprendizagem exclusivamente LMS; denúncias CGU; revisão editorial Wiki/Revista; pagamentos Commerce; catálogo de conquistas User/Game; CSS/SDC tema. Cada integração requer teste de presença/ausência de módulo opcional e degradação sem exception.

## 6. Lacunas adicionais do Discourse - revisão 2026

| Feature de referência e fonte oficial | ACULTA atual | Ação recomendada |
| --- | --- | --- |
| **Solved / resposta aceita** ([Discourse Solved](https://meta.discourse.org/t/discourse-solved/30155)) | Não confirmada implementação funcional equivalente em Fórum CURRENT | P1 - aceitar uma resposta, autor do tópico ou moderador autorizado; guardar `accepted_post_id`, aceitar/revogar auditado e publicar em Index/Statistics/Game, sem extrapolar LMS |
| **Topic Voting / ideias** ([Discourse Topic Voting](https://meta.discourse.org/t/discourse-topic-voting/40121)) | Poll Builder no roadmap, mas **votar num tópico inteiro não é enquete** | P2 - votação em categorias de propostas, alternativa a likes; escopo por categoria e limite de votos; nunca confundir com poll de múltipla escolha |
| **Assign / responsável pelo tópico/caso** ([Discourse Assign](https://meta.discourse.org/t/discourse-assign/58044)) | CGU e moderação em planejamento | P1 - atribuir caso a moderador na Review Queue Studio; responsável não ganha ACL por atribuição, tudo auditado |
| **Reactions com Like canônico** ([Discourse Reactions](https://meta.discourse.org/t/discourse-reactions/183261)) | ReactionManager com vários emojis, sem regra dedicada de Like | P0/P1 - normalizar semântica like para Game e perfil mantendo emoji original e sem dupla contagem |
| **Badges + métricas de perfil** ([guia oficial](https://meta.discourse.org/t/badges-reference-guide/310717)) | Game existente, apenas first comment no Growroom | P1 - troféus reais, filtros de privacidade e apresentação na Conta/autor/card |
| **Trust Levels** ([guia oficial](https://meta.discourse.org/t/discourse-trust-levels-a-detailed-explanation/396792)) | Papéis e email_confirmed institucionais | Adotar **somente** boas práticas de onboarding e indicadores individuais; não automatizar cargos/moderação |
| **Polls no composer** ([guia oficial](https://meta.discourse.org/t/creating-and-managing-polls/77548)) | FDC-R5 planejado | P1 - Enquete nativa atômica ao tópico; opção no popup, votação e resultado, Statistics; sem shortcode como fonte |
| **Review Queue e staff notes** ([guia de moderação](https://meta.discourse.org/t/discourse-moderation-guide-part-3-managing-content/406266)) | CGU existe; popup tópico não executa toda ação in-modal | P0 - caso/nota/timeline protegidos no Studio, comandos comuns backend/admin e popup frontend, sem redirecionar |
| **Slow mode, fechamento agendado e notas de equipe** (mesmo guia) | Fechar/arquivar já existe por estado; demais fluxos não comprovados | P2 - limitar frequência de resposta por tópico e agendar reabertura/fechamento, mantendo cache/ACL |
| **Bookmarks com lembretes** ([referência](https://meta.discourse.org/t/discourse-2-5-0-beta3-release-notes/148766)) | Flag seguir/salvar e Messenger existentes | P2 - lembrar de voltar ao tópico via Messenger, opt-in, sem tarefa/cron alternativo |
| **Citação por seleção, rascunhos, menção, links cruzados, timeline** ([documentação Discourse](https://meta.discourse.org/)) | Parcial (token UID, composer, timeline, Index/History) | P1/P2 - verificar o comportamento real e completar draft, quote, seleção, backlinks e leituras com fallback sem JS |
| **Busca por filtros e relatórios** (Discourse) | Views/Search API/Index/Statistics parcialmente disponíveis | P1 - busca avançada por autor/categoria/tag/data/solved e links contextuais com grants e paginação |
| **Chat, IA, MCP e ranking gamificado** ([Gamification oficial](https://meta.discourse.org/t/discourse-gamification/225916)) | Messenger cobre comunicação; assistentes externos não são requisito | P3 sob decisão separada; não clonar stack Discourse nem criar chatbot/motor de XP em nome da paridade |

**Ordem das entregas:** corrigir ForumPost + ACL/popup + Studio/CGU (FDC-R1..R4) **antes** de acionar novos prêmios; depois stats pessoais/like + badges (FDC-R9..R10); incorporar Solved, Assign, Poll/Topic Voting, bookmarks e Index conforme matriz de risco; finalizar com Homelab/CI/Runtime real. Interface admin só administrator.

## 7. Novos marcos no roadmap (continuam FDC-R0..R8)

| ID | Prioridade | Implementação | Gate |
| --- | --- | --- | --- |
| **FDC-R9 - perfil quantitativo** | P0/P1 | Contrato `forUser`: likes dados/recebidos por tipo, tópicos criados, respostas publicadas, tópicos distintos respondidos; separação view/created | Count CURRENT exact sob ACL, sem revista/arquivo/LMS, sem `getUserStats` indevido, sem N+1 |
| **FDC-R10 - troféus Game** | P1 | Catalogar 10/25/50, first like/received, criar tópico, gatilhos/locks/privacidade/avisos | Concessão idempotente, reversão de reação não duplica, badges imutáveis do User, sem XP/papel |
| **FDC-R11 - Discourse utility parity** | P1/P2 | Resposta aceita (Solved), atribuição de caso (Assign), bookmarks/lembretes, tags, citação, backlinks, slow mode e voto em tópico como feature separada de poll | Teste Unit/Kernel/Functional/UI, acl por target, integridade, origin e por domínio |
| **FDC-R12 - integrações transversais** | P1/P2 | Aculta Index/Messenger/Game/Statistics/Studio, Wiki/Revista/LMS, Gallery/Media/Links/Events/Activity | Matriz de contratos, módulo opcional ausente/presente, sem cross-host/sigilo, cache e performance |
| **FDC-R13 - conformidade e release** | P0/P1 | Benchmarks, reconciliador dry-run, Selenium/Playwright, PHPStan10/PHPCS/Unit/Kernel/Functional + Runtime | Sem P0/P1; privilege backend admin-only; testes de regressão e upgrade; aprovação antes de RC |

## 8. Matriz de aceite adicional (executar antes de declarar paridade)

- **T-43** um usuário com três tópicos CURRENT e dois posts CURRENT expõe `topics_created=3`, `replies=2`; `getAuthorPublicPostCount=5` não é reutilizado como `topics_created`. Comentário Wiki/LMS/Revista e arquivo não alteram contadores Growroom.
- **T-44** `like` canônico separado dos emojis: reação broccoli/heart/rocket e toggle/removal produzem dados/recebidos e saldos coerentes; `ForumReactionEvent` agregado não autoriza criar badge nominal.
- **T-45** conteúdo despublicado, removido, restaurado, autor modificado em revisão, fraude e reindex: snapshot, prêmio e histórico sem concessão dupla; Migrate/ARCHIVE 0 conquistas e stats atuais.
- **T-46** primeiro like dado, primeira recebida, Nice/Good/Great Topic e Reply por **alvo individual** em 10/25/50 contas distintas elegíveis; teste com vários posts que somam o limiar sem nenhum alvo individual atingir.
- **T-47** `Appreciated`: 20 posts diferentes com ao menos 1 curtida cada; várias curtidas num único post não qualificam. Remoção e regra de concessão histórica obedecem política aprovada.
- **T-48** perfil privado/ocultação de conquista e conta bloqueada: nenhum badge ou estatística pessoal expostos por SDC, JSON, autocomplete, menu, cache cruzado ou Index; owner vê só informação autorizada.
- **T-49** `forum.topics.views` e `history` não são interpretados como tópicos distintos lidos; `mark all read` não concede Reader; coleta de pessoa depende consentimento/decisão.
- **T-50** desabilitar Game e reinstalar: Fórum e Stats seguem operacionais, awards anteriores no User preservados, nenhum hook duplicado; locks/races não dobram concessão.
- **T-51** Solved: aceita, retira, muda autor, edita, remove resposta atual, arquiva tópico e audita, com permalink e Index sem vazamento.
- **T-52** Topic Voting distingue-se de Poll Builder; sem voto em enquete capaz de influenciar votos de ideias, e sem badges por voto privado.
- **T-53** Assign de moderação only Marley: moderador acessa caso atribuído sem adquirir permissões adicionais; CGU único, LMS pedagógico isolado.
- **T-54** Index backlinks e links de wiki/matéria/evento: acesso ao destino revalidado, nenhuma entidade privada aparece em resposta pública.
- **T-55** Messenger reprocessa badge/curtida/solução sem aviso duplicado, opt-out e revogação de acesso testados; nomes de usuários resolvidos por UID atual.
- **T-56** Admin-only backend + Studio: moderador vê apenas Marley Fórum, content_editor opera Studio editorial, Educador só GanjaLab, sem atribuir papéis por conquista.
- **T-57** estatísticas e perfis em 1k/10k/100k posts e múltiplos emojis sem N+1, deduplicação/reconciliação em banco de teste, todos os comandos documentados.
 
**Todos T-43..T-57 = PLANNED.** Não promover a PASS até executar Unit/Kernel/Functional/Browser, PHPStan10, performance e Runtime autorizado; cada evidência inclui SHA, comando, ambiente, métricas, outputs e rollback.

## 9. Fontes externas verificadas nesta rodada

- Discourse badges: https://meta.discourse.org/t/badges-reference-guide/310717
- SQL das badges padrão: https://meta.discourse.org/t/the-default-badge-queries/276812
- Discourse Gamification (pontos/leaderboards como plugin, deliberadamente não adotados): https://meta.discourse.org/t/discourse-gamification/225916
- Reações e Like: https://meta.discourse.org/t/discourse-reactions/183261
- Trust Levels: https://meta.discourse.org/t/discourse-trust-levels-a-detailed-explanation/396792
- Solved: https://meta.discourse.org/t/discourse-solved/30155
- Assign: https://meta.discourse.org/t/discourse-assign/58044
- Topic Voting: https://meta.discourse.org/t/discourse-topic-voting/40121
- Poll: https://meta.discourse.org/t/creating-and-managing-polls/77548
- Moderação: https://meta.discourse.org/t/discourse-moderation-guide-part-3-managing-content/406266
