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

F-D44 fixa `aculta_legado_autor` como a entidade canônica de autoria, sem
User/Profile ou rota individual; a extração transfere seu ownership ao addon e
preserva o ID da entidade e o contrato da tabela/base storage. A implementação
atual está em `aculta_user`, com `origem_id` e `nome`, e `field_autor_legado`
aponta para ela. A entidade foi introduzida em 16/10; `LegacyAuthorManager::upsert()`
está registrado como serviço, mas não tem chamada de produção no código
versionado. `scripts/growroom/build_mapping.py` gera mapas de origem fora do Git
a partir do corpus. A transferência preserva a definição e os vínculos que forem
criados pela futura importação; não presume linhas históricas já importadas no
Runtime. Não transformar autores em User/Profile, login, papéis, permissões de
conta, amizade, mensagens ou participação. A apresentação inline segue F-D44 e
o memorial coletivo continua como único destino público de memória. Classes,
controllers e serviços atuais em `aculta_user` são acoplamentos a extrair, não
implementação já concluída no addon.

**Estado do acervo no Runtime alvo, informado pelo responsável em 2026-11-19:**
a importação de Growroom ainda não foi feita e não há autores, tópicos ou
comentários históricos importados no Runtime alvo. O corpus/manifests de origem
e as definições de código/configuração não são registros históricos importados.
As fases deste plano devem partir desse baseline vazio de conteúdo legado e
confirmá-lo no censo; não presumir que exista arquivo histórico no banco.

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

### Baseline anterior à implementação Dev.2 — 2026-11-19 (census estático, não homologado)

Os bullets e a tabela abaixo registram a fotografia inicial anterior à criação
do addon. Eles não descrevem o HEAD atual; o delta implementado aparece após
esse baseline. A importação histórica continua não executada.

- **Na baseline anterior a Dev.2, o addon ainda não estava implementado.** Na árvore atual há módulo/manifesto, serviços e provider de origem. Autoria, fields, memorial, apresentação e integrações seguem parcialmente acoplados a `aculta_user`/`aculta_forum`.
- **Na baseline anterior a Dev.2, não havia classificador central de origem.** A implementação atual inclui contratos neutros no motor e provider opcional que reconhece `ARCHIVE`; a cobertura não removeu todos os acoplamentos listados no census.
- **O ownership atual continua em `aculta_user` e `aculta_forum`.** `aculta_user` contém `Entity\LegacyAuthor`, a tabela/type `aculta_legado_autor`, manager, handler de acesso, memorial, apresentação e hooks de autoria/moderação. `aculta_forum` contém os fields, verificações de marcador, migrações e ferramentas relacionadas, inclusive as referências listadas no census abaixo. Não tratar a fronteira pretendida como já extraída.
- `config/sync` contém storage/instâncias de `field_autor_legado` e displays de ForumPost que mencionam `legacy_author`; não existe configuração de addon no sync encontrada nesta branch. Esses arquivos/configurações são candidatos distintos de dados persistidos e exigem comparação por UUID/ID e dependências em clone.
- As três migrations Comment→ForumPost estão registradas e executáveis pelo sistema Migrate, mas o responsável confirma que a importação histórica não foi executada no Runtime alvo. Portanto, ForumPosts/Flaggings históricos importados não são baseline existente nem devem ser presumidos. O censo somente leitura confirmará status/mapas vazios ou ausência de execução e distinguirá essa configuração/código de quaisquer resíduos de teste/refatoração. Não tocar no corpus fonte.
- Esta revisão foi somente leitura da árvore Git: não consultou o banco Runtime e não executou Drush, import, update, migration, uninstall, rebuild ou limpeza.

### Delta implementado em Dev.2–Dev.3 — 2026-11-19 (código local; sem Runtime)

- `aculta_forum_legacy` existe como addon opcional e declara dependência somente de `aculta_forum`; os demais módulos não dependem dele.
- O motor fornece API central de classificação; o addon registra provider `ARCHIVE`. ForumPosts correntes, Comments contextuais e origem desconhecida têm regras próprias e testes focalizados.
- Respostas a tópicos correntes usam o formulário revisionável `ForumPostSubmissionForm`. Game e Messenger consultam o resolver central.
- A exclusão do arquivo Growroom das filas ordinárias de moderação foi movida para o addon. A política genérica de denúncias permaneceu no Fórum, mas agora usa o resolver central e falha fechada para origem diferente de `CURRENT`.
- Autoria/fields, memorial coletivo, apresentação histórica, ferramentas Migrate/auditoria e demais acoplamentos continuam pendentes de extração. Nenhuma importação, migração, configuração ou operação de dados foi executada no Runtime.

### Auditoria estática preliminar dos acoplamentos — 2026-11-19

Esta busca cobre código/configuração PHP, YAML e Twig versionados nesta worktree;
não substitui o census de Runtime/config ativo do FDC-11.0. Achados que devem
orientar a extração e a revisão:

| Área | Evidência atual | Destino/critério do plano |
| --- | --- | --- |
| Configuração de fields | `field.storage.*.field_autor_legado` e `field.field.*.field_autor_legado` continuam em `config/sync`; defaults relacionados existem em `aculta_forum` e `aculta_user`. Não há defaults do addon porque o módulo ainda não existe na branch. | Comparar config ativa, sync e defaults por UUID/ID/dependências em clone restaurável. A transferência ao futuro addon não prova ownership seguro nem autoriza import; provar instalação/atualização preservando storage, valores e dependências antes de remover definições antigas. |
| Resíduo de refatoração em ForumPost/docs | O `ForumPost` atual não declara `legacy_author`, mas quatro displays de ForumPost no config sync ainda o listam como hidden. Documentos/roadmap de Forum e User também descrevem `ForumPost.legacy_author` ou autoria histórica no post revisionável, em conflito com a política atual de não converter Comments históricos em ForumPosts. | Tratar a chave de display e instruções normativas obsoletas como artefatos da refatoração, não como registros do acervo. Antes de removê-las, confirmar em config/schema/testes que não existe field atual nem dado histórico armazenado ali. Concentrar a política normativa e a semântica histórica em `aculta_forum_legacy`; manter changelog histórico apenas como registro datado e explicitamente supersedido, sem guiar implementação atual. |
| Documentação e ferramentas de migração/refatoração | `docs/OWNERSHIP.md` ainda liga autoria histórica a `aculta_user` e cita fila/hook antigo; `REVISIONABLE-DISCUSSION-WORKFLOW.md` e seções do `ROADMAP.md` descrevem `ForumPost.legacy_author` e conversão Comment→ForumPost. No código do Fórum há auditoria Drush/Migration e plugins source/destination que conhecem os campos/autores legados. `MIGRATION-REMOVAL.md` é auditoria histórica da retirada do Drupal Forum contrib e inclui decisões de limpeza antigas. | Transferir política normativa e ferramentas read-only do acervo para a documentação/addon; atualizar ownership e roadmap atuais para não prescrever conversão nem dependência de User. Provar quais comandos/plugins foram executados e se criaram registros da refatoração. Conversores Comment→ForumPost ficam fora do fluxo executável; só propor sua retirada depois de verificar uso e dependências. Preservar auditorias/changelogs como evidência datada, marcando trechos superados sem apagar histórico. Distinguir retirada do contrib da extração do acervo Growroom. |
| Acoplamentos diretos no Fórum | Não há classificador genérico de origem no código PHP. Uma busca estática em `src/` encontrou referências legadas em `DiscussionReplyController`, `DiscussionReplyAccess`, `ForumQuoteHooks`, `ForumHistoricModerationQueueHooks`, `HistoricForumFlagAccessHooks`, `PublicForumLastActivityAuthorHooks`, `ForumPostMigrationFieldHooks`, `ForumCommentMigrationAudit`, `ForumCommentMigrationAuditCommand`, `ForumCommentPost`, `ForumComment` (source Migrate), `ForumCommentFlagging` (source/destination), `ForumFlaggingMigrationContext`, `ForumCommentMover` e `ForumTopicMover`. Há leitura direta de `field_autor_legado`, acesso à entidade `aculta_legado_autor`, apresentação de autoria, exclusões de fila/flag, auditoria e suporte a conversão. | São acoplamentos reais que contradizem a fronteira pretendida, não prova de que o core já esteja desacoplado. No FDC-11.2, classificar cada arquivo como semântica de arquivo a mover para o addon, adapter a substituir por contrato genérico, ou ferramenta de conversão/refatoração a retirar. O acervo ainda não foi importado no Runtime alvo; preservar corpus/manifests fonte e não tratar os módulos como se já tivessem dados históricos persistidos. Cobrir rotas e consumidores antes de remover os marcadores diretos. |
| Migrações de Comment para ForumPost | `migrations/aculta_forum_comment_posts.yml`, `aculta_forum_comment_parents.yml` e `aculta_forum_comment_flaggings.yml` estão registradas no módulo. A source filtra tipo/campo/host Forum e Domain, mas não `field_autor_legado`, status publicado nem rejeição; o destino cria ForumPosts e transporta `legacy_author`; as migrations seguintes recriam parent/child e flaggings no alvo ForumPost. Os destinos estendem `EntityContentBase`; no Core 11, o `save()` herdado chama `setSyncing(TRUE)`. | A inclusão do acervo histórico conflita com a decisão vigente: Comments Growroom históricos nunca podem ser convertidos, ter sua árvore refeita ou suas flags transferidas. Nenhum import é autorizado nesta fase. O plano deve restringir a origem por classificador central fail-closed; eventual migração de Comments correntes precisa ser escopo separado e aprovado. O estado `isSyncing` suprime eventos de atividade nos hooks atuais do Fórum e os eventos de publicação do Messenger para Nodes, mas não substitui teste de todos os consumers nem supressão CGU. Procurar execuções/mapas antes de qualquer alteração. Se a refatoração criou ForumPosts/flagging, identificar somente esses destinos e removê-los em plano reversível autorizado; preservar integralmente Comments/autores/tópicos/flags históricas de origem. |
| Acoplamentos em User, Game e Messenger | `aculta_user` ainda define a entidade/tabela `aculta_legado_autor`, manager, access handler, nome de exibição, rota MAIN `/u/lendashistoricas`, memorial coletivo, reserva literal do slug em `AccountNames::RESERVED`, cartões, hooks de autoria/moderação, `ReportQueue` e exceção em `ParticipationRules`. A varredura também encontrou integração genérica por bundle em `ParticipationRules::publishedPublicForumCommentCount`, `hasPublishedDiscussionComment`, `ActivityController` (listas de tópicos/comentários/posts), `ParticipationHooks`, `NodeCoAuthorHooks`, `CoAuthorSettingsForm`/`CoAuthorAdminController` e `AuthorCardVariantResolver`; não classificam todos os itens por proveniência. Em particular, `hasPublishedDiscussionComment()` conta ForumPosts publicados por UID sem filtrar origem, e `publishedPublicForumCommentCount()` conta Comments publicados sem excluir autoria histórica; `isSyncing` não protege essas consultas posteriores à gravação. `aculta_game::ForumParticipationPolicy` bloqueia marcadores legados e entidades sincronizadas no caminho de concessão de conquista; `aculta_messenger::ForumNoticeAccessPolicy`, `MessageSubscribeEventHooks` e `MessageSubscribeEventWorker` verificam o campo, enquanto outros caminhos do Messenger reconhecem o bundle do tópico corrente. | Mover entidade/config/rota/memorial/apresentação e regras exclusivamente históricas para o addon. A reserva do slug deve usar contrato genérico de slugs reservados, com o addon contribuindo `lendashistoricas`; não deixar literal legado em `aculta_user`. Manter recursos correntes de conta, coautoria, atividade e participação no domínio/adapter ACULTA, mas substituir verificações históricas por consulta ao contrato genérico de origem. Para cada consulta por bundle **e para cada agregação derivada por UID**, provar se precisa incluir `CURRENT`, excluir `ARCHIVE`/`UNKNOWN` ou tratar contexto próprio; UID e bundle sozinhos não provam origem. Game, Messenger, User e demais consumidores principais não devem ler nomes de field nem classes do addon. Preservar a política: importação/reconstrução não gera participação, avisos, assinaturas, XP ou conquistas e autores históricos não viram perfil/conta corrente; testar tanto efeitos imediatos de criação quanto consultas/agregações posteriores. |
| Classificação de origem | Não foram encontrados `ForumDiscussionOriginPolicy`, `CurrentDiscussionOriginClassifier` ou provider `aculta_forum_legacy` em PHP. `DiscussionContextResolver` resolve hosts de discussão; `ForumAnalyticsScopeResolver` trata escopo analítico. Ambos são contratos diferentes de classificar legado/corrente/contextual. O histórico ainda é detectado por campos/classes diretamente. | Projetar e testar um resolver central genérico para `ARCHIVE`, `CURRENT`, `CONTEXTUAL` e `UNKNOWN`; provider de arquivo pertence ao addon opcional. Testar addon ausente/presente, marker ausente, precedência, host cruzado e saída inválida. Falhas de origem para escrita/eventos devem ser fechadas. Views, Search API e agregadores ainda precisam consumir a classificação. |
| Views e navegação | Views públicas/administrativas filtram `node:aculta_forum_topic` por bundle/status; `PublicForumListViewHooks` calcula contagens por bundle/categoria. Esses caminhos não filtram a origem histórica por si mesmos. | Separar listagem atual, filas e contagens do catálogo histórico. Aplicar filtro de origem por provider em Views/query/access e nas contagens auxiliares; preservar rota/canonical de leitura histórica e grants. Só usar marker após o census provar cobertura; não presumir que todo registro possui marker. |
| Index/Search | `ForumContentIndexProvider::isIndexable()` aceita tópicos do bundle, ForumPosts default e Comments `aculta_discussion_post` do host tópico; não consulta origem nem `field_autor_legado`. O texto descritivo chama isso de indexação durante migração. Portanto o índice corrente pode misturar origem histórica e corrente se dados marcados existirem. | Não executar rebuild. Testar filtros de origem e acesso direto; excluir `ARCHIVE`/`UNKNOWN` do índice corrente usando classificador genérico. O addon futuro deve prover catálogo histórico separado, ACL-aware, sem depender do Search público corrente. |
| CGU e sinalizações | O acoplamento é dividido entre `aculta_forum/src/Hook/ForumReportCguBridgeHooks.php` (cria/remove denúncia ao criar/remover Flagging e grava `legacy_flag_id`/`sinalizacao_legada_forum`), `aculta_cgu/src/Service/CguReportQueue.php` (reconcilia flags, evita duplicatas e oferece migração/rollback) e `aculta_cgu/src/Entity/CguReport.php` (campo `legacy_flag_id`). A ponte de inserção classifica ForumPost apenas por entity type, não pela origem nem por `isSyncing`. `ForumCommentFlagging` salva a flag de destino `sinalizar_post_forum`; o `presave` herdado de Migrate é autorizado para aquela instância pelo `ForumFlaggingMigrationContext`, mas esse contexto não silencia o hook `flagging_insert` do CGU. Assim, apesar de `EntityContentBase` marcar a Flagging como sincronizada e os hooks atuais do Fórum/Messenger suprimirem parte dos eventos de importação, uma flag migrada com owner válido ainda pode criar denúncia CGU. É risco estático, não evidência de execução em Runtime. | Identificar cada Flagging por alvo e proveniência: acervo histórico, conteúdo corrente ou origem desconhecida. O arquivo fica fora das filas ordinárias; exceções exigem protocolo específico, auditável e autorizado. CGU conserva a propriedade genérica de denúncias, casos e decisões; o addon fornece integração histórica por contrato/adaptador e não passa a ser dependência de CGU. Não executar nem reconciliar migrations nesta fase. Antes de qualquer migração corrente aprovada, provar exclusão de `ARCHIVE`/`UNKNOWN`, definir como evitar que eventos técnicos gerem denúncias/notificações e testar que import/replay cria zero caso/denúncia sem ação autorizada. Em etapa futura, remover a ponte somente após mapear referências/relatórios já persistidos e provar compatibilidade sem perda da auditoria. |
| Estatísticas | Existe `MetricProviderInterface` no Portal e a tag `aculta_statistics.metric_provider`; o `MetricProviderCollector` ignora métricas que `MetricCatalog::has()` não reconhece, e `MetricCatalog::all()` atualmente mantém lista fixa sem provider de definições. Portanto, o contrato atual coleta valores de métricas cadastradas, mas não permite que o addon declare sua série histórica. `AuthorizedEntityMetricAggregator` valida bundle/permissão para `forum.replies.created`, mas não consulta origem. `ForumActivityEventHooks` recusa entidades `isSyncing`; o destino Core `EntityContentBase` marca saves de migration como sincronizados, cobrindo a emissão de atividade dos ForumPosts desse destino. Isso não cobre automaticamente outros importers, hooks diretos nem dados já recontados. | Reutilizar o provider genérico de valores e projetar uma extensão genérica do catálogo para definições de métricas de módulos opcionais; ela deve validar colisões de ID, tipo, sensibilidade, retenção, dimensões e autorização de consulta sem adicionar nomenclatura Growroom ao Statistics. O addon registra a série histórica somente se habilitado. Validar cada candidato por origem antes do drilldown. Testar que destinations correntes usam `isSyncing`/contexto de importação e que a migration de flagging não emite métrica ou evento CGU. Separar séries histórica/corrente, evitar dupla contagem e não reconstruir nesta fase. |
| Catálogo histórico | `LegacyMemorialController` em `aculta_user` busca/pagina nomes de autores; não foi encontrado catálogo ou busca de texto de tópicos/respostas no arquivo. O `ForumContentIndexProvider` corrente não exige origem `CURRENT` e não é substituto seguro para um índice histórico separado. | Especificar índice/catálogo no addon com ACL, hierarquia, URLs e busca de tópicos/Comments históricos. Excluir origem ARCHIVE do índice corrente e contextual do arquivo. Sem rebuild até autorização operacional. |
| Imagens e documentação de importação | `aculta_image/docs/IMPORTACAO-GROWROOM.md`, `AUDITORIA-GROWROOM-2026-10-19.md` e `HANDOFF.md` registram seleção, caminhos do corpus, estado/contagens de captura, mapeamento de imagem/link, aprovações pendentes e autoria F-D44. `aculta_image` também fornece o ingester, deduplicação, quarentena e origem de arquivo como capacidades genéricas; `docs/architecture/avatar.md` documenta builders históricos em `aculta_user`. | Mover ownership normativo da importação, estado do corpus e regras específicas de imagem/link/autoria para `aculta_forum_legacy`; deixar `aculta_image` como serviço genérico de mídia e expor adapter/API neutra para o addon. Manter decisões de imagem comuns, ingestão e armazenamento com `aculta_image`. Preservar os documentos antigos como evidência datada, marcando-os como supersedidos e apontando ao dono atual após a extração; manter `avatar.md` genérico e referenciar a política do addon em vez de duplicá-la. Não mover nem apagar corpus, imagens ou manifests durante a documentação. |
| Autoria e UI | `aculta_user::DiscussionCommentAuthorHooks` renderiza cartão legado sempre que um Comment `aculta_discussion_post` tem `field_autor_legado`; em hosts diferentes de `author_forum`, chama `buildLegacy()`. `aculta_magazine/docs/README.md` descreve autoria vinculada ao acervo no Comment editorial `revista`. O hook também renderiza `legacy_author` em `aculta_forum_post`, entidade de publicação corrente, embora ForumPost não seja conteúdo histórico autorizado por F-D44. Isso pode expor autoria Growroom fora de tópicos/comentários do arquivo. `AuthorCardBuilder` e o SDC `author-card` declaram o cartão/variante históricos no User/tema. | Mover classificação, nome de exibição e decisão de renderizar autoria histórica ao addon. Exibir o cartão somente quando tópico/comentário for classificado `ARCHIVE` dentro do Fórum legado; um marker isolado em Wiki, Revista, LMS, ForumPost ou host desconhecido não pode expor `LegacyAuthor`. ForumPosts correntes usam identidade de User. Qualquer `legacy_author` persistido em ForumPost é candidato de refatoração somente se a provenance da migration o provar. Manter o SDC e a variante visual reutilizáveis, mas sem política de origem no tema. Atualizar `aculta_magazine/docs/README.md` e cobrir testes de host cruzado para que F-D44 só se aplique a tópicos e comentários do arquivo. |
| Dependências e testes de ownership | Não há manifesto do addon para auditar. `aculta_forum.info.yml` declara `aculta_user` como dependência obrigatória, além de serviços atuais de identidade/participação; `aculta_user` guarda hoje entity/schema/rota históricos, embora seu `.info.yml` não declare dependência do Fórum. `AcultaUserInstallRollbackKernelTest` lista `aculta_legado_autor` como tabela/entity de User e espera que uninstall de `aculta_user` a remova. | Não remover em bloco a integração de identidade ACULTA corrente. Separar no census dependências correntes necessárias de serviços/fields legados; substituir apenas a parte corrente por adapter opcional quando requerido por portabilidade e mover ownership histórico para addon. Rever o teste de rollback de User para enumerar só o storage que continuar pertencendo a User. Criar testes do addon para transferir o provider/ownership da entidade e dos fields existentes, preservando ID e contrato de storage, confirmar baseline vazio no Runtime atual e bloquear uninstall após futura importação; provar também a matriz sem `aculta_user` e com addon ausente/presente. Manifestos e service containers dos módulos principais não podem exigir o addon. |

### Census de fixtures e testes acoplados — HEAD publicado `65c1f8145`

Uma busca textual versionada por `aculta_legado_autor`, `field_autor_legado`,
`legacy_author`, hooks de fila/Flagging histórica e `legacy_flag_id` encontrou
28 fixtures/testes em cinco módulos. É um inventário de arquivos candidatos a
revisão, não uma decisão de mover cada suíte inteira:

- **`aculta_forum` (14):** fixture `tests/modules/aculta_forum_post_test/aculta_forum_post_test.module`;
  Kernel `ForumCommentMigrationBatchKernelTest`,
  `ForumCommentMigrationDestinationKernelTest`,
  `ForumCommentMigrationSourceKernelTest`, `ForumCommentMoverKernelTest`,
  `ForumPostParticipationPolicyKernelTest`, `ForumTopicMoverKernelTest`,
  `PublicForumLastActivityAuthorKernelTest`; Unit `DiscussionReplyAccessTest`,
  `ForumHistoricModerationQueueHooksTest`, `ForumTopicDisplayConfigTest`,
  `ForumTopicMoverTest`, `HistoricForumFlagAccessHooksTest` e
  `Hook/ForumPostMigrationFieldHooksTest`.
- **`aculta_cgu` (2):** `CguNotifierAndQueueKernelTest` e
  `ForumCguIntegrationKernelTest`.
- **`aculta_game` (3):** `BadgeEvaluationForumPostKernelTest`,
  `BadgeEvaluationHooksTest` e `ForumParticipationPolicyTest`.
- **`aculta_messenger` (1):** `AvisoRulesTest`.
- **`aculta_user` (8):** Kernel `AcultaUserInstallRollbackKernelTest`,
  `ParticipationRulesCharacterizationKernelTest`,
  `PublicDiscussionModerationKernelTest` e
  `SharedParticipationIsolationKernelTest`; Unit `ForumModerationHooksTest`,
  `Moderation/ReportQueueTest`, `ParticipationRulesTest` e
  `PublicForumTopicAuthorHooksTest`.

No FDC-11.2, separar os testes de importação, autoria histórica, fila/Flagging
histórica e classificação de arquivo para `aculta_forum_legacy`. Os módulos
correntes mantêm testes de Game, Messenger, User e CGU para seus contratos
próprios, mas substituem fixtures que escrevem `field_autor_legado`,
`legacy_author`, IDs de entidade ou tabelas de autoria por providers/classifiers
genéricos de teste (`CURRENT`, `CONTEXTUAL`, `ARCHIVE`, `UNKNOWN`). Os testes do
CGU preservam cobertura de denúncias/casos genéricos; cobertura de backfill e
reconciliação de Flagging histórico pertence ao adapter do addon. A matriz deve
provar os consumers sem addon e com addon, sem depender de tabela/field legado
nos testes do motor ou dos módulos correntes.

A integração CGU precisa preservar a fronteira de ownership da F-D48: o
`aculta_cgu` conserva denúncia, caso, decisão, auditoria e ACL genéricos; o
backfill, a deduplicação e o rollback do antigo Flagging ficam em adapter ou
ferramenta de `aculta_forum_legacy`. `CguReport.legacy_flag_id` e o motivo
`sinalizacao_legada_forum` são compatibilidade específica que não pode continuar
como regra de domínio no CGU. Se denúncias correntes ainda precisarem de
idempotência por origem, projetar um vínculo genérico provider/source ID e
transferir os registros existentes em clone sem perder relatórios ou decisões.
A bridge corrente deve classificar o alvo via contrato neutro antes de criar
qualquer denúncia: `ARCHIVE` segue o protocolo excepcional aprovado,
`UNKNOWN` falha fechado e `CURRENT`/`CONTEXTUAL` respeitam seus donos locais.
Revisar também `aculta_cgu/docs/CONTRATO-INTEGRACAO-FORUM-CGU-2026-11-19.md`;
a retirada de campo/motivo ou mudança de storage requer censo, plano de
reversão e autorização próprios.

O acoplamento do Fórum a `aculta_user` deve ser decomposto por responsabilidade,
não removido em bloco: `ForumThemeHooks` injeta `avatar_resolver` e
`author_card_builder` para autoria User corrente; `ForumPublicationTimeHooks`
usa `participation_rules`; serviços de acesso restrito usam `active_associate`;
`LmsEditWindowExemption` é provider corrente registrado na extensão
`aculta_user.edit_window_exemption`. Esses contratos dizem respeito a identidade,
associação, LMS e participação ACULTA e não migram para o addon histórico. Em
contraste, `PublicForumLastActivityAuthorHooks` usa o mesmo `AuthorCardBuilder`
para carregar `aculta_legado_autor` e renderizar `buildLegacyForum()`; essa
ramificação e sua leitura histórica devem migrar ao addon, mantendo no core um
slot/provider genérico de autoria de atividade. `ForumAdminOverviewController`
também aponta para `aculta_user.denuncias_forum`; esse caminho deve ser
reavaliado no FDC-04 para apontar ao owner CGU, sem confundi-lo com autoria
legada. A portabilidade de `aculta_forum` requer testar a ausência de
`aculta_forum_legacy` separadamente da eventual ausência de adapters ACULTA de
User: o primeiro caso é obrigatório para F-D48; o segundo é uma etapa de
portabilidade adicional, não condição para redefinir a propriedade do legado.

A leitura estática dos manifests confirma que `aculta_messenger` depende de
`aculta_user` e `aculta_forum`; `aculta_magazine`, `aculta_wiki` e `aculta_lms`
também declaram dependências dos módulos correntes `aculta_user` e
`aculta_forum`. Esses vínculos atendem identidade corrente, avisos e Comments
contextuais e devem ser mantidos até que contratos genéricos provem outra
composição. Nenhum desses módulos pode passar a declarar dependência de
`aculta_forum_legacy`; em contrapartida, as matrizes FDC-11.1/11.6 devem instalar
e exercitar esses consumidores com o addon ausente, além do cenário com addon
instalado. A eventual instalação do addon pela configuração do projeto ACULTA
é escolha do projeto e não deve ser codificada como dependência de seus
consumidores.

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

Uma varredura separada dos testes encontrou fixtures e expectativas legadas em
`aculta_forum`, `aculta_user`, `aculta_game`, `aculta_messenger` e
`aculta_cgu`; por exemplo, `ForumCguIntegrationKernelTest` instala o schema de
`aculta_legado_autor`, testes de Forum/User/Game criam fields históricos, e
testes de migração do Fórum registram o target type e os maps legados. Embora
isso não seja dependência carregada em runtime, esses testes hoje mantêm a
semântica específica espalhada e podem exigir a extensão opcional para rodar.
No FDC-11.2, mover cenários cuja finalidade é testar autoria, classificação,
memorial, imutabilidade e efeitos do arquivo para testes do addon/integrados;
nos módulos principais manter testes de contrato neutro (`ARCHIVE`,
`CURRENT`, `CONTEXTUAL`, `UNKNOWN`) usando providers/fixtures genéricos, sem
instalar entidade, field, rota ou schema exclusivo do legado. A suíte sem addon
deve continuar instalável e provar as políticas genéricas de fail-closed.

O census também separou referências genéricas ao Fórum de referências ao
acervo. `aculta_statistics` consome eventos correntes de atividade sem
classificar proveniência no subscriber; sua integração deve ser validada para
que import/replay histórico não gere métricas. `aculta_messenger` possui
caminhos genéricos para tópicos atuais além das guardas históricas diretas.
Uma busca direcionada em PHP/configuração de produção dos módulos Forum, User,
CGU, Messenger, Game, Index, Statistics, Wiki e Magazine não encontrou uso
direto dos identificadores contrib `comment_forum`, `field_discussao` ou
`taxonomy_term.forums`; ocorrências restantes aparecem em documentação e
changelogs históricos. Os hits da substring `forum_topic` referem-se ao bundle
corrente `aculta_forum_topic`, a suas rotas/Views e à tabela própria
`aculta_forum_topic_deletion`; não provam dependência do Forum contrib nem do
arquivo Growroom. Preservar esse motor corrente e os Comments contextuais;
revisar cada caminho por origem/contexto, sem mover infraestrutura atual por
semelhança de nome.
`aculta_gallery` e `aculta_document_viewer` referenciam o bundle de anexos do
Fórum para publicação/acesso a arquivos; não há evidência estática de que esse
uso seja específico do arquivo histórico, então permanece no domínio genérico
do Fórum salvo prova em contrário. Referências a `aculta_forum_topic` em
Statistics, User, Messenger, Game, Index, CGU e no próprio Fórum são integração
com o host atual e devem ser avaliadas por contrato/origem; não devem ser
movidas automaticamente para o addon apenas por conter “forum” no nome.

O censo documental fora dos módulos encontrou regras históricas que precisam
de fonte normativa única na extração: `docs/portal/PAPEIS-PARTICIPACAO.md`
agora descreve `ARCHIVE`, registra o field direto apenas como implementação
atual e data o estado antigo de Runtime; após a extração deve remover também
essa referência de implementação. `ACULTA-USER-PLAN.md`, `SOURCE-OF-TRUTH.md`,
`docs/architecture/aculta_forum.md` e `docs/architecture/avatar.md` ainda
descrevem autoria, campos e ownership em User/Forum. `docs/modules/PERFIL-PUBLICO-E-CARTAO-DE-AUTOR.md`
delimita o contrato de perfis User, remete a apresentação histórica em
tópicos/comentários à F-D44 e ao addon, e deixa proveniência/imagens históricas
sob responsabilidade do addon, sem autorizar ingestão ou associação a contas.
Dentro dos módulos, `aculta_user/docs/AUTHOR-CARDS.md`,
`AUTHOR-CARD-VARIANTS.md`, `AUTHOR-CARD-API.md`, `AUTHOR-PANE-PORT.md`,
`CLASSES-E-HOOKS.md` e `README.md` ainda descrevem a entidade, cartões,
memorial e hooks atuais; mover a política histórica para a documentação do
addon e manter nos docs de identidade somente o contrato genérico e a variante
visual reutilizável. `aculta_forum/README.md`, `ARQUITETURA.md`, `OWNERSHIP.md`,
`CONTEXTS.md`, `CLASSES-AND-HOOKS.md`, `MOVE-TOOLS.md`, `RECONSTRUCAO.md` e
`REVISIONABLE-DISCUSSION-WORKFLOW.md` contêm ownership, campos e regras de
moderação/migração; atualizar os contratos correntes e preservar changelogs e
ROADMAP como registros datados, explicitamente supersedidos quando necessário.
`aculta_game/docs/ARQUITETURA.md`, `EXECUCAO.md` e `ROADMAP.md`, e
`aculta_messenger/docs/AVISOS-E-MENSAGENS.md` devem descrever exclusões de
eventos por origem neutra, sem citar field ou entidade históricos. O README de
`aculta_magazine` atualmente permite autoria `author_growroom_historico` em
Comment editorial, em conflito com F-D44: corrigir para resolver apenas
contexto/editoria e nunca carregar autoria histórica por marcador. Os docs
`aculta_image/docs/IMPORTACAO-GROWROOM.md`, auditoria e handoff mantêm detalhes
normativos de seleção, autoria e imagens; transferir essa política ao addon,
preservando a documentação de APIs genéricas de mídia em `aculta_image`.
`ROUTES-SITEMAP.md` e
`FRIENDLY-PORTUGUESE-SLUGS.md` registram a rota e reserva do memorial; e
`FORUM-GAME-PESQUISA-VALIDACAO.md` resume regras de integração. No FDC-11.2,
mover a política detalhada para os documentos do addon e deixar nesses
documentos transversais apenas contratos atuais e links para o dono normativo.
O contrato de perfil User deve continuar sem regras de imagem/autoria próprias
do acervo; referenciar a política do addon quando aplicável. Preservar rota e
reserva por provider genérico. `RUNTIME-WINDOW-PLAN-2026-11-12.md` é evidência
operacional datada: não executá-lo como autorização atual nem
apagar seu histórico; se a migração de ownership tornar seus passos obsoletos,
marcá-los como supersedidos por este plano.

O censo de interface/configuração também separou infraestrutura corrente de
semântica de arquivo: o domínio e alias do Fórum, a página inicial de
categorias, Views/Search API e o cabeçalho visual do Growroom continuam com o
motor/tema do fórum atual. A missão `Primeira contribuição no Growroom420` em
`aculta_game.missions.yml` descreve uma resposta atual publicada; permanece no
Game. A proteção contra import/replay histórico deve chegar por contrato
genérico de origem, sem mover a missão ou tornar Game dependente do addon.
Strings genéricas como “histórico do Game” também não são acoplamento ao acervo
Growroom sem evidência de fluxo ou dado compartilhado.

`aculta_deployer/config/deploy.json` contém hosts e regras de deploy do site
atual, não código do acervo, e permanece no deployer. Referências em testes e
documentos de Magazine ao Growroom verificam que Comments contextuais editoriais
não entram no host do Fórum; devem migrar para contratos genéricos de origem e
contexto, sem fazer `aculta_magazine` depender do addon.

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

A suíte de contrato do resolver deve usar fixtures sintéticas ou exemplos
sanitizados derivados do manifesto fonte aprovado; não usar nem inventar IDs do
Runtime, pois a importação ainda não ocorreu. Incluir tópico histórico com o
campo e tópico histórico sem o campo, tópico corrente, Comment em cada um desses
hosts, Comment contextual de Wiki/Revista/LMS que tenha um marker coincidente,
e entidade cujo host/proveniência não possa ser confirmado. Para cada caso,
fixar o resultado esperado e testar leitura, escrita, reação, resposta, citação,
apresentação do autor, fila/moderação e emissão de eventos. A fixture histórica
sem campo só pode ser `ARCHIVE` pelo sinal alternativo demonstrado na política;
sem essa evidência, deve ser `UNKNOWN`. A mera presença de `field_autor_legado`
fora do host histórico nunca pode promover uma entidade a `ARCHIVE`. Depois da
importação futura aprovada, um teste separado de integração no clone valida os
IDs de origem reais contra o manifesto, sem expor dados pessoais.

### Estado atual do acervo, importação futura e dados da refatoração

O responsável confirma que a importação do Growroom ainda não ocorreu e que
não há autores, tópicos ou comentários históricos importados no Runtime alvo.
O corpus e os manifests versionados/gerados que servem de fonte para a futura
importação não são conteúdo do banco Runtime e não entram em limpeza. F-D44
continua definindo a entidade canônica `aculta_legado_autor` e a identidade que
será importada; a definição da entidade ou uma tabela vazia não prova que haja
autores ou conteúdo históricos persistidos. O addon deverá importar o corpus
somente em fase futura aprovada, mantendo IDs de origem, autoria, hierarquia,
URLs e regras de apresentação.

“Limpar dados da refatoração” significa remover registros ou artefatos
persistidos criados pela própria refatoração, não apagar o corpus de origem nem
dados históricos que ainda serão importados. Antes de qualquer remoção, provar
a origem e separar esses artefatos do conteúdo corrente de Wiki, Revista, LMS,
Fórum ou outros hosts. Uma vez comprovado que o registro foi criado pela
refatoração, removê-lo é o resultado esperado da fase de limpeza. A extração de
ownership e essa limpeza são operações distintas. Nenhuma exclusão persistente
será executada nesta fase de planejamento; a operação futura precisa de lista
exata, método reversível e validação em clone. Não é necessário manter tabelas
temporárias/paralelas comprovadamente criadas pela refatoração. O censo verifica
se o storage canônico/fields existem e estão vazios, sem presumir linhas.
ForumPosts/Flaggings de importação, caso encontrados apesar de a importação não
ter sido executada, exigem investigação de proveniência; só registros provados
como artefatos da refatoração entram na limpeza. Não exportar dados pessoais.

Para confirmar o baseline informado e identificar eventual resíduo de teste ou
refatoração, o census deve conferir status e mapas das migrations. Como a
importação não foi executada no Runtime alvo, os destinos históricos esperados
são zero; esta matriz documenta o que verificar se a execução estiver marcada,
se houver dados parciais ou se aparecerem alvos inesperados:

| Migration | Evidência de proveniência | Possível efeito da refatoração |
| --- | --- | --- |
| `aculta_forum_comment_posts` | Map/message tables do Migrate: cada CID de origem mapeado para ID de destino `aculta_forum_post`; conferir CID de origem, ID de destino e existência/estado sem expor corpo ou autoria. | Cria ou atualiza ForumPosts a partir de Comments, incluindo dados `legacy_author`. O destination define explicitamente `ROLLBACK_DELETE`; teste Kernel confirma que rollback exclui ForumPosts importados e preserva os Comments origem. |
| `aculta_forum_comment_parents` | Mapa da migration e IDs de destino; compara `parent` atual com a relação derivada dos CIDs de origem/pai. | Atualiza a hierarquia via destination `entity:aculta_forum_post`; o rollback padrão de EntityContentBase também pode excluir destinos mapeados, não apenas limpar `parent`. |
| `aculta_forum_comment_flaggings` | Map/message tables próprios e campo `entity_type/entity_id` dos flaggings mapeados, cruzados com lookup da migration de posts; conferir `aculta_cgu_report.legacy_flag_id` pelo ID de cada flagging destino. | Cria flaggings contra ForumPosts destino. O hook `flagging_insert` pode criar denúncia CGU e chamar `ModerationNotifier`, que pode enviar e-mail via Mailer; o rollback Core exclui o flagging, mas o hook atual só remove denúncia estritamente pendente, não as que avançaram de estado. |

Mapas ausentes, incompletos ou incompatíveis não autorizam inferir origem por
UID, data, texto ou semelhança do conteúdo. Se o census divergir do baseline
de importação não executada, interromper a limpeza e investigar a origem; não
classificar automaticamente esses registros como legado original ou como
refatoração. Exigir evidência de clone/backup e revisão antes de qualquer plano
reversível.
Não executar `drush migrate:rollback` em lote como atalho: destinos do Migrate
usam `ROLLBACK_DELETE`, e o rollback pode excluir ForumPosts que hoje tenham
edições, filhos ou relações posteriores. Primeiro inventariar revisões,
respostas, flaggings, reações, referências e eventos ligados aos IDs de destino;
provar o comportamento na versão Core efetiva e em clone restaurável. Os
O corpus/manifests de origem, conteúdo corrente dos demais hosts e qualquer
registro cuja proveniência não tenha sido demonstrada nunca são alvos dessa
limpeza. Como não há importação histórica no Runtime alvo, não presumir
Comments/autores/tópicos históricos de origem no banco. Core: [EntityContentBase
no Drupal 11](https://api.drupal.org/api/drupal/core%21modules%21migrate%21src%21Plugin%21migrate%21destination%21EntityContentBase.php/11.x).

Para a migration de flaggings, incluir no census apenas IDs de denúncias
relacionados por `legacy_flag_id` aos flaggings destinos provados pelo mapa.
Conferir status, decisões, vínculos do caso, mensagens pendentes e logs do
Mailer antes de planejar a remoção de qualquer registro CGU; a exclusão de uma
Flagging não remove automaticamente denúncias fora do estado `pendente` nem
desfaz e-mails já enviados. Não classificar denúncia como dado da refatoração
por motivo, data ou alvo apenas. Bloquear a ponte/notificação durante qualquer
importação futura até o teste provar zero efeitos externos.

O escopo do census de proveniência não termina nas três migrations. A
refatoração pode ter produzido efeitos persistidos por hooks, workers ou
projeções; a tabela abaixo define domínios a verificar, não afirma que existam
registros deles no Runtime:

| Domínio a verificar | Evidência para atribuir à refatoração | Tratamento planejado |
| --- | --- | --- |
| Revisões, relações pai/filho, reações e referências em ForumPosts de destino | ID de destino ligado ao CID e à migration; revisar histórico/revisões e relações atuais; reações e referências só entram se ligadas ao ID exato | Remover os registros comprovados e tratar alterações posteriores dependentes no plano reversível; não usar rollback em lote. |
| Eventos de atividade/participação, contagens ou concessões do Game | Ledger/event ID ou referência explícita ao ID de destino/importação; comparar hooks e regras de supressão efetivamente vigentes na versão executada | Apagar eventos/registros comprovados da refatoração; separar projeções reconstruíveis de concessões/participações persistidas e reconciliar saldos sem remover por janela de tempo, UID ou similaridade. |
| Filas, avisos, subscriptions e mensagens do Messenger | Item de fila com entidade/source ID, logs de worker ou vínculo explícito com operação de importação; auditar também itens já consumidos | Remover itens e registros pendentes comprovados; mensagens/notificações já entregues são efeitos externos e não são desfeitos pelo rollback, portanto registrar como não reversíveis. |
| Documentos de Search API/Index e métricas/projeções de Statistics | IDs de entidade de destino nos trackers/índices ou chaves de origem no ledger de métricas; snapshots agregados antes/depois sem exportar conteúdo pessoal | Remover projeções comprovadas e reconstruir somente após plano operacional próprio com comparação de baseline; nunca executar rebuild durante esta fase. Isolar métricas históricas das correntes. |
| Cache, estado temporário e logs de execução | Chaves namespaced pela migration/entidade e retenção documentada; logs sem copiar PII para o relatório | Registrar como estado operacional, não como acervo. Não limpar globalmente nem tratar cache/log como prova única da origem de conteúdo. |

Em todos esses domínios, IDs e vínculos explícitos são evidência; horário,
autor, texto, bundle ou coincidência de contagem não bastam. O census deve
anotar também efeitos que não possam ser revertidos (e-mail ou mensagem já
entregues) para que a remoção do registro de origem não seja descrita como
reversão total. Artefatos reconstruíveis só serão reindexados/reconciliados em
etapa operacional própria, autorizada e com comparação de baseline.

### Fase planejada: remoção de dados comprovadamente produzidos pela refatoração

Esta é uma fase separada do FDC-11.0 (census) e do FDC-11.3 (transferência de
ownership). O censo prova proveniência; a transferência não apaga dados; a fase
de limpeza remove registros persistidos da refatoração, caso existam. Como a
importação histórica não ocorreu, o resultado esperado do censo no Runtime é
zero ForumPosts/Flaggings/métricas/eventos produzidos por essas migrations; se
houver registros, investigar a divergência antes de classificá-los. Esta fase
ocorre depois do FDC-11.0 e antes de desativar ou retirar em FDC-11.2 as
definições/plugins necessários à proveniência. Manter mapas e definições de
migration até concluir o censo e qualquer limpeza necessária; não os apagar
para simplificar a extração. Para cada resíduo comprovado, identificar IDs
exatos nos domínios acima, incluindo ForumPosts, relações pai/filho, Flaggings
e denúncias CGU relacionadas por `legacy_flag_id`. Em clone restaurável,
inspecionar revisões, filhos, reações, referências, casos/decisões, filas,
eventos de atividade/participação, mensageria, projeções e efeitos já entregues;
produzir prévia, método reversível e comparação antes/depois por conjunto de
IDs. A validação deve provar que o corpus fonte e conteúdo corrente de outros
hosts permanecem intactos. Não executar rollback em lote; e-mails e eventos
externos já entregues não são apagáveis por rollback, e denúncias que avançaram
de estado precisam de tratamento próprio.

Todos os registros cuja origem na refatoração esteja demonstrada por mapas e
proveniência são alvo de remoção, depois de resolvidas suas dependências e
preparados backup, reversão e autorização operacional da execução concreta.
Registros sem evidência suficiente permanecem intocados até nova prova. F-D44
determina manter a entidade/tabela canônica `aculta_legado_autor` para a futura
importação; o plano não presume linhas de autores, tópicos ou comentários no
Runtime atual. Não confundir registros duplicados criados pelas migrations
com a definição/storage canônica. Se o censo confirmar o baseline vazio e
nenhum resíduo de refatoração, esta fase registra resultado sem exclusão; não
cria nem mantém tabela paralela para satisfazer o plano.

### Pipeline de coleta e curadoria do acervo

O censo da branch também encontrou ferramentas legadas fora dos módulos
Drupal, em `scripts/growroom/`: `wayback_fetch.py` obtém capturas CDX;
`build_mapping.py` seleciona tópicos e mapeia fórum/autoria/origem estável;
`marcar_comercial.py` identifica conteúdo comercial para triagem;
`mapear_links.py` classifica destinos internos, imagens, anúncios e links
legados; `gerar_imagem_padrao.py` cria o placeholder de imagem ausente; e
`verificar_importacao.py` valida hashes, manifestos, links, seleção, privacidade
e autoria. `tests/scripts/test_growroom_origin_ids.py` testa parte da chave
estável dos autores, e `tests/scripts/README.md` descreve a execução dessas
verificações. Scripts, testes e instruções de seleção/validação são
específicos da importação histórica e devem ter ownership em
`aculta_forum_legacy`, não no motor de discussões.

O acervo de imagens usa o serviço genérico `aculta_image` para ingestão,
deduplicação e armazenamento; o addon deve orquestrar o adapter e guardar as
regras de seleção, associação e placeholder específicas do Growroom. A
existência de documentação de importação dentro de `aculta_image` não torna a
política histórica parte do módulo de imagens.

Os scripts atuais usam caminhos privados fixos sob `/home/aculta/private/` e
produzem manifests/mapas derivados fora do Git. Na extração, tornar os caminhos
argumentos/configuração explícitos e manter o corpus bruto, CDX, HTML/assets e
saídas de curadoria fora do pacote público do módulo; não copiar dados privados
para o repositório. Preservar manifests e resultados de seleção que dão
proveniência à importação. Eles e o corpus histórico não são “dados criados pela
refatoração”; não os remover por nome, data ou por serem derivados. São
distintos dos dados persistidos que a refatoração possa ter criado no Drupal.
Estes últimos — ForumPosts/Flaggings migrados, efeitos vinculados em CGU,
Game/Messenger, projeções e índices — têm remoção como resultado esperado
quando sua proveniência estiver comprovada por mapa, ID de origem ou vínculo
explícito, conforme a fase de limpeza acima. A opção de testar links no site
vivo exige execução externa explícita e fica fora do census/leitura local.

### Inventário a migrar para o addon

- **Dados e autoria:** transferir para o addon a definição da entidade/tabela `aculta_legado_autor`, o manager/upsert por ID de origem, `field_autor_legado`, configurações e ownership dos contratos; preservar IDs e contratos de storage para a importação futura. Não presumir linhas/valores de referência importados no Runtime: a importação ainda não ocorreu. Autores futuros nunca viram conta User/login; `uid=0` e IDs de origem serão verificáveis após a importação aprovada. Inventariar arquivos do corpus de autoria sem movê-los ou excluí-los nesta fase. `aculta_user` não será o owner futuro da política legada.
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

1. **FDC-11.0 — Census somente leitura:** inventariar tipos/bundles/fields/rotas/Views/config, tabelas, referências de autoria, flags, Search API, eventos/consumidores, filas, projeções e caches; confirmar que a importação histórica não foi executada e que os destinos/mapas correspondentes estão ausentes ou vazios; registrar contagens agregadas sem PII. Qualquer ForumPost, Flagging, relatório, métrica ou evento inesperado exige investigação antes de classificar ou limpar. Mapear cada acoplamento em `aculta_forum`, `aculta_portal`, `aculta_user`, CGU, Index, Statistics, Messenger, Game e outros consumidores. Para cada integração, registrar owner atual, contrato de destino e arquivo futuro no addon. Não executar rebuild, import, sync, update, uninstall ou limpeza.
2. **FDC-11.1 — Contratos e matriz de instalação:** definir interfaces/providers genéricos e testar os módulos principais sem o addon, o addon instalado e consumidores opcionais ausentes. Provar que os módulos principais não carregam código/config do addon e que o manifesto só declara a dependência unidirecional do addon para as APIs necessárias. Provar que novas respostas Growroom usam ForumPost; Wiki/Revista/LMS preservam Comment contextual e seus grants; origem desconhecida falha fechada; arquivo fora das filas ordinárias do CGU e exceção somente por protocolo autorizado/auditável; arquivo e importações/reconstruções não geram aviso, assinatura, XP, participação ou perfil corrente; autoria histórica continua fora de contas/perfis `aculta_user`.
3. **FDC-11.2 — Extração de código/config:** criar o módulo opcional e mover para ele toda semântica, configuração, rotas, ferramentas read-only, projeções e providers específicos do acervo. Remover/substituir referências diretas nos principais a `aculta_legado_autor`, `field_autor_legado`, `legacy_author`, classes, IDs de config/rotas e equivalentes. Corrigir o escopo das migrations para que Comment `ARCHIVE` nunca entre em ForumPost/parent/flagging; qualquer migração de Comments correntes exige escopo e autorização próprios. Módulos consumidores integram-se pelo resolver genérico, sem depender do addon. Nenhum comportamento histórico pode depender de `aculta_user` nem tornar addon obrigatório para `aculta_forum`/`aculta_portal`.
4. **FDC-11.3 — Dados e ownership:** separar a transferência estrutural do futuro carregamento de conteúdo. No clone de upgrade, confirmar o baseline sem registros históricos importados; validar a transferência de ownership sem criar ou apagar conteúdo. Em clone isolado de importação futura, comparar o manifesto aprovado aos registros importados (IDs de origem, autoria, árvore/ordem, URLs, timestamps, idiomas e arquivos) e verificar ACL anônimo/autenticado/moderador. A importação histórica não é executada nesta fase. Preservar a definição/ID da entidade `aculta_legado_autor` e o contrato da tabela/base storage conforme F-D44; não presumir linhas ou referências importadas no Runtime, pois a importação ainda não ocorreu. Validar IDs de origem e vínculos no clone da futura importação aprovada. Manter entity type ID e nome da tabela, por si só, **não** prova transferência segura: Drupal registra definições instaladas e provider de entity/field storage, e o Core desinstala entity types/field storages cujo provider ainda seja o módulo removido. A lista de mudanças do `EntityDefinitionUpdateManager` detecta mudanças de schema, mas documenta que ainda não cobre todas as mudanças sem impacto de schema. O `ConfigManager::uninstall()` também remove ou atualiza configurações dependentes do módulo; mover YAMLs de `config/install` não transfere automaticamente UUIDs/dependências da configuração ativa. O caminho de upgrade também precisa ser desenhado em etapas: instalar um addon com defaults de configuração que já existem na configuração ativa pode provocar colisão de configuração (`PreExistingConfigException`); não presumir que uma instalação nova do addon ou um único `config:import` resolva a transferência. Provar em clones restauráveis tanto o caminho de instalação limpa quanto o upgrade do site que já tem esses fields/config, com uma sequência que não crie janela sem provider nem acione uninstall/purge. O clone precisa validar explicitamente a transferência de provider da entidade e dos fields/config (`field_autor_legado`) sem recriar/remover tabelas, purgar fields, apagar config dependente nem alterar UUIDs; conferir definições instaladas e uninstall validator antes/depois. A mudança de módulo dono de entity/field/config e a cópia de arquivos são operações estruturais: requerem clone restaurável, plano de reversão, backup, análise das definições e autorização operacional específica. Nunca desinstalar `aculta_user` para “transferir” storage. Referências do Core: [ModuleInstaller 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21ModuleInstaller.php/class/ModuleInstaller/11.x), [EntityDefinitionUpdateManager::getChangeList 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityDefinitionUpdateManager.php/function/EntityDefinitionUpdateManager%3A%3AgetChangeList/11.x), [ConfigManager::uninstall 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Config%21ConfigManager.php/function/ConfigManager%3A%3Auninstall/11.x) e [ConfigInstaller::installDefaultConfig 11.x](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Config%21ConfigInstaller.php/function/ConfigInstaller%3A%3AinstallDefaultConfig/11.x).
   A validação com Core 11 deve considerar ainda que
   `EntityDefinitionUpdateManager::getChangeList()` compara mudanças de schema
   e mantém um TODO explícito para updates de definição sem mudança de schema;
   portanto, troca somente do provider pode não aparecer no status automático.
   `EntityTypeListener::onEntityTypeUpdate()` grava a definição como última
   instalada no repositório do Core. A prova em clone deve executar
   explicitamente o mecanismo de atualização escolhido, comparar
   `getDefinition()` com `getLastInstalledDefinition()` antes/depois, confirmar
   provider da entidade e do field storage após novo bootstrap e demonstrar que
   nenhuma tabela/linha foi recriada ou apagada. Se a atualização não for
   representada ou aplicada com segurança pela API escolhida, não concluir a
   transferência de ownership; desenhar outra estratégia de deploy/compatibilidade.
   Referências: [getChangeList no Core 11](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityDefinitionUpdateManager.php/function/EntityDefinitionUpdateManager%3A%3AgetChangeList/11.x), [EntityTypeListener::onEntityTypeUpdate](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityTypeListener.php/function/EntityTypeListener%3A%3AonEntityTypeUpdate/11.x), [EntityLastInstalledSchemaRepository](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21EntityLastInstalledSchemaRepository.php/function/EntityLastInstalledSchemaRepository%3A%3AgetLastInstalledDefinitions/11.x).

5. **FDC-11.4 — Projeções separadas:** implementar em `aculta_forum_legacy` o provider do catálogo/pesquisa próprios do arquivo e o provider da série intitulada **“Estatísticas Históricas da Restauração do Growroom”**. `aculta_index` já oferece provider tagged com contratos neutros, mas cada provider deve implementar contexto/política de acesso; esse contrato não concede ACL. `aculta_statistics` já oferece provider tagged para valores, porém sua lista de métricas é fixa: antes da série histórica, criar extensão genérica do catálogo para definições externas, com validação de ID, tipo, sensibilidade, retenção, dimensões e autorização de consulta. Nenhum módulo principal recebe lógica, configuração ou nomenclatura histórica específica. O catálogo e a pesquisa devem ser ACL-aware e isolados do índice/pesquisa do fórum atual; a série histórica não pode ser somada nem duplicada nas métricas correntes. Provar escopos, regras de acesso e separação das contagens. Reações históricas são sempre ocultas e recusadas. Fazer census de eventuais rows de reação sem apagar ou corrigir dados. Não rodar rebuild de índice nem reconstrução de métricas sem autorização operacional.

   A revisão estática da API unificada de reações encontrou caminhos que ainda
   precisam de classificação de origem em todas as projeções: `DiscussionReactionManager`
   consulta rows por tipo/ID sem excluir `ARCHIVE` ou `UNKNOWN`; o builder pode
   carregar as contagens antes de avaliar `can_react`; as estatísticas públicas
   de perfil e a ordenação de comentários também consomem agregados. O resolver
   analítico atual distingue bundle, purpose, publicação e Groups, mas não a
   proveniência histórica; `countActiveReactions()` soma rows públicas de Node,
   Comment e ForumPost sem filtro de origem. Portanto, fechar apenas a escrita
   não atende ao requisito. A API central deve unificar contagens para Portal,
   Fórum, LMS, Wiki, Revista e outros hosts, enquanto cada provider/contrato
   aplica acesso contextual e o addon fornece a classificação `ARCHIVE`.
   Cobrir leitura, renderização, perfis, ordenação, métricas e escrita nos testes:
   `ARCHIVE` e `UNKNOWN` não exibem nem contam reações e recusam novas; remoção
   de qualquer row só pode ocorrer após provar sua proveniência como dado criado
   pela refatoração e aprovar a operação própria.
6. **FDC-11.5 — Autoria e UX:** validar a apresentação individual nos tópicos e Comments `ARCHIVE` hospedados pelo Fórum legado, conforme o perfil legado, e preservar o memorial coletivo `/u/lendashistoricas` como única página pública de memória. Testar que `field_autor_legado` isolado em Wiki, Revista, LMS, ForumPost ou host desconhecido não seleciona cartão histórico nem cria uma identidade pública. Não criar link, rota, perfil ou listagem individual; impedir colisão/resolução acidental com `/u/{apelido}` e `/user/{uid}`. A regra de produto é F-D44; F-D47 foi invalidada e não deve ser usada como requisito.
7. **FDC-11.6 — Fechamento:** varrer dependências diretas/indiretas e comprovar que módulos principais não contêm código ou configuração específica do importador nem dependem do addon; validar reinstalação limpa com/sem addon, atualização em clone, ACL/cache, rotas, CGU, providers de Index/Statistics, Messenger/Game e UI read-only via UI Suite existente. Só então propor mudança de fase/status e publicar resultados observados.

**Regra de instalação:** “opcional” significa que novos projetos podem não instalar o addon. Não significa que seja seguro desinstalá-lo de uma instalação com arquivo: uninstall pode remover configuração, fields ou schema. O Core 11 fornece `ModuleUninstallValidatorInterface`, registrado como serviço com a tag `module_install.uninstall_validator`; a documentação diz que os validators participam do uninstall e da validação anterior ao `config:import`. O FDC-11.2 deverá implementar validator no addon que bloqueie a operação quando houver autores, conteúdo/referências, fields/storage, configuração ou arquivos sob ownership legado, e apresentar motivo acionável. A cobertura não pode se limitar às tabelas da entidade de autor e dos dois fields: o inventário também precisa localizar referências em conteúdo, revisões, arquivos, configuração ativa/sync, mapas de migration, denúncias, filas, índices e projeções persistidas. Testar presença isolada em cada classe de dependência, ausência de cada tabela opcional e instalação limpa sem arquivo; testar validação pela tela de uninstall e pelo caminho de import de configuração. Não executar esses fluxos contra Runtime compartilhado. O validator é uma guarda, não autorização de limpeza nem prova que remover o código do módulo é seguro: não retirar/distribuir o código do addon enquanto seus dados, referências ou schema ainda dependerem dele. Até existir plano específico aprovado para desativação, desabilitar apresentação não equivale a desinstalar o fornecedor de dados. Nenhuma migração de storage, `config:import`, `updatedb`, Composer, rebuild ou operação persistente fica autorizada por este planejamento. Consulte [ModuleUninstallValidatorInterface no Drupal 11](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21ModuleUninstallValidatorInterface.php/interface/ModuleUninstallValidatorInterface/11.x) e [hook_uninstall no Drupal 11](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Extension%21module.api.php/function/hook_uninstall/main).

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
