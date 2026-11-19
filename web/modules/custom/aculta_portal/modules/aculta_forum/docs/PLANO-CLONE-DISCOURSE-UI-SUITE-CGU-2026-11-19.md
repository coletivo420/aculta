> **POLÍTICA VIGENTE (2026-11-19):** [acervo histórico Growroom](POLITICA-ACERVO-HISTORICO-GROWROOM-2026-11-19.md). Comentários históricos **não são comentários padrão** e **não serão migrados** para ForumPost. Coexistência técnica não autoriza conversão, mesclagem, recontagem como atividade corrente ou moderação pela fila comum. A separação física requer inventário e homologação; alterações desta PR são apenas guardas iniciais.

# Política ACULTA Forum: clone funcional Drupal do Discourse

Decisão de produto aprovada em 2026-11-19. **Paridade funcional e de experiência com Discourse**, implementação nativa sobre Drupal 11/12, identidade ACULTA e UI Suite/SDC como arquitetura visual obrigatória. O `aculta_forum` é o **motor único de discussões e comentários de todos os módulos do site**. Isso **não** significa transformar qualquer comentário editorial, pedagógico ou restrito em tópico público; cada host preserva entidade, acesso, workflow e propósito. A moderação de denúncias e os casos usam `aculta_cgu` como dono, com adapters de ações por contexto.

## Objetivo do plano e critérios de paridade

**Clonar o Discourse é uma política de produto, não copiar seu stack.** Toda capacidade essencial do Discourse (listagem, tags, Latest/Top/New/Unread, leitura contínua, composer, rascunhos, citações, menções, subscriptions, busca, perfis e moderação) terá contrato Drupal com teste e story SDC. Funcionalidades opcionais podem seguir como P2/P3, mas só depois de classificadas na matriz. O `aculta_forum` é o único motor de discussão e comentários do ACULTA, com adapters para todos os hosts; a entidade de origem, o grupo e as regras editoriais permanecem com o módulo correspondente.

**Documentos de evidência:** [Auditoria e achados](AUDITORIA-CLONE-DISCOURSE-INTEGRACOES-2026-11-19.md), [UI Suite e paridade](MATRIZ-PARIDADE-DISCOURSE-UI-PATTERNS-2026-11-19.md), [Testes e release](MATRIZ-ACEITE-DISCOURSE-ACULTA-2026-11-19.md), [Plano específico CGU](../../aculta_cgu/docs/CONTRATO-INTEGRACAO-FORUM-CGU-2026-11-19.md).

## Arquitetura alvo - obrigações

### Ownership da importação histórica — F-D48

`aculta_forum_legacy` é um addon/importador opcional e autocontido, específico
dos projetos que adotarem o acervo histórico Growroom. Ele reúne todos os
complementos necessários a essa importação: código, configuração, fields,
rotas, Views, comandos/ferramentas, apresentação, projeções, métricas,
políticas e adapters legados. As referências e arquivos de autoria existentes
devem ser inventariados e preservados; não se define novo armazenamento de avatar
nesta fase. Nenhum desses elementos específicos deve ser instalado ou embutido
nos módulos principais.

`aculta_forum`, `aculta_portal`, `aculta_index`, `aculta_statistics`, CGU,
Messenger, Game, User e demais módulos reutilizáveis oferecem somente APIs e
contratos genéricos. O addon registra seus providers/adapters por esses
contratos; não há dependência reversa, carregamento de classes, configuração,
IDs ou convenções do addon pelos módulos principais. A dependência declarada
é unidirecional: `aculta_forum_legacy` pode depender dos módulos e contratos
genéricos necessários, enquanto esses módulos não dependem do addon. A
instalação do site ACULTA pode optar pelo addon; outros projetos Drupal com o
motor `aculta_forum` podem omiti-lo por completo.

Esta fronteira vale para toda integração originada pela importação histórica,
não apenas para Index e Statistics. A ausência do addon deixa o motor corrente
funcional; origens sem provider autorizado falham fechadas. O addon opcional
não é requisito do `aculta_forum` nem do `aculta_portal`.

### Fronteiras dos módulos pela política vigente

O addon não pode criar dependência reversa de CGU, Game, Messenger ou
`aculta_user`. Ele publicará a origem `ARCHIVE` por contrato genérico e manterá
seus providers próprios; cada módulo principal trata a origem dentro de suas
responsabilidades sem conhecer entidades, campos ou rotas do addon. Pelo
contrato vigente do acervo, conteúdo histórico fica fora das filas ordinárias
do CGU; medida excepcional exige protocolo específico, auditável e autorizado.
O CGU continua dono de denúncias, casos e decisões e não deve oferecer ação
ordinária que altere o arquivo imutável.

O arquivo não cria avisos, assinaturas, XP, participação ou perfil de usuário
corrente. Importação, rebuild, migração ou restauração também não podem gerar
esses eventos colaterais. Os consumidores implementam essas exclusões por
contratos genéricos de origem, sem acoplamento direto ao addon.

`aculta_user` continua responsável por usuários atuais. Autores históricos
seguem a entidade e a apresentação definidas em F-D44, sem converter autoria
histórica em conta ou perfil individual.

“Autor legado” significa exclusivamente a entidade de conteúdo existente
`aculta_legado_autor`, atualmente implementada por
`Drupal\aculta_user\Entity\LegacyAuthor`. A entidade e o armazenamento já
existentes devem ser transferidos em ownership para o addon preservando IDs,
referências e dados. Não criar conta/entidade Drupal User ou Profile. A
entidade não recebe login, papéis, permissões, perfil de conta, amizade,
mensagens ou participação. A exibição individual nos tópicos e comentários do
arquivo segue F-D44; o único destino público de memória permanece o memorial
coletivo. A classe, controller e serviços atuais ainda estão em `aculta_user`;
isso é acoplamento a extrair, não implementação já concluída no addon.

1. **`aculta_forum`: API de discussões universal.** Criar/estabilizar `DiscussionHostProviderInterface` (ou ampliar `DiscussionContextProviderInterface` atual) com `supports`, resolução de contexto, `read/write/moderate`, estado aberto/fechado, editor/formato, URL/âncora, política de anexos, citação, notificações e cacheability. Registry/tag no Fórum; nenhum módulo de domínio cria engine de comentários paralelo. Core Comment é armazenamento interno admissível conforme o host. **Não criar ForumPost global** para LMS/Wiki/Revista: ForumPost permanece o post revisionável do Growroom.
2. **Consumidores obrigatórios:** Growroom `node:aculta_forum_topic` → ForumPost novo; Wiki `node:wiki_entry` → Comment contextual; Revista `node:revista` → Comment contextual; LMS `node:aculta_lms_topic` → Comment contextual com Group e Educador; LMS feedback `aculta_lms_answer.comments`/`aculta_lms_answer_comment` → inventário e adapter obrigatório de interação, preservando avaliação privada e ACL. Qualquer novo campo de comentário do site deve registrar provider ou reutilizar adapter universal; falhar fechado se não há suporte. Não ativar comentários sem política.
3. **`aculta_cgu`: único owner de denúncias, casos, review queue, ações auditadas e decisões.** Fórum implementa adapter do alvo e executa mutações de ForumPost/Comment/tópico somente com ACL revisada. CGU separa denúncia, caso, decisão e ação aplicada. Na moderação LMS, Educador/Group continua a decidir; permissões globais de fórum jamais abrem conteúdo pedagógico. Revista mantém `EditorialCapability` e Wiki mantém editoria.
4. **`aculta_index`: catálogo transversal.** `aculta_forum` fornece projeções mínimas e relações (host/post/parent) dos próprios conteúdos, sem corpos pessoais; `ContentIndexAccessFilter` deve revalidar entidades por purpose/host. Comentários de Wiki/Revista/LMS entram em descoberta contextual somente com provider/escopo autorizado e política explícita, não no índice público do Growroom; evitar disputa entre providers sobre `comment:aculta_discussion_post` e estabilizar chave/projeção. O `Search API` indexa texto quando autorizado. **Não confundir Index e Search API.**
5. **`aculta_statistics`: fonte única de métricas.** Fórum emite eventos estáveis sem texto/autor/identificador pessoal sensível, usa ledger para não duplicar publicação ou migração; CGU expõe moderação; Index revalida conteúdo antes de agregação com permissões independentes. Distinguir estoque, fluxo, privacidade e as diferenças de purpose; por padrão métricas de LMS e contextos restritos não entram no painel público.
6. **`aculta_messenger` / `aculta_mailer`:** únicos owners dos avisos, preferências e entrega; evento neutro, idempotência e revalidação de entidade, matrícula e assinatura no consumo, opt-in por tópico/categoria/tag; nenhuma thread de chat criada ao seguir tópico.
7. **Identidade corrente e extensões opcionais:** `aculta_forum` consome contratos neutros para autoria, cartão, avatar e participação; não depende de `aculta_user` para existir. A integração ACULTA de identidade corrente (contas, perfis, associados, exceções à janela de edição) pertence a um adapter opcional do projeto, fora do pacote histórico. `aculta_game` continua dono de XP/conquistas e consome eventos correntes sem reconhecer o arquivo como participação nova.
8. **`aculta_forum_legacy`:** único addon opcional dono de toda integração do acervo histórico Growroom. Centraliza entidade e apresentação de autoria histórica nos tópicos/comentários do arquivo, memorial coletivo, classificação/origem, exclusões de filas e eventos, catálogo/pesquisa, métricas históricas e adapters dos demais módulos. Não há perfil individual público. O addon não transforma autores em Users nem converte Comments em ForumPosts.
9. **`aculta_editor`, Media, Gallery, Image:** editor e upload transversais sob suas APIs; incorporar embed em composer SDC, proteger privados e limpeza conforme revisão/tombstone.
10. **`aculta420_v2` e UI Suite:** único dono visual. UI Patterns é **obrigatório** nas Views, Layouts, Field Groups e biblioteca de componentes; SDC schemas/slots/stories, UI Skins no lugar de purpose-palette, UI Styles para variantes, UI Icons/Phosphor por UI Icons Picker. Módulo Fórum entrega somente render arrays neutros, sem montar CSS próprio ou acoplar classes de tema; adapters Twig mínimos são permitidos enquanto necessários para preservar metadados Core e slots. `UI Patterns CKEditor 5` continua fora do escopo aprovado.

## Implementação em pacotes sequenciais

| Pacote | Prioridade | Entrega concreta | Gate |
| --- | --- | --- | --- |
| FDC-00 - Preflight e baseline | P0 | Capturar HEAD, DB de teste, tabelas/config, endpoints/consumers, providers e styles ativos, matriz de ocorrências Comment por `entity_type, bundle, field_name` e rota | Diferença entre código e Runtime documentada; backup/rollback; sem escrita não autorizada |
| FDC-01 - Segurança | P0 | Resolver AF-01/02/05/06/09 e AC-01, revisitar cache/Context, abortar em escopo desconhecido | Unit+Kernel+Functional para citação cruzada, GET/HEAD, redirect, host/pid, Group/CGU |
| FDC-02 - Contrato universal | P0 | Provider/adapters para Growroom, Wiki, Revista, LMS tópicos e LMS feedback; compositor universal acionado no conteúdo atual; manter Core Comment/ForumPost onde adequado | Create/reply/quote/edit/delete/preview/errors/fallback retornam ao host, acessível, sem duplicação, nenhum cross-host |
| FDC-03 - Novo ForumPost e arquivo histórico segregado | P1 | Roteamento das **novas respostas** Growroom exclusivamente para ForumPost; classificador e apresentação do histórico read-only, com URLs e autoria preservadas; **não migrar Comment histórico** | Testes Kernel/Browser sem novos Comments no Growroom, sem mutação de arquivo e com fontes Index/Statistics separadas |
| FDC-04 - Moderação CGU | P0/P1 | Corrigir resolvePurpose por host, criar adapter de decisão/ação, fila CGU única, timeline, notas restritas, atribuição e evolução da bridge de Flagging | Aprovar denúncia não muta conteúdo implicitamente; ação explícita, por purpose/Group, audita, não duplica e pode reverter quando aplicável |
| FDC-05 - Index/Search | P1 | Inventário e projeções por tipo/host; proteger indexação de Comments, Query Access + Search API, invalidação, reindexação e relações de thread | Busca completa dentro do escopo, zero LMS privado em índice público, revogação fail-closed antes de paginação |
| FDC-06 - Statistics/FST | P1 | Convergir eventos, ledger, contagens atual/current, reações, busca, moderação CGU, métricas por conteúdo autorizado | Snapshots + `reconcile` dry-run e testes Kernel round-trip com Index/CGU/Stats; nenhum conteúdo privado em dimensão pública |
| FDC-07 - UI Suite estrutural | P1 | Mapear cada View/display/row/formatter e construir páginas via UI Patterns Views/Layouts/Field Group; SDCs do tema; UI Skins/Styles/Icons | Resolver consumers reais, config install/sync, story e acessibilidade; sem hardcoded theme markup; rollback do display |
| FDC-08 - Paridade de UX Discourse | P2 | Latest/Top/New/Unread, filtros/tags, timeline, jump unread, composer persistente, draft/autosave, citações por seleção, menções, acompanhar/silenciar | Matriz desktop/mobile, claro/escuro/skin, teclado, zoom, leitor de tela e sem-JS |
| FDC-09 - Ferramentas comunidade | P2 | Busca contextual, links/quotes, bookmarks, notas de equipe, split/merge/move, tópicos fixos/fechados, preferências e estados | Integridade das relações, URLs e ACL nas duas pontas; audit log e recovery |
| FDC-10 - Performance/RC | P1 | Profiling SQL, queries N+1, API pagination, 1k/10k posts, CI PHPUnit/PHPCS/PHPStan, testes multimódulos | CI atual verde nos gates afetados, benchmark com SLO definidos e Runtime homologado antes de RC |
| FDC-11 - Addon opcional `aculta_forum_legacy` | P0 de arquitetura antes de FDC-03/FDC-05/FDC-06 | Inventário completo de dependências; definir contratos neutros; concentrar no addon toda leitura, classificação, autoria, perfil histórico, memorial, apresentação somente leitura, filtros, catálogo e métricas do arquivo. `aculta_forum` deve operar e instalar sem o addon. | Matriz de instalação com addon ausente/presente; nenhum identificador/campo/tipo de entidade legado referenciado diretamente pelo core; arquivo e URLs preservados em clone de teste; gates e revisão independente antes de qualquer transferência de ownership de storage/config |

## FDC-11 — Fronteira do legado e portabilidade

`aculta_forum_legacy` será um submódulo custom opcional do projeto, sem dependência reversa do core. Ele centralizará **absolutamente toda a semântica específica do fórum legado**. Com o addon ausente, o motor oferece discussões atuais e Comments contextuais sem carregar rotas, entidades, campos, consultas, eventos ou decisões do arquivo. Com o addon presente, providers/adapters registrados pelo próprio addon acrescentam leitura histórica, mantendo a política de imutabilidade e isolamento.

### Evidência da implementação local — 2026-11-19 (census estático, não homologado)

- **O addon ainda não está implementado nesta branch.** Não há diretório/módulo `aculta_forum_legacy` no código custom, nem manifesto Drupal, serviços, schema, configuração de instalação ou implementação de uninstall validator. As menções ao addon nos documentos descrevem a arquitetura-alvo, não código instalado.
- **Ainda não há classificador central de origem histórica.** A busca não encontrou implementações PHP de `ForumDiscussionOriginPolicy` ou `CurrentDiscussionOriginClassifier`, nem provider do addon. `DiscussionContextResolver` resolve host/provider de discussão e `ForumAnalyticsScopeResolver` resolve escopo analítico; nenhum deles prova classificação de origem `ARCHIVE`. O código corrente identifica histórico diretamente por campos/classes em vários serviços.
- **O ownership atual continua em `aculta_user` e `aculta_forum`.** `aculta_user` contém `Entity\LegacyAuthor`, a tabela/type `aculta_legado_autor`, manager, handler de acesso, memorial, apresentação e hooks de autoria/moderação. `aculta_forum` contém os fields, verificações de marcador, migrações e ferramentas relacionadas, inclusive as referências listadas no census abaixo. Não tratar a fronteira pretendida como já extraída.
- `config/sync` contém storage/instâncias de `field_autor_legado` e displays de ForumPost que mencionam `legacy_author`; não existe configuração de addon no sync encontrada nesta branch. Esses arquivos/configurações são candidatos distintos de dados persistidos e exigem comparação por UUID/ID e dependências em clone.
- As três migrations Comment→ForumPost estão registradas e executáveis pelo sistema Migrate, mas nenhuma evidência estática prova se foram executadas em qualquer Runtime. Os destinos podem ter criado ForumPosts e flaggings da refatoração; consultar mapa/histórico de execução e proveniência no census autorizado antes de propor limpeza. Não tocar nos Comments/autores/tópicos de origem.
- Esta revisão foi somente leitura da árvore Git: não consultou o banco Runtime e não executou Drush, import, update, migration, uninstall, rebuild ou limpeza.

### Auditoria estática preliminar dos acoplamentos — 2026-11-19

Esta busca cobre código/configuração PHP, YAML e Twig versionados nesta worktree;
não substitui o census de Runtime/config ativo do FDC-11.0. Achados que devem
orientar a extração e a revisão:

| Área | Evidência atual | Destino/critério do plano |
| --- | --- | --- |
| Configuração de fields | `field.storage.*.field_autor_legado` e `field.field.*.field_autor_legado` continuam em `config/sync`; defaults relacionados existem em `aculta_forum` e `aculta_user`. Não há defaults do addon porque o módulo ainda não existe na branch. | Comparar config ativa, sync e defaults por UUID/ID/dependências em clone restaurável. A transferência ao futuro addon não prova ownership seguro nem autoriza import; provar instalação/atualização preservando storage, valores e dependências antes de remover definições antigas. |
| Resíduo de refatoração em ForumPost/docs | O `ForumPost` atual não declara `legacy_author`, mas quatro displays de ForumPost no config sync ainda o listam como hidden. Documentos/roadmap de Forum e User também descrevem `ForumPost.legacy_author` ou autoria histórica no post revisionável, em conflito com a política atual de não converter Comments históricos em ForumPosts. | Tratar a chave de display e instruções normativas obsoletas como artefatos da refatoração, não como registros do acervo. Antes de removê-las, confirmar em config/schema/testes que não existe field atual nem dado histórico armazenado ali. Concentrar a política normativa e a semântica histórica em `aculta_forum_legacy`; manter changelog histórico apenas como registro datado e explicitamente supersedido, sem guiar implementação atual. |
| Documentação e ferramentas de migração/refatoração | `docs/OWNERSHIP.md` ainda liga autoria histórica a `aculta_user` e cita fila/hook antigo; `REVISIONABLE-DISCUSSION-WORKFLOW.md` e seções do `ROADMAP.md` descrevem `ForumPost.legacy_author` e conversão Comment→ForumPost. No código do Fórum há auditoria Drush/Migration e plugins source/destination que conhecem os campos/autores legados. `MIGRATION-REMOVAL.md` é auditoria histórica da retirada do Drupal Forum contrib e inclui decisões de limpeza antigas. | Transferir política normativa e ferramentas read-only do acervo para a documentação/addon; atualizar ownership e roadmap atuais para não prescrever conversão nem dependência de User. Provar quais comandos/plugins foram executados e se criaram registros da refatoração. Conversores Comment→ForumPost ficam fora do fluxo executável; só propor sua retirada depois de verificar uso e dependências. Preservar auditorias/changelogs como evidência datada, marcando trechos superados sem apagar histórico. Distinguir retirada do contrib da extração do acervo Growroom. |
| Acoplamentos diretos no Fórum | Não há classificador genérico de origem no código PHP. Uma busca estática em `src/` encontrou referências legadas em `DiscussionReplyController`, `DiscussionReplyAccess`, `ForumQuoteHooks`, `ForumHistoricModerationQueueHooks`, `HistoricForumFlagAccessHooks`, `PublicForumLastActivityAuthorHooks`, `ForumPostMigrationFieldHooks`, `ForumCommentMigrationAudit`, `ForumCommentMigrationAuditCommand`, `ForumCommentPost`, `ForumComment` (source Migrate), `ForumCommentFlagging` (source/destination), `ForumFlaggingMigrationContext`, `ForumCommentMover` e `ForumTopicMover`. Há leitura direta de `field_autor_legado`, acesso à entidade `aculta_legado_autor`, apresentação de autoria, exclusões de fila/flag, auditoria e suporte a conversão. | São acoplamentos reais que contradizem a fronteira pretendida, não prova de que o core já esteja desacoplado. No FDC-11.2, classificar cada arquivo como semântica de arquivo a mover para o addon, adapter a substituir por contrato genérico, ou ferramenta de conversão/refatoração a retirar após provar que não é necessária ao acervo preexistente. Não apagar autores, tópicos, comentários, relações ou arquivos históricos. Cobrir rotas e consumidores antes de remover os marcadores diretos. |
| Migrações de Comment para ForumPost | `migrations/aculta_forum_comment_posts.yml`, `aculta_forum_comment_parents.yml` e `aculta_forum_comment_flaggings.yml` estão registradas no módulo. A source filtra tipo/campo/host Forum e Domain, mas não `field_autor_legado`, status publicado nem rejeição; o destino cria ForumPosts e transporta `legacy_author`; as migrations seguintes recriam parent/child e flaggings no alvo ForumPost. | A inclusão do acervo histórico conflita com a decisão vigente: Comments Growroom históricos nunca podem ser convertidos, ter sua árvore refeita ou suas flags transferidas. Nenhum import é autorizado nesta fase. O plano deve restringir a origem por classificador central fail-closed; eventual migração de Comments correntes precisa ser escopo separado e aprovado. Procurar execuções/mapas antes de qualquer alteração. Se a refatoração criou ForumPosts/flagging, identificar somente esses destinos e removê-los em plano reversível autorizado; preservar integralmente Comments/autores/tópicos/flags históricas de origem. |
| Acoplamentos em User, Game e Messenger | `aculta_user` ainda define a entidade/tabela `aculta_legado_autor`, manager, access handler, nome de exibição, rota MAIN `/u/lendashistoricas`, memorial coletivo, reserva literal do slug em `AccountNames::RESERVED`, cartões, hooks de autoria/moderação, `ReportQueue` e exceção em `ParticipationRules`. A varredura também encontrou integração genérica por bundle em `ParticipationRules::publishedPublicForumCommentCount`, `ActivityController` (listas de tópicos/comentários/posts), `ParticipationHooks`, `NodeCoAuthorHooks`, `CoAuthorSettingsForm`/`CoAuthorAdminController` e `AuthorCardVariantResolver`; não classificam todos os itens por proveniência. `aculta_game::ForumParticipationPolicy` lê `field_autor_legado`; `aculta_messenger::ForumNoticeAccessPolicy`, `MessageSubscribeEventHooks` e `MessageSubscribeEventWorker` verificam o campo, enquanto outros caminhos do Messenger reconhecem o bundle do tópico corrente. | Mover entidade/config/rota/memorial/apresentação e regras exclusivamente históricas para o addon. A reserva do slug deve usar contrato genérico de slugs reservados, com o addon contribuindo `lendashistoricas`; não deixar literal legado em `aculta_user`. Manter recursos correntes de conta, coautoria, atividade e participação no domínio/adapter ACULTA, mas substituir verificações históricas por consulta ao contrato genérico de origem. Para cada consulta por bundle, provar se precisa incluir `CURRENT`, excluir `ARCHIVE`/`UNKNOWN` ou tratar contexto próprio; UID e bundle sozinhos não provam origem. Game, Messenger, User e demais consumidores principais não devem ler nomes de field nem classes do addon. Preservar a política: importação/reconstrução não gera participação, avisos, assinaturas, XP ou conquistas e autores históricos não viram perfil/conta corrente; testar no enfileiramento e no consumo dos eventos. |
| Classificação de origem | Não foram encontrados `ForumDiscussionOriginPolicy`, `CurrentDiscussionOriginClassifier` ou provider `aculta_forum_legacy` em PHP. `DiscussionContextResolver` resolve hosts de discussão; `ForumAnalyticsScopeResolver` trata escopo analítico. Ambos são contratos diferentes de classificar legado/corrente/contextual. O histórico ainda é detectado por campos/classes diretamente. | Projetar e testar um resolver central genérico para `ARCHIVE`, `CURRENT`, `CONTEXTUAL` e `UNKNOWN`; provider de arquivo pertence ao addon opcional. Testar addon ausente/presente, marker ausente, precedência, host cruzado e saída inválida. Falhas de origem para escrita/eventos devem ser fechadas. Views, Search API e agregadores ainda precisam consumir a classificação. |
| Views e navegação | Views públicas/administrativas filtram `node:aculta_forum_topic` por bundle/status; `PublicForumListViewHooks` calcula contagens por bundle/categoria. Esses caminhos não filtram a origem histórica por si mesmos. | Separar listagem atual, filas e contagens do catálogo histórico. Aplicar filtro de origem por provider em Views/query/access e nas contagens auxiliares; preservar rota/canonical de leitura histórica e grants. Só usar marker após o census provar cobertura; não presumir que todo registro possui marker. |
| Index/Search | `ForumContentIndexProvider::isIndexable()` aceita tópicos do bundle, ForumPosts default e Comments `aculta_discussion_post` do host tópico; não consulta origem nem `field_autor_legado`. O texto descritivo chama isso de indexação durante migração. Portanto o índice corrente pode misturar origem histórica e corrente se dados marcados existirem. | Não executar rebuild. Testar filtros de origem e acesso direto; excluir `ARCHIVE`/`UNKNOWN` do índice corrente usando classificador genérico. O addon futuro deve prover catálogo histórico separado, ACL-aware, sem depender do Search público corrente. |
| CGU e sinalizações | O acoplamento é dividido entre `aculta_forum/src/Hook/ForumReportCguBridgeHooks.php` (cria/remove denúncia ao criar/remover Flagging e grava `legacy_flag_id`/`sinalizacao_legada_forum`), `aculta_cgu/src/Service/CguReportQueue.php` (reconcilia flags, evita duplicatas e oferece migração/rollback) e `aculta_cgu/src/Entity/CguReport.php` (campo `legacy_flag_id`). É uma ponte executável de Flagging legado, não apenas referência documental. | Identificar cada Flagging por alvo e proveniência: acervo histórico, conteúdo corrente ou origem desconhecida. O arquivo fica fora das filas ordinárias; exceções exigem protocolo específico, auditável e autorizado. CGU conserva a propriedade genérica de denúncias, casos e decisões; o addon fornece integração histórica por contrato/adaptador e não passa a ser dependência de CGU. Não migrar, excluir nem reconciliar linhas nesta fase. Em etapa futura, remover a ponte somente após mapear referências/relatórios já persistidos e provar compatibilidade sem perda da auditoria. |
| Estatísticas | Não foi encontrado `LegacyArchiveMetricsProvider`. `AuthorizedEntityMetricAggregator` valida bundle/permissão para `forum.replies.created`, mas não consulta origem. Os subscribers de atividade/reação do Statistics precisam ser revisados para eventos importados/sincronizados. | Projetar provider de série histórica no addon e consumi-lo por API genérica; validar cada candidato por origem antes do drilldown. Separar séries histórica/corrente, evitar dupla contagem e não reconstruir nesta fase. |
| Catálogo histórico | `LegacyMemorialController` em `aculta_user` busca/pagina nomes de autores; não foi encontrado catálogo ou busca de texto de tópicos/respostas no arquivo. O `ForumContentIndexProvider` corrente não exige origem `CURRENT` e não é substituto seguro para um índice histórico separado. | Especificar índice/catálogo no addon com ACL, hierarquia, URLs e busca de tópicos/Comments históricos. Excluir origem ARCHIVE do índice corrente e contextual do arquivo. Sem rebuild até autorização operacional. |
| Autoria e UI | Não existe `LegacyAuthorCardBuilder` no código da branch. `aculta_user` implementa `AuthorCardBuilder`, `LegacyAuthorDisplayName`, `DiscussionCommentAuthorHooks` e `LegacyMemorialController`; há SDC `author-card` no tema. | Extrair autoria histórica e memorial para o addon e reutilizar o SDC com variante somente leitura/archive; não duplicar componente nem criar perfil individual. Verificar slots/cache/access antes de remover Twig/hooks existentes. |
| Dependências | Não há manifesto do addon para auditar. `aculta_forum.info.yml` declara `aculta_user` como dependência obrigatória, além de serviços atuais de identidade/participação; `aculta_user` guarda hoje entity/schema/rota históricos, embora seu `.info.yml` não declare dependência do Fórum. | Não remover em bloco a integração de identidade ACULTA corrente. Separar no census dependências correntes necessárias de serviços/fields legados; substituir apenas a parte corrente por adapter opcional quando requerido por portabilidade e mover ownership histórico para addon. Definir/testar matriz sem `aculta_user` e com addon ausente/presente; manifestos e service containers não podem exigir o addon. |

O census estático ampliado de PHP e YAML de produção (excluídos testes e
documentação) encontrou referências específicas ao acervo em `aculta_forum`
(respostas, citações, fila/flag, autoria, movers, auditoria e migrations), em
`aculta_user` (entidade/tabela, manager, access handler, memorial/reserva de
slug, cartões, moderação, fila de denúncia e participação), em `aculta_game`
(elegibilidade de participação), `aculta_messenger` (política, enfileiramento e
consumo de avisos/assinaturas) e `aculta_cgu` (ponte Flagging→denúncia e
`legacy_flag_id`). A ausência de um literal específico nos demais módulos não
prova ausência de acoplamento indireto: eventos e contratos genéricos ainda
precisam ser auditados pelos efeitos que produzem.

O census também separou referências genéricas ao Fórum de referências ao
acervo. `aculta_statistics` consome eventos correntes de atividade sem
classificar proveniência no subscriber; sua integração deve ser validada para
que import/replay histórico não gere métricas. `aculta_messenger` possui
caminhos genéricos para tópicos atuais além das guardas históricas diretas.
`aculta_gallery` e `aculta_document_viewer` referenciam o bundle de anexos do
Fórum para publicação/acesso a arquivos; não há evidência estática de que esse
uso seja específico do arquivo histórico, então permanece no domínio genérico
do Fórum salvo prova em contrário. Referências a `aculta_forum_topic` em
Statistics, User, Messenger, Game, Index, CGU e no próprio Fórum são integração
com o host atual e devem ser avaliadas por contrato/origem; não devem ser
movidas automaticamente para o addon apenas por conter “forum” no nome.

No tema V2, o SDC compartilhado `author-card` declara a variante visual
`author_growroom_historico`; templates, stories e `PagePresentationHooks`
também contêm apresentação do Growroom por rota/bundle. Esses são pontos de
apresentação, não classificadores de origem. Manter o SDC e a variante visual
no tema conforme a UI Suite, mas preparar os dados/política de autoria e
somente-leitura no addon; revisar hooks do tema para que não decidam origem por
bundle nem carreguem regras ou acesso do acervo.

As migrations
Comment→ForumPost atualmente registradas não excluem autoria histórica da
origem. A busca estática não prova se alguma migration foi executada nem
quantos registros a refatoração criou; isso exige census somente leitura no
ambiente autorizado e distinção por provenance. O catálogo do addon e o
classificador central também ainda não existem nesta branch. Referências
indiretas a Flagging, métricas, eventos, rotas Core, config ativa e dados sem
marcador continuam no escopo da revisão.

As instruções normativas sobre imutabilidade, identidade e apresentação do
arquivo devem ter `aculta_forum_legacy` como documento dono. O Fórum pode
referenciar um contrato genérico de origem e uma página de integração, sem
armazenar regras, campos, nomes de rota ou decisões específicas do acervo.

### Contrato mínimo do classificador de origem

O resolver genérico deve aceitar entidade e contexto/host e devolver origem
estável com metadados de cacheabilidade. Esse é um contrato novo, separado de
`DiscussionContextResolver`: o serviço atual apenas escolhe o provider do host
por `supports()`/`getContext()`, retorna `NULL` quando nenhum serve e lança erro
se mais de um provider de host casar. Ele não classifica proveniência. Não
sobrecarregar `DiscussionContextProviderInterface` nem seu tag para guardar
regras do acervo. Definir uma interface/tag própria de origem; somente o addon
conhece fields, classes, rotas e proveniência exclusivos do acervo. O Fórum
mantém os providers correntes e contextuais necessários para continuar
funcionando sem o addon. Evidência conflitante ou insuficiente vira `UNKNOWN`;
um provider corrente não pode inferir `CURRENT` apenas da ausência de marker.

| Caso de entrada | Classificação exigida |
| --- | --- |
| Tópico Growroom cuja proveniência histórica está comprovada pelo addon | `ARCHIVE`; somente leitura histórica, sem resposta, citação comum, reação, mover, fila ordinária CGU ou eventos de participação/notificação. |
| Tópico Growroom comprovadamente corrente | `CURRENT`; novas respostas são ForumPosts. Ausência do marcador histórico, por si só, não prova que um tópico antigo seja corrente. |
| ForumPost proveniente da migration Comment→ForumPost | Se a origem mapeada for histórica, `ARCHIVE` e artefato de refatoração sob auditoria; se source/map/proveniência faltarem ou a origem for outra não aprovada, `UNKNOWN`. Em ambos os casos, restringir ações/eventos e não apresentá-lo como publicação corrente validada. |
| Comment de Wiki, Revista ou LMS com host/contexto reconhecido | `CONTEXTUAL`; manter ACL/política do host, sem entrar no arquivo Growroom ou na busca pública do Fórum. |
| Comment com host desconhecido, inacessível, divergente ou conflitante com a origem própria | `UNKNOWN`; negar escrita, reação, projeção/contagem ampliada e eventos; não inferir pelo UID, data, texto ou bundle isolado. |

O census precisa comprovar qual sinal permite distinguir tópicos históricos
sem `field_autor_legado` e tópicos correntes antes de habilitar o provider de
arquivo. A integração é aceita somente quando core sem addon continua servindo
discussões novas/contextuais, addon com arquivo classifica a origem histórica,
e todos os consumidores consultam o resolver em vez de ler markers diretamente.

### Regra para dados preexistentes e dados produzidos pela refatoração

Autores, tópicos, comentários, relações, URLs e arquivos que pertencem ao
acervo legado preexistente devem continuar existindo. A extração de ownership
para `aculta_forum_legacy` não autoriza apagar nem recriar esses registros.
“Limpar dados da refatoração” significa identificar somente registros ou
artefatos persistidos que foram criados pela própria refatoração; antes de
qualquer remoção, provar sua origem e separá-los do acervo anterior.
Nenhuma exclusão persistente será executada nesta fase de planejamento, e
nenhuma tabela histórica será removida como atalho para a extração. O censo
FDC-11.0 deve registrar essa distinção sem exportar dados pessoais.

Para identificar dados eventualmente criados pela refatoração, o census deve
correlacionar a proveniência das migrations antes de propor limpeza:

| Migration | Evidência de proveniência | Possível efeito da refatoração |
| --- | --- | --- |
| `aculta_forum_comment_posts` | Map/message tables do Migrate: cada CID de origem mapeado para ID de destino `aculta_forum_post`; conferir CID de origem, ID de destino e existência/estado sem expor corpo ou autoria. | Cria ou atualiza ForumPosts a partir de Comments, incluindo dados `legacy_author`. O destination define explicitamente `ROLLBACK_DELETE`; teste Kernel confirma que rollback exclui ForumPosts importados e preserva os Comments origem. |
| `aculta_forum_comment_parents` | Mapa da migration e IDs de destino; compara `parent` atual com a relação derivada dos CIDs de origem/pai. | Atualiza a hierarquia via destination `entity:aculta_forum_post`; o rollback padrão de EntityContentBase também pode excluir destinos mapeados, não apenas limpar `parent`. |
| `aculta_forum_comment_flaggings` | Map/message tables próprios e campo `entity_type/entity_id` dos flaggings mapeados, cruzados com lookup da migration de posts. | Cria flaggings contra ForumPosts destino; destination custom delega ao `EntityContentBase`, cujo rollback padrão exclui destinos mapeados. |

Mapas ausentes, incompletos ou incompatíveis não autorizam inferir origem por
UID, data, texto ou semelhança do conteúdo. Nesses casos, manter os registros
e exigir evidência de clone/backup e revisão antes de qualquer plano reversível.
Não executar `drush migrate:rollback` em lote como atalho: destinos do Migrate
usam `ROLLBACK_DELETE`, e o rollback pode excluir ForumPosts que hoje tenham
edições, filhos ou relações posteriores. Primeiro inventariar revisões,
respostas, flaggings, reações, referências e eventos ligados aos IDs de destino;
provar o comportamento na versão Core efetiva e em clone restaurável. Os
Comments, autores, tópicos, referências e arquivos preexistentes nunca são
alvos dessa limpeza. Core: [EntityContentBase no Drupal 11](https://api.drupal.org/api/drupal/core%21modules%21migrate%21src%21Plugin%21migrate%21destination%21EntityContentBase.php/11.x).

### Inventário a migrar para o addon

- **Dados e autoria:** entidade existente `aculta_legado_autor`, manager/upsert por ID de origem, `field_autor_legado` e referências legadas, configuração de storage/form/display e a propriedade desses contratos. Autores continuam sem conta User/login; `uid=0` e as chaves, IDs, referências e relações existentes devem ser preservados conforme plano aprovado. Os arquivos de autoria são inventariados e preservados, sem mudar seu armazenamento nesta fase. A localização atual em `aculta_user` não torna `aculta_user` dono futuro do legado.
- **Identidade pública histórica:** memorial coletivo único `/u/lendashistoricas`, reserva do slug, cartões e nomes históricos. Todos esses elementos e suas configurações são fornecidos por `aculta_forum_legacy`. Nos tópicos e comentários do fórum legado, a exibição individual deve respeitar o perfil legado definido em F-D44. Não criar página, rota ou listagem individual pública, nem link para perfil/conta User. Manter a convenção `-gr` apenas onde já decidida para cartões do fórum, sem alterar nomes canônicos do memorial.
- **Classificação e integridade:** implementar classificador central por origem/host; guardas read-only; regras de mover/citar/responder; prevenção de reações e eventos colaterais de importação/reconstrução. O plano deve especificar separadamente a política de denúncias/moderação e os eventos correntes permitidos. O addon futuro fornecerá classificação `ARCHIVE` pelo contrato genérico; o core não deve conhecer nomes de campo nem IDs de entidade legados. Origem não reconhecida ou ausência do provider exigido falha fechada.
- **Integrações de leitura:** `aculta_forum_legacy` é dono das projeções, catálogo e pesquisa próprios do arquivo, além do provider da série de métricas históricas. `aculta_index` e `aculta_statistics` permanecem genéricos: oferecem contratos de extensão e infraestrutura de consulta/agregação, sem lógica, configuração ou nomenclatura Growroom embutida. O addon publica seus providers por contratos genéricos; os módulos principais mantêm suas responsabilidades e não dependem do addon.
- **Ferramentas e apresentação:** Views/rotas do memorial coletivo, cartões de autoria e renderização read-only, cache tags/contexts e invalidação, além dos hooks de autoria/atividade que hoje carregam marcador histórico.
- **Migração e auditoria:** ferramentas de census do arquivo pertencem ao addon e são somente leitura por padrão. Comment histórico Growroom não pode virar ForumPost. Eventual conversão de Comments correntes é decisão e escopo separados, com filtro comprovado que exclui `ARCHIVE`, prévia/dry-run, aprovação operacional e plano de reversão; não presumir que a decisão sobre o acervo histórico proíba ou autorize migrar outros Comments.

### O que permanece no motor e nos adapters correntes

`aculta_forum` mantém ForumPost revisionável para novas publicações Growroom e Comments contextuais de Wiki, Revista e LMS, API de hosts, reações correntes, moderação autorizada, cache e contratos neutros. O core fornece pontos de extensão para classificação/origem, apresentação de autoria, catálogo e métricas; sem addon, origens não suportadas não recebem escrita nem leitura ampliada.

Não mover para `aculta_forum_legacy` os serviços atuais de User, associação, avatar/perfil de conta, participação e janela de edição. Para portabilidade além do ACULTA, esses serviços devem ser opcionais e acessados por contratos/adapter de integração ACULTA (nome a definir). O addon histórico não deve virar dependência oculta de `aculta_user`.

O requisito “portável a outros projetos” exige auditoria adicional do manifesto completo: dependências ACULTA não históricas, serviços obrigatórios e configuração de instalação do `aculta_forum`. Remover só `aculta_user` não prova portabilidade. Dependências funcionais necessárias ao motor devem ser declaradas; integrações opcionais usam providers/serviços opcionais e configuração que só é instalada quando os módulos correspondentes estão presentes. Não instalar configuração que aponta para plugins/entidades ausentes.

### Sequência planejada e critérios de aceite

1. **FDC-11.0 — Census somente leitura:** inventariar tipos/bundles/fields/rotas/Views/config, tabelas, referências de autoria, flags, Search API, eventos/consumidores e caches; incluir IDs/histórico de execução das migrations, map tables, ForumPosts e flaggings de destino com provenance agregada; reportar contagens sem exportar PII. Distinguir registros anteriores à refatoração de registros que ela eventualmente criou. Mapear cada referência em `aculta_forum`, `aculta_portal`, `aculta_user`, CGU, Index, Statistics, Messenger, Game e outros consumidores. Para cada integração, registrar owner atual, contrato de destino e arquivo futuro no addon. Não executar rebuild, import, sync, update, uninstall ou limpeza.
2. **FDC-11.1 — Contratos e matriz de instalação:** definir interfaces/providers genéricos e testar os módulos principais sem o addon, o addon instalado e consumidores opcionais ausentes. Provar que os módulos principais não carregam código/config do addon e que o manifesto só declara a dependência unidirecional do addon para as APIs necessárias. Provar que novas respostas Growroom usam ForumPost; Wiki/Revista/LMS preservam Comment contextual e seus grants; origem desconhecida falha fechada; arquivo fora das filas ordinárias do CGU e exceção somente por protocolo autorizado/auditável; arquivo e importações/reconstruções não geram aviso, assinatura, XP, participação ou perfil corrente; autoria histórica continua fora de contas/perfis `aculta_user`.
3. **FDC-11.2 — Extração de código/config:** criar o módulo opcional e mover para ele toda semântica, configuração, rotas, ferramentas read-only, projeções e providers específicos do acervo. Remover/substituir referências diretas nos principais a `aculta_legado_autor`, `field_autor_legado`, `legacy_author`, classes, IDs de config/rotas e equivalentes. Corrigir o escopo das migrations para que Comment `ARCHIVE` nunca entre em ForumPost/parent/flagging; qualquer migração de Comments correntes exige escopo e autorização próprios. Módulos consumidores integram-se pelo resolver genérico, sem depender do addon. Nenhum comportamento histórico pode depender de `aculta_user` nem tornar addon obrigatório para `aculta_forum`/`aculta_portal`.
4. **FDC-11.3 — Dados e ownership:** primeiro reproduzir a extração em clone restaurável, comparar contagens, entity IDs, UUIDs, source IDs, chaves, autoria, árvore/ordem, URLs, timestamps, idiomas, avatares (quantidade, checksums, paths, referências e render) antes/depois; verificar acesso anônimo/autenticado/moderador. Preservar conteúdo e bytes. Preservar o ID `aculta_legado_autor`, a tabela/base storage, as referências existentes e os arquivos de autoria. Manter entity type ID e nome da tabela, por si só, **não** prova transferência segura: Drupal registra definições instaladas e provider de entity/field storage, e o Core desinstala entity types/field storages cujo provider ainda seja o módulo removido. A lista de mudanças do `EntityDefinitionUpdateManager` detecta mudanças de schema, mas documenta que ainda não cobre todas as mudanças sem impacto de schema. Por isso, o clone precisa validar explicitamente a transferência de provider da entidade e dos fields/config (`field_autor_legado`) sem recriar/remover tabelas, purgar campos nem alterar UUIDs; conferir as definições instaladas e o comportamento do uninstall validator antes/depois. A mudança de módulo dono de entity/field/config e a cópia de arquivos são operações estruturais: requerem clone restaurável, plano de reversão, backup, análise das definições e autorização operacional específica. Nunca desinstalar `aculta_user` para “transferir” storage. Referências do Core: [ModuleInstaller 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21ModuleInstaller.php/class/ModuleInstaller/11.x) e [EntityDefinitionUpdateManager::getChangeList 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityDefinitionUpdateManager.php/function/EntityDefinitionUpdateManager%3A%3AgetChangeList/11.x).
5. **FDC-11.4 — Projeções separadas:** implementar em `aculta_forum_legacy` o provider do catálogo/pesquisa próprios do arquivo e o provider da série intitulada **“Estatísticas Históricas da Restauração do Growroom”**. `aculta_index` e `aculta_statistics` fornecem apenas as APIs genéricas de extensão, consulta e agregação; não recebem lógica, configuração ou nomenclatura histórica específica. O catálogo e a pesquisa devem ser ACL-aware e isolados do índice/pesquisa do fórum atual; a série histórica não pode ser somada nem duplicada nas métricas correntes. Provar escopos, regras de acesso e separação das contagens. Reações históricas são sempre ocultas e recusadas. Fazer census de eventuais rows de reação sem apagar ou corrigir dados. Não rodar rebuild de índice nem reconstrução de métricas sem autorização operacional.
6. **FDC-11.5 — Autoria e UX:** validar a apresentação individual nos tópicos e comentários históricos conforme o perfil legado e preservar o memorial coletivo `/u/lendashistoricas` como única página pública de memória. Não criar link, rota, perfil ou listagem individual; impedir colisão/resolução acidental com `/u/{apelido}` e `/user/{uid}`. A regra de produto é F-D44; F-D47 foi invalidada e não deve ser usada como requisito.
7. **FDC-11.6 — Fechamento:** varrer dependências diretas/indiretas e comprovar que módulos principais não contêm código ou configuração específica do importador nem dependem do addon; validar reinstalação limpa com/sem addon, atualização em clone, ACL/cache, rotas, CGU, providers de Index/Statistics, Messenger/Game e UI read-only via UI Suite existente. Só então propor mudança de fase/status e publicar resultados observados.

**Regra de instalação:** “opcional” significa que novos projetos podem não instalar o addon. Não significa que seja seguro desinstalá-lo de uma instalação com arquivo: uninstall pode remover configuração, fields ou schema. O Core 11 fornece `ModuleUninstallValidatorInterface`, registrado como serviço com a tag `module_install.uninstall_validator`; a documentação diz que os validators participam do uninstall e da validação anterior ao `config:import`. O FDC-11.2 deverá implementar validator no addon que bloqueie a operação quando houver autores, conteúdo/referências, fields/storage, configuração ou arquivos sob ownership legado, e apresentar motivo acionável. Testar a validação pela tela de uninstall e pelo caminho de import de configuração, sem executar esses fluxos contra Runtime compartilhado. O validator é uma guarda, não autorização de limpeza nem prova que remover o código do módulo é seguro: não retirar/distribuir o código do addon enquanto seus dados, referências ou schema ainda dependerem dele. Até existir plano específico aprovado para desativação, desabilitar apresentação não equivale a desinstalar o fornecedor de dados. Nenhuma migração de storage, `config:import`, `updatedb`, Composer, rebuild ou operação persistente fica autorizada por este planejamento. Consulte [ModuleUninstallValidatorInterface no Drupal 11](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21ModuleUninstallValidatorInterface.php/interface/ModuleUninstallValidatorInterface/11.x) e [hook_uninstall no Drupal 11](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_uninstall/main).

## UI Suite: contrato vinculante

- **UI Patterns Views**: `forum-topic-row` e `forum-category` como row plugins nos displays reais; `forum-list-page`, `forum-category-page`, `forum-search-page` compõem Header/filters/items/empty/pager/sidebar via slots; não colar campos manualmente quando mapeamento disponível. `views.view.aculta_forum_categories.yml` possui displays de row `fields` e `ui_patterns`; migrar sem perder filtros/grants/pagers/ordem.
- **UI Patterns Layouts/Layout Builder**: usar `layout-page`, `layout-grid`, `layout-two-columns` e `layout-section` já presentes, semanticamente; configurar layout da lista, tópico e conteúdo contextual sem criar segundo `main` nem menus repetidos.
- **UI Patterns Field Group**: agrupar metadados de tópico, autor, ações e estados de moderação nos displays; avaliar versão dev conforme ADR-055 e fixar API instalada. Não usar no Field Group para substituir validação Entity Access.
- **UI Skins + UI Styles**: skins semânticas/escopo visual e modos claro/escuro; UI Styles para superfície, acento lateral esquerdo/direito, espelhado, prioridade de alerta e densidade da thread. Não criar `purpose-palette`.
- **UI Icons, UI Icons for Menu, UI Icons for UI Patterns, UI Icons Picker**: Phosphor e `aculta brocolis` via biblioteca; os rótulos ARIA/status devem existir sem ícone; evitar SVG manual em lógica de módulo.
- **UI Patterns Library**: todos SDCs do Fórum permanecem catalogados (internos reutilizáveis e editoriais), story default, mobile, light/dark, moderado, excluído, vazio e erro; botão de exibir todas as prévias sem render massivo inicial.
- **Code boundary**: contratos correntes no Fórum; dados e políticas do arquivo em `aculta_forum_legacy`; identidade corrente em adapters opcionais do projeto; design no tema. O componente não consulta banco, não decide acesso e não gera link externo inseguro.

## Escopo CGU e serviço universal de moderação

O formulário de resposta e a ação Denunciar são APIs do Fórum, o fluxo de denúncia persistido é CGU. A rota do MAIN `/painel-administrativo/moderacao/denuncias` é central; /painel-administrativo/forum pode apontar para o filtro `purpose=forum` sem criar fila. Preservar os processos de revisão editorial `Content Moderation` e `LmsForumModerationManager` como adapters decisores/autoridades locais, não componentes concorrentes de denúncias. Tratar pedido de exclusão pelo autor como operação distinta de denúncia de terceiros, mas coordenar visibilidade e auditoria no CGU.

## Governança de execução

Os pacotes FDC-00/01/02/04/11 exigem revisão independente de segurança. FDC-03, FDC-11.3 e mudanças em DB/config/Composer exigem plano com backup, dry-run e autorização operacional separada, segundo AGENTS.md; publicação documental não autoriza migração. Abrir checklists por pacote, marcar `implementado` apenas com commits e `homologado` apenas com resultados reais. Atualizar `README.md`, `TODO.md`, `docs/operations/status/*` no mesmo commit **quando uma fase alterar status efetivo**, não nesta entrega documental.
