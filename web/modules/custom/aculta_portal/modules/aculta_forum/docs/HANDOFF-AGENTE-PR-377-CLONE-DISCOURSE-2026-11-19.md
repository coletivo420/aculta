# Handoff para agente CLI - PR #377, primeira rodada do clone Discourse

Data: 2026-11-19. Branch de trabalho: `docs/forum-clone-discourse-ui-cgu-integracoes-20261119`, PR: https://github.com/coletivo420/aculta/pull/377, destino: `dev`. **Manter commits nessa branch**, sem force-push ou bypass do PHPStan level 10. Esta é uma entrega de código inicial, **não é homologação do Runtime**.

## Fechamento de gates — 2026-11-20

Autorizado pelo responsável: Kernel no banco dedicado `aculta_simpletest`. Resultados nesta branch:

- Kernel CGU e Fórum (`aculta_cgu` + `aculta_forum`): 181 testes, 3.668 asserções, exit 0. Dois testes de denúncia falhavam por fixture sem dono no tópico (`CURRENT` exige `uid`); o hook fail-closed está correto e a fixture foi corrigida.
- Kernel LMS (`aculta_lms`): 74 testes, 2.966 asserções, exit 0.
- Unit completo: 3.379 testes, exit OK. A branch quebrava 17 arquivos de teste pelo contrato Portal (`#theme => portal_component`, `#name`, `data-portal-ui`, `portal_button`); os testes e um fixture pré-refator foram atualizados ao contrato atual.
- PHPStan nível 10: exit 0. Baseline reduzido legitimamente: 9 entradas removidas e 2 contagens reduzidas, sem aumento. `phpstan-debt-guard check` e `diff` PASS.
- Lint dos PHP alterados, `phpunit-suites --check`, `validate-agent-skills`, `validate-agent-token-budget` e `validate-aculta-portal-drupal12`: PASS.

Pendentes, sem execução nesta rodada: teste Runtime (exige decisão sobre checkout do servidor de desenvolvimento), PHPCS do escopo, Functional HTTP/HTMX autenticado e checagem do CI do SHA publicado. Plano P1/P2 (Index, Statistics, UI Suite, CGU além do P0, paridade Discourse) permanece aberto.

## Auditoria P0 — FDC-02 e CGU — 2026-11-20

- **FDC-02 (respostas de tópico):** `DiscussionReplyFormBuilder::buildForumPostReplyForm` já cria `ForumPost` para tópicos `aculta_forum_topic`. Respostas `Comment` ficam restritas a hosts contextuais (Wiki, Revista, LMS), como o handoff manda. Verificado pelo Kernel `DiscussionReplyForumPostKernelTest` (3 testes, 42 asserções, exit OK).
- **CGU — propósito por host:** `ReportService::resolvePurpose` resolve por host e recusa com erro explícito contextos sem adapter (fail-closed).
- **CGU — julgada vs. aplicada, propostas:** `ContributionLifecycle::decide` grava o desfecho e aplica os campos na mesma transação. Conflito de origem vira `conflict` sem aplicar nada.
- **CGU — julgada vs. aplicada, denúncias:** `ReportService::decideReport` grava apenas status, notas e autoria; não altera o conteúdo denunciado. O campo `batch_apply` em `CguReportDecisionForm` replica o mesmo julgamento a outras denúncias pendentes do mesmo alvo. O nome é enganoso, mas não há ação de moderação automática nesse caminho.

Pendências: P1 (Index, Statistics, UI Suite) e P2 (paridade Discourse) não iniciados.

## P1 FDC-06 — métricas excluem acervo histórico — 2026-11-20

- **Corrigido:** `ForumMetricsProvider` contava como respostas públicas os `Comment` de tópicos com `field_autor_legado` (acervo). Agora as consultas de Comment passam por `applyCurrentCommentScope`, que aplica o escopo público e exclui tópicos hospedeiros do acervo, pela mesma fronteira de `LegacyDiscussionOriginClassifier`. Posts `ForumPost` correntes não mudam.
- **Verificação:** PHPStan nível 10 exit 0; `phpstan-debt-guard` check e diff PASS; Unit `aculta_forum` 278 testes PASS.
- **DEFERRED:** teste Kernel que prova a exclusão. As consultas dependem de `SiteManager` e do domínio do Fórum registrado no site, que exigem fixture de site própria. Até essa fixture existir, a correção não tem prova automatizada.

## Entidades próprias do acervo histórico — 2026-11-20

Decisão do responsável: o acervo do Growroom tem entidades próprias do módulo `aculta_forum_legacy`, separadas das entidades do Fórum corrente (`node`, `comment`, `aculta_forum_post`). O acervo é somente leitura: sem comentários, sem reações, sem moderação e sem criação pela interface.

Entidades (todas `internal`, permissão `administer aculta legado`):

- `aculta_forum_legacy_user` (autoria histórica, já existia).
- `aculta_forum_legacy_topics` (tópicos históricos).
- `aculta_forum_legacy_comments` (respostas históricas, sem campo de comentário ou reação).
- `aculta_forum_legacy_attachments` (anexos históricos, com URI própria e sem vínculo com mídia corrente).

Reações foram removidas do escopo: o acervo não aceita reações, e o sistema corrente não lê reações históricas.

Comportamento:
- `LegacyForumContentAccessControlHandler`: somente `view` para quem tem a permissão; `create`, `update` e `delete` negados pela interface. Importação passa pela API de entidade.
- `LegacyDiscussionOriginClassifier`: entidades do acervo são sempre `ARCHIVE`, sem depender de marcador.

Validação local: Kernel `LegacyForumContentEntitiesKernelTest` 1 teste, 20 asserções, exit OK; PHPStan nível 10 exit 0; guard PASS; PHPCS sem erros nos arquivos novos.

Pendências, sem execução:
- `aculta_forum_legacy_update_8001` cria as tabelas no Runtime. Não foi aplicado; exige autorização e snapshot (fluxo estrutural).
- Migração das linhas marcadas com `field_autor_legado` em `node` e `comment` para as entidades novas: não iniciada. Antes, precisa de contagem somente leitura no Runtime.
- Denúncias do acervo continuam no CGU, referenciadas pelo alvo; não são entidade do legado.

## P1 FDC-05 — índice público só com discussões correntes — 2026-11-20

- **Antes:** `ForumContentIndexProvider::isIndexable` aceitava qualquer tópico `aculta_forum_topic` e qualquer `Comment` de tópico, inclusive os do acervo histórico.
- **Agora:** o provider recebe `DiscussionOriginResolver` e indexa tópico e Comment só quando a origem é `CURRENT`. Origem `ARCHIVE` ou `UNKNOWN` falha fechada e não entra no índice público. `ForumPost` continua pela regra de revisão padrão.
- **Verificação:** Unit `aculta_forum` 278 testes sem falhas; Unit `aculta_index` `ContentIndexAccessFilterTest` 10 testes OK; PHPStan nível 10 exit 0; guard PASS; PHPCS sem erros no provider.
- **Custo conhecido:** tópicos correntes sem dono (`uid` vazio) passam a ficar fora do índice por falha fechada. Precisa de revisão de dados antes de qualquer reindexação.
- **DEFERRED:** reindexação do Runtime e teste Kernel de busca. Não executados.

## P1 FDC-06 (complemento) — tópicos do acervo fora das métricas — 2026-11-20

- **Antes:** só as respostas eram filtradas. Contagens de tópicos, tópicos sem resposta e reações em tópico usavam `applyPublicTopicConditions`, que não exclui tópicos com `field_autor_legado`.
- **Agora:** `ForumMetricsProvider` aplica `applyCurrentTopicScope` em todas as consultas de tópico (13 pontos). Tópicos do acervo saem das métricas correntes; as respostas continuam filtradas pelo host.
- **Verificação:** PHPStan nível 10 exit 0; guard PASS; Unit `aculta_forum` 278 testes sem falhas.
- **DEFERRED:** Kernel de prova das contagens (mesma fixture de site pendente da fase anterior).

## P1 FDC-07 (início) — variante somente leitura do cartão — 2026-11-20

- `forum-post-card` ganhou a prop `read_only` (padrão falso). Com ela, o cartão do acervo não mostra resposta, reações nem ações. Story `forum-post-card.archive.story.yml`.
- **Verificação:** schema SDC PASS (151 componentes, 2874 verificações); fundamentos de design PASS.
- **DEFERRED:** `validate-aculta420-v2-sdc.php` (exige bootstrap Drupal com `DRUPAL_ROOT`, Drush e site instalado) e render do Twig na story. Sem Runtime nem banco de site, não há PASS dessas partes.
- **Pendente:** aplicar `read_only` nas Views/templates do acervo quando o arquivo for exibido pelas entidades próprias; o cartão ainda não é usado por essas telas.

## Matriz de consumo dos SDCs de Comunidade pelo Fórum — 2026-11-20

Contagem: referências a cada SDC fora da própria pasta (PHP, Twig, YAML) em `aculta420_v2` e `aculta_portal`. Não é prova de renderização: "0" é lacuna confirmada; "≥1" é referência encontrada, ainda sem prova visual ou Kernel.

Pedidos do responsável, com situação:

- Postagem de abertura do tópico (`forum-opening-post`): 3 referências. Referenciado.
- Citação (`forum-quote`): 1 referência. Referenciado.
- Cartão de Post do Fórum (`forum-post-card`): 2 referências. Ganhou a prop `read_only` nesta rodada.
- Cartão do Post Espelhado (`forum-reply-mirror`): 1 referência. Referenciado.
- Compositor de Criação de Tópico (`discussion-topic-composer`): 1 referência, **só na Story**. O popup real usa `TopicPopupLayout`, não o SDC. **Lacuna**: o SDC não é o compositor de produção.
- Compositor de Resposta da Discussão (`discussion-reply-composer`): 6 referências, incluindo template `discussion-reply-composer.html.twig`. Referenciado.
- Ícones (`icon`): consumido em 60 pontos do tema e do portal.
- Respostas em níveis (`reply-indent`): 2 referências.
- Abas (`forum-tabs`): 2 referências.
- Diretrizes do compositor (`forum-composer-guidelines`): 0 referências antes desta rodada. **Implementado**: `TopicPopupLayout::apply` monta o SDC recolhido com três dicas. Prova: `TopicPopupLayoutTest` (1 teste, 10 asserções, OK). Render do Twig não provado.

Correção (2026-11-20): a contagem anterior não varria `components/`, e por isso marcou falsos zeros. Recontagem com `components/` incluído, excluindo stories:

- `forum-author-line`: 4 referências (`forum-reply-compact`, `forum-post-card`). Consumido.
- `comment-admin-trigger`: 1 referência (`content-reply-bar`, controle de moderação na resposta). Consumido.
- `forum-reply-mirror`: 1 referência, só na página de visão geral da UI Patterns. Fora do Fórum; o espelhamento real usa `forum-post-card` com `author_position: side`.
- `discussion-topic-composer`: consumido pelo enquadramento do formulário de tópico.

Lacuna real restante (confirmada com a recontagem):

- `forum-topic-card`: 0 referências. Falta decidir onde o cartão de tópico com miniatura aparece (listagem alternativa ou destaque de categoria). Decisão pendente do responsável.

Demais itens do grupo Comunidade com referência: `comment-admin-controls`, `content-reply-bar`, `delete-confirm`, `discussion-content-composer`, `discussion-reaction-bar`, `discussion-reactions-received`, `forum-category`, `forum-category-header`, `forum-category-page`, `forum-comment`, `forum-list-page`, `forum-reply-compact`, `forum-search-page`, `forum-sidebar-panels`, `forum-topic`, `forum-topic-meta`, `forum-topic-page`, `forum-topic-row`, `reaction-emoji`.

Pendências:

1. Trocar a integração do compositor de criação de tópico para o SDC, ou documentar que o formulário é a fonte de produção e a Story é só de biblioteca.
2. Integrar `forum-author-line`, `forum-topic-card` e `comment-admin-trigger` nas telas que deveriam usá-los, com prova de renderização.
3. Provas de render (Twig e Kernel) de todos os itens marcados como referenciados. DEFERRED.

## Compositor de tópico e cartão espelhado — verificação e decisão — 2026-11-20

- **Compositor de criação de tópico (`discussion-topic-composer`):** agora enquadra o formulário Drupal real. `TopicPopupLayout` define `#theme_wrappers = discussion_topic_composer`, e o template do módulo chama o SDC com `standalone_popup: false` e o formulário no slot `form`. Os campos não são movidos, então valores e handlers continuam os mesmos. Prova: `TopicPopupLayoutTest` (1 teste, 11 asserções, OK). Render Twig e Kernel DEFERRED.
- **Cartão espelhado (`forum-reply-mirror`):** não é usado pelo Fórum. Ele aparece só na página de visão geral da UI Patterns.
- **Espelhamento real das respostas:** `DiscussionReactionEntityViewHooks::discussionSide` alterna o lado por nível (raiz pela ordem; resposta em nível é oposta ao pai) e passa `accent_side` ao `forum-post-card`. O efeito é só a cor e a faixa do cartão.
- **Decisão do responsável (2026-11-20):** ligar o layout espelhado completo. Implementado: `comment--field-aculta-discussion` define `author_position` (`side` quando o lado é `end`, senão `top`); `forum-comment` aceita e repassa a prop ao `forum-post-card`. Schema SDC PASS (151 componentes). **DEFERRED:** prova visual em navegador (coluna de autoria e reações à direita) e render Twig. Reações ainda não foram movidas para a direita pelo cartão; isso depende do CSS do `forum-post-card--author-side` e precisa de revisão visual.

## Decisões de consumo: resposta compacta, espelhado e mobile em todos os consumidores — 2026-11-20

Decisões do responsável (confirmadas em duas rodadas de pergunta):

1. **Consumidores** (LMS, Wiki, Revista e comentários gerais) usam a **resposta compacta** (`forum-reply-compact`), que já tem Stories para desktop, espelhado, mobile e espelhado-mobile.
2. **Espelhamento** nos consumidores vem da resposta compacta com `accent_side` alternado, igual ao Fórum.
3. **Mobile** vale nas páginas reais, não só nas Stories.
4. **Consumidores não têm abertura de tópico**: a conversa começa na primeira resposta.
5. **Fórum**: o cartão do post (`forum-post-card`) continua com `author_position: side` no espelhado (entregue antes).

Implementado nesta rodada:

- `comment--field-aculta-discussion.html.twig`: o ramo da resposta compacta passa a valer para Revista e consumidores. A ordenação própria da Revista (`revista-comment-order`) continua só para `publication_comment`.
- `DiscussionReactionEntityViewHooks::preprocessComment`: calcula `consumer_comment` (host que não é tópico do Fórum).
- `forum-reply-compact.css`: regras de densidade compacta valem por largura de tela abaixo de 48rem (mobile-first, sem depender de prop).

Validação: Unit do Fórum 279 testes OK; PHPStan exit 0; guard PASS; PHPCS sem erros no hook; schema SDC PASS (151 componentes).

DEFERRED:

- Stylelint do CSS da resposta compacta: binário ausente (exit 127). Não é PASS.
- Flag `consumer_comment`: decisão extraída para `DiscussionReactionEntityViewHooks::isConsumerHost()` e provada por `ConsumerCommentHostTest` (3 testes, 6 asserções, OK). A montagem completa do `preprocessComment` continua sem teste Unit, por exigir mocks de storage e avatar.
- Prova visual em navegador de mobile e espelhado nos quatro consumidores.

Lacunas confirmadas nesta rodada:

- `forum-opening-post`: **consumido em produção** por `aculta420_v2/src/Hook/PagePresentationHooks.php` (`#component: aculta420_v2:forum-opening-post`), dentro da página completa do tópico, em `$content['aculta_opening']`. A contagem anterior não cobria `src/Hook` do tema. Regra: o post de abertura é sempre o primeiro post do tópico do Fórum (`aculta_forum_topic`); a montagem ocorre só nesse bundle. Consumidores (LMS, Wiki, Revista e comentários) não têm post de abertura, por decisão.
- `forum-topic-card`: 0 referências. **Decisão (2026-11-20):** não usar; a lista de tópicos fica só em linhas (`forum-topic-row`), como o Discourse. O cartão com miniatura é variante opcional do Discourse e não entra no Fórum. Documentado como não consumido por decisão, não como lacuna.

## Decisão — cartão espelhado no Fórum: forum-reply-mirror no desktop, variante mobile — 2026-11-20

Decisão do responsável: no Fórum, o desktop espelhado usa `forum-reply-mirror` e o mobile usa a variante "Cartão do Post do Fórum Espelhado (mobile)" (a story `forum-reply-mirror.mobile.story.yml`). O lado normal continua em `forum-post-card`.

Implementado:

- `forum-comment.twig`: `forum-reply-mirror` é usado para **resposta da resposta** (`reply_depth` > 0) com lado `end`. Respostas raiz seguem em `forum-post-card`. Decisão do responsável (2026-11-20): o mirror é para resposta da resposta. Antes, a regra usava só o lado e pegava respostas raiz. com as mesmas slots (avatar, corpo, ações, reações). Lado normal segue com `forum-post-card`.
- `forum-reply-mirror.twig`: passa `author_position: 'side'` (autoria em coluna à direita, como o contrato).
- `forum-reply-mirror.css`: no desktop (48rem), o rodapé vira linha com reações à direita e ações à esquerda (`row-reverse`).

Validação: schema SDC PASS (151 componentes, 2891 verificações).

DEFERRED: stylelint (binário ausente, exit 127) e prova visual de desktop e mobile nos consumidores.

## Design compacto dos consumidores: alternância por posição — 2026-11-20

Decisão do responsável: no design compacto, a alternância é resposta > espelhada > resposta > espelhada, pela ordem de exibição.

- `DiscussionReactionEntityViewHooks::consumerSide` calcula a posição pela ordem `thread` ASC entre os comentários publicados do mesmo host e campo.
- `alternatingSide(position)`: par = `start` (resposta), ímpar = `end` (espelhada).
- Fórum (host `aculta_forum_topic`) mantém `discussionSide` por árvore.
- Prova: `ConsumerCommentHostTest` com 4 testes, 10 asserções, OK. PHPStan exit 0; guard PASS.

Pendente: prova de ordem real (Kernel de comentários em LMS, Wiki e Revista) e visual. DEFERRED.

## Sequência de lados no Fórum e nos consumidores — 2026-11-20

Decisão do responsável: a sequência é por posição, com a abertura na posição 0 no Fórum.

- Fórum: abertura > espelhada > resposta > espelhada, e assim por diante. A 1.ª resposta é espelhada (`alternatingSide(índice + 1)`).
- Consumidores (LMS, Wiki, Revista, comentários): resposta > espelhada > resposta > espelhada (`alternatingSide(índice)`).
- `forum-reply-mirror` vale para toda resposta espelhada (lado `end`), raiz ou aninhada. A restrição por profundidade foi removida.
- Código: `sequenceSide` em `DiscussionReactionEntityViewHooks`; `discussionSide` (árvore) removido por não ser mais usado.
- Prova: Unit do Fórum 284 testes OK; PHPStan exit 0; guard PASS; PHPCS sem erros.

DEFERRED: prova visual e de ordem real nos consumidores e no Fórum.

## Respostas em níveis (`reply-indent`) — consumo e posições — 2026-11-20

- Consumo: `reply-indent` é incluído por `comment--field-aculta-discussion`, `comment--comments` e `forum-comment` (Fórum e consumidores). A seta de primeira resposta (`reply_first`) vem de `DiscussionReactionEntityViewHooks` pelos irmãos do mesmo pai.
- Posições: respostas aninhadas criam posições como `0.1`. A sequência de lados usa a ordem `thread` ASC, que já coloca cada resposta aninhada na ordem de exibição, então a alternância conta também os níveis.
- Profundidade: `DiscussionReactionEntityViewHooks::replyDepth()` lê a coluna `thread` (`01/` raiz, `01.01/` nível 1), limitado a 3, o máximo do SDC.
- Prova: Unit do Fórum 285 testes OK; PHPStan exit 0; guard PASS; PHPCS sem erros.
- DEFERRED: prova visual de recuo e seta nas páginas reais, e Kernel de ordem com respostas aninhadas.

## Ícone de administração generalizado (comentário e tópico) — 2026-11-20

Decisão do responsável: usar o ícone de administração existente (`comment-admin-trigger`) também no tópico, generalizado, e todas as ações administrativas em popup seguindo o padrão dos SDCs.

- `comment-admin-trigger`: novas props `kind` (`comentario`, padrão, ou `topico`) e `title` (opcional). O `data-action` vira `<kind>-controles`. Comentário mantém o comportamento atual.
- `comment-admin-popup.js`: o seletor passa a `[data-action$="-controles"]`, então o mesmo popup atende comentário e tópico.
- Validação: schema SDC PASS (151 componentes, 2895 verificações); `node --check` OK.

Implementado depois:

- Rota `aculta_forum.topic_admin_popup` (`/forum/topicos/{node}/controles`), protegida por `move aculta forum topics`. Controller `TopicAdminPopupController` devolve o link de mover categoria.
- Página do tópico (`PagePresentationHooks::composeForumTopic`, visão completa): para quem pode mover, o popup `aculta-admin-dialog` e o trigger `comment-admin-trigger` com `kind: topico` são mesclados ao `$page`. A biblioteca `aculta_forum/comment-admin-popup` é anexada.
- Validação: PHPStan exit 0; guard PASS; schema SDC PASS.
- DEFERRED: teste da rota (o controlador depende do contêiner do Drupal) e prova visual/HTMX do popup na página real.

Desfeito nesta rodada: o `forum-topic-admin-menu` criado antes, que não era necessário.

## Paridade dos controles de tópico com o Discourse — 2026-11-20

Decisão do responsável: os controles do `topic-admin-controls` são os controles de moderação do tópico, em paridade com o Discourse (mover, apagar, etc.), e não a estrutura de fatos do comentário.

Implementado (com rota e acesso no código):
- Editar tópico: `aculta_forum.discussion_composer` com `editar_topico=1`, visível com `access('update')`.
- Mover tópico de categoria: `aculta_forum.topic_move`, permissão `move aculta forum topics`.
- Excluir tópico: `aculta_forum.discussion_composer` com `excluir_topico=1`, visível com `access('delete')`.
- Registro de decisões do CGU sobre o tópico (slot `log`).

Lacunas de paridade (sem rota ou funcionalidade no código; não implementadas):
- Fechar/abrir tópico, fixar/desafixar, arquivar, tornar não listado, tags, dividir/juntar tópico, despublicar/republicar tópico.

## topic-admin-controls: moderação de tópicos com correspondência no CGU — 2026-11-20

Decisão do responsável: o Stories UI Patterns `comment-admin-controls` deve corresponder aos controles de moderação dos comentários no `aculta_cgu`; criar `topic-admin-controls` com a mesma correspondência para tópicos.

- Componente `topic-admin-controls` (`aculta420_v2`): mesma estrutura de `comment-admin-controls`, com categoria; slots `actions` (ações autorizadas) e `log` (registro de decisões). Schema SDC PASS (152 componentes).
- Controlador `TopicAdminPopupController`: monta o componente com autor, situação, categoria, data, reações e respostas. O registro vem dos relatórios do `aculta_cgu` (`aculta_cgu_report`, tipo `node`, id do tópico), sem os pendentes.
- Validação: PHPStan exit 0; guard PASS; PHPCS sem erros no controlador.
- DEFERRED: teste da rota e prova visual/HTMX do popup.

## Fixar tópico (paridade Discourse, parcial) — 2026-11-20

- Ação "Fixar tópico"/"Desafixar tópico" nos controles administrativos, com formulário de confirmação `TopicModerationPinForm` (rota `aculta_forum.topic_moderation_pin`) que usa o campo sticky do nó.
- Página do tópico mostra o selo "Fixado".
- Fixados no topo: IMPLEMENTADO na configuração. `sticky` DESC é o primeiro critério em `views.view.aculta_forum_public_topics` (listagem pública e "Mais comentadas", em `config/sync` e na instalação do módulo). A linha `forum-topic-row` recebe `pinned` do campo `sticky` e mostra o selo "Fixo". Story fixada (default) e não fixada (`unpinned`). PENDENTE: prova no Runtime (import de config e visual) e teste Kernel da ordenação.
- Validação: Unit do Fórum 288 OK; PHPStan e guard PASS. DEFERRED: Kernel e visual.

## Controles administrativos nos consumidores — 2026-11-20

Decisão: os controles administrativos valem também para os consumidores do Fórum (Wiki, Revista, LMS e comentários).

- Respostas: o gatilho `comment-admin-trigger` já aparece em cada resposta de consumidor para quem pode administrar comentários (existente).
- Discussão do conteúdo-host: novo gatilho na página do host (`DiscussionHostAdminHooks`, node_view), que abre o popup por HTMX (`aculta_forum.discussion_host_admin_popup`). A ação "Abrir ou fechar discussão" leva ao formulário `DiscussionHostStatusForm` (alterna o campo `field_aculta_discussion`).
- Componentes e Stories: `discussion-admin-controls` (novo, com story default e README) e `comment-admin-trigger` (reutilizado com `kind: topico`).
- Validação: schema SDC PASS (153 componentes); Unit do Fórum 288 OK; PHPStan e guard PASS; PHPCS sem erros nos arquivos novos.
- DEFERRED: Kernel do formulário de abrir/fechar discussão e prova visual/HTMX do popup nos consumidores.

## Revisão das decisões recentes — comportamento esperado para confirmação — 2026-11-20

Legenda: IMPLEMENTADO (código, com validação local) · PARCIAL · PENDENTE.

Barra de resposta (consumidores e Fórum)
- Autor: pode editar a própria resposta pela barra (IMPLEMENTADO).
- Ninguém exclui pela barra (IMPLEMENTADO: delete_allowed = FALSE).
- Moderador não edita pela barra (IMPLEMENTADO).

Edição administrativa de comentário
- Moderador edita pelo painel administrativo (link "Editar pela moderação", IMPLEMENTADO).
- Ao salvar, o comentário recebe "Editado pela moderação": selo visível, marca permanente, sem caminho de remoção (IMPLEMENTADO; Unit prova autor não marcado e moderador marcado).
- Log administrativo com quem editou e quando, só nos controles administrativos, sem exibição pública (IMPLEMENTADO; Unit cobre uid e data).

Exclusão de comentário
- Autor: mensagem própria "Comentário excluído pelo autor" (EXISTENTE).
- Moderação: "Removido pela moderação" pelos controles, com restaurar (EXISTENTE; mensagem de tombstone 'moderacao').

Exclusão de tópico pelo autor
- Tópico continua aberto com "Conteúdo excluído pelo(a) autor(a).", e o CGU recebe relatório pendente (IMPLEMENTADO; Forum depende de aculta_cgu no info.yml).

Remoção de tópico pela moderação
- Tópico continua aberto com "Conteúdo removido pela moderação.", pela ação "Remover pela moderação" nos controles (PARCIAL: formulário e marca implementados; ainda sem prova Kernel).

Fechar tópico
- Ação "Fechar ou reabrir tópico" nos controles; tópico fechado fica legível e mostra "Tópico fechado pela moderação." (PARCIAL: marca, formulário e aviso implementados).
- Bloqueio de novas respostas em tópico fechado: IMPLEMENTADO em `DiscussionReplyAccess::checkAccess` (negado com "Topic is closed by moderation."); leitura continua. Unit do Fórum 287 OK; prova de ponta a ponta (Kernel) DEFERRED.

Excluir tópico totalmente
- Ação "Excluir tópico" nos controles, com o acesso decidido pelo Drupal (EXISTENTE; sem alteração nesta rodada).

Controles administrativos do tópico
- Editar, mover de categoria, excluir, fechar/reabrir, remover pela moderação, registro do CGU (IMPLEMENTADO no popup; visual e rota DEFERRED).
- Lacunas de paridade com o Discourse (fixar, arquivar, tags, dividir/juntar, despublicar): PENDENTES.

Provas
- Forum Unit 287 OK; PHPStan e guard PASS nesta rodada.
- DEFERRED: Kernel dos fluxos de exclusão e remoção; prova visual; stylelint ausente (exit 127).

## Correção: o autor nunca exclui pela barra — 2026-11-20

Decisão do responsável: o autor jamais exclui pela barra de resposta; só a moderação exclui, pelos controles administrativos.

- Implementado: `delete_allowed` é sempre falso na barra (`DiscussionReactionEntityViewHooks`).
- Implementado nesta rodada: `aculta_forum` declara dependência de `aculta_cgu` em `aculta_forum.info.yml` (o CGU não depende do Fórum). Exclusão do tópico pelo autor: `TopicAuthorDeletion` grava a marca (`aculta_forum_topic_tombstones`), abre o tópico com a mensagem "Conteúdo excluído pelo(a) autor(a)." (`DiscussionTopicAuthorDeletionHooks`, node_view) e cria o relatório pendente no CGU via `aculta_cgu.report_service`. Unit do Fórum 287 OK; PHPStan e guard PASS.
- Ainda não provado: Kernel do fluxo de exclusão e prova visual da mensagem.
- Histórico anterior: ao o autor excluir um tópico, o tópico permanece aberto com a mensagem própria "Tópico excluído pelo autor", e a moderação é avisada pelo CGU (relatório pendente). Hoje `TopicDeleteConfirmForm` chama `processDeletion`, que exclui ou envia para a fila; o fluxo novo exige marca de tombstone do tópico, a mensagem na página e o relatório no CGU. DEFERRED.

## Edição e exclusão pelo autor; marca e log da moderação — 2026-11-20

Decisões do responsável:

- Editar e excluir pela barra de resposta é só do autor. A moderação edita e exclui pelos controles administrativos (`DiscussionCommentModerationForm`), com os comportamentos já previstos (remover, restaurar, excluir definitivamente).
- Ao salvar a edição, um moderador (não autor) gera a marca "Editado pela moderação". A marca é permanente: não há caminho para removê-la, e é independente de "Removido pela moderação".
- Log administrativo: cada edição de moderador grava quem editou e quando (`DiscussionModerationEditHooks`, coleção `aculta_forum_comment_moderation_edit_log`). Aparece só nos controles administrativos (slot `log`), sem exibição pública. O público vê apenas o selo.
- Validação: `DiscussionModerationEditHooksTest` (autor não é marcado; moderador é marcado e registrado com uid e data), OK. PHPStan exit 0; guard PASS; schema SDC PASS.
- DEFERRED: prova visual do selo e do log nos controles, e Kernel de exclusão/edição pela moderação.

## Migração do acervo: mapeamento de tópico (lote 1 de campos) — 2026-11-20

- `LegacyArchiveTopicMapper::map()` converte uma linha de tópico (nid, título, corpo, autor legado, domínio, criação) nos valores de `aculta_forum_legacy_topics`. Função pura: não lê nem escreve no banco.
- Chave estável `node:<nid>` (via `LegacyArchiveOrigin`); título limitado a 255 caracteres; título vazio recebe placeholder com o nid; linha sem id positivo é recusada.
- Prova: `LegacyArchiveTopicMapperTest` (3 testes, 14 asserções, OK). PHPStan exit 0; guard PASS.
- Pendente: leitura da fonte, escrita idempotente das entidades, e cópia dos comentários. Nenhuma linha foi migrada; Runtime não foi tocado.

## Popups do Fórum e dos consumidores; anexos provados por configuração — 2026-11-20

Decisões do responsável: todos os popups do Fórum e dos consumidores (LMS, Wiki, Revista, comentários) usam o estilo do Stories `popup`; os popovers ficam como estão.

- Verificado: os módulos LMS, Wiki e Revista não têm diálogo próprio. Os modais vêm dos componentes do Fórum e já usam `aculta420_v2:popup`.
- Popovers mantidos: `discussion-reaction-bar`, `discussion-reactions-received` e `delete-confirm` (`popover="auto"`).
- Anexos (decisão de consumidores): `ConsumerAttachmentPolicyTest` lê `config/sync` e prova que o bundle de comentário compartilhado não tem campo de arquivo ou imagem nem aponta para `forum_attachment`. 1 teste, 14 asserções, OK.

## Estilo do popup nas telas do Fórum — 2026-11-20

Decisão do responsável: todos os popups do Fórum usam o estilo do Stories `popup`, exceto os popovers nativos ancorados (`discussion-reaction-bar` e `discussion-reactions-received`). Os popups fora do Fórum não entram neste escopo.

Verificado: nenhum componente do Fórum desenha `<dialog>` próprio; os modais usam o SDC `popup` (resposta, citação, Revista e administração do tópico).

## Recontagem de consumo por código (somente twig, php e yml) — 2026-11-20

Recontagem excluindo docs, CHANGELOG e Stories. Antes, `forum-topic-card` e `forum-reply-mirror` pareciam consumidos por causa de docs.

- Sem consumidor de código: `forum-topic-card` (decisão: não usar, lista só em linhas) e `forum-reply-mirror` (coberto pela resposta compacta espelhada e pelo `forum-post-card` com `author_position: side`).
- Todos os demais SDCs de Comunidade têm consumidor de código.

Pendente de decisão do responsável: remover ou manter como componentes sem uso. Nada foi apagado.

## Decisão — anexos só no Fórum; consumidores apenas mídia embutida — 2026-11-20

Decisão do responsável:

- **Somente o próprio Fórum** (`aculta_forum`) aceita o sistema amplo de anexos (`forum_attachment` e `ForumAttachmentPublication`).
- **Consumidores do Fórum** (LMS, Wiki, Revista e comentários gerais) **não aceitam anexos**. Neles só é permitida **mídia embutida** (embedded media, referência inline ao conteúdo), sem campo de anexo.
- Entidades do acervo (`aculta_forum_legacy_attachments`) continuam somente leitura, e o anexo histórico não ganha canal de envio.

Plano ajustado:

1. Auditoria de configuração (2026-11-20): o bundle de comentário `aculta_discussion_post`, usado por Wiki, Revista e LMS contextual, tem apenas `comment_body`, `field_aculta_published_at`, `field_aculta_rejected` e `field_autor_legado`. Nenhum campo de arquivo ou anexo. Campos de mídia de LMS, Wiki e Commerce são galeria, capa e imagem, não anexo. Resultado: a regra vale por configuração. Falta prova em teste de recusa e de upload no formulário do consumidor. Pendente.
2. Impedir anexo nos consumidores pelo mesmo mecanismo de origem/contexto, com teste que prove a recusa. Pendente.
3. A lacuna de anexos do acervo no índice (abaixo) fica restrita ao Fórum: anexo só é indexável por referência de tópico ou resposta `CURRENT` do próprio Fórum.

## Verificação — reações compartilhadas no bundle `aculta_discussion_post` — 2026-11-20

Decisão do responsável: o bundle de comentário compartilhado deve compartilhar reações entre os consumidores.

- **Render:** `DiscussionReactionEntityViewHooks` monta a barra de reação para qualquer `aculta_discussion_post` em `field_aculta_discussion`, sem filtro por consumidor. Verificado por código.
- **Permissão:** `DiscussionReactionAccess` resolve o host pelo `DiscussionContextResolver`. Estão registrados os contextos público do Fórum, Publicação (Revista), Wiki e LMS. Host sem contexto registrado é recusado. Verificado por código.
- **Armazenamento:** tabela `aculta_discussion_reaction`, indexada por entidade e contexto público. Não separa por consumidor. Verificado por código.
- **Prova:** Unit com mocks em `DiscussionReactionAccessTest`, `DiscussionReactionEntityViewHooksTest` e `DiscussionReactionManagerOriginTest`. **DEFERRED:** Kernel de reação real em comentário de Wiki, Revista e LMS. Não há prova automatizada de ponta a ponta.

## Achado aberto — anexos do acervo no índice público — 2026-11-20

- **Lacuna:** `MediaContentIndexProvider::isIndexable` (provider de `forum_attachment`, registrado em `aculta_forum.attachment_content_index_provider`) aceita qualquer mídia do bundle. Anexos de tópicos do acervo entram no índice público pelo caminho de mídia, sem passar pelo filtro de origem aplicado a tópicos e comentários.
- **Correção necessária:** provider de anexo do Fórum que exige referência de um tópico ou resposta `CURRENT` (via o `DiscussionOriginResolver`). Falha fechada se não houver referência corrente.
- **Pré-requisito:** descobrir o campo reverso que liga o anexo ao tópico/resposta (uso por `entity_usage` ou campo de referência). Não alterado nesta rodada.
- **Status:** aberto, sem prova automatizada.

## Revalidação de continuidade — 2026-11-20

Base: `origin/docs/forum-clone-discourse-ui-cgu-integracoes-20261119` em `506512bd8`. A cópia local de `docs/...` no checkout principal estava atrás do remoto em 141 commits sem commits próprios; o trabalho foi feito em worktree dedicado, sem tocar o worktree `aculta-pr377`, que tem alterações de outra frente.

Resultados medidos nesta rodada:

- Unit `aculta_forum`: 278 testes e 2.130 asserções PASS, com 17 depreciações PHPUnit.
- Unit `aculta_cgu`: 3 testes e 19 asserções PASS.
- Focal não-Kernel dos P0 (`DiscussionReplyControllerQuoteTest`, `DiscussionReplyAccessTest`, `ForumQuoteHooksTest`, `TopicDeletionRoutesTest`): 15 testes e 94 asserções PASS, com 49 depreciações.
- `php -l` nos PHP alterados existentes: PASS.
- Kernel focal (`CguReportPolicyKernelTest`, `ForumCguIntegrationKernelTest`): **DEFERRED**. Os 14 casos falham antes da execução por falta de `SIMPLETEST_DB`. A tentativa de carregar `secrets/simpletest-db.env` para rodar no banco `aculta_simpletest` foi bloqueada pelo classificador de permissões da sessão; não houve execução de teste nem acesso ao Runtime. Precisa de autorização explícita do responsável para repetir.

Não executados nesta rodada: PHPCS, PHPStan nível 10 do escopo, Functional HTTP/HTMX autenticado, ESLint e o CI deste SHA. Os itens P1/P2 do plano (Index, Statistics, UI Suite, paridade Discourse) permanecem pendentes.

## Handoff — fronteira módulo/tema e validação local — 2026-11-20

O código desta fase foi publicado na PR em `55f022ad3` (`refactor(portal): concluir fronteira entre módulo e tema`); o registro da redução PHPStan foi publicado em `006983f84` (`docs(phpstan): registrar redução de dívida validada`). O HEAD remoto da PR foi confirmado em `006983f84`. A revisão humana foi informada pelo responsável. Nenhuma alteração foi integrada em `dev` nem aplicada ao Runtime.

### Feito e validado

- Concluído o lote de fronteira Portal/tema: estilos de checkout e loja passaram ao tema V2, render arrays usam o contrato de componente do Portal e o validador da fronteira passou. O teste `ForumTopicDisplayConfigTest` foi corrigido para garantir que `aculta_forum` não dependa do campo histórico `field_autor_legado`, agora pertencente ao addon opcional `aculta_forum_legacy`.
- Corrigida dívida PHPStan em arquivos alterados e removidas entradas resolvidas do baseline, sem baixar nível ou adicionar `@phpstan-ignore`. Relatório e snapshot refletem o SHA do commit funcional.
- PHP lint: PASS, 196/196 arquivos. PHPStan 2.3.5 nível 10: PASS, zero erros não suprimidos. `phpstan-debt-guard check` e `diff`: PASS; 243 entradas removidas e 14 contagens reduzidas. Bats dos guardrails: PASS, 20 casos.
- Unit focal Fórum/H5P e testes Unit alterados: PASS, 330 testes e 2.356 asserções; PHPUnit reportou 17 depreciações. Stylelint, validador Portal Drupal 12 (1.248 verificações), fronteira módulo/tema (7.306 verificações), classes mapeadas e fundamentos V2: PASS.
- O acervo Growroom não foi migrado, convertido, excluído nem misturado. Não foram executados Drush, sync/import de configuração, migrações ou operações no Runtime.

### Pendências e resultados não verdes

- PHPCS retornou código 123: zero erros e 309 avisos nos PHP examinados (292 de linha longa, 16 funções globais e 1 descrição `t()`); não declarar PHPCS PASS.
- A suíte Unit completa não concluiu: o processo terminou por redeclaração de `aculta_messenger_schema()` ao carregar a cópia do módulo na worktree e no checkout principal. Os testes focais acima passaram após corrigir as expectativas obsoletas do campo legado.
- Kernel permanece DEFERRED sem `SIMPLETEST_DB` de teste isolado. ESLint permanece DEFERRED porque o executável `eslint` não está instalado. Os checks de CI disparados pelo push não foram consultados nesta rodada.

### Próximos passos

1. Consultar os checks da PR para o SHA `006983f84`, distinguindo falha, pendente, cancelado e skipped.
2. Repetir a Unit completa em ambiente que não carregue `aculta_messenger.install` das duas árvores; não usar banco do Runtime. Rodar Kernel focal somente com banco descartável de testes.
3. Resolver ou classificar os avisos PHPCS por comparação com a base, sem alterações cosméticas em massa; executar ESLint quando houver binário disponível.
4. Retomar os itens pendentes do plano FDC e da matriz de aceite deste handoff. O resumo desta fase de fronteira não equivale a aceite funcional integral do clone Discourse.

## Atualização Dev.5 — 2026-11-19

A importação histórica do Growroom **ainda não foi feita**. Não há conteúdo
histórico importado no alvo; esta afirmação não descreve nem remove o corpus de
origem. Dev.5 remove do motor a conversão Comment→ForumPost e a autoria
`ForumPost.legacy_author`, além dos displays, plugins, mapas e bypass de
Flagging que existiam exclusivamente para esse fluxo. Nenhum registro foi
apagado, migrado ou alterado no Runtime.

Validação local da fase: Unit focal Fórum/Legacy/User 453 testes e 2.837
asserções PASS (24 depreciações); lint PHP, PHPCS, PHPStan nível 10, `phpstan-debt-guard check`
e `diff`, descoberta de suítes, índice de status e gate Portal Drupal 12 PASS.
Kernel focal foi tentado, mas não iniciou por falta de `SIMPLETEST_DB` (9 casos,
zero asserções); HTTP/Functional está DEFERRED por falta de
`SIMPLETEST_BASE_URL`. A suíte Unit completa foi tentada, mas a descoberta
parou antes dos testes porque o checkout não contém o módulo contrib
`webform_score`, exigido por `WebformScoreAccessTest`. A revisão independente
R2 da Dev.5 foi concluída sem achados no escopo revisado. Nenhuma operação no
Runtime foi executada.

## Atualização Dev.6 — 2026-11-19

`ForumTopicMover` e `ForumCommentMover` agora usam `DiscussionOriginResolver`;
eles movem somente tópico, Comment e descendentes classificados `CURRENT`.
Origem `ARCHIVE` ou `UNKNOWN` falha fechada, sem ler `field_autor_legado` no
motor. As fixtures Kernel deixaram de instalar esse field.

Validação local Dev.6: PHP lint e PHPCS nos seis arquivos PHP alterados PASS;
PHPStan nível 10 global (0 erros/0 file errors) e `phpstan-debt-guard check`/
`diff` PASS; Unit focal Fórum/Forum Legacy/User-Identity 300 testes e 2.220
assertions PASS (25 depreciações); gate Portal Drupal 12 PASS (1.248 checks),
índice de status PASS (43 componentes) e `git diff --check` PASS. A correção de
tipos em `aculta_user` removeu nove ocorrências reais do baseline de
`AuthorCard.php`, sem supressões.

Revisão independente R2 da Dev.6 concluída sem achados. Os dois Kernel focais
estão DEFERRED por falta de `SIMPLETEST_DB`; a descoberta PHPUnit ampla também
falha antes da execução porque falta o módulo contrib `webform_score`. CI do
novo commit ainda estava pendente/em execução na última consulta. Nenhuma
operação no Runtime foi executada.

## Instrução principal

Você está continuando o clone funcional Drupal do Discourse nativo sobre `aculta_forum`, sistema único de comentários de Growroom corrente, Wiki, Revista, LMS e demais hosts registrados. **Não misture os Comments históricos do Growroom com comentários padrão nem os converta em ForumPost**. A política permanente está em [POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md](POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md); ela prevalece sobre qualquer documentação histórica de migração. Históricos ficam read-only, com identidade, links e estatísticas próprios. Novas publicações Growroom usam ForumPost; Wiki/Revista/LMS usam Comment contextual sem criar tópicos públicos paralelos.

## Passo 0 - Inspeção do checkout e dos commits

```bash
git fetch origin
git switch docs/forum-clone-discourse-ui-cgu-integracoes-20261119 || git switch --track -c docs/forum-clone-discourse-ui-cgu-integracoes-20261119 origin/docs/forum-clone-discourse-ui-cgu-integracoes-20261119
git status --short
git rev-parse HEAD
git log --oneline -12
git diff --stat origin/dev...HEAD
git diff --check origin/dev...HEAD
```

Não limpar/stash/resetar mudanças de outros agentes. Se a worktree compartilha alterações, selecionar apenas arquivos próprios para commit. Conferir `AGENTS.md`, instruções do Portal e submódulo, `docs/operations/GATES.md` e regras R2/R3 de revisão independente.

## Fase de validação imediata - auditar e consertar o que entrou na PR

Commits desta rodada criaram o controle de citação por host/ACL e exclusão histórica, resolveram `ReportService::resolvePurpose()` por host com fail-closed em LMS sem adapter, alteraram rotas GET para formulários de confirmação, adicionaram Unit/Kernel e atualizaram documentação. **Nenhum teste PHPUnit ou PHPStan foi executado neste ambiente**: executar agora, corrigir os novos FAILs e não marcar PASS sem registro.

```bash
git diff --check origin/dev...HEAD
php scripts/phpunit-suites.php --check
vendor/bin/phpunit --filter 'DiscussionReplyControllerQuoteTest|DiscussionReplyAccessTest|ForumQuoteHooksTest|TopicDeletionRoutesTest'
vendor/bin/phpunit --filter 'CguReportPolicyKernelTest|ForumCguIntegrationKernelTest'
php scripts/validate-aculta-portal-drupal12.php
php scripts/validate-admin-routes-main-domain.php
python3 scripts/validate-agent-skills.py
php scripts/validate-agent-token-budget.php
vendor/bin/phpstan analyse
php scripts/phpstan-debt-guard.php check
php scripts/phpstan-debt-guard.php diff
```

Usar MariaDB **de testes**, não `aculta_runtime`, para Kernel; gates Drupal/Runtime conforme `docs/operations/GATES.md`. Rodar `php -l` nos PHP alterados e PHPCS do escopo. Há proteção do check `PHPStan level 10 gate` na `dev`. Se o job falhar, corrigir na PR; não contornar baseline/checks ou fingir PASS.

### Contratos a provar com prioridade P0

1. **Citação cruzada:** `DiscussionReplyController`, `ForumQuoteHooks`, `DiscussionReplyAccess`, `DiscussionReplyFormHooks` e form endpoint devem negar fontes de outro host, com ACL revogada, tombstone, contexto LMS restrito, wiki não publicada, campos/bundles diferentes e acervo histórico. Exigir teste Functional HTTP/HTMX autenticado além dos Unit.
2. **GET/HEAD não destrutivo:** `TopicDeletionDecisionForm` deve apenas apresentar confirmação em GET/HEAD e só executar em submit POST com CSRF da Form API. Testar GET/HEAD, POST inválido, token, perfil insuficiente, id ausente, dupla requisição, resultado efetivo e retorno de formulário. Revisar transação/idempotência em `TopicDeletionModerationManager` antes de aplicar exclusão real.
3. **CGU purpose real:** `ReportService` deve resolver Comment pelo host, não pelo bundle `comment`; Revista=revista, Wiki=wiki, Growroom corrente=forum; LMS/feedback Group ficam fail-closed até adapter Educador/Group, sem autorização global por conveniência. Validar fila/flagging e escopo de permissões.
4. **Separação histórica:** `field_autor_legado` nos tópicos/Comments e a proveniência devem identificar o arquivo, sem permitir reply/edit/quote/reaction/flag em rotas alternativas, busca/caches, formulários administrativos e GraphQL/API se houver. `ForumPost` é corrente e não usa autoria histórica; o arquivo não vira Comment padrão nem ForumPost.

## Próximos pacotes de implementação

**P0 FDC-00 / classificador:** inventário completo de entidades, campos, hosts, relações, Views e endpoints; criar `ForumDiscussionOriginPolicy` (ou nome compatível) que classifica `historic_archive`, `forum_live`, `contextual_comment`, `unknown`. Não depender apenas de um marcador incompleto: identificar legado sem marker por proveniência e testar. Unknown falha fechado. Não fazer migração física sem autorização.

**P0 FDC-02 / motor universal:** revisar `DiscussionReplyFormBuilder`, que ainda cria Comment para qualquer host; novas respostas `aculta_forum_topic` devem passar a `ForumPostSubmissionForm` com parent ForumPost, workflow e URL no tópico. Arquivo histórico permanece read-only, sem converter registros. Wiki e Revista mantêm Comment contextual Core e seus field access; LMS tópicos preservam Group/curso/turma/Educador, feedback `aculta_lms_answer_comment` mantém semântica avaliativa. O compositor não pode redirecionar a pessoa para outro site; manter HTMX + fallback sem JavaScript com Form API.

**P0/P1 FDC-04 / CGU:** `aculta_cgu` é dono de denúncia/caso/decisão/ação. Criar adapter de alvo e autorização por host, evitar duplicação da fila Flagging, separar denúncia julgada de ação efetivamente aplicada; preservar trilha excepcional de acervo e moderação contextual LMS/Revista/Wiki. Testes de privacidade do denunciante e cases/replay.

**P1 FDC-05 / Index e Search API:** revisar `ForumContentIndexProvider` e todas as projeções para diferenciar acervo/novo/contextual. Não retirar acervo da pesquisa sem interface histórica substituta; não levar LMS/Group privados a índice público; contratos de relation/access devem falhar fechados. Não aumentar `projection version` nem rodar rebuild sem preflight e autorização.

**P1 FDC-06 / Statistics:** `ForumMetricsProvider` soma Comments Core e ForumPosts; separar `historic_archive` de eventos/métricas correntes, sem reincorporar legado em participação, novas respostas, XP e notificações. Reconciliar FST ledgers, `aculta_index.statistics_bridge` e métricas CGU sem dados pessoais. Cobrir migração desabilitada/zero replay.

**P1 FDC-07 / UI Suite:** inspecionar consumers reais antes de criar componentes; usar SDCs existentes `forum-list-page`, `forum-category`, `forum-topic-page`, `forum-post-card`, `forum-comment`, `forum-reply-compact`, `forum-reply-mirror`, composers, reaction bar e panels. Views/Layouts/Field Group por UI Patterns, UI Styles/Skins/tokens e UI Icons/Phosphor; variante `archive/read-only` sem ações; stories mobile/light/dark/erro/pendente; não habilitar UI Patterns CKEditor 5. Priorizar a migração de rows Fields para UI Patterns nas Views realmente ativas sem remover Twig que ainda é consumer.

**P2 FDC-08+ / paridade Discourse:** Latest/Top/New/Unread, tags, bookmarks, timeline, jump unread, rascunhos, menções, acompanhar/silenciar, review queue CGU, filtros e split/merge com auditoria. Preservar arquitetura Drupal e direitos de cada host, sem tentar copiar Rails/Ember.

## Gates e saída

- Usar `docs/MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md` para testes T-01 a T-28 e incluir casos de origem histórica incompleta. Teste que não rodou = DEFERRED, jamais PASS.
- Revisão independente R2 para acesso/CGU/queries privadas. Transações/banco/config/Composer são R3 e precisam aprovação por operação.
- Publicar commits pequenos em **origin/docs/forum-clone-discourse-ui-cgu-integracoes-20261119**, nunca diretamente em `dev` ou `main`, sem force. Verificar estado de PR #377, status CI, comparação e SHAs. Se proteção impedir merge, deixar PR pendente e informar check exato.
- Não alterar banco Runtime, importar config, desinstalar módulos, rodar DROP, apagar arquivo histórico nem gerar dados de migração. Runtime sync cotidiana somente após revisão/gates e conforme skill, estrutural somente com autorização.
- Entrega final do agente: tabela por ID com P0/P1, arquivos, commits, testes Unit/Kernel/Functional/browser, status do PHPStan, situação de Index/Statistics/CGU, evidência de histórico preservado, pendências/rollback e link PR.

**Documentação base:** `docs/AUDITORIA-CLONE-DISCOURSE-INTEGRACOES-2026-11-19.md`, `docs/PLANO-CLONE-DISCOURSE-UI-SUITE-CGU-2026-11-19.md`, `docs/MATRIZ-PARIDADE-DISCOURSE-UI-PATTERNS-2026-11-19.md`, `docs/MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md`, `aculta_cgu/docs/CONTRATO-INTEGRACAO-FORUM-CGU-2026-11-19.md`.
