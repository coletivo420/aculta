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

### Integrações negativas: sem capacidades correntes

O addon não consome APIs de CGU, Game, Messenger ou `aculta_user`. Sua única
contribuição transversal é publicar a origem `ARCHIVE` pelo contrato genérico
de classificação, além dos providers de arquivo que possui. Os módulos
principais consultam esse contrato e excluem o arquivo: CGU não oferece
denúncia, caso ou moderação; Messenger não envia aviso, assinatura ou
mensagem; Game não atribui participação, atividade, XP ou conquista; `aculta_user`
não trata autores do arquivo como usuários. Não criar adapters do addon que
acionem ou consumam essas capacidades.

“Autor legado” significa exclusivamente a entidade de conteúdo existente
`aculta_legado_autor`, implementada pela classe PHP
`Drupal\aculta_forum_legacy\Entity\LegacyAuthor`, sem criar conta/entidade
Drupal User ou Profile. A entidade preserva uma referência persistente e
estável para autoria, mas não recebe login, papéis, permissões, perfil de
conta, amizade, mensagens ou participação. A exibição individual nos tópicos e
comentários do arquivo segue F-D44; o único destino público de memória
permanece o memorial coletivo.

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

### Evidência da implementação local — 2026-11-19 (parcial, não homologada)

- O contrato genérico de origem e a reserva de slugs opcionais estão no Portal; o Forum agrega classifiers por tag e consumidores consultam somente as categorias `current`, `archive`, `contextual` e `unknown`.
- A implementação inicial do addon contém entidade/autoria histórica, campos, memorial coletivo, renderização sem perfil individual, classificação, guardas read-only, filtros de moderação, métricas históricas e reserva de rota. A atividade de Forum exige origem `current`; o catálogo corrente não inclui origens desconhecidas.
- O addon tem uninstall validator para remoção direta e `config:import`: enquanto houver linhas nas tabelas conhecidas de autoria/marcadores, sua remoção é bloqueada. Teste unitário cobre ambos os caminhos. Isso protege os dados encontrados por esses marcadores, mas não substitui o census.
- **Bloqueio de segurança ainda aberto:** o classificador corrente reconhece o bundle de tópico como atual. Não há evidência disponível nesta worktree de que todo tópico histórico esteja marcado por autoria. Antes de homologar a opção de omitir/desinstalar o addon, o FDC-11.0 deve provar cobertura completa ou definir um discriminador adicional. UID e data não serão usados como substitutos sem evidência.
- A configuração versionada habilita o addon para o site ACULTA, mas nenhum config import, update, uninstall, rebuild de índice, migração ou operação no Runtime foi executada nesta rodada.
- Revisão independente apontou o risco de desinstalação; o bloqueio por linhas conhecidas foi implementado. A cobertura de tópicos históricos sem marcador segue pendente.

### Auditoria estática preliminar dos acoplamentos — 2026-11-19

Esta busca cobre código/configuração PHP, YAML e Twig versionados nesta worktree;
não substitui o census de Runtime/config ativo do FDC-11.0. Achados que devem
orientar a extração e a revisão:

| Área | Evidência atual | Destino/critério do plano |
| --- | --- | --- |
| Configuração de fields | `field.storage.*.field_autor_legado` e `field.field.*.field_autor_legado` continuam em `config/sync`; a worktree também copia defaults desses IDs para o addon e remove parte dos defaults de `aculta_forum`/`aculta_user`. | Comparar config ativa, sync e defaults por UUID/ID/dependências em clone restaurável. A cópia para `config/install` não prova transferência segura nem autoriza import; provar que habilitar o addon mantém a storage e seus valores e que remover defaults antigos não apaga configuração ativa. |
| Resíduo de refatoração em ForumPost/docs | O `ForumPost` atual não declara `legacy_author`, mas quatro displays de ForumPost no config sync ainda o listam como hidden. Documentos/roadmap de Forum e User também descrevem `ForumPost.legacy_author` ou autoria histórica no post revisionável, em conflito com a política atual de não converter Comments históricos em ForumPosts. | Tratar a chave de display e instruções normativas obsoletas como artefatos da refatoração, não como registros do acervo. Antes de removê-las, confirmar em config/schema/testes que não existe field atual nem dado histórico armazenado ali. Concentrar a política normativa e a semântica histórica em `aculta_forum_legacy`; manter changelog histórico apenas como registro datado e explicitamente supersedido, sem guiar implementação atual. |
| Documentação de ownership/migração | `docs/OWNERSHIP.md` ainda liga autoria histórica a `aculta_user` e cita fila/hook antigo; `REVISIONABLE-DISCUSSION-WORKFLOW.md` e seções do `ROADMAP.md` descrevem `ForumPost.legacy_author` e conversão Comment→ForumPost. `MIGRATION-REMOVAL.md` é auditoria histórica da retirada do Drupal Forum contrib e inclui decisões de limpeza antigas. | Transferir a política normativa do acervo para a documentação de `aculta_forum_legacy`; atualizar ownership e roadmap atuais para não prescrever conversão nem dependência de User. Preservar auditorias/changelogs como evidência datada e marcar explicitamente trechos superados, sem apagá-los nem usá-los como plano executável. Distinguir a retirada do contrib da extração do acervo Growroom. |
| Fórum core | `ForumDiscussionOriginPolicy` e consumidores de resposta, reação, flag, moderação e atividade reconhecem categorias genéricas `ARCHIVE`/`UNKNOWN`; o core não deve conhecer campos, entidade ou rotas do Growroom legado. | Manter somente o contrato genérico e o fail-closed. Toda regra para reconhecer o acervo e toda apresentação/consulta histórica pertencem ao addon. Cobrir também as rotas Core e consumidores indiretos. |
| Registro e consumidores do classificador | `CurrentDiscussionOriginClassifier` classifica `node:aculta_forum_topic` como `CURRENT`; o classificador do addon reconhece `ARCHIVE` por markers e se registra com prioridade superior. CGU, Index, Game, Messenger e User consomem o resolver genérico, em geral opcional; Views, Search API e o agregador de métricas por entidade ainda não o consultam. | Testar precedência do addon sobre CURRENT, ausência do provider, marker ausente e saída inválida. Consumidores de escrita/eventos falham fechados sem resolver; acrescentar integração genérica a Views, Search API e agregação por entidade. O censo deve provar quais tópicos históricos não têm marker antes de confiar na precedência. |
| Views e navegação | Views públicas/administrativas filtram `node:aculta_forum_topic` por bundle/status; `PublicForumListViewHooks` calcula contagens por bundle/categoria. Esses caminhos não filtram a origem histórica por si mesmos. | Separar listagem atual, filas e contagens do catálogo histórico. Aplicar filtro de origem por provider em Views/query/access e nas contagens auxiliares; preservar rota/canonical de leitura histórica e grants. Só usar marker após o census provar cobertura; não presumir que todo registro possui marker. |
| Search API | `search_api.index.aculta_forum` contém tópicos/ForumPosts; `ForumPostSearchAccess` valida bundle, Domain e grants, mas não injeta o classificador de origem. | Search atual indexa apenas tópicos correntes e seus ForumPosts. O addon fornece catálogo/pesquisa histórica ACL-aware separado; acesso direto e resultados não podem depender de rebuild para revogar permissão. Validar no clone antes de qualquer rebuild operacional. |
| CGU e sinalizações | `CguReportQueue`, `ForumReportCguBridgeHooks`, `CguReport` e o contrato CGU mantêm compatibilidade com `ReportQueue`/Flagging antigos (`legacy_flag_id`, `sinalizacao_legada_forum`, ARQ-06D/06F). | Census deve separar flags associadas ao acervo importado, flags antigas de conteúdo corrente e relatórios UGC genéricos. Mover para o addon somente adaptadores/proveniência exclusivos do acervo; CGU continua dono de denúncias, casos e decisões. Não migrar nem remover linhas nesta fase. |
| Métricas | `LegacyArchiveMetricsProvider` filtra Comments pelo bundle/campo, mas a consulta ainda precisa provar que o host é `node:aculta_forum_topic`; atribuição explícita no Comment, isoladamente, não deve incluir Comments contextuais de Wiki/Revista/LMS. | Restringir a projeção histórica pelo classificador/host exato e testar host cruzado, campo de autoria no host e autoria explícita no Comment. Manter métrica histórica fora da série corrente e sem reconstrução nesta fase. |
| Drilldown de Statistics | `AuthorizedEntityMetricAggregator` valida apenas `node:aculta_forum_topic` e permissão `view content-level statistics`; não valida a origem do tópico. | Revalidar cada candidato pela origem corrente antes de agregar `forum.replies.created`; excluir `ARCHIVE`/`UNKNOWN` sem depender de que o Index já tenha filtrado a lista. |
| Catálogo histórico | O memorial do addon busca autores; não há evidência nesta worktree de busca independente de texto de tópicos/respostas do arquivo. O `ForumContentIndexProvider` corrente já exige origem `CURRENT`, mas não substitui o índice histórico próprio. | Especificar índice/catálogo de arquivo separado, ACL-aware, com busca de tópicos e Comments históricos preservando hierarquia e URLs. Não incluir conteúdo contextual nem usar busca pública corrente como substituto; rebuild requer autorização própria. |
| Autoria e UI | O addon implementa `LegacyAuthorCardBuilder` e template Twig próprio, embora o tema já possua SDC `author-card`. | Reutilizar o SDC existente com variante somente leitura/archive e provider de dados do addon; não criar SDC duplicado nem expor link/perfil individual. Confirmar slots e contrato antes de remover o template. |
| Dependências | `aculta_forum` ainda declara dependência e serviços obrigatórios de `aculta_user` para identidade e participação correntes; o manifesto do addon depende somente do Fórum e módulos Drupal, sem dependência reversa encontrada. | Separar identidade ACULTA corrente da identidade histórica. O acoplamento corrente a `aculta_user` não deve ser confundido com legado Growroom, mas a portabilidade do motor exige adapter opcional e matriz de instalação sem User ACULTA. |

O inventário literal encontrou os identificadores de storage/autoria (`aculta_legado_autor`,
`field_autor_legado`, classe `LegacyAuthor` e rota do memorial) no addon; fora
dele, os matches de produção no Fórum são principalmente a categoria genérica
de origem. Isso não prova ausência de acoplamentos: referências indiretas a
Flagging, métricas, eventos, rotas Core, config ativa e dados sem marcador
continuam no escopo do census e da revisão manual.

As instruções normativas sobre imutabilidade, identidade e apresentação do
arquivo devem ter `aculta_forum_legacy` como documento dono. O Fórum pode
referenciar um contrato genérico de origem e uma página de integração, sem
armazenar regras, campos, nomes de rota ou decisões específicas do acervo.

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

### Inventário a migrar para o addon

- **Dados e autoria:** entidade existente `aculta_legado_autor`, manager/upsert por ID de origem, `field_autor_legado` e referências legadas, configuração de storage/form/display e a propriedade desses contratos. Autores continuam sem conta User/login; `uid=0` e as chaves, IDs, referências e relações existentes devem ser preservados conforme plano aprovado. Os arquivos de autoria são inventariados e preservados, sem mudar seu armazenamento nesta fase. A localização atual em `aculta_user` não torna `aculta_user` dono futuro do legado.
- **Identidade pública histórica:** memorial coletivo único `/u/lendashistoricas`, reserva do slug, cartões e nomes históricos. Todos esses elementos e suas configurações são fornecidos por `aculta_forum_legacy`. Nos tópicos e comentários do fórum legado, a exibição individual deve respeitar o perfil legado definido em F-D44. Não criar página, rota ou listagem individual pública, nem link para perfil/conta User. Manter a convenção `-gr` apenas onde já decidida para cartões do fórum, sem alterar nomes canônicos do memorial.
- **Classificação e integridade:** classificador central por origem/host; guardas read-only; exclusão dos fluxos de denúncia/moderação; regras de mover/citar/responder; prevenção de reações e eventos de participação. O addon fornece classificação `ARCHIVE` pelo contrato genérico; não implementa nem consome CGU, Messenger, Game ou `aculta_user`. Esses módulos principais apenas excluem a origem histórica de seus fluxos correntes usando contratos genéricos. O core não conhece nomes de campo nem IDs de entidade legados. Origem não reconhecida ou ausência do provider exigido falha fechada.
- **Integrações de leitura:** `aculta_forum_legacy` é dono das projeções, catálogo e pesquisa próprios do arquivo, além do provider da série de métricas históricas. `aculta_index` e `aculta_statistics` permanecem genéricos: oferecem contratos de extensão e infraestrutura de consulta/agregação, sem lógica, configuração ou nomenclatura Growroom embutida. O addon não consome esses módulos nem os serviços correntes de CGU, Messenger, Game ou User; registra providers genéricos para o arquivo e mantém exclusões de capacidades correntes.
- **Ferramentas e apresentação:** Views/rotas do memorial coletivo, cartões de autoria e renderização read-only, cache tags/contexts e invalidação, além dos hooks de autoria/atividade que hoje carregam marcador histórico.
- **Migração e auditoria:** todo código de census/auditoria do acervo pode residir no addon como ferramenta read-only. O conversor Comment→ForumPost não é parte do alvo executável: a política vigente proíbe essa conversão. Qualquer utilitário futuro deve ser explicitamente não destrutivo, dry-run/read-only por padrão e não importar dados por efeito colateral.

### O que permanece no motor e nos adapters correntes

`aculta_forum` mantém ForumPost revisionável para novas publicações Growroom e Comments contextuais de Wiki, Revista e LMS, API de hosts, reações correntes, moderação autorizada, cache e contratos neutros. O core fornece pontos de extensão para classificação/origem, apresentação de autoria, catálogo e métricas; sem addon, origens não suportadas não recebem escrita nem leitura ampliada.

Não mover para `aculta_forum_legacy` os serviços atuais de User, associação, avatar/perfil de conta, participação e janela de edição. Para portabilidade além do ACULTA, esses serviços devem ser opcionais e acessados por contratos/adapter de integração ACULTA (nome a definir). O addon histórico não deve virar dependência oculta de `aculta_user`.

O requisito “portável a outros projetos” exige auditoria adicional do manifesto completo: dependências ACULTA não históricas, serviços obrigatórios e configuração de instalação do `aculta_forum`. Remover só `aculta_user` não prova portabilidade. Dependências funcionais necessárias ao motor devem ser declaradas; integrações opcionais usam providers/serviços opcionais e configuração que só é instalada quando os módulos correspondentes estão presentes. Não instalar configuração que aponta para plugins/entidades ausentes.

### Sequência planejada e critérios de aceite

1. **FDC-11.0 — Census somente leitura:** inventariar tipos/bundles/fields/rotas/Views/config, tabelas, referências de autoria, flags, Search API, eventos/consumidores e caches; reportar contagens agregadas, sem exportar PII. Mapear cada referência residual em `aculta_forum`, `aculta_portal`, `aculta_user`, CGU, Index, Statistics, Messenger, Game e outros consumidores. Para cada integração legada, registrar código/config atual, contrato genérico de destino e arquivo do addon que será seu owner. Não executar rebuild, import, sync, update, uninstall ou limpeza.
2. **FDC-11.1 — Contratos e matriz de instalação:** definir interfaces/providers genéricos e testar os módulos principais sem o addon, o addon instalado e consumidores opcionais ausentes. Provar que os módulos principais não carregam código/config do addon e que o manifesto só declara a dependência unidirecional do addon para as APIs necessárias. Provar que novas respostas Growroom usam ForumPost; Wiki/Revista/LMS preservam Comment contextual e seus grants; origem desconhecida falha fechada; origem `ARCHIVE` é excluída de CGU/moderação, notificações Messenger, participação Game e identidade `aculta_user`.
3. **FDC-11.2 — Extração de código/config:** mover para `aculta_forum_legacy` toda semântica, configuração, rotas, ferramentas, projeções e providers específicos do acervo, eliminando dos módulos principais referências literais a `aculta_legado_autor`, `field_autor_legado`, `legacy_author`, IDs de config/rotas legadas e equivalentes. Módulos consumidores chamam somente contratos genéricos para excluir `ARCHIVE` de CGU, Messenger, Game e User; o addon não consome APIs desses módulos. Nenhum comportamento histórico pode depender de `aculta_user` nem tornar o addon obrigatório para `aculta_forum`/`aculta_portal`.
4. **FDC-11.3 — Dados e ownership:** primeiro reproduzir a extração em clone restaurável, comparar contagens, entity IDs, UUIDs, source IDs, chaves, autoria, árvore/ordem, URLs, timestamps, idiomas, avatares (quantidade, checksums, paths, referências e render) antes/depois; verificar acesso anônimo/autenticado/moderador. Preservar conteúdo e bytes. Preservar o ID `aculta_legado_autor`, as referências existentes e os arquivos de autoria. A mudança de módulo dono de entity/field/config e a cópia de arquivos são operações estruturais: requerem clone restaurável, plano de reversão, backup, análise das definições e autorização operacional específica. Nunca desinstalar `aculta_user` para “transferir” storage.
5. **FDC-11.4 — Projeções separadas:** implementar em `aculta_forum_legacy` o provider do catálogo/pesquisa próprios do arquivo e o provider da série intitulada **“Estatísticas Históricas da Restauração do Growroom”**. `aculta_index` e `aculta_statistics` fornecem apenas as APIs genéricas de extensão, consulta e agregação; não recebem lógica, configuração ou nomenclatura histórica específica. O catálogo e a pesquisa devem ser ACL-aware e isolados do índice/pesquisa do fórum atual; a série histórica não pode ser somada nem duplicada nas métricas correntes. Provar escopos, regras de acesso e separação das contagens. Reações históricas são sempre ocultas e recusadas. Fazer census de eventuais rows de reação sem apagar ou corrigir dados. Não rodar rebuild de índice nem reconstrução de métricas sem autorização operacional.
6. **FDC-11.5 — Autoria e UX:** validar a apresentação individual nos tópicos e comentários históricos conforme o perfil legado e preservar o memorial coletivo `/u/lendashistoricas` como única página pública de memória. Não criar link, rota, perfil ou listagem individual; impedir colisão/resolução acidental com `/u/{apelido}` e `/user/{uid}`. A regra de produto é F-D44; F-D47 foi invalidada e não deve ser usada como requisito.
7. **FDC-11.6 — Fechamento:** varrer dependências diretas/indiretas e comprovar que módulos principais não contêm código ou configuração específica do importador nem dependem do addon; validar reinstalação limpa com/sem addon, atualização em clone, ACL/cache, rotas, CGU, providers de Index/Statistics, Messenger/Game e UI read-only via UI Suite existente. Só então propor mudança de fase/status e publicar resultados observados.

**Regra de instalação:** “opcional” significa que novos projetos podem não instalar o addon. Não significa que é seguro desinstalá-lo de uma instalação com arquivo: uninstall pode remover configuração, campos ou schema. Até uma política de desativação segura ser aprovada, desabilitar apresentação não equivale a desinstalar o fornecedor de dados. Nenhuma migração de storage, `config:import`, `updatedb`, Composer, rebuild ou operação persistente fica autorizada por este planejamento.

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
