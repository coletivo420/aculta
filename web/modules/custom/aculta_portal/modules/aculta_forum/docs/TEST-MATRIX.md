# Matriz de testes por fase

## Evidência dev.30 — primeira resposta não lida

- `ForumTopicDisplayConfigTest` verifica que somente a View
  `/discussoes/nao-lidas` habilita `node_new_comments`, liga à primeira resposta,
  esconde zeros e mantém cache desativado por conta. Forum Unit: 47 testes / 340
  assertions PASS; Kernel MariaDB: 4/75 PASS. PHPCS, Portal 11+ (970), fronteira
  (1368), mapa (154) e slugs (387) PASS. Cinco deprecações Kernel vêm do Domain
  contrib; esse Kernel cobre access/History adjacente, não renderização do campo
  novo; três avisos PHPUnit são anteriores a esta alteração.
- Runtime: import parcial da View com snapshot de recuperação e `config:status`
  sem diferenças. O handler History instalado resolve e a View retorna 8 itens
  após troca de conta por Drush; anônimo recebe 403. Nenhum tópico tem resposta
  aprovada no Runtime, então contagem positiva/fragmento não foi exercitado.
  Renderização CLI repetiu `Missing text format: basic_html` nas fixtures; HTTP
  autenticado e inspeção visual permanecem pendentes.

## Evidência dev.29 — marcar todas as discussões públicas como lidas

- PHPUnit Unit do Fórum: 47 testes / 335 assertions PASS; o teste novo verifica
  que a rota permite somente a conta autenticada no purpose `forum`.
- PHPUnit Kernel em MariaDB `aculta_simpletest`: 4 testes / 75 assertions
  PASS. O `ForumReadMarkerKernelTest` limita o conjunto a tópico publicado,
  acessível e do Domain Fórum e confirma que somente o History da conta atual
  recebe o registro. Cinco deprecações observadas vêm do Domain contrib 3.0.1.
- Runtime `toca`: `drush cr`, resolução de rota e callbacks DI do Batch,
  construção do formulário, service container e HTTP anônimo 403 — PASS. A
  submissão autenticada não foi executada, evitando gravar History de uma conta
  real. RUNTIME STATUS: PARTIAL.
- PHPCS, lint, gate Drupal 11+ (970), fronteira (1368), mapa (154), slugs (387)
  e `git diff --check` — PASS.

## Evidência não marcada — agregado de respostas públicas

- `ForumTopicMoverKernelTest` valida Comment e `comment_entity_statistics` em
  MariaDB dedicado: resposta publicada conta como 1, resposta pendente mantém
  o contador em 1 e mover a categoria preserva o mesmo agregado no Node. 2
  testes / 48 assertions PASS.
- Runtime de teste continua sem respostas aprovadas; portanto, a ordenação
  `/discussoes/mais-comentadas` com contagens distintas ainda aguarda fixture
  deliberada em homologação. Nenhum conteúdo de Runtime foi criado.

## Evidência não marcada — contagem e atividade na listagem pública

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 13 testes / 137 assertions PASS.
- No Runtime `toca` (Drupal 11.4.10, `aculta_runtime`, URI
  `https://growroom420.aculta.toca.net.br`), import parcial da View e cache
  rebuild; query retorna 8 tópicos com contagem 0, e HTTP 200 apresenta
  `0 respostas` nas oito linhas. Nenhum Node/Comment foi criado ou alterado.
- O sort Core `comment_ces_last_updated` lançou `TypeError` em 11.4.10; a
  configuração foi revertida e a rota voltou HTTP 200. O sort virtual
  `PublicForumLastActivity` então executou: Views reconhece o plugin do ACULTA,
  SQL contém `GREATEST(node.changed, last_comment_timestamp) DESC`, 8 resultados,
  campo Core `last_updated` renderizado e HTML HTTP 200 com `0 respostas` e
  “Última atividade” em todas as linhas. Nenhum conteúdo foi alterado.
- Caso positivo e invalidação após moderação continuam DEFERRED. Render direto
  por Drush registrou `Missing text format: basic_html` nos teasers; drift a
  investigar separadamente. Inspeção visual também pendente. RUNTIME STATUS:
  PARTIAL.
  RUNTIME STATUS: PARTIAL.
- Rollback seguro ao estado funcional anterior (contador sem data/sort) em
  `/home/aculta/private/aculta/forum-activity-20261019/views.view.aculta_forum_public_topics.yml`.

## Evidência dev.28 — índice público de categorias

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 13 testes / 123 assertions PASS;
  install e Config Sync das Views de categorias e tópicos são equivalentes.
- YAML parse, `git diff --check` e gates Drupal 11+, fronteira Portal/tema,
  mapa módulo/tema e slugs PASS.
- Runtime de teste `toca`, Drupal 11.4.10, banco `aculta_runtime`, URI explícita
  `https://growroom420.aculta.toca.net.br`: importação parcial somente das duas
  Views alteradas e cache rebuild. `/` e `/categorias` responderam HTTP 200;
  a View retornou os 12 termos existentes, com descrições, e os links entre os
  índices apareceram no HTML. A página não encaminha para Views genéricas de
  Taxonomy. Config Sync não apresenta diferenças para essas Views. Nenhum termo,
  Node ou Comment foi criado, alterado ou excluído.
- RUNTIME STATUS: PASS para rotas, View e navegação HTTP. Inspeção visual e
  apresentação hierárquica permanecem pendentes para a fase ACULTA420.

## Evidência inicial dev.10 — tópicos não lidos

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 14 testes / 157 assertions PASS;
  a View de não lidos mantém os filtros da listagem pública, exige role
  `authenticated`, usa o filter/field plugin de History e desativa cache
  compartilhado.
- Composer: `drupal/history:^1.0` foi declarado diretamente para sobreviver à
  retirada futura de `drupal/forum`, sua dependência anterior. Composer validate
  e audit reportam nenhum advisory conhecido; o projeto History declara que não
  está coberto pela política de avisos do Drupal.org. Acompanhamento de releases
  permanece obrigatório.
- Runtime `toca`: Views data confirmou o plugin instalado e a query SQL juntou
  History pelo uid atual. A conta uid 5 passou no plugin de acesso e encontrou
  7 discussões novas/atualizadas; o anônimo recebeu HTTP 403 em
  `/discussoes/nao-lidas`. `/discussoes` é caminho interno bloqueado (404).
  Nenhum Node, Comment ou registro de leitura foi alterado.
- Renderização direta da View pelo Drush emitiu `Missing text format:
  basic_html` nos teasers das fixtures; mesmo formato já é usado pela listagem
  pública e requer investigação própria. HTTP com sessão autenticada e
  inspeção visual pendentes. RUNTIME STATUS: PARTIAL.

## Evidência inicial dev.10 — telas de descoberta

- PHPUnit Unit `ForumTopicDisplayConfigTest`: 15 testes / 184 assertions PASS;
  PHPCS PASS. A View compartilha os filtros da lista pública e a tela sem
  resposta usa `comment_entity_statistics.comment_count = 0`; a tela popular
  ordena pelo agregado Core e por atividade.
- `PublicForumExposedFormHooksTest`: 3 testes / 11 assertions PASS. A exceção
  ao CAPTCHA cobre somente os quatro filtros GET da View pública; formulários
  de escrita não são alterados.
- Suite Unit completa do Fórum: 46 testes / 332 assertions PASS; inclui
  `ForumTopicMoveHooksTest` (wiring do mover para o service ID). Uma
  depreciação preexistente de metadata PHPUnit fica em
  `DiscussionModerationHooksTest`.
- Runtime `toca`: após importar somente a View pública e reconstruir caches,
  `/discussoes/novas`, `/discussoes/sem-resposta` e
  `/discussoes/mais-comentadas` retornaram HTTP 200 e exibiram tópicos `[TESTE]`.
  Cada query encontrou 8 itens; todos 8 aparecem sem resposta porque há 0
  comentários públicos. Config Sync ficou sem diferença para a View.
- O cache rebuild inicial revelou wiring inválido de `ForumTopicMoveHooks`;
  a injeção explícita do service ID foi adicionada e o rebuild subsequente
  passou. Nenhum Node, Comment ou registro de leitura foi alterado.
- Ainda não foi validada uma ordenação com comentários positivos nem a
  visualização autenticada em browser. RUNTIME STATUS: PARTIAL.

Esta matriz separa plano das evidências já executadas. Na `0.1.0-dev.4`, a
Context API pública e configuração nativa foram exercitadas no Runtime de teste.
Na dev.5, um teste Kernel prova storage Comment compartilhado em três bundles
Node e Unit cobre access/cache do provider editorial. As field instances da
Revista estão aplicadas no Runtime de teste; HTTP/browser e envio de Comment
continuam pendentes.

| Camada | Casos mínimos | Evidência |
| --- | --- | --- |
| Instalação/configuração | install profile limpo, dependências, IDs e nomes <=32, field Comment nos bundles Node permitidos | Kernel/install tests; validação de config. |
| Context API | resolver cada contexto, provider ausente/desabilitado, host inválido e URLs canônicas | Unit + Kernel. |
| Comment | tipo compartilhado (ou variantes justificadas), host bundles e field settings, thread, revisão, publicação, permalink/ancora, formulário | Kernel + Functional. |
| Formulário/display de tópico público | title/body/categoria editáveis; status de comentário e autoria histórica não editáveis; full apresenta discussão compartilhada; teaser omite thread | Unit verifica contrato YAML e equivalência Config Sync/config install. Runtime de teste contém 12 termos iniciais e 8 tópicos `[TESTE]`; registros em `TEST-FIXTURES.md`. Todos os oito aliases HTTP 200; a raiz renderiza os cinco itens do pager inicial. |
| Ação de criação no índice | link aparece apenas se a rota Core autoriza; variação por usuário/permissões e contexto `forum`; ausência em outros displays/domínios | Unit `PublicForumTopicCreateActionHooksTest`: 5 testes/19 assertions PASS. Runtime anônimo: raiz 200, link omitido, rota 403. Conta autorizada, navegador e apresentação integral pendentes. |
| Acesso ao formulário de tópico | `email_confirmed` cria e gerencia somente tópico próprio; sem permissões `edit any`/`delete any`; access real da entidade | Unit `ForumTopicDisplayConfigTest`: 7 testes/47 assertions PASS. Runtime `toca`: uid 5 autorizado na rota; formulário Core renderizado em memória, sem submissão; contagens seguem 0 tópicos/Comments. Browser e envio funcional DEFERRED. |
| Respostas no tópico público | Field Comment compartilhado aberto, formulário inline Core, anônimo bloqueado e permissão `post comments` | Unit `ForumTopicDisplayConfigTest` verifica equivalência install/Sync e contrato de field/role. Runtime `toca`: status Open, uid 5 tem permissão, 0 tópicos/Comments. Render e envio vinculados a tópico salvo DEFERRED para preservar acervo limpo. |
| Navegação por categoria | filtro Views nativo na referência Taxonomy, hierárquico, escopo fixo no vocabulário novo e query args em cache | Unit `ForumTopicDisplayConfigTest` cobre filtro e hierarquia. Runtime `toca`: 12 termos/8 tópicos; filtro `categoria=77` mostra o tópico esperado. O CAPTCHA global foi removido apenas da View GET de leitura, que antes abortava a listagem; formulário de criação anônimo permanece 403. Navegador visual ainda pendente. |
| Acesso público | anônimo/membro/editor; publicação, conteúdo fechado, flags e permissões | Kernel + Functional/browser. |
| Publicação | comentários no Node editorial, conteúdo unpublished, canonical, âncora, sem Node forum gerado | Kernel + Functional; anti-regressão Magazine. |
| Moderação inicial | Primeiro comentário e pendências em qualquer Node com `field_aculta_discussion`; LMS sem moderação, e comentário LMS publicado libera o bloqueio temporário de tópico | Kernel `PublicDiscussionModerationKernelTest` PASS; Runtime editorial PARTIAL (field/form renderizados sem envio). |
| Moderação por contexto/estado | Fórum mostra abas Publicados, Rejeitados e Aguardando moderação; Revista tem sua própria listagem; Wiki/LMS aguardam adapters; filas não se misturam | Unit Forum: `ForumTopicDisplayConfigTest` + `DiscussionModerationHooksTest`, 37 testes/218 assertions PASS. Runtime `toca`: Views retornaram cada estado do Fórum e a fila Magazine trouxe somente os seus comentários; API de tarefas locais confirmou as três abas sob Fórum. Render API público mostrou somente publicados no tópico e na publicação. Os seis registros `[QA 20261019]` foram removidos; contagem final 0 nos dois destinos. Browser autenticado DEFERRED. RUNTIME STATUS: PARTIAL. |
| LMS | duas turmas isoladas, usuário em A e não B, role global estudante sem membership; moderação por Educador com acesso contextual atual e negação para educador sem membership/acesso | Kernel/Functional; Route/View/Search/Cache e teste de navegador. |
| Wiki | uma thread por verbete, comentário não altera revisão, acesso de edição distinto | Contract tests nesta fase; integração real só após Wiki. |
| Busca/Views | filtro de contexto fixo, access query, títulos/trechos/contagens/facets e paginação sem vazamento | Kernel/Functional, index rebuild no ambiente de teste. |
| Contagem de respostas em tópicos | zero e plural; bundle fixo `aculta_forum_topic` com seu campo único de discussão; estados de moderação conforme agregação Core; cache/invalidação | Unit de contrato cobre config install/Sync, estatística Core e exibição de zero. Execução da query em Runtime e invalidação após moderação DEFERRED. |
| Cache | tags/context/max-age em variações por usuário/permissão/Group/membership, revogação e conteúdo despublicado | Kernel + Functional; inspeção de cache. |
| Notificações | dedupe, agrupamento, opt-in e confirmação, revalidação de acesso na fila, retirada/revogação, zero conteúdo sensível | Unit/Kernel do produtor e messenger + Functional. |
| Autoria | conta privada, apelido alterado, foto/privacidade, usuário bloqueado, autor legado, dados ausentes | Unit/Kernel + browser e cache. |
| Atividade de comentários do Fórum em Minha Conta | Query inclui apenas `aculta_discussion_post` publicado em `field_aculta_discussion` de `aculta_forum_topic`; Revista, pendentes e contextos privados não entram; links usam Domain Fórum + âncora; Entity Access revalidado | Kernel `PublicDiscussionModerationKernelTest`: 6 testes/73 assertions PASS para query/contexto/estado. Runtime `toca`, uid 5 e busca total sem dados compatíveis: render retornou lista vazia, sem mutação; caminho com link/card e browser seguem pendentes. |
| Autoria de tópico novo | User usa `author_forum`; referência histórica usa `author_growroom_historico`; nenhuma conversão para conta; somente `full`/`teaser` | Unit `PublicForumTopicAuthorHooksTest` e `AuthorIdentityTest`: 6 testes/23 assertions PASS. Runtime: hook registrado; node `unsaved` em memória renderizou `author_forum`, contagem permaneceu 0. Tópico persistido/browser pendentes. |
| Imagens/anexos | ingestão obrigatória aculta_image, uso/referências, anexo privado, substituição e limpeza | Gate `validate-aculta-image.php`, Kernel + testes de acesso. |
| Moderação | criar, editar, sinalizar, aprovar, ocultar, despublicar, bloquear, auditoria | Unit/Kernel/Functional por papel. |
| Integração Game | primeira resposta publicada em `aculta_discussion_post`/`aculta_forum_topic`; aprovação tardia uma vez; outras entidades, sincronização e `field_autor_legado` excluídos | `aculta_game` Unit `BadgeEvaluationHooksTest` cobre contrato de bundle/field e casos negativos. Concessão real/cache/commit externo e Runtime seguem pendentes. |
| Mover tópico individual entre categorias | permissão específica e Entity Access no purpose público; categoria do vocabulário correto; confirmação; submissão adulterada; preservação de NID/UUID/alias/Comments/flags; nova revisão; autoria histórica negada mesmo para admin | Unit `ForumTopicMoverTest`: 4 testes/31 assertions PASS. Kernel `ForumTopicMoverKernelTest`: 2 testes/44 assertions PASS em MariaDB `aculta_simpletest`; comprova mesmo NID/UUID, nova revisão, alias e Comment/CID preservados e bloqueio histórico mesmo com permissões de administrador. Functional e Runtime autenticado pendentes; nenhuma fixture do Runtime foi movida. |
| Mover comentário e subárvore para outro tópico público | autorização, dois endpoints no purpose Fórum, tópico aberto, legado imutável, estados publicados/pendentes, thread profunda, identidade/datas, estatísticas de origem/destino, cache, gate desabilitado por default e rollback | Unit `ForumCommentMoveHooksTest` verifica DI. Kernel `ForumCommentMoverKernelTest`: 4 testes/92 assertions PASS em MariaDB `aculta_simpletest`; inclui três níveis, estado pendente, autoria histórica, flag disabled e falha injetada após gravação parcial. Faltam Unit direto de política/Form, Functional, colisão concorrente, Search API, History, idiomas, flags, anexos/file usage, efeito de avisos, permalink antigo e browser. Runtime permanece desabilitado. |
| Outras ferramentas de movimento | reorganizar Taxonomy; lote via Views/VBO; split/merge; verificar revisões/URLs/arquivos/flags/estatísticas/cache/busca/notificações e recuperação | Planejadas. `MOVE-TOOLS.md` registra pesquisa de Comment Mover e VBO; não instalar VBO nem expor ferramenta em lote até aprovar o escopo e segurança das Actions. |
| Acervo Growroom restaurado | autoria por `field_autor_legado`; edição/exclusão/aprovação negadas a qualquer papel; tópico fechado; bulk admin e IDs adulterados rejeitados | Unit `ForumModerationHooksTest`; Kernel/Functional de importação e access pendentes. Não confundir com Nodes `forum` do módulo contrib, cuja retirada é separada. |
| UX/acessibilidade | mobile, teclado, foco, leitor de tela, light/dark, reduced motion e fallback sem JS | Firefox/browser, auditoria manual e automatizada existente. |
| Performance | listagens, thread grande, Views, Query counts e contagens/cache | profiling comparável; limites definidos na fase de benchmark. |
| Remoção/migração | dry run, inventário, exclusão escopada, uninstall, composer remove, rollback/restauro e ausência de residuais | prova somente no Runtime de teste e ponto de recuperação revisado. |

## Evidência dev6 — autorização de moderação por e-mail confirmado

- PHPUnit Unit `ForumModerationHooksTest`: 5 testes / 21 assertions — PASS.
- Cobertura: editor confirmado pode editar/excluir tópico de outra pessoa;
  editor sem marcador confirmado é negado mesmo com papel `email_confirmed`;
  autoria própria segue o access handler normal; bundle alheio não é afetado;
  bypass administrativo permanece.
- PHPCS Drupal/DrupalPractice, lint PHP, gate Drupal 11+ (969), pacotes próprios
  (22), fronteira módulo/tema (1.304), mapa (154) e `git diff --check` — PASS.
- Runtime de teste: DEFERRED. Nenhuma permissão, configuração ou conteúdo foi
  aplicado no Runtime. A listagem Views/CTools e o fluxo de moderação ainda
  precisam de validação integrada.

## Evidência dev.8 — acervo Growroom restaurável em modo somente leitura

- PHPUnit Unit `ForumModerationHooksTest`: 10 testes / 53 assertions — PASS.
- Cobre tópico histórico imutável mesmo para administrador, comentários do
  tópico, fechamento do Comment field no presave, filtragem da fila
  administrativa e rejeição de IDs forjados.
- A identificação usa `field_autor_legado`, não os bundles `forum` ou
  `comment_forum` da implementação contrib a retirar.
- Storage e field instances são instaláveis/sincronizados; importação e Runtime
  ficam DEFERRED. Nenhum conteúdo ou configuração persistente foi alterado.

## Evidência dev.5 — política de aprovação inicial compartilhada

- PHPUnit Kernel `PublicDiscussionModerationKernelTest`: 5 testes / 63
  assertions — PASS em MariaDB `aculta_simpletest` (PHP CLI 8.4.26).
- Cobertura: comentários LMS permanecem publicados sem moderação; uma publicação
  LMS conta como aprovada para contextos não LMS e libera o bloqueio temporário;
  política administrativa desligável; campo compartilhado em Wiki/outros Nodes;
  comentário não LMS pendente bloqueia criação de tópico quando configurado.
- `phpcs --standard=Drupal` nos arquivos PHP alterados: PASS. Gate Drupal 11+:
  969 checks — PASS. `git diff --check` — PASS.
- As decisões de acesso incluem a tag `config:aculta_user.settings` e os
  contextos `user`/`user.roles`; mudanças nas opções ou papéis não deixam
  resultados incompatíveis em cache.
- Runtime editorial e instalação/atualização de configuração continuam
  DEFERRED. Nenhum conteúdo ou configuração persistente foi alterado.

## Evidência `0.1.0-dev.4` (2026-10-19)

- Unit aculta_forum: resolver/provider, 7 testes e 25 assertions — executado.
- Unit Portal `ContentPurposeResolver`: 2 testes/2 assertions para o bundle novo e o legado — PASS.
- Gate Drupal 11+: 969 checks; fronteira módulo/tema: 1.290 verificações;
  mapa: 154 checks; pacotes de módulos próprios: 21 checks — PASS.
- No servidor `toca`, host de teste `growroom420.aculta.toca.net.br`, Drupal
  11.4.10: módulo instalado, serviço `DiscussionContextResolver` resolvido,
  campos `body`, `field_aculta_categories` e `field_aculta_discussion` presentes,
  zero Nodes do novo bundle. Config Sync não mostra divergência nos objetos
  novos. Nenhum tópico/resposta de usuário foi criado.
- PHP CLI do servidor reporta 8.4.26; o gate de versão de PHP 8.5 permanece
  pendente de validação pelo PHP-FPM/runtime web.
- Nenhum provider de Publicação/LMS/Wiki, acesso de escrita/moderação, Views,
  UX ou navegação pública foi homologado nesta subfase.

## Evidência dev.5 — provider editorial e Comment multi-bundle

- PHPUnit Unit `aculta_forum`: 12 testes / 52 assertions — PASS (PHP CLI 8.4.26).
- PHPUnit Kernel `SharedDiscussionFieldKernelTest`: 1 teste / 18 assertions —
  PASS usando exclusivamente MariaDB `aculta_simpletest`; o mesmo storage
  `field_aculta_discussion` e Comment type podem ser anexados a `article`,
  `blog` e `aculta_forum_topic`.
- Unit cobre leitura via Entity/Field Access, permissões nativas `post comments`
  e `administer comments`, campo fechado, negação de access, stable ID e cache.
- O Kernel prova a capacidade do Core com a configuração equivalente; não instala
  `aculta_forum`, não cria conteúdo e não substitui teste Functional/Runtime dos
  fields reais da Magazine.
- Runtime de teste: cache rebuild e smoke de DI confirmaram os dois providers; o
  provider editorial não atende Article enquanto o field ainda não existe.
  Nenhuma importação, field editorial ou conteúdo foi criado. RUNTIME STATUS:
  PARTIAL para o registro do serviço; integração editorial segue pendente.

## Evidência dev.10 — formulário e displays do tópico público

- PHPUnit Unit do `aculta_forum`: 15 testes / 67 assertions — PASS.
- PHPCS Drupal/DrupalPractice no teste novo, PHP lint, YAML parse, gates Portal
  Drupal 11+, fronteira módulo/tema, mapa módulo/tema e `git diff --check` —
  resultados registrados no commit desta entrega.
- No servidor de teste `toca`, Drupal 11.4.10, URI explícita
  `https://growroom420.aculta.toca.net.br`, banco `aculta_runtime`: importação
  parcial incluiu somente os displays Form, Full e Teaser. O `config:status`
  por prefixo confirmou os três sem divergências.
- Smoke Form API usou Node em memória: título, corpo e categoria aparecem como
  entradas; o Core inclui `revision_log` no grupo padrão de revisão. O status
  Comment é `Open`. Nenhum formulário foi salvo.
- Contagens posteriores: 0 `aculta_forum_topic`, 0 `aculta_discussion_post`,
  30 Nodes legados `forum` e 0 Comments `comment_forum`. Nenhum conteúdo foi
  criado, alterado ou excluído.
- RUNTIME STATUS: PARTIAL. Fluxo persistido de criação/resposta, acesso
  integrado, apresentação no navegador e UX ainda aguardam a fase funcional.

## Evidência dev.11 — View pública isolada por domínio

- PHPUnit Unit `aculta_forum`: 21 testes / 102 assertions — PASS.
- PHPCS Drupal/DrupalPractice, PHP lint, YAML parse, Drupal 11+ (969), pacotes
  (22), fronteira (1.323), mapa (154) e `git diff --check` — PASS.
- No Runtime `aculta_runtime`, importação parcial incluiu a field instance de
  Domain Source, a View nova e os três displays atualizados. A field instance
  já existente no Runtime tinha outro UUID, então o Drush a substituiu durante
  a sincronização; antes da operação havia zero
  Nodes `aculta_forum_topic` e zero linhas dessa bundle em
  `node__field_domain_source`. Os 30 Nodes legados e seus valores de domínio
  permaneceram intactos. O restante da configuração não foi importado.
- Após cache rebuild, os hooks OOP foram encontrados pelo ModuleHandler; smoke
  sem salvar Node atribuiu `forum_aculta_org`. A View carregou/executou e retornou
  zero itens, conforme o acervo novo vazio. As cinco configurações alvo ficaram
  sem divergência no `config:status` por prefixo.
- RUNTIME STATUS: PARTIAL. A View não foi colocada, não há tópico persistido e
  browser/UX seguem pendentes.
- A View nova tem Page display interno `/discussoes` e serve a raiz do purpose forum; o caminho direto e o antigo `/forum` permanecem fechados. O bloco do acervo contrib continua sem colocação na raiz.

## Evidência dev.12 — índice na raiz do Growroom420

- URI Drush: `https://growroom420.aculta.toca.net.br`; banco `aculta_runtime`.
- Importação parcial de apenas `views.view.aculta_forum_public_topics`; cache rebuild. A rota `view.aculta_forum_public_topics.page_discussions` resolve `/discussoes` com purpose `forum`; a View executa com zero linhas.
- HTTP: raiz `/` retorna 200 com título “Discussões do Growroom420”; `/discussoes` e o antigo `/forum` retornam 404.
- Nenhum Node, Comment ou arquivo criado/alterado/apagado. RUNTIME STATUS: PARTIAL; sem criação/resposta persistida e sem inspeção visual.

## Evidência dev.13 — criação por formulário Core

- Rota `aculta_forum.topic_add` resolve `/criar-topico`, `node.default`, bundle `aculta_forum_topic` e `_entity_create_access: node:{node_type}`; `DomainRouteSubscriber` classifica a rota no purpose `forum`.
- Drush no Runtime confirmou URI do fórum e banco `aculta_runtime`. HTTP anônimo no fórum retornou 403; a mesma URL no purpose principal retornou 404. Conta de teste sem a permissão de criação também foi negada.
- Nenhum formulário foi submetido, nenhum Node/Comment salvo. RUNTIME STATUS: PARTIAL; fluxo autorizado e inspeção visual pendentes.

## Evidência dev.9 Magazine — comentários diretos no Runtime de teste

- Importação parcial de quatro objetos: fields `field_aculta_discussion` em
  `article`/`blog` e displays full dos dois bundles. Sem importação integral,
  update de banco ou gravação em Nodes/Comments.
- Runtime `https://revista420.aculta.toca.net.br`, Drupal 11.4.10, banco
  `aculta_runtime`: 14 publicações publicadas; campo existe, component
  `comment_default` presente e status do campo de amostra `Open` (2). A build do
  Node usa pré-render do Core. Com o usuário de teste uid 5, permissionado a
  `post comments`, o formatter renderizou o campo e o formulário em memória.
  Nenhum formulário foi enviado; saída HTTP/browser ainda não verificada.
- Após a operação: 0 `aculta_discussion_post`, 30 Nodes legados `forum`, 0
  `comment_forum` e 0 `aculta_forum_topic`. Os 12 Nodes vinculados às publicações
  permanecem intocados; os campos `field_discussao` continuam guardando os
  vínculos durante a transição.
- RUNTIME STATUS: PARTIAL. Nenhum comentário enviado; acessibilidade, moderação
  efetiva e UI em navegador pendentes.

Gates aplicáveis seguem `scripts/validate-aculta-portal-drupal11.php`, fronteira
módulo/tema, mapa módulo-tema, slugs, rotas, endereços, imagem, hierarquia de
papéis e gates relacionados ao código tocado. O gate final será recalibrado na
fase funcional; não assumir que uma lista estática substitui teste real.

Fase RC executará a cobertura integrada: Unit, Kernel, Functional, Domain matrix,
cache/access, browser/mobile, acessibilidade, desempenho, busca, notificações,
instalação/update/uninstall e rollback. Runtime permanece DEFERRED até validação
real no servidor de teste.

## Fórum dev.21 / Portal dev.190 — paginação pública

- Unit `ForumTopicDisplayConfigTest`: 9 testes / 62 assertions PASS; install e
  Config Sync equivalentes; pager Core completo com 20 itens e seletor de
  quantidade fechado.
- Runtime `toca`: config ativa confirma `type: full`, `items_per_page: 20`; raiz
  HTTP 200 mostra as 8 fixtures; filtro `?categoria=77` HTTP 200 mostra o tópico
  esperado. Nenhum conteúdo foi gravado. Navegação entre páginas (mais de 20
  tópicos) e apresentação visual dos controles ainda não exercitadas.
- Gates Drupal 11+ 969, fronteira 1334, mapa 154, slugs 387 e PHPCS PASS.
  RUNTIME STATUS: PARTIAL.

## Fórum dev.22 / Magazine dev.12 / Portal dev.191 — organização administrativa por contexto

- PHPUnit Unit Forum + Magazine: 12 testes / 96 assertions — PASS. Verifica
  escopo de bundle/campo, permissões, caminhos, bloco da Revista e equivalência
  entre configuração de instalação e Config Sync.
- PHP lint e PHPCS Drupal/DrupalPractice nos testes novos — PASS. Gate Portal
  Drupal 11+: 969 checks; fronteira: 1.336; mapa: 154; slugs: 387; ACULTA420
  Foundation via Drush: 355; SDC: 516; design: 2.078 — PASS. `git diff
  --check` — PASS.
- Runtime `toca`, Drupal 11.4.10, PHP CLI 8.4.26, banco `aculta_runtime`:
  importação parcial incluiu View de comentários públicos, Views de conteúdo e
  comentários da Revista e bloco editorial na região `content` do tema Claro,
  condicionado ao purpose `main` e à URL administrativa. Cache reconstruído.
- Drush confirmou os caminhos de Fórum e Revista; as Views carregam/executam
  sem erro. Conta uid 5 tem acesso às Views por permissões efetivas; visitante
  anônimo recebe HTTP 403 nos endpoints. Renderização HTTP com sessão editorial
  e exibição do bloco no navegador ainda pendentes.
- Nenhum Node, Comment, arquivo, revisão editorial ou dado de usuário foi
  criado, alterado ou removido. Discussões LMS/Wiki permanecem sem rotas
  executáveis até seus adapters provarem access contextual. RUNTIME STATUS:
  PARTIAL.

## Correção Portal dev.184 — raiz e cache de rotas

- A inspeção de `Drupal\Core\Routing\RouteProvider::getRouteCollectionForRequest()` confirmou que o cache conserva caminho, query e rotas, sem os atributos do Request marcados pelo path processor.
- Após `drush cr` no Runtime `aculta_runtime`, HTTP direto em `/` e com query de diagnóstico retornou 200; `/discussoes` retornou 404. Nenhum Node, Comment ou termo foi criado. RUNTIME STATUS: PARTIAL; criação e apresentação visual ainda pendentes.

## Fórum dev.16 / Portal dev.185 — estado vazio da View pública

- Unit `ForumTopicDisplayConfigTest`: 6/42 PASS. Sync e configuração de instalação equivalentes; mensagem usa Views Core `text_custom` e não tokeniza conteúdo.
- Runtime `toca`: importação parcial apenas de `views.view.aculta_forum_public_topics`. Após cache rebuild, raiz HTTP 200 mostrou título, seletor de categoria e texto vazio; rota interna `/discussoes` retornou 404. Tópicos e comentários compartilhados continuam em zero.
- RUNTIME STATUS: PARTIAL; listagem vazia conferida por HTTP, navegador e tópico persistido seguem pendentes.
