# ACULTA Content Index — auditoria IDX 0.1-A

Data: 2026-10-22
Branch: `docs/aculta-forum-0.1.0-dev.1`
PR: [#297 — ACULTA Forum](https://github.com/coletivo420/aculta/pull/297), aberta para `main`
HEAD auditado: `cc4d4fdfa514ab257c10ecee48a45cc1d5ee131b`
Remoto: `origin/docs/aculta-forum-0.1.0-dev.1` em `6653ab93`; HEAD local quatro commits à frente durante a auditoria.

## Resultado

Não foi encontrado um catálogo ACULTA normalizado e transversal de entidades e relações. Há, porém, infraestrutura que precisa ser composta, não duplicada:

- Search API já atende busca textual, rastreamento de entidades indexáveis, datasource e backend SQL. Existem os índices `aculta_conteudo` (configuração sincronizada) e `aculta_forum` (configuração de instalação do Fórum). O índice especializado do Fórum declara que não abrange Wiki, Revista, LMS ou comentários contextuais.
- Entity Usage já registra referências source/target por plugins e é dependência do Fórum. Seu domínio é uso de entidades (incluindo referências embutidas), não o catálogo e as consultas editoriais pretendidos.
- O Portal já fornece `DomainPurposeManager`; módulos já aplicam Entity Access, grants, Group e contexts de cache conforme o domínio.
- `aculta_statistics` mantém métricas separadas de descoberta de conteúdo. Oferece catálogo de métricas, recorder, agregado diário, reconciliação e providers de relatório, mas não é uma fila genérica nem um indexador de entidades.
- Não foi identificada fila genérica ACULTA que deva ser promovida a requisito do índice. `aculta_links` tem uma fila local para contagem de cliques; sua semântica não cobre projeção de entidades.

IDX 0.1-A está concluída. Nenhum código de runtime foi alterado nesta fase. A matriz abaixo distingue código confirmado de documentação legada; nenhum trecho de módulo contrib foi copiado. Decisão posterior do responsável: não instalar módulos contrib pesquisados, integrar APIs ACULTA existentes e somente portar código se uma lacuna concreta justificar a operação, preservando proveniência. A identidade escolhida é uma entrada por entidade e idioma (hoje `pt_BR`), sem linhas por revisão.

## Auditoria prioritária: `aculta_statistics`

### Código e responsabilidades confirmados

| Capacidade | Evidência no código | Resultado para `aculta_index` |
|---|---|---|
| Catálogo de métricas | `modules/aculta_statistics/src/Catalog/MetricCatalog.php`, `MetricDefinition.php` | Manter dono Statistics. Não reutilizar como catálogo de entidades. |
| Gravação e validação de métricas | `Recorder/StatisticsRecorder.php`; serviço `aculta_statistics.recorder` | Não reutilizar para sincronização de conteúdo. É uma API própria de métricas. |
| Agregação SQL diária | `Storage/DailyAggregateStorage.php`, tabela `aculta_statistics_daily` criada em `aculta_statistics.install` | Manter armazenamento e schema. Não adicionar colunas ou métricas à tabela do índice. A chave `(metric, dimension, day)` e `merge` atômico são padrões SQL observados, não abstração compartilhada necessária. |
| Retenção e cron | `Retention/RetentionPolicy.php`, `Hook/StatisticsCronHooks.php` (`#[Hook('cron')]`) | Não reaproveitar como worker de indexação. Retenção tem semântica própria por métrica. |
| Reconciliação | `Reconciliation/CommerceReconciler.php`, `EmailReconciler.php`; hooks `ReconciliationCronHooks.php`, `EmailReconciliationCronHooks.php` | Referência para delta/reconciliação idempotente de agregados; não há scheduler ou job genérico exportável identificado. |
| Integração Wiki | `Wiki/WikiProvider.php`, `Wiki/WikiRevisionReport.php`, `Hook/WikiPublicationHooks.php`, `Hook/WikiReadHooks.php` | Consumir como fonte analítica no Statistics; não extrair provider para o índice sem contrato de domínio comum demonstrado. |
| Fórum | `EventSubscriber/ForumActivitySubscriber.php` escuta nomes de evento desacoplados | Reutilizar o padrão de eventos nomeados sem dependência circular; o consumidor Statistics grava contagens, o índice deverá atualizar projeções. |
| LMS | `EventSubscriber/LmsEventsSubscriber.php`, `LmsClassStatusSubscriber.php`, `LmsStudentStatusSubscriber.php`, `Lms/LmsClassResolver.php` | Preservar contagens agregadas e dados individuais no LMS. Os subscribers de Statistics não são providers de indexação. |
| Commerce/pagamentos | `CommerceOrderPaidSubscriber.php`, `CommercePaymentRefundHooks.php`, `CommerceOrderDomainHooks.php` | Eventos e resolução de purpose servem de referência para desacoplamento. Dados analíticos continuam no Statistics e dados oficiais no Commerce. |
| E-mail | `Email/EmailProvider.php`, `EmailFunnel.php`, `EmailReconciler.php` | Fonte e agregação já têm dono. Não indexar destinatários, logs ou identificadores pessoais. |
| Providers e APIs de relatório | `EmailProviderInterface.php`, `LmsClassResolverInterface.php`, `PurposeResolverInterface.php`, `WikiProvider.php`; contratos em `aculta_statistics.services.yml` | Contratos são locais a Statistics; criar ponte opcional no índice sem dependência obrigatória/circular. |
| Jobs, filas e reconciliação | Não há fila/workers genéricos de sincronização em `aculta_statistics`; reconciliação de e-mail/Commerce roda em cron e LMS usa KeyValue para deduplicação | Recalcula agregados e deduplica eventos analíticos | Adaptar apenas padrões idempotentes; não conectar o fluxo analítico ao worker do índice. Sem serviço genérico a extrair. |
| Consulta administrativa e histórico | `Controller/StatisticsDashboardController.php`, `Wiki/WikiRevisionReport.php`, `DailyAggregateStorage.php` | Painéis, relatório de revisões e séries diárias | Manter no Statistics; o índice fornece IDs/seleções somente via bridge opcional. Não incluir métrica/histórico nas projeções. |
| Persistência e cache | `aculta_statistics.services.yml`, `DailyAggregateStorage.php`; tag `aculta_statistics` | Não compartilhar storage. Revisar cache de consulta no consumidor e invalidar por tags próprias do índice. |
| Tabelas e estatísticas | `aculta_statistics.install`, dados de retenção do LMS e fontes Commerce/e-mail | Nenhuma tabela de métricas deve migrar para `aculta_index`. |

### Divergência documental

O estado do código em 2026-10-22 prevalece sobre documentos desatualizados do módulo. `aculta_statistics.info.yml` existe e o código contém funcionalidades além da descrição antiga de fase 0.1.0 em `docs/ARCHITECTURE.md` e afirmações antigas de ausência de `info.yml`. `docs/CURRENT-STATE.md` informa estado do Runtime verificado em 2026-10-20, não foi revalidado nesta auditoria e não é prova de ativação atual. Essa divergência deve ser saneada pelo proprietário do Statistics em uma tarefa própria; não foi alterada neste escopo.

## Inventário e matriz de reaproveitamento

`Direto` significa consumir a API atual como está. `Extrair` requer refatoração e evidência de benefício antes de fazê-la. `Adaptar` significa implementar uma integração no índice usando o contrato existente. `Referência` não autoriza copiar código.

| Origem | Implementação | Responsabilidade / consumidores existentes | Reaproveitamento e justificativa | Impacto / destino | Estado |
|---|---|---|---|---|---|
| `aculta_portal/src/Search` | `SearchPageController`, `SearchResults` | Busca pública e resultados via Search API; conferem acesso nos resultados | Adaptar para permanecer consumidor de busca textual. Não mover busca ao catálogo. | O índice e Search API terão integração futura explícita; segurança continua na entidade. | Planejado |
| Config Sync | `search_api.index.aculta_conteudo.yml` | Busca geral já indexa bundles configurados, inclusive Revista/Wiki conforme configuração | Adaptar; inventariar campos e bundles antes de sobrepor. É mecanismo textual, não catálogo universal. | Evitar conflito de IDs/configuração e duplicar o índice de busca. | Planejado |
| `aculta_forum` | `config/install/search_api.index.aculta_forum.yml`, processadores `ContentAccess`, `ForumPostSearchAccess` e propriedades de grants | Índice textual especializado em tópico/post; views do Fórum | Adaptar padrões de campos auxiliares e grants; não generalizar seu processador privado como autorização universal. | Permissões próprias do Fórum e Search API permanecem donas. | Planejado |
| `aculta_forum` | `src/Entity/ForumPost.php`, `src/Access/ForumPostAccessHooks.php`, `src/Context/DiscussionContextResolver.php`, `src/Migration/ForumCommentMigrationAudit.php`, `src/ContentIndex/ForumContentIndexProvider.php` | `node:aculta_forum_topic`, `aculta_forum_post:aculta_forum_post` revisável/traduzível e Comments Core `aculta_discussion_post` coexistem; publicação/moderação, contexto `forum`, Domain e áreas restritas com Group/node grants | Provider implementado com projeções allowlisted para ambos os modelos durante coexistência; relações topic/parent explícitas; Media `forum_attachment` tem adapter próprio. Usar APIs de entidade e manter autorização no módulo proprietário. | Responsável decidiu revalidar no purpose canônico `forum`; o filtro preserva conta, grants e falha fechado se o Domain não existir. Kernel Index cobre tópico + Comment Core e ForumPost de produção, relações `forum_topic`/`forum_parent_post`, update/delete, rejeitados e revisão antiga não padrão (2/51); enumerador suporta bundle fixo sem chave só com declaração canônica (Unit 5/31). Grants/Domain reais e boot completo do owner permanecem pendentes. | Provider implementado; harness CRUD parcial; homologação pendente |
| `aculta_wiki` | `WikiController::findWikiEntries()`, `WikiContentIndexProvider` | EntityQuery com `accessCheck(TRUE)`, domínio Wiki e verificação `entity->access('view')`; `field_wiki_related` nomeado `related_entry` | Adapter próprio do módulo projeta metadados e relações explícitas; consulta existente segue específica da Wiki. Categorias e imagens ficam adiadas até a finalização do contrato da Wiki. | Unit cobre projeção allowlisted, revisão padrão e relação deduplicada. Kernel harness usa a classe real do provider para backfill/update/delete (1/17), com tag fixture. Unit do filtro usa o provider real e confirma Entity Access sob `wiki420_aculta_org`, preservando usuário e restaurando Domain (parte da suíte 8/70); isso não prova grants reais nem boot owner. O mapa de services lê o YAML Wiki real e compila a tag. O módulo permanece desabilitado; categorias/imagens continuam adiadas. | Código, Unit e CRUD Kernel parcial; Domain real/grants/Runtime pendentes |
| `aculta_statistics` | Catalog, Recorder, DailyAggregateStorage e retention | Indicadores agregados consumidos pelo painel | Não reutilizar como catálogo; integração via bridge opcional e agregações existentes. | Sem mover tabelas; fallback quando Statistics desabilitado. | Planejado |
| `aculta_statistics` | Eventos de Fórum/LMS/Commerce e hooks Wiki | Coletores analíticos sem dependência obrigatória dos módulos produtores | Adaptar convenção de eventos/contratos desacoplados quando houver evento de mudança do índice. | Não encaminhar evento analítico ao worker do índice. | Planejado |
| `aculta_statistics` | Reconciliadores Commerce/e-mail | Recalcula agregados a partir das fontes oficiais | Referência para comparar snapshot/delta e tratar falha; não extrair, pois semelhança é insuficiente para compartilhar domínio. | Classes mantidas no Statistics. | Planejado |
| `aculta_statistics` | `DomainPurposeManager` opcional via `PortalPurposeResolver` | Mapeia purpose para dados analíticos | Reaproveitar o serviço Portal existente e o padrão de dependência opcional, nunca criar resolver paralelo. | Integration bridge sem acoplamento reverso. | Planejado |
| `aculta_lms` / `aculta_lms_resources` | `CourseInterface::takeAccess()`, `LearningDashboardManager`, `CourseResourceBuilder`, `field_lms_readings`, `field_lms_galleries` | Group é fonte do curso/membership; LMS mantém lições e progresso; LMS Resources define referências de leitura e galeria | Provider `Group:lms_course` implementado em `aculta_lms_resources`; relações allowlisted `course_reading` (`revista`, `wiki_entry`) e `course_gallery`. Entity Access mais `takeAccess()` ou permission contextual de educador. | Sem progresso, membros, respostas, notas ou certificados. Targets passam por provider e Entity Access próprios; Kernel/DI e Runtime ainda pendentes. | Provider implementado; homologação pendente |
| `aculta_lms_h5p` | Campos H5P por Group e serviços de atividade | Atividades e resultados H5P no contexto do curso | Não indexar respostas, tentativas ou resultados pessoais; atividades H5P seguem dívida até contrato de acesso/payload minimizado. | Sem provider de H5P nesta fase; preservar isolamento Group. | Planejado / dívida explícita |
| `aculta_magazine` | `src/ContentIndex/MagazineContentIndexProvider.php`; `field_domain_source`, `field_published_at`, referências editoriais; workflow `revista` | Projeta metadados da revisão padrão e relações editoriais explicitamente permitidas | Provider implementado via interface neutra no Portal; `field_editorial_gallery` estruturado e relação `editorial_gallery`; comando de migração em lotes copia apenas embed único resolvido e preserva o corpo. Search API continua dona da busca textual. | Kernel do Index executa a classe de produção do provider e verifica backfill, update de título/relação e delete via worker (1/14). Provider é registrado por fixture isolada; o teste de service-map valida separadamente o YAML real do owner. A cadeia de dependências completa do `aculta_magazine`, purpose/domain real e grants editoriais ainda não são exercitados nesse Kernel. Migração de conteúdo não executada; casos com várias galerias são listados para revisão. | Implementado; CRUD Kernel parcial; homologação de owner/domain e execução operacional pendentes |
| `aculta_activity` (novo ownership) | `ActivityContentIndexProvider`; bundle Node `activity` | Projeção mínima e relação Revista → atividade | Provider tagged no módulo dono; inclui somente metadados comuns e mantém o campo de referência existente no provider Magazine. | Usa Entity Access no purpose `main`; edição, workflow, autoria e migração continuam sem decisão nesta microfase. | Kernel ativa o módulo owner e cobre descoberta/tag, backfill, update e delete via worker (2/29); domínio/grants reais pendentes |
| `aculta_gallery` / `aculta_image` | Entidades Media/Gallery, gestores de arquivos e acesso | Galerias e política de arquivos | Providers implementados para `media_gallery` e `media:image`; relações `gallery_image` e `gallery_cover` saem do provider Gallery; Entity Access da galeria usa o purpose canônico `magazine`. `media:image` é registrado por `aculta_image`; serviços permanecem nos módulos donos. | Arquivos privados, URLs, alt, descrição, direitos e binários não são projetados; Entity Access é revalidado. Providers também existem para `document` (Document Viewer), `course_document` (PDF Viewer Group, purpose `courses`) e `forum_attachment` (Forum, purpose `forum`). `remote_video` permanece sem provider até definir módulo proprietário. | Implementado parcialmente; `remote_video` adiado |
| `aculta_document_viewer`, `aculta_pdf_viewer` | Viewer e integração de Media/Group | Apresentação/acesso de documentos e acesso Group de turma | Provider `media:document` registrado pelo Document Viewer; `media:course_document` registrado pelo submódulo Group com purpose `courses`. Outros tipos documentais dependem de seus módulos donos. | Não copiar arquivo, URI, URL ou conteúdo; Entity Access/Group permanece autoridade. Integração Kernel e Runtime pendente. | Implementação local; homologação pendente |
| `aculta_user` | `ActivityController` e contratos de identidade/autoria | Atividade individual e privada | Não reutilizar como consulta geral. Provider só expõe IDs de autor se houver necessidade, minimização e autorização justificadas. | Evitar dados pessoais e vazamento por facetas/contagens. | Planejado |
| `aculta_links` | `LinkAnalytics`, agregado diário e fila local de cliques | Métrica de uso de links | Usar somente como referência de fila/retensão. Não extrair: payload e semântica são cliques, não projeções. | Sem nova dependência. | Planejado |
| `aculta_portal_sitemap` | Geração de sitemap por domain-purpose | Seleção de conteúdo publicável e URLs canônicas | Reutilizar regras de purpose/seleção onde contrato permita; sitemap não é catálogo nem fonte de autorização. | Não criar dependência do sitemap no índice. | Planejado |
| Core Entity API | Entity Access, revisions, translations, hooks/attributes | Fonte de verdade e autorização de cada entidade | Usar APIs Core via DI; hooks só enfileiram IDs/versões mínimas e transição para privado exige bloqueio síncrono. | Verificar Core 11.3 instalado antes de cada hook/evento. | Planejado |
| Search API 8.x-1.42 | `ContentEntityTrackingManager`, entity datasource, trackers, `IndexingBatchHelper`, Database backend | Descobre mudanças de entidades; processa texto e reindex | Referência arquitetural e integração para busca; não duplicar datasource/tracker nem assumir seus grants como política universal. | Commit `444de021...`; GPL-2.0-or-later; nenhum trecho copiado. | Auditada |
| Advanced Queue 8.x-1.7 | JobType, backend Database, queue actions retry/release/delete | Jobs persistentes, estados, retentativas/execução por worker | Considerar integração se dependência estiver/for aprovada; portar conceitos idempotentes apenas se Core Queue não cumprir requisito medido. | Commit `37450d9c...`; GPL-2.0-or-later; requer análise de custo/compatibilidade antes de dependência. | Auditada |
| Entity Usage 8.x-2.3 | `EntityUsageTrackManager`, plugins Track, `EntityUsage`, batch de reconstrução | Descobre e persiste relações configuradas, including entity references/embeds | Adaptar conceitos de plugins e source/target; não reutilizar diretamente storage porque semântica inclui field, revision e usage count. Já exigido pelo Fórum. | Commit `4a7893f0...`; GPL-2.0+ (equivalente SPDX `GPL-2.0-or-later`); nenhum trecho copiado. | Auditada |
| Views Bulk Operations 4.4.9 | `ViewsBulkOperationsActionProcessor`, `ViewsBulkOperationsBatch`, batch size/options | Ações sobre resultados Views e Batch API | Referência para feedback/limites de processamento administrativo, não para workers duráveis/checkpoints. | Commit `fc5a5e26...`; GPL-2.0-or-later; já declarado pelo Fórum. | Auditada |
| Track Usage 1.0.0-alpha7 | `Tracker`, `Recorder`, track plugins; instant/batch/queue configuráveis | Registra usages por configuração e atualiza via hooks/Drush/queue | Referência secundária para mecanismo e falhas; não adotar alpha como dependência nem confundir uso de field com catálogo. | Projeto `track_usage` (slug singular); commit `a0799d50...`; GPL-2.0-or-later; nenhum trecho copiado. | Auditada |

## Fontes contrib: proveniência da inspeção

Fontes clonadas fora do checkout, em `/tmp/aculta-index-audit-*`. Os checksums abaixo são SHA-256 do `git archive HEAD`; o commit fixa a revisão examinada. Nenhuma alteração ou cópia foi feita nos clones ou no repositório ACULTA.

| Projeto / release | Commit | Tree | SHA-256 do arquivo Git | Licença em `composer.json` |
|---|---|---|---|---|
| Search API `8.x-1.42` | `444de02192456fb619e8163ffeb330b93e0a4732` | `3b8bd1d816297e446d053f6364b0acde9913bf51` | `00b7f0aab27495e19c9462e8b1f97fe73715e44cb79f7001de32e89c49d29b65` | GPL-2.0-or-later |
| Advanced Queue `8.x-1.7` | `37450d9cdd5bd3cdb164ffafe4e5b24866265573` | `7cb5eb7a72b74fb887daf158357b6185bea70ad` | `89fbc9d3dd1955b6bb8779faf6ba94abff8c6b7e1284a6de3a171b429a0ef3e1` | GPL-2.0-or-later |
| Entity Usage `8.x-2.3` | `4a7893f08105f1e4c62fcf1a4ca1cbe4da48cc1e` | `b300fae4d1678f5c93becd3fab4ba3be7fc2da46` | `89b21c4e692d99d5c5ccf13cb94e982c00ff1cf1625a744364e233695b37239a` | GPL-2.0+ |
| Views Bulk Operations `4.4.9` | `fc5a5e2699c4191f85421d22ffe7f2b9ddaaeefa` | `194f082a236ceec5d27db3c6bc65d12cbd143332` | `4fd7f3a6d410bf3fdf392442f2cde724b1e25abe02990aedfb5744c1be870bdf` | GPL-2.0-or-later |
| Track Usage `1.0.0-alpha7` | `a0799d505cb05f2f3028110d367c65d15c0f1c3c` | `a531e719fab045915df3b8a2ec74d9828cc345cd` | `73ea6155d947918e9baf5ff0813fbdea692f9e3ef9a05ab3341c06abf292a0dc` | GPL-2.0-or-later |

Arquivos examinados incluem `composer.json`, serviços, plugins, tracker/datasource/backend e os pontos de batch/queue relevantes. A auditoria é uma inspeção de código e contratos, não um teste de compatibilidade instalado em um site Drupal.

## Decisões e riscos que seguem para as fases

1. `aculta_index` pertence ao Kernel `aculta_portal`, sob `modules/aculta_index`, como submódulo opcional. A instalação/desinstalação não altera nem exige dados originais.
2. Catálogo relacional próprio para metadados e relações selecionadas; Search API fica dona de busca textual, com possível consumer/bridge posterior. Não criar outro parser/fulltext.
3. Providers são de descoberta/projeção. Cada provider determina tipos, bundle, propriedades permitidas e relações; nenhuma varredura genérica de referências transforma relações em dados públicos.
4. Estatísticas permanecem em `aculta_statistics`; bridge opcional depende do contrato do índice (Statistics pode operar sem ele, e vice-versa). Não haverá dependência circular.
5. Persistir IDs e payload mínimo em jobs. Na leitura, carregar/autorizar a entidade original sob contexto de domínio e Group apropriado; candidatos SQL, facetas e totais não são autorização. Estados de acesso/revogação exigem invalidar/restringir sincronicamente antes do worker.
6. Um rebuild com geração não protege sozinho contra estado privado obsoleto. A API deve validar acesso e versão atual; as mudanças de visibilidade invalidam ou bloqueiam a entrada de forma síncrona.
7. Core Queue é candidato inicial; Advanced Queue somente após evidência de que estados persistentes, retries, atrasos ou operação não cabem na infraestrutura instalada. A escolha deve considerar dependências do projeto e testes.
8. Relações são direcionadas e tipadas, com produtor, cardinalidade e lifecycle declarados. Relação a usuário/autoria exige justificativa e escopo de privacidade.
9. O catálogo usa cursor/keyset; tabela de entradas, relação, job e geração precisam índices compostos validados para MariaDB e SQLite. Definição exata fica na IDX 0.1-C.
10. A busca pública existente segue independente. O índice novo não substituirá Search API nem o mecanismo do Fórum nesta versão.

Riscos em aberto: revogação de acesso por grants/Group/domain nem sempre corresponde a save da entidade; versões e translations precisam identidade estável; ativação opcional do provider deve resistir a módulo de origem desabilitado; rebuild precisa capturar deltas concorrentes; contagens/facetas podem vazar acesso; portabilidade de SQL concorrente precisa ser testada nos bancos suportados.

## Roadmap revisado

| Fase | Estado após esta auditoria | Gate |
|---|---|---|
| IDX 0.1-A — auditoria e ADR | Concluída nesta entrega documental | Não foi criado runtime nem copiado contrib. |
| IDX 0.1-B — fundação | Próxima | Manifesto/versionamento do submódulo, contratos mínimos e lifecycle opcional. |
| IDX 0.1-C — persistência e primeiro provider | Pendente | Identidade, schema e relações revisados antes de instalar tabelas. |
| IDX 0.1-D — sincronização incremental | Em desenvolvimento | Core Queue escolhida; estado mínimo próprio, hooks CRUD, sequência e worker inicial em implementação. Alterações de grants externos seguem revalidação na consulta. |
| IDX 0.1-E — rebuild e reparação | Pendente | Geração, cursor/delta, retomada e rollback demonstráveis. |
| IDX 0.1-F — integrações | Pendente | Providers só após contratos e testes do módulo proprietário; Statistics bridge opcional. |
| IDX 0.1-G — consultas/desempenho | Pendente | Segurança, paginação e medições antes de declarar escala. |
| IDX 0.1-R — homologação | Pendente | Gate completo de segurança, banco, concorrência, performance e reversão. |

## O que não foi verificado

- Runtime, ativação de módulos e estado atual do banco: não consultados nesta fase documental.
- Track Usage releases 5.x e compatibilidade com Drupal 11.3: não fazem parte da versão alpha inspecionada.
- Compatibilidade efetiva de Advanced Queue `8.x-1.7` e demais referências com a instalação Composer ativa: não é assumida pelo clone upstream.
- Métricas de desempenho em qualquer volume: não medidas.
- Alterações locais de outras frentes foram preservadas. O worktree já continha alterações de config/Magazine e arquivos não rastreados `node_modules/`, `package.json`, `package-lock.json` antes desta auditoria.
