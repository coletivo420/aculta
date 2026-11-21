> **FDC-R14 incluído (2026-11-20):** [Sincronização automática de curtidas por mensagem e por usuário](PLANO-REACOES-SINCRONIZADAS-BLOCOS-2026-11-20.md) com testes T-62..70. Um clique no brócolis/descurtida/troca/Undo atualiza a mensagem e os cartões/perfil do usuário visíveis **na mesma página**, via HTML autoritativo do POST HTMX, sem segundo GET. Inclui cabeçalhos, emojis e cartões normal/espelhado; respetar cache tags, ACL, perfis e histórico. Implementação inicial remota, aceites Runtime/Browser DEFERRED. **Não restringir o agente a curtidas: concluir todo o Fórum FDC-R0..R14.**

> **CONTINUIDADE EM CÓDIGO 2026-11-20:** [Handoff integral para agente CLI](HANDOFF-AGENTE-CLI-FORUM-COMPLETO-2026-11-20.md) inclui execução de **FDC-R0..R13 e T01..57**, implementação de moderação frontend, Game, Statistics, Index, enquetes e todas as suítes Studio. Patch adicional `2eb08c16` endureceu redirect same-origin do POST e fixou brócolis como quick-like padrão; testes escritos mas ainda sem execução Runtime. O popin só contém positivos + Undo; a descurtida 👎🏽 segue no botão próprio. Esta regra substitui formulações antigas que diziam sem Undo ou propunham descurtida no popin.

> **AMPLIAÇÃO FDC-R9 a R13 (2026-11-20):** [auditoria de Game, curtidas, tópicos, comentários, troféus e integrações ACULTA](ESTUDO-DISCOURSE-GAME-METRICAS-INTEGRACOES-2026-11-20.md). Usar o `aculta_game` + `aculta_user` existentes para troféus 10/25/50 do Discourse e o `aculta_statistics` para métricas, sem rank/XP/papéis por popularidade. Distinguir tópicos criados de abertos/visualizados e like de outras reações. **Status: planejado; não há homologação.** FDC-R0..R8 e ST-OP continuam precedência de segurança.

> **COMPLEMENTO 2026-11-20:** a camada de interfaces operacionais frontend pertence ao **`aculta_studio`**. A fila de moderação comunitária será na **aba Fórum do Marley**, e não em um workspace independente no Growroom. Popups de tópico/enquete permanecem contextuais na página pública. Hoje `ForumTab` redireciona a `aculta_user.denuncias_forum` administrativo, pendente de substituição. [Plano Studio](../../aculta_studio/docs/PLANO-FRONTEND-OPERACIONAL-2026-11-20.md).

# Plano executivo - Paridade Discourse com moderação frontend, novo papel Moderador e enquetes

**Data:** 2026-11-20. **Branch:** `feat/forum-paridade-discourse` (PR #394). **Snapshot da inspeção:** `0c3cad8112173c26753c37cac057860758c48fd9`. **Natureza:** plano prospectivo baseado em código e pesquisa Discourse, não execução de Runtime. Atualizar HEAD e baseline antes de implementar.

**Política superior:** [frontend operacional em todas as suítes e backend exclusivo a administradores](../../../../../../../docs/portal/FRONTEND-OPERACIONAL-BACKEND-EXCLUSIVO-ADMIN-2026-11-20.md). Este plano corrige o roteiro FDC-00..11; se houver conflito, estas decisões mais recentes prevalecem. Não converter comentários históricos Growroom em ForumPost, nem mesclá-los com comentários normais.

## 1. Regras aprovadas

- Backend (`/admin/**`, `/painel-administrativo/**`, todas as rotas administrativas equivalentes) **exclusivo do papel administrator** em todo o Portal. Moderadores, content_editor, Educadores e gerentes não acessam backend. Interface operacional de cada área pertence a seu **frontend**: Fórum/Growroom, Wiki/WE Sativa, Revista/Marley, LMS/GanjaLab, Loja/Green Stash, Eventos/Culturas e demais suítes.
- Criar papel Drupal `moderador` com rótulo Moderador, inferior a `content_editor` em capacidades. Permissões finas de moderação comunitária atribuídas explicitamente; não dar `access administration pages`, `access content overview`, `administer comments`, `administer aculta forum posts` ou bypass de entidade. Inicialmente papel seguro e sem poderes de mutação até que ACL/rotas frontend estejam prontos.
- `content_editor` conserva autoria/revisão editorial por frontend, e pode receber também `moderador` sem herança automática. A moderação pedagógica LMS pertence **exclusivamente à política própria do LMS**, via Group/matrícula/turma e Educador; não integrar fila de pedagogia à fila global CGU.
- Todos os controles contextuais de moderação devem se abrir e concluir **dentro dos popups SDC**, inclusive validação, confirmações e feedback; fallback sem JS não pode redirecionar não administrador ao backend. O administrador usa os **mesmos serviços de ações** no backend e pode operá-los no frontend; permissões distintas de entrada, ACL de conteúdo sempre respeitada.
- **Enquetes nativas:** no popup de criação de tópicos adicionar opção `Adicionar enquete`, com formulário de opções, voto único/múltiplo, encerramento, resultados e integração `aculta_statistics`. Código próprio em `aculta_forum` preferencialmente, sem pacote Discourse Poll ou módulo contrib por padrão.

## 2. Referências Discourse (pesquisa de 2026-11-20)

- [Guia de moderação 2026, Parte 3](https://meta.discourse.org/t/discourse-moderation-guide-part-3-managing-content/406266) - ferramentas contextuais para tópico, Review Queue, slow mode, fechamentos e ações de aprovação/denúncia.
- [Nova Review Queue](https://meta.discourse.org/t/a-new-review-queue-layout-with-all-new-features/388194) - separação entre metadados, notas, linha do tempo e ações; oferta com controle de ativação e releases subsequentes.
- [Enquetes - criar e administrar](https://meta.discourse.org/t/creating-and-managing-polls/77548) - construtor por menu + do compositor, votação única/múltipla, opções, resultados e encerramento; ranqueada/números são opções avançadas.
- [Discourse, auditoria de fechamento de enquete, 2026](https://meta.discourse.org/t/who-closed-the-poll/405495) - identificação de quem/por que fechou ou reabriu; reproduzir trilha de ação ACULTA.
- [Rich editor e edição de enquetes, setembro de 2026](https://meta.discourse.org/t/edit-dates-polls-tables-and-more-in-the-rich-text-editor/411468) - direção UX, não implementar shortcode/HTML como fonte.
- [Plugin Poll upstream](https://github.com/discourse/discourse/tree/main/plugins/poll) - **somente referência funcional**, sem porte de código Ruby/Ember.

Paridade de *comportamento* e UX, não cópia de stack, telas pixel a pixel ou privilégios administrativos equivalentes. O ACULTA intencionalmente difere: separa moderador comunitário de administrador e moderação pedagógica LMS da global.

## 3. Código observado e lacunas reais

| Código | Evidência observável | Ação de continuidade |
| --- | --- | --- |
| `TopicAdminPopupController` + `moderation-action.twig` | popup monta ações com links comuns | Transformar em formulários e POST dentro do diálogo via Form API/HTMX |
| `TopicModerationPin/Close/Archive/PublishForm` | persistem estado mas redirecionam para node | Extrair comando compartilhado, responder com atualização do fragmento |
| `TopicSplitForm` / `TopicMergeForm` | movem `comment`; novo compositor usa `aculta_forum_post` | Corrigir primeiro, transação/locks, preservação de revisões/permalinks |
| `DiscussionCommentModerationForm` | modal HTMX para Comments | Reusar SDC/serviço, não presumir suporte a ForumPost |
| `ForumPostModerationQueueController` e `ForumPostModerationAccess` | workflow revisionável na administração | Criar superfície frontend para moderador, restringir backend a administrator |
| `TopicPopupLayout::KEEP` | preserva título/corpo/categoria, elimina `field_aculta_tags` | Corrigir tags e inserir campos do Poll Builder |
| `config/sync/user.role.content_editor.yml` | access administration pages, administer comments/posts e revisões amplas | Revisão transversal de grants e migração para frontend antes de revogar |
| `config/sync/user.role.lms_teacher.yml` | também tem access administration pages | Migrar tarefas de Educador para GanjaLab e fechar backend |
| `DiscussionHostAdminHooks` | gatilho em consumidores baseado em `administer comments` | ACL por provider/host; LMS jamais herdado |
| `aculta_cgu::ReportService` | decisão de denúncia não aplica ação editorial tipada | Adapter de execução explícita, auditada, sem automatismo implícito |
| `aculta_statistics::ForumMetricsProvider` | tem contagens/estoques de Fórum; eventos e FST prévios | Integrar enquete via catálogo/recorder existentes, sem analytics paralelo |
| `aculta_forum_legacy` | addon histórico existe, migração e isolamento pendentes | Não tocar arquivo, não permitir votação/moderação em ARCHIVE |

A existência de classe/rota/story não é homologação. Status: **código parcial**, Kernel/Functional/browser/Runtime não comprovados para fluxo integrado.

## 4. Papéis e autorização - contrato de destino

| Papel | Frontend Fórum | Frontend editorial Wiki/Revista | Frontend GanjaLab | Backend |
| --- | --- | --- | --- | --- |
| Público/membro | Leitura/criar/votar conforme elegibilidade | Participação autorizada | Somente matrícula/curso | Não |
| **moderador** | Moderação comunitária por popups e fila frontend, conforme capacidade por ação | Nada por herança | Nada por herança | **Não** |
| **content_editor** | Só se receber capacidade comunitária explícita; edição normal é outra função | Autoria, revisão e moderação editorial autorizadas pelo host | Nada por herança | **Não** |
| **lms_teacher** | Participante comum, se elegível | Nada por herança | Moderação pedagógica contextual (Group/turma/curso) | **Não** |
| **administrator** | Capacidades institucionais respeitando origem de conteúdo | Administração institucional | Administração institucional | **Sim** |

Drupal não herda permissões entre roles por peso. `moderador` fica abaixo de `content_editor` em escopo e peso de exibição -3 (content_editor -4 no snapshot), **sem se tornar editor**. Compatibilizar os serviços existentes com novas permissões `moderate aculta forum topics`, `moderate aculta forum posts`, `moderate aculta forum comments`, `review aculta forum reports`, `reorganize aculta forum topics` e `close aculta forum polls`; nomes propostos até conferir colisões. Não usar `administer comments` para viabilizar o moderador. Exclusão física, políticas de conta e alterações de configuração permanecem admin-only.

**CGU:** mantém casos/denúncias/decisão e trilha; workspace de moderação comunitária no Studio Marley, aba Fórum, separado da moderação de publicação da Revista/Wiki e do trabalho pedagógico do LMS. O motor de comentário shared fornece ações/popup/formatter, não autoridade universal.

**Backend:** proteger *todas* as rotas administrativas efetivas de Core/contrib/custom e seus endpoints auxiliares, e não apenas prefixos ou menus. Auditar `user.role.*`, `_admin_route`, `access administration pages`, `access content overview`, `view the administration theme`, edição/entidade, VBO, revision e API. Testar bypass uid 1 explicitamente. Proteger por policy de route access/permits e revisar exceções deliberadas. Migração de `content_editor`/`lms_teacher`/`gerente_loja` para UI operacional frontend **antes** de retirar grants amplos. Uma página admin não deve ser reutilizada como fallback de popup.

## 5. Serviços de moderação únicos, UI por contexto

Pipeline: **popup frontend / workspace comunitário / painel admin** -> access policy (role+purpose+origin+host+Group) -> command (Node tópico, ForumPost revisionável, Comment contextual elegível) -> transação+locks+idempotência -> entidade/workflow -> auditoria CGU/Forum -> invalidar cache -> eventos mínimos Statistics/Messenger -> fragmento HTMX ou fallback Form API. **LMS** chama contratos de discussão genérica quando apropriado, mas possui **seus próprios comandos pedagógicos e ACL**.

- GET/HEAD nunca mutam; POST+CSRF e autorização novamente no momento da gravação; erro preserva formulário/foco/valores; atualização de fragmento e anúncio acessível após salvar.
- SDC `aculta420_v2:popup`, `topic-admin-controls`, `comment-admin-controls`, `discussion-admin-controls`, `moderation-action`, `moderation-submit`; novos SDC só quando lacuna concreta. Backend e frontend usam comandos, não links para o painel no frontend.
- `ForumPost` tem revisões/workflow e precisa aprovar/rejeitar/editar moderadamente via popups equivalentes, sem conceder `administer aculta forum posts` ao moderador.
- Split/merge devem operar sobre ForumPost CURRENT, manter parent/thread_position, URLs, revisões, assinaturas, anexos, flags, caches, contadores e auditabilidade; não mover material histórico. Enquete associada à origem não deve migrar implicitamente sem contrato.
- CGU decide denúncia separadamente da ação editorial (despublicar/reverter/etc), com autor/id/código de ação/estado anterior e posterior/tempo e motivo restritos. Não gerar nome de usuário persistido; referenciar UID e renderizar nome atual.

## 6. Enquetes: desenho de MVP e proposta de evolução

**Compositor do tópico:** opção `Adicionar enquete` dentro do popup existente. Toggle desligado por padrão; expandir pergunta, 2 a 20 opções, ordenação, escolha única ou múltipla (min/max quando múltipla), data de encerramento opcional em fuso explícito e política de resultados (`após votar` ou `ao encerrar`). Prévia no mesmo popup, validação Form API/HTMX, confirmação acessível, fallback sem JS. Corrigir tags invisíveis nesse mesmo pacote.

**Storage nativo em `aculta_forum`:** propor `aculta_forum_poll` (entidade revisionável referenciando um tópico corrente), opções `aculta_forum_poll_option` com ordem e ID imutável depois do voto e cédulas+seleções normalizadas com UNIQUE(poll_id,uid) e UNIQUE(ballot_id,option_id), índices e FK/Entity API coerentes. Avaliar entidades versus tabelas de cédulas após benchmark; não guardar votos em body HTML, shortcode, Flag, KeyValue ou `aculta_statistics`. Uma enquete por tópico no MVP; restringir a node CURRENT, não Comment/ForumPost/arquivo histórico/LMS. Criar Node+enquete em transação, sem registros órfãos; idempotência no envio duplo.

**Voto:** autenticação e participação conforme política vigente, acesso read/write ao tópico, estado publicado/aberto e enquete aberta; transação+lock para concorrência, um voto por conta, escolha dentro dos limites. Mudança de voto desligada no MVP. Não registrar IP/UA; UID necessário operacionalmente para impedir voto duplo, mas resultados públicos nunca exibem eleitores. Depois do primeiro voto, bloquear edição semântica da pergunta/opções/modo (ou exigir nova enquete em versão futura). Encerramento automático e manual, registrar UID do ator para ação humana e marcador de sistema em auto-close, horário, razão e audit trail; reabertura exige política/ação explícita. Uma enquete despublicada, arquivada ou removida não recebe votos.

**Resultados:** barra e números com alternativas, votos totais e percentuais; política de visibilidade revalidada a cada leitura. Votantes únicos ≠ total de seleções em modo múltiplo; denominadores explícitos. SDC novos apenas se inexistentes: `forum-poll-builder`, `forum-poll-card`, `forum-poll-results`, schemas/stories/UI Patterns Library, cores via tokens, dark/light, teclado/foco, sem cor única de significado. Modo número/ranked-choice, enquete em respostas e consumidores, múltiplas enquetes, votos nominais, imagens e alterações dinâmicas ficam **P2/P3 sob decisão futura**.

## 7. Integração aculta_statistics (preferência código próprio)

- `aculta_forum` é fonte de verdade operacional: enquete/opções/cédulas, acesso e totalizações imediatas transacionais.
- `aculta_statistics` é owner do catálogo de métricas, recorder, ledger, séries/snapshots, auditoria de reconciliação e dashboards; não criar subsistema paralelo.
- **Candidatos de catálogo**, validar com `MetricCatalog` e FST: `forum.polls.created` (evento uma vez), `forum.polls.closed` (evento), `forum.polls.active_current` (snapshot), `forum.polls.ballots_current` (snapshot), `forum.polls.first_ballots` (eventos deduplicados), `forum.moderation.actions_applied` (agregado por classe de ação autorizada). Não somar snapshots como eventos; não considerar seleção múltipla como número de votantes.
- Eventos idempotentes após confirmação de escrita. Replay/republicação/edição/duplicidade não criam votos ou incrementos novos. Analytics não contém identidade do eleitor, título, texto de opção ou target sensível.
- Painel do admin tem dimensões autorizadas e análise agregada; perfil do moderador só recebe indicadores comunitários autorizados no frontend, sem revelação de denunciante ou contagem capaz de identificar votantes por diferenças em grupos pequenos. FST integra Index e reconciliação, respeita política do arquivo.
- Não usar eventos de voto para conceder XP/participação automaticamente, nem notificar cada votante por Messenger sem contrato de produto.

## 8. Roadmap sequencial revisado

| Fase | Prioridade | Trabalho | Gate de aceite |
| --- | --- | --- | --- |
| **FDC-R0** inventário de privilégios e frontend por módulo | P0 | HEAD, roles/grants, rotas admin e AJAX, flows content_editor/LMS/loja/wiki/revista, estado config ativo | Matriz de URLs e tarefas, baseline+rollback; nenhuma quebra silenciosa |
| **FDC-R1** novo Moderador e backend admin-only | P0 | Role seguro, capacidades granulares, deny backend a todos não-admins, migrar permissões amplas em fases | 403/404 GET/POST/HTMX/JSON inclusive URLs diretas e cache quente; admin preservado |
| **FDC-R2** frontend operacional nas suítes | P0 | Mover fluxos de Wiki/Marley/GanjaLab/Commerce/Culturas e outros para interfaces próprias | Usuário elegível completa tarefa em sua suite sem backend; ACL e owner preservados |
| **FDC-R3** moderação comunidade pelo frontend | P0 | Workspace CGU e popup tópico/ForumPost/Comment, comandos comuns, log de ações e decisão separada | Ação dentro do popup, sem redirect admin, GET não muta, CSRF, acessibilidade, sem JS |
| **FDC-R4** integridade ForumPost e CGU | P0 | Split/merge reais de posts atuais, transações e locks, fixar/fechar/arquivar/publicar, Index/Messenger/Statistics | Rollback sem resultado parcial; arquivo imutável; todas as métricas e URLs coerentes |
| **FDC-R5** enquete MVP | P1 | Entidades/storage, toggle no popup, Form API, voto, limites, fechar/editar, resultado, SDC | Criação+enquete atômicas; um voto por conta; ACL, concorrência, estados de tópico e UX |
| **FDC-R6** Analytics de enquete | P1 | Catálogo/ledger/snapshot/reconciliação, agregados de moderação, relatórios | Replay sem contagem dupla; privacidade e reconciliação comprovadas |
| **FDC-R7** paridade Discourse restante | P2 | Top/New/Unread, tags, timeline, draft, tracking, slow mode, fechamento agendado, notas e ações lote | Matriz UI/ACL/browser/Stats e performance |
| **FDC-R8** Release | P0/P1 | Unit+Kernel+Functional+Browser+Playwright, PHPStan10, PHPCS, CI e Runtime autorizado | Sem P0/P1 aberto; rollback testado; publicação somente após gates e autorização |

**Regra de execução:** o novo role pode existir em Config Sync com grants mínimos antes do Runtime. Não atribuir ele a pessoas como moderador funcional antes de R1/R3 e revisão das permissões. DB/update/config import/deploy requerem autorização operacional e homologação própria.

## 9. Testes adicionais vinculantes

- T-29: cada usuário não administrador (`moderador`, `content_editor`, Educador, gerente e combinados) recebe 403/404 em qualquer rota backend real, GET/POST/HTMX/JSON; administrador continua acessando; testagem de session, cache e acesso de entidade.
- T-30: Moderador isolado vê workspace da aba Fórum do Studio Marley e finaliza decisão de ForumPost sem abrir backend; sem `administer comments` e sem editoria global.
- T-31: Content_editor mantém tarefas editoriais Wiki/Revista **no frontend** e não entra no backend; autorizações permanecem distintas de moderação comunitária.
- T-32: Educador LMS modera somente grupo/turma autorizada em GanjaLab; moderador comunidade e editor não alcançam pedagogia; revogação de matrícula bloqueia até POST já aberto.
- T-33: todos os consumidores com operação autenticada possuem interface frontend sem link/redirect para admin; admin e usuário da área não recebem widgets indevidos de outras suites.
- T-34: cada ação de tópico/ForumPost/Comment abre popup, valida, confirma e atualiza fragmento, com foco/aria, CSRF e fallback sem JS.
- T-35: split/merge/move sobre ForumPost real com rollback transacional e preservação de revisões, threads, flags, permalinks e contagens; Comment histórico não sofre alteração.
- T-36: criação de tópico com `Adicionar enquete` opcional; ao habilitar 2..20 opções, única/múltipla, prazo, resultados, validação e atomicidade. Sem JS funcional.
- T-37: voto duplo, concorrente, cross-user e target privado; POST não duplica, seleção inválida falha, fechado/tombstone/arquivado negam.
- T-38: resultado privado e voto nominal não vazam, escolha múltipla contabiliza cédulas e seleções corretamente; closing audit registra ator/tempo/motivo.
- T-39: Statistics eventos deduplicados, snapshots reconciliados, 0 nomes/UID/opções no catálogo público, sem variação que infira escolhas individuais.
- T-40: pacote administrativo segue admin-only mesmo quando o usuário possui `moderador` + `content_editor` e permissões editoriais Core preexistentes.

**Status de todos os testes:** PLANNED, até execução e evidência. Sem acesso ao Runtime nesta alteração.
