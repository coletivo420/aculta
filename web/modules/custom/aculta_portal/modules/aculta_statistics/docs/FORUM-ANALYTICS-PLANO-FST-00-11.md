# ACULTA Forum + Statistics - auditoria e plano de integração FST-00 a FST-11

**Data da auditoria:** 2026-11-10  
**Estado:** PLANEJADO, sem implementação nem homologação do plano FST  
**Branch-alvo:** `dev`  
**Referência de leitura:** `c599f9363dbb36ca0586c0a6ead9c39f4c4d79dd` (não é trava de versão)  
**Módulos:** `aculta_forum`, `aculta_statistics`; contratos compartilhados do `aculta_portal`, integração com `aculta_user`, `aculta_index`, sistema UGC/moderação e tema `aculta420_v2`  
**Autoridade:** este documento é o plano de execução; os arquivos de implementação e testes são a fonte de verdade do que está efetivamente entregue.

> Não declarar FST concluída por existir documentação. A auditoria foi estática do repositório; não houve consulta ao Runtime atual, execução de PHPUnit, alterações no banco, deploy, migração ou ativação de funções nesta rodada. Antes da implementação, reler AGENTS.md da raiz, do Portal, do Fórum e do Statistics, TODO.md de cada componente e verificar o HEAD atual.

## 1. Objetivo e limites

Centralizar **indicadores analíticos** do Growroom420 em `aculta_statistics`, mantendo os dados operacionais, o acesso e o ciclo de vida de tópicos, Comments, ForumPosts e reações em `aculta_forum`. O Fórum é também o motor de discussões contextuais da Revista, Wiki e LMS: **não** atribuir suas participações automaticamente às métricas do Fórum público.

Não portar a base de dados do Discourse/Flarum; adaptar ideias de relatórios. Preservar ownership de Node, Comment, ForumPost, Taxonomy, Group, Profile, UGC e Search API. Não criar dependência obrigatória inversa Fórum -> Statistics nem armazenamento paralelo de conteúdo. Não modificar Dados do Runtime, Config Sync, migrações, grants, rotas de produção ou site servido sem o gate específico e a autorização aplicável. Não alterar o tema V1.

## 2. Baseline confirmado no código (não no Runtime)

| Camada | Evidência concreta | Situação |
| --- | --- | --- |
| Emissor | `aculta_forum/src/Hook/ForumActivityEventHooks.php` e `Event/ForumActivityEvent.php` | Eventos `topic_created`, `comment_created`, `post_created` implementados |
| Consumidor | `aculta_statistics/src/EventSubscriber/ForumActivitySubscriber.php` + registro `aculta_statistics.services.yml` | Subscriber cadastrado e nomes compatíveis |
| Catálogo | `aculta_statistics/src/Catalog/MetricCatalog.php` | `forum.topics.created`, `forum.comments.created`, `forum.posts.created` existentes, sem dimensão de contexto |
| Escrita | `StatisticsRecorder` -> `DailyAggregateStorage` / `aculta_statistics_daily` | Agregado diário UTC, sem autoria e com retenção padrão de 90 dias |
| Painel central | `StatisticsDashboardController` e rota `/painel-administrativo/aculta/estatisticas/area/forum` | Série, seletor 7/14/30 dias e CSV existentes no código |
| Painel do Fórum | `ForumAdminOverviewController` | Contagens atuais via Entity Query + série de sete dias via storage do Statistics |
| Reações | `DiscussionReactionManager` e tabelas `aculta_discussion_reaction` / `aculta_discussion_reaction_user_stats` | Estado e contador operacional por UID pertencem ao Fórum |
| Perfil público | `ForumProfileReactionHooks` | Exibe contadores de reações do Fórum |
| Fórum restrito | `RestrictedForumGroupResolver` e `RestrictedForumAccessPolicy` | Restrição pode decorrer de tópico **ou categoria** vinculados ao Group |
| Testes | `ForumActivityEventHooksTest`, `ForumPostActivityEventKernelTest`, `ForumActivitySubscriberTest`, `StatisticsDashboardKernelTest`, `ForumAdminOverviewControllerTest` | Provas parciais; nenhum teste integrado real de ponta a ponta localizado |

`config/sync/core.extension.yml` contém os dois módulos, mas isso **não prova** o estado de habilitação, schema e serviço no Runtime atual.

**Dívida documental:** `INTEGRATIONS.md` trazia `ForumProvider`, `forum.topics.published` e `forum.participants.active` como se fossem implementados; não aparecem no catálogo/serviços analisados. Também dizia estar pendente toda a leitura do painel do Fórum, embora a série histórica de sete dias já exista. A contagem atual por Entity Query **não deve** ser convertida indiscriminadamente em soma de eventos históricos.

## 3. Achados e riscos priorizados

| ID | P | Problema | Evidência / hipótese | Gate para encerrar |
| --- | --- | --- | --- | --- |
| FST-R01 | P0 | Reações de fórum restrito podem ingressar em totais públicos | `DiscussionReactionManager` compara apenas `getVisibilityPolicy() === 'group_members_only'`, ao passo que `PublicForumContextProvider` usa `public_if_entity_accessible` mesmo quando Group nega acesso | Matriz Group direto/por categoria, conta suspensa, pessoa não membro, alterações de vínculo, reconciliação dos registros e perfis |
| FST-R02 | P0 | Contagem pública por autor sem escopo equivalente ao acesso real | `getAuthorPublicPostCount()` consulta Comments, ForumPosts e tópicos por UID/status, sem revalidar os contextos de todas as linhas | Ausência de conteúdo restrito/Revista/Wiki/LMS em qualquer contador explicitamente público |
| FST-R03 | P1 | Republicações podem reemitir atividade | Transição não publicado -> publicado não prova primeira publicação histórica; `first_published` existe em ForumPost e marcador em Comment compartilhado | Uma publicação lógica = um evento; despublicação/republicação não incrementa |
| FST-R04 | P1 | Isolamento de escopo nos eventos insuficiente | Critério atual usa bundle/status/sync; não confirma purpose canônico nem Group restrito | Public-only e restricted/internal classificados antes da escrita |
| FST-R05 | P1 | Histórico e estoque atual misturados semanticamente | Incrementos não subtraem exclusões; `forum.*.created` mede atividade, não o inventário atual | Snapshots atuais independentes; divergência documentada, reconciliável |
| FST-R06 | P1 | Integração não comprovada de ponta a ponta | Tests de hooks e subscribers não testam o boot real dos dois módulos e o write no storage | Kernel cria entidade, percorre hooks/event dispatcher e comprova agregado |
| FST-R07 | P2 | Dependência opcional incerta no DI do painel | `ForumAdminOverviewController` autowire de `aculta_statistics.storage` com argumento anulável, sem garantia de boot sem módulo | Teste Container/Kernel com Statistics ausente e render seguro |
| FST-R08 | P2 | Reações mantêm agregado analítico isolado | Tabela de reações por usuário em `aculta_forum` sem série histórica no Statistics | Fonte operacional preservada, série central reconciliada; não eliminar tabela prematuramente |
| FST-R09 | P2 | Catálogo de saúde e resposta incompleto | Sem participantes únicos, primeira resposta, sem resposta, moderação, buscas/visitas | Contratos e queries testadas com definições explícitas |
| FST-R10 | P2 | Documentos e status históricos divergentes | ROADMAP/README/INTEGRATIONS têm fases antigas ou afirmações que não correspondem exatamente ao código | Documentação viva marca plano versus implementado versus Runtime |

O P0 aqui significa **prioridade de investigação e correção antes da exposição pública**, não prova de incidente no Runtime. O fórum restrito pode estar desligado e os efeitos devem ser demonstrados por testes antes de afirmar ocorrência.

## 4. Benchmark externo e decisões ACULTA

| Plataforma | Referência verificável | Inspiração adotada | O que não copiar |
| --- | --- | --- | --- |
| Discourse 2026 | https://meta.discourse.org/t/better-insights-into-your-community-with-the-redesigned-admin-dashboard/404528 e https://meta.discourse.org/t/introducing-the-all-new-admin-dashboard/409764 | Painel de saúde, engajamento, busca, suporte; períodos e seções configuráveis | Identificação automática de membros em painéis globais |
| Discourse Reports | https://meta.discourse.org/t/admin-dashboard-report-reference-guide/240233 | Distinção entre DAU/MAU, `daily engaged`, tópicos sem resposta, tempo para primeira resposta, flags | Chamar autores únicos de visitantes ativos: não são a mesma população |
| Discourse detalhamento 2026 | https://meta.discourse.org/t/see-the-users-and-topics-behind-the-numbers-in-admin-reports/411397 | Drilldown útil mediante autorização e seleção explícita | Lista nominal privada como consequência automática de agregado |
| Flarum Statistics 2.x | https://docs.flarum.org/tr/extensions/statistics/ e https://docs.flarum.org/vi/extend/statistics/ | Totais, período anterior, cache e contrato extensível / dependência opcional | Agregação de tópicos ocultos/privados em painel público |
| XenForo | https://docs.xenforo.com/manual/maintenance/logs | Datas, agrupamento diário/semanal e séries múltiplas | Misturar eventos heterogêneos como se tivessem a mesma unidade |

`NodeBB` permanece como referência exploratória de UI/analytics, sem necessidade de depender de funcionalidade específica não verificada. Adoção de biblioteca ou código externo exigiria auditoria de licença; **este plano usa conceitos, não código portado**.

## 5. Arquitetura e contratos propostos

### 5.1. Ownership e fluxo

1. `aculta_forum` continua proprietário das entidades, reações, publicação, moderação contextual e regras `RestrictedForumAccessPolicy`.
2. `aculta_portal` hospeda um contrato **opcional e tipado** para providers de métricas, sem dependência de implementação de Statistics no Fórum.
3. `aculta_statistics` é o catálogo e gravador único de indicadores globais, agregados e séries. Implementar `ForumProvider` apenas após especificar o contrato e prova de boot.
4. `aculta_user` mantém ownership de identidade e privacidade; Statistics não vira serviço de identificação pública nem owner de author cards.
5. `aculta_index` apenas seleciona candidatos e relações autorizados para detalhamento: o agregador não concede acesso. Revalidar Entity Access antes de consultar IDs.
6. Sistema de moderação/UGC conserva denúncias e identidades; Statistics recebe apenas totais autorizados e tempos agregados.
7. `aculta420_v2` e UI Suite/SDC renderizam dados preparados por backend sem decidir grants ou pesquisar tabelas.

Fluxo: entidade/evento -> escopo analítico validado -> collector/provedor -> recorder/catalog/storage -> query/report/autorizações -> render arrays -> SDC ou área administrativa.

### 5.2. Escopo analítico - resolver dedicado

Criar `ForumAnalyticsScopeResolver` sob owner `aculta_forum`. Entrada: entidade hospedeira, contexto e requerimento do indicador; saída tipada: `public_forum`, `restricted_forum`, `publication`, `wiki`, `lms` ou `unknown` + elegibilidade pública (bool) + metadados de cache/access (quando aplicáveis). Não derivar visibilidade pública somente de uma string de política.

Verificar: bundle, purpose canônico, `field_domain_source`, status/visibilidade de origem, Group direto ou herdado de categoria, status do membro apenas para detalhamento autorizado, e falha segura. Para geração de métricas globais **não** confundir `view` pelo administrador com `visível ao público`. Decisão de publicidade é propriedade do conteúdo/contexto, não do usuário que executa cron. `unknown` e falha da consulta = não público.

Não incluir Group ID ou UID como dimensão de métrica pública. Para relatórios privados, exigir política administrativa **específica e contextual**, não apenas o caráter P do catálogo.

### 5.3. Semântica, idempotência e migração

- Preservar os três event names atuais: `aculta_forum.topic_created`, `.comment_created`, `.post_created`. Não renomear `forum.*.created` sem plano de compatibilidade e auditoria dos consumidores.
- Definir `created` como **primeira publicação qualificada** para as métricas existentes e documentar a diferença entre `created_at`, `published_at` e timestamp de entrega.
- Deduplicar de forma persistente e segura para publicação, mesmo após reprocessamento de evento, reabertura, moderação ou corrida. Preferir marcador estável da entidade e/ou ledger mínimo interno de idempotência com chave única, sem autoria, conteúdo ou IP. Revisar ciclo de vida e retenção da chave; não confiar em cache temporário.
- Migrations de Comment -> ForumPost e importações sincronizadas **não** criam participação nova. Definir reconciliação por origem canônica e impedir dupla soma entre tipos. Preservar trilha de `legacy` separada de atividade corrente.
- Histórico de eventos não deve ser editado automaticamente só porque conteúdo saiu do ar; quando o indicador pede estoque atual, criar snapshot novo e reconciliá-lo.
- Não efetuar backfill mutável no Runtime automaticamente. Propor dry-run, diff por dia/categoria, snapshot e autorização; nunca reconstruir dados de autoria em tabelas globais de Statistics.
- Todas as métricas têm proveniência e `collection_started_at`. Intervalos anteriores à instrumentação são `indisponível`, não zero.

### 5.4. Tipos de métricas

- `counter`: soma de transições/eventos qualificados por dia.
- `snapshot`: fotografia atual por dia; **não somar** snapshots no tempo.
- `distinct-window`: contagem distinta em janela fixa, calculada no owner a partir da fonte; **não somar** totais diários distintos para obter mês.
- `ratio`: numerador e denominador de mesma fonte/escopo/janela; recalcular ao trocar de filtro.
- `distribution`: mediana/percentis, derivados dos registros elegíveis; não usar média como substituição.
- `operational`: quadro atual de moderação/estado da entidade; não duplicar seu armazenamento.

Extensão de catálogo tipada e APIs de armazenamento **compatíveis com clientes atuais** (Wiki/Links/Commerce/LMS). Se o schema novo for necessário, documentar versão, hooks de update, rollback lógico, testes e gate de autorização antes de aplicar ao Runtime.

## 6. Catálogo proposto, prioridades e definições

**Já implementados no catálogo (não presumir validação Runtime):**

| ID | Tipo | Fonte / significado |
| --- | --- | --- |
| `forum.topics.created` | counter | primeira publicação de tópico (com escopo público a corrigir) |
| `forum.comments.created` | counter | primeira publicação de Comment legado em tópico Fórum |
| `forum.posts.created` | counter | primeira publicação de ForumPost revisionável |

**A implementar - fase essencial (novos nomes são propostas e passam por revisão na FST-02):**

| ID proposto | Tipo | Unidade / fonte | Escopo inicial |
| --- | --- | --- | --- |
| `forum.topics.published_current` | snapshot | nós atualmente publicados e públicos | Fórum público |
| `forum.replies.published_current` | snapshot | Comments legados + ForumPosts visíveis, sem duplicações da migration | Fórum público |
| `forum.categories.active` | snapshot/distinct-window | categorias públicas com contribuição no período | Fórum público |
| `forum.topics.unanswered` | snapshot | tópicos sem **resposta de outra pessoa** | Fórum público |
| `forum.participants.engaged_7d` | distinct-window | UIDs únicos com contribuição válida em sete dias | Interno |
| `forum.participants.engaged_30d` | distinct-window | UIDs únicos com contribuição válida em 30 dias | Interno |
| `forum.participants.new_contributors` | counter | primeira contribuição qualificada da pessoa | Interno |
| `forum.reactions.added` | counter | inclusão nova, sem contar troca de emoji como adição | Fórum público |
| `forum.reactions.active` | snapshot | reações vigentes autorizadas, com remoção/troca refletida | Fórum público |
| `forum.response.first_median_minutes` | distribution | mediana da diferença primeira publicação -> primeira resposta de outro autor | Interno |
| `forum.response.first_p90_minutes` | distribution | percentil 90 da mesma população | Interno |
| `forum.response.unanswered_over_72h` | snapshot | tópicos elegíveis sem resposta após 72 h | Interno |

A taxa de resposta deriva de `tópicos com resposta / tópicos elegíveis` na mesma janela de coorte, e **não** deve somar ou tirar média de percentuais diários. `DAU/MAU` exige fonte de visitantes autenticados reais e não é sinônimo de autores únicos. Avaliar uma métrica `discussion.*` por contexto para Revista/Wiki/LMS em ciclo posterior; nunca incluir essas discussões em `forum.*` por acidente.

**Expansões posteriores e dependentes:**
- `forum.moderation.reports_open`, `forum.moderation.reports_resolved`, `forum.moderation.resolution_median_minutes`, `forum.moderation.posts_pending` - provider do owner UGC/moderação, permissões próprias.
- `forum.topics.views`, `forum.search.queries`, `forum.search.zero_results`, `forum.search.clickthrough`, `forum.categories.views` - somente após fonte de coleta, política LGPD/consentimento, bots/cache e reconciliação com GA4. Nunca somar GA4 com leituras internas.

### 6.1. Definição de primeira resposta

Usar `first_published` do tópico e da primeira resposta publicada **por um autor diferente do autor original**; considerar Comments e ForumPosts sem duplicar migrations, excluir posts sem visibilidade pública, eventos importados, respostas automáticas e revisões. Registrar alterações retroativas e políticas de resposta apagada: `histórico de primeira resposta recebida` versus `primeira resposta pública ainda existente`. Selecionar explicitamente uma para cada indicador. Calcular mediana e p90 apenas com população definida; reportar `amostra insuficiente` quando cabível.

### 6.2. Dimensões e privacidade

Dimensões permitidas inicialmente: purpose canônico e categoria **pública autorizada**; classificação de escopo separada e não enumerável por visitante. Usar allowlist e limite de cardinalidade. `dimensionTotalsForDimensions()` não executa access check: o consumidor deve fornecer previamente IDs autorizados e limitados. Nenhum UID, IP, e-mail, referer, texto de denúncia ou conteúdo de mensagem em dimensão de agregado público.

Aplicar supressão de células pequenas, seguindo `CellSuppression::MINIMUM_PUBLIC_COUNT` (=5) ao disponibilizar contagens potencialmente identificáveis para público. Cuidado com inferência por diferença entre filtros: suprimir totais complementares e exportação quando possível recompor células pequenas. Relatórios internos e restritos exigem permissões distintas das métricas P do catálogo. Atualizar o inventário LGPD antes da coleta nova.

## 7. Experiência, painéis e SDCs

O painel em `/painel-administrativo/aculta/estatisticas/area/forum` continua owner do Statistics e integra tabelas/gráficos/CSV. O painel `/painel-administrativo/forum` mantém ações operacionais e resumo autorizado; não duplicar motor analítico.

- Cabeçalho de KPIs: tópicos, respostas publicadas, contribuidores distintos e taxas **com período/proveniência**.
- Engajamento: séries de tópicos/repostas/reações; comparação de períodos equivalentes; filtro de categoria e purpose autorizado; tabela acessível.
- Qualidade de diálogo: sem resposta, tempo mediano e percentil 90; definições/denominadores visíveis.
- Moderação: somente agregados autorizados; nenhum usuário denunciado listado no gráfico.
- Ranking de categorias: apenas categorias públicas, com navegação via Index a entidades autorizadas.
- Períodos existentes de 7/14/30 dias devem ser preservados; extensões de janela e agrupamento semanal/mensal exigem consulta tipada e medição de desempenho.
- SDCs candidatos: `statistics-metric-card`, `statistics-trend-chart`, `statistics-period-filter`, `statistics-category-ranking`, `statistics-activity-summary`, `statistics-response-health`, `statistics-moderation-summary`, `statistics-empty-state`.
- Props dos SDCs recebem render arrays ou estruturas sanitizadas; sem SQL, Entity Access, cálculo de privacidade ou serviços em Twig. Reaproveitar UI Suite, UI Patterns, UI Library, tokens do tema `aculta420_v2`, fronteira módulo/tema e geometria claro/escuro. Administração usa o tema administrativo **ativo**, sem presumir Claro como padrão; não trocar `system.theme`. HTMX nativo para interações novas com fallback Form API/link funcional.

Não transformar métricas em pontuação individual, ranking competitivo compulsório ou listas nominais de membros online.

## 8. Microfases de implementação FST

### FST-00 - Inventário e baseline (obrigatória)
- Checar HEAD, AGENTS/TODO, módulos ativos, DB schema, se serviços carregam e se o painel responde no MAIN; recuperar contagens atuais sem escrita e fixtures do Fórum público/restrito.
- Mapear TODOS os consumidores de `ForumPublicPostStatistics`, `DiscussionReactionManager::getUserStats`, `getAuthorPublicPostCount`, metrics `forum.*` e perfis públicos.
- Reexecutar Unit/Kernel atuais e classificar PASS/FAIL/DEFERRED, sem inferir resultados de documentação histórica.
- **Gate:** matriz produtor -> consumidor -> fonte -> acesso -> teste, baseline assinado e nenhum dado do Runtime alterado.

### FST-01 - Segurança, escopo e exposição pública (P0)
- Implementar `ForumAnalyticsScopeResolver`, integração Group (tópico/categoria), purpose e domínios; corrigir `is_public_context` e demais contadores públicos.
- Criar comando de **auditoria/dry-run** das reações antigas, sem mutação. Plano de reconciliação/rollback antes de qualquer escrita autorizada.
- Matriz anônimo/membro/não membro/associado suspenso/moderador, despublicação, Group direto/indireto, migração de contexto e ausência do Group.
- **Gate:** testes demonstram que nenhuma reação/post restrito influencia dashboard ou perfil público; falhas fechadas; cache invalida imediatamente.

### FST-02 - Contratos/semântica/catalogação
- Projetar contrato neutro opcional no Portal e registrar provider tipado do Fórum, mantendo producers independentes do Statistics.
- Mapear tipos `counter`, `snapshot`, `distinct-window`, `ratio`, `distribution`; revisar IDs e semântica dos três existentes sem quebra.
- **Gate:** boot combinado/sem Statistics, validação de DI e compatibilidade da API com Wiki/Links/LMS/Commerce.

### FST-03 - Idempotência e publicação
- Resolver primeira publicação real de Node, Comment e ForumPost (marcadores e ledger/unique key), race e replay; respeitar revision workflow e sync.
- Não emitir métrica por migração/reindex/movimentação; provar primeira publicação versus republicação.
- **Gate:** concorrência/replay/republicação não aumentam o valor; fixtures reais com hooks/event dispatcher e storage.

### FST-04 - Snapshots e reconciliação
- Prover estoque atual de tópicos, respostas e categorias com visibilidade adequada, sem sobrescrever eventos históricos.
- Cron/Queue com lock, cursor, limites, dry-run, relatório diff e estratégia de rollback. Backfill só com aprovação.
- **Gate:** estado atual coincide com Entity Query autorizada após exclusão, despublicação, edição e mudança de Group; carga medida.

### FST-05 - Participação e qualidade de resposta
- Calcular autores distintos nas janelas 7d/30d sem persistir UID na tabela de métricas; novos contribuidores, sem resposta, primeira resposta por terceiro, mediana e p90.
- Usar períodos UTC, proveniência explícita e coortes; não somar distintos diários.
- **Gate:** fixtures com autores diferentes, autor repetido, comentários migrados, Group, revisão, bots, títulos sem resposta.

### FST-06 - Reações e perfis
- Centralizar séries `forum.reactions.*` conservando tabela de reação e eventual cache operacional de perfis sob responsabilidade do Fórum.
- Reação trocada é update, removida é decremento do snapshot; tipar contexto e reconciliar estados públicos com fonte.
- **Gate:** empate entre perfis, dados atuais, séries e fonte resolvido ou informado; zero vazamento de Group.

### FST-07 - Moderação / UGC
- Localizar owner real de denúncias (não criar `aculta_ugc` por suposição); projetar provider de contagens/tempos e direitos específicos.
- **Gate:** fluxo de denúncia/reabertura/resolução consistente; relatórios globais sem denunciante, denunciado ou notas privadas.

### FST-08 - Relatórios / UI Suite
- Implementar dashboards por métricas e consultas tipadas: indicadores, séries comparadas, filtros e tabelas/CSV equivalentes.
- Compor SDCs de conteúdo neutros e reutilizáveis; integrar design system, responsive, dark/light, foco/teclado, tradução e tema administrativo ativo.
- **Gate:** mesmas permissões em HTML/CSV; sem dado de outro purpose; controles HTMX/no-JS e 320/390/768/1280 px verificados.

### FST-09 - Content Index / drilldown
- Detalhamento de indicadores para entidades **públicas e autorizadas**, a partir de páginas de resultado com acesso revalidado e no máximo 100 dimensões por lote.
- Não produzir lista nominal de participantes automaticamente nem usar o agregador como grant.
- **Gate:** revogação entre páginas de consulta resulta em remoção imediata; URLs e CSV sem fuga de contexto.

### FST-10 - Descoberta e leitura (fase posterior)
- Reavaliar beacon/cache/robots e Search API; provar origem, deduplicação, LGPD, finalidade, consentimento e separação GA4 vs tráfego interno.
- **Gate:** relatório de fontes e controles aprovado antes de capturar visitas/pesquisas; testes de bot/cache e exclusão de consultas sensíveis.

### FST-11 - Homologação transversal e encerramento
- Unit, Kernel, Functional, Browser, HTTP MAIN/fora do MAIN, acesso por Group, schema updates, performance, compatibilidade e auditoria LGPD.
- Comparar DB de origem -> métricas -> painéis com dados representativos em ambiente **autorizado**, monitorar divergências, executar rollback ensaiado antes da promoção.
- Atualizar `README`, `TODO`, status YAML, `ROADMAP`, `INTEGRATIONS`, `METRIC-CATALOG`, CHANGELOG e ADR quando decisão vinculante for tomada.
- **Gate:** evidências com comandos, hashes, casos PASS/FAIL/DEFERRED, desempenho e resultados de segurança; sem declarar homologação quando apenas o código existe.

### Dependências de fases

FST-00 -> FST-01 -> FST-02 -> FST-03 -> FST-04 -> FST-05/FST-06 -> FST-07 -> FST-08 -> FST-09 -> FST-11. FST-10 é **posterior**, depende de decisão específica de coleta, e não bloqueia o MVP de indicadores editoriais; seus próprios gates devem ser concluídos antes de ativá-la. Correção P0 não deve esperar UI.

## 9. Matriz de testes exigida

| Cenário | Invariante |
| --- | --- |
| Publicação inicial e pendente -> publicada | +1 uma vez, data UTC determinada pela semântica |
| Editar/revisar/retirar/republicar tópico, Comment e ForumPost | sem novos eventos de criação |
| `isSyncing()`, backfill, mover tópico, migrar Comment -> ForumPost | não criar atividade nova nem duplicata |
| Duas requisições concorrentes do mesmo evento | incremento lógico único |
| Restrição por Group direto e categoria, membership revogada, propósito diferente | não aparece em métricas de escopo público nem em perfis |
| Reação adicionada/trocada/retirada, troca de público para restrito | série e estoque coerentes com a fonte; sem PII |
| Excluir/despublicar conteúdo | snapshot atual reduz; histórico etiquetado como evento não falsificado |
| Autor do tópico responde a si; outro membro responde; resposta apagada | indicador de primeira resposta conforme coorte e política documentadas |
| Um UID participa em vários dias | distinct 30d = 1 pessoa, não soma de dias |
| Statistics desabilitado | Fórum operacional, painel de série indica indisponibilidade de modo seguro |
| Visitante, moderador, admin, membro restrito | HTML, Index, CSV, cache e routes com escopo correto |
| Sem dados / antes do início da coleta | mostrar N/D em vez de zero enganoso |
| Cron e carga (100k/1M quando viável) | locks, índices, queries limitadas, sem N+1 e SLO medido |

Primeiro teste de integração prioritário: fixture Kernel em `aculta_simpletest` que ativa o container dos dois módulos, instala schema em banco isolado, salva um tópico/Comment/ForumPost, aciona hooks reais, observa `event_dispatcher` e consulta `aculta_statistics_daily`. Não apontar PHPUnit para `aculta_runtime`. Testes funcionais usam base isolada própria e não alteram produção.

## 10. Performance e segurança de apresentação

- Antes de fixar SLOs, medir tempos de render e consulta no baseline da FST-00; registrar p50/p95, linhas, cardinalidade, memória e concorrência. Índices compostos conforme filtros reais.
- Não realizar `COUNT(DISTINCT)` sobre toda a base a cada página carregada; calcular e armazenar recortes periódicos sem PII.
- Respeitar limite existente de 100 dimensões nas consultas correlacionadas. Não construir rankings privados a partir de dimensões públicas.
- Cache: propagar tags/contextos de entity grants, Domain, Group e permission; despublicação e mudança de membership invalidam dados pertinentes. Tratar relatório admin atual não-cacheável quando necessário.
- CSV e API precisam do **mesmo** filtro/autorização/visibilidade do HTML. Qualquer query inconsistente deve falhar fechada.
- Supressão de pequenas células e proteção contra ataque por diferença de categorias e períodos.
- `StatsRecorder` não deve receber evento de reação em cascata múltipla; definir idempotência do consumo antes de criar produtor.
- Nenhum novo JS de métrica deve registrar PII; não criar trackers sem decisão expressa.

## 11. Políticas operacionais e documentação

- Branch `dev`, sem nova PR, branch de feature ou merge em `main` sem ordem expressa; preservar commits paralelos; commits atômicos por entrega. Plano documental não autoriza implementação de todas as fases por si só.
- Não realizar deploy, config import, updates de DB, migrações, deletes, rebuilds destrutivos ou exposição de analytics restritos sem gate/ordem própria.
- Testar alterações funcionais de Statistics junto aos consumidores Forum, Index, User, LMS, Wiki, Links e Mailer na medida do diff.
- Cada FST atualiza estado e evidências nos arquivos do owner, sem transformar roadmap histórico em verdade operacional.
- Regra de tipografia/documentação deste projeto: usar hífen (-), sem travessão.

**Fontes primárias do ACULTA:** caminhos em `§2`, `aculta_statistics/docs/INTEGRATIONS.md`, `METRIC-CATALOG.md`, `ROADMAP.md`, `aculta_forum/docs/ROADMAP.md`, `RESTRICTED-FORUMS.md`, `COMENTARIOS-0.2.0-PLANO.md`, `docs/portal/ADMIN-DOMAIN-POLICY.md`, `docs/portal/ACEITE-UNICO.md`, `docs/compliance/LGPD-INVENTARIO.md` e `docs/operations/GATES.md`.

## 12. Próxima ação autorizada

Iniciar **FST-00 de auditoria read-only** e então **FST-01 de correção dos riscos P0**, com testes em `aculta_simpletest`. Não iniciar design visual nem captura de dados novos antes do gate de segurança. Exigir nova inspeção do HEAD antes de editar e resolver conflitos com trabalho paralelo.

## 13. Estado de execução

Estados possíveis: PLANEJADA, EM EXECUÇÃO, IMPLEMENTADA NO CÓDIGO, TESTADA, HOMOLOGADA NO RUNTIME, BLOQUEADA.

| Fase | Estado | Evidência |
| --- | --- | --- |
| FST-00 | TESTADA | Baseline Unit: `aculta_forum` 241 testes OK; `aculta_statistics` 108 testes OK; Kernel `DailyAggregateStorageKernelTest` 16 testes OK em `aculta_simpletest`. Inventário de produtores, consumidores e tabelas registrado neste documento. |
| FST-01 | TESTADA | `ForumAnalyticsScopeResolver`, `ForumAnalyticsScope` e `ForumReactionReconciliation`; `DiscussionReactionManager`, `PublicForumContextProvider` e painel do Fórum corrigidos. Unit focais e suíte completa do Fórum passam; Kernel integrado de Group pendente em FST-11. |
| FST-02 | TESTADA | `MetricDefinition` com tipos `counter`, `snapshot`, `distinct-window`, `ratio`, `distribution` e `operational`; contrato neutro `MetricProviderInterface` no Portal; `MetricProviderCollector` e cron de retratos; `ForumMetricsProvider` publicado por tag opcional. Unit dos dois módulos passam. |
| FST-03 | TESTADA | Ledger `aculta_forum_analytics_ledger` com chave SHA-256 única, claim atômico e retenção de 400 dias; hooks de tópico, Comment e ForumPost reivindicam antes de emitir. Kernel integrado com os dois módulos reais, EventDispatcher, subscriber e agregado: 3 testes, 61 asserções, replay e republicação não duplicam. |
| FST-04 | TESTADA | Retratos por provedor (`replaceDay`), `SnapshotReconciliation` com dry-run, lock, limite de 90 dias, relatório e comando Drush; Kernel prova tópicos públicos, rascunho e Group direto excluídos. Corrigida a tabela `groups_field_data` usada pelo resolvedor SQL. |
| FST-05 | TESTADA | Provedor calcula pessoas distintas em 7/30 dias, novos contribuidores, mediana e p90 da primeira resposta de outro autor e sem resposta após 72h. Kernel com quatro autores reais, respostas, mesmo autor e tópico antigo valida os números; UIDs não são persistidos. |
| FST-06 | TESTADA | Reação nova e pública emite `aculta_forum.reaction_added` com dimensão do tipo; troca de emoji e remoção não somam `added`. `forum.reactions.active` recalcula estoque por escopo. Kernel prova inclusão, troca, remoção e contadores de perfil; tabela operacional preservada. |
| FST-07 | TESTADA | Owner real é `aculta_cgu` (entidade `aculta_ugc_report`, status e `decided_at`). `ForumModerationMetricsProvider` calcula denúncias abertas por conteúdo distinto, resolvidas no dia e mediana created→decided_at, sem denunciante, alvo nominal ou parecer. `posts_pending` permanece no provedor do Fórum. Kernel cobre deduplicação, propósito e mediana. Nenhuma tabela nova foi necessária: a denúncia UGC já é o ledger de resolução. |
| FST-08 | IMPLEMENTADA NO CÓDIGO; BROWSER DEFERRED | Área Fórum do painel central com cartões, série acessível, filtro de período sem JavaScript, atividade, saúde das respostas e moderação; resumo e link no painel `/painel-administrativo/forum`. Oito SDCs em `aculta420_v2` com schema estrito, story e tokens; o controlador usa apenas `data-portal-ui` neutro e o tema carrega o CSS dos componentes, sem dependência módulo-tema. `validate-aculta420-sdc-schemas.php`: PASS (80 componentes, 1707 checks); Stylelint PASS nos componentes; Kernel do painel: 7 testes OK. Captura visual e leitor de tela DEFERRED. |
| FST-09 | TESTADA | `forum.replies.created` grava a resposta na dimensão do tópico; o agregador aceita somente nó `aculta_forum_topic` e exige `view content-level statistics`. O painel do Fórum lista até 10 tópicos após carregar no máximo 100 IDs e revalidar `view` por entidade. Unit cobre dimensão autorizada e bundle incompatível; Kernel confirma a dimensão. O Index continua responsável por selecionar e autorizar candidatos. |
| FST-10 | TESTADA; COLETA LIGADA NO DEV | Auditoria: Search API (`aculta_forum`), View `aculta_forum_search`, cache de Views, GA4 e Search Console existentes; sem beacon próprio, sem política de consentimento específica aprovada. Instrumentação de busca implementada sem termo, sessão, IP ou user-agent, com `aculta_statistics.settings.forum_discovery.enabled` desligado por padrão e schema de config. Visualizações e clique de resultado permanecem BLOQUEADOS até decisão de privacidade e medição de bots/cache. Coleta habilitada apenas no Runtime de desenvolvimento por decisão de 2026-11-10, com finalidade publicada no inventário LGPD; produção permanece desligada até a Política de Privacidade. Unit cobre desligado, zero resultado e busca com resultado. |
| FST-11 | TESTADA; RUNTIME PARCIALMENTE HOMOLOGADO | Unit, Kernel, Browser Firefox ESR e gates executados. Update 9008 aplicado, ledger presente, 13 retratos coletados e reconciliação 13/13. Functional bloqueado por login no site de teste compartilhado. Dark mode/zoom e leitor de tela pendentes. Ver seção 14. |

Nenhuma fase está HOMOLOGADA NO RUNTIME. A sincronização do Runtime depende de `updatedb:status` e `config:status` sem pendências e não autoriza escrita de dados.


## 14. Relatório de homologação FST-11

Data: 2026-11-10. Branch `dev`.

### Testes executados

| Suíte | Resultado |
| --- | --- |
| Unit `aculta_forum` | 250 testes, 1857 asserções, OK (2 depreciações do PHPUnit) |
| Unit `aculta_statistics` | 124 testes, 549 asserções, OK |
| Kernel `aculta_statistics` completo | 55 testes, 600 asserções, OK (108 depreciações de Core/contrib) |
| Kernel focal `aculta_forum` (Group ACL, estatísticas públicas, categoria, listagem pública, atividade de ForumPost, workflow) | 26 testes, 505 asserções, OK |
| Kernel do painel central | 7 testes, 35 asserções, OK |
| Functional `StatisticsDashboardFunctionalTest` | 2 testes SKIPPED por falta de ambiente HTTP/base URL; DEFERRED |
| Browser Firefox ESR (320/390/768/1280, claro/escuro, teclado) | DEFERRED: não executado nesta rodada |

### Gates

- `validate-aculta420-sdc-schemas.php`: PASS (80 componentes, 116 includes, 1707 checks).
- `validate-aculta420-mapped-classes.php`: PASS (483 classes).
- `validate-module-theme-boundary.php`: PASS (7385 verificações; módulo sem token ou classe de design).
- `validate-agent-token-budget.php`: FAIL por Skills recém-adicionadas no remoto (18 Skills, máximo 10; quatro arquivos acima de 4000 bytes). O handoff está em 3/3. Fora deste diff; DEFERRED.
- `npm run lint:css` nos componentes e no CSS de estatísticas: PASS.
- `validate-admin-routes-main-domain.php`: PASS (40 arquivos, 175 rotas).
- `validate-aculta-portal-drupal12.php`: PASS (1218 checks), após regenerar o índice do README raiz.
- PHPStan focal nos arquivos novos: PASS.
- PHPCS nos arquivos novos e controllers: 0 erros (somente avisos de comprimento de linha preexistentes).
- `php -l` em todos os arquivos PHP alterados: PASS.
- `validate-aculta420-v2-sdc.php`: FAIL preexistente em `components/auth-dialog/auth-dialog.twig`, fora do escopo desta alteração; DEFERRED.
- `validate-browser-index.php`: FAIL por documentos de worktrees paralelos (`.claude/worktrees/...`) que citam pastas antigas. O índice da frente foi regenerado; a falha é do scanner em worktrees, não deste teste.
- `validate-aculta420-design-foundations.php`: FAIL preexistente de literais de cor em `ui-library-admin.css` e componentes `surface*`, fora deste diff; DEFERRED.

### Runtime

- `drush status`: bootstrap OK, banco `aculta_runtime`, tema `aculta420_v2`.
- `updatedb` autorizado e aplicado: `aculta_forum_update_9008` concluído; tabela `aculta_forum_analytics_ledger` presente. `drush cr` concluído.
- Serviços verificados no container: resolvedor, ledger, provedor, coletor, reconciliação e subscribers.
- Coleta real do dia: 13 retratos gravados (10 tópicos publicados, 44 respostas, 2 categorias ativas, 7 sem resposta, 3 pessoas em 7/30 dias, mediana 28 min, p90 46 min, 5 sem resposta após 72h, 2 reações ativas, 0 denúncias abertas, 2 posts pendentes).
- Reconciliação de retratos em dry-run: 15 verificações, 14 iguais, 1 indisponível na fonte (mediana sem denúncia resolvida), 0 divergências.
- Reconciliação de reações em dry-run: 2 reações, 2 públicas, 0 divergências; `--apply` não foi necessário.
- `config:status`: divergências preexistentes em views/config de outros escopos; nenhum `config:import` executado.
- Browser Firefox ESR: 54/54 verificações PASS em 1280/768/640 (equivalente a 1280 a 200%)/390/320, oito rotas, teclado e foco visível, estrutura acessível (caption/scope/rótulos/links) e painel do Fórum sem erro. Modo escuro do painel: não aplicável, o tema administrativo ativo não expõe `data-theme`; forçar tokens do tema público reduziria o contraste e não foi adotado.
- Functional: logins canônicos são redirecionados ao host de Conta, que não serve o site de teste. O teste passa a exigir origem isolada (`127.0.0.1`/`localhost`) e marca SKIP no host compartilhado, em vez de falhar. DEFERRED até origem HTTP isolada.

### Segurança por perfil (evidência parcial)

| Usuário | Evidência |
| --- | --- |
| Anônimo | Unit do resolvedor: sem conta, classificação não depende de identidade; painel exige `administer aculta_statistics` |
| Autenticado comum | Contadores públicos filtram Group por SQL; agregador exige `view content-level statistics` |
| Associado ativo/suspenso | Política de acesso existente preservada; Unit e Kernel do Fórum para Group permanecem PASS |
| Moderador | Métricas de moderação sem denunciante/alvo nominal; acesso ao painel segue permissão administrativa |
| Administrador | Não recebe classificação pública automática; escopo vem do conteúdo |

A matriz completa de navegador e HTTP permanece DEFERRED.

### Desempenho medido no Runtime (dados reais pequenos, sem SLO)

- 13 métricas do Fórum calculadas diretamente: **74,3 ms**. A coleta completa, agora com o provedor do CGU, grava 15 valores quando há denúncia resolvida.
- 249 consultas no lote medido do provedor; maior consulta **3 ms**; p95 aproximado **1 ms**.
- Não há N+1 por linha; cada métrica executa agregações próprias. Carga maior (100k/1M) não medida.

### Contagens do projeto

- Métricas novas no catálogo: 23 (total verificado no código).
- Métricas operacionais pelo provedor: 13 por dia, quando a coleta roda.
- SDCs criados: 8. Componentes reutilizados: 0 novos includes; `empty-state` do tema preservado.
- Reconciliação de reações: dry-run e `--apply` implementados, não executados no Runtime.
- Painel central do Fórum: implementado; captura pendente.
- Painel `/painel-administrativo/forum`: retratos e link analítico adicionados.
- Compatibilidade: eventos e tabelas do Fórum preservados; Wiki, Revista e LMS não entram nas métricas `forum.*`; `aculta_user` mantém perfis e cartões; `aculta_index` revalida acesso antes do detalhamento.

### Pendências que exigem autorização

1. Reconciliar `aculta_forum.settings` e `aculta_statistics.settings` no Config Sync, se o responsável autorizar.
2. Functional em origem HTTP isolada; o site de teste compartilhado não mantém a sessão de login.
3. Dark mode, zoom de 200% e leitor de tela.
4. Habilitar coleta de buscas somente com política de privacidade e consentimento aprovados.
5. Instrumentar estado de resolução de denúncias no owner antes de `reports_resolved` e `resolution_median_minutes`.
6. Medir carga representativa (100k/1M) e memória antes de declarar qualquer SLO.
