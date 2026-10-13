# ACULTA User: verificação U0

Fase U0 do plano `docs/portal/ACULTA-USER-PLAN.md` (seção 6 e seção 9, item 1). Escopo: somente verificação, sem código de feature.

- Branch: `aculta-user-u0`, criado a partir de `origin/main` (`f853347`).
- Ambiente: Homelab (Runtime). Drupal Core **11.4.8**, Drush 13.8.0.0, banco `aculta_runtime` conectado, perfil `standard`, tema padrão `aculta420`.
- PHP do host: **8.4.26**. O projeto exige PHP 8.5 (`DT-P04`, ainda aberta). Os resultados abaixo valem para o PHP 8.4 do host.
- `composer install`: concluiu sem erro ("Nothing to install"; `vendor/`, `web/core` e `web/modules/contrib` já estavam presentes e de acordo com o `composer.lock`).

Convenções: **CONFIRMADO** = a afirmação se sustenta na evidência lida. **CONTRARIADO** = a evidência mostra outra coisa. **NÃO CONFIRMADO** = não há evidência suficiente. Quando a evidência é só de leitura de código, isso é dito na linha.

## 1. Tabela de verificação

| # | Afirmação | Evidência (arquivo:linha) | Resultado |
| --- | --- | --- | --- |
| 1 | Flag 5.1.1 aceita a entidade `user` como alvo (tipo `entity:user`) | `web/modules/contrib/flag/src/Plugin/Flag/UserFlagType.php:15` (`id = "entity:user"`, `entity_type = "user"`) | **CONFIRMADO** (leitura de código). No Runtime não há flag de usuário configurada; o tipo existe, mas não foi usado. |
| 2a | Pathauto gera alias para `user` | `web/modules/contrib/pathauto/src/Plugin/Deriver/EntityAliasTypeDeriver.php:81` (exige rota `canonical` e campo `path`). `web/modules/contrib/pathauto/src/Hook/PathautoEntityHooks.php:82-83` (campo `path` só para tipos em `enabled_entity_types`). `config/sync/pathauto.settings.yml` e Runtime (`pathauto.settings:enabled_entity_types`) listam `user`. Rota canônica do user: `web/core/modules/user/src/Entity/User.php:64` | **CONFIRMADO** (código e configuração). |
| 2b | Pathauto resolve token do perfil `participante` para `user` | `web/modules/contrib/profile/profile.tokens.inc:15-25` (registra cada tipo de perfil como token de `user`, tipo `profile`); `:32-70` (`[user:participante:<campo>]` via `loadByUser`). Tipo `participante` em `config/sync/profile.type.participante.yml`; campo `field_nickname` em `config/sync/field.field.profile.participante.field_nickname.yml` | **CONFIRMADO** (código). Não executado: nenhum alias de usuário foi gerado no Runtime. |
| 2c | Já existe padrão de alias para `user` | `config/sync/pathauto.pattern.*`: não há padrão de `user` (só `aculta_activity`, `aculta_article`, `aculta_document`, `aculta_page`, `aculta_produto`, `aculta_project`, `group_relationship`, `wiki_entry`) | **CONFIRMADO**: o padrão de `user` precisa ser criado (configuração nova). |
| 2d | Unicidade do alias gerado | `web/modules/contrib/pathauto/src/AliasUniquifier.php:91-93`: alias repetido recebe sufixo numérico incremental. Atualização após U0: o apelido precisa de validação própria, não do Pathauto | **PARCIAL**: o comportamento de sufixo está confirmado por leitura; a geração real de alias e a unicidade não foram testadas no Runtime (teste em U1). |
| 3a | Views lista revisões de `node` por `revision_uid` na tabela `node_field_revision` | `revision_uid` é campo de revisão do `node` (`web/core/modules/node/src/NodeStorageSchema.php:43-44`, tabela `node_revision`). Campo é `entity_reference` para `user` (`web/core/lib/Drupal/Core/Entity/RevisionLogEntityTrait.php:44-47`). Runtime: `revision_uid` existe em `node_revision`; `SHOW COLUMNS FROM node_field_revision LIKE 'revision_uid'` não retornou coluna | **CONTRARIADO** na premissa da tabela: a coluna está em `node_revision`, não em `node_field_revision`. |
| 3b | Caminho de relacionamento para listar revisões com o autor | Base de revisões traduzível = `node_field_revision` (`web/core/modules/views/src/EntityViewsData.php:255-258`), com join para `node_revision` por `vid` (`:260-266`). Campo `entity_reference` gera relationship para a tabela de usuários (`web/core/modules/views/src/EntityViewsData.php:660-665`) | **CONFIRMADO** (código). Caminho: `node_field_revision` → `node_revision` (vid) → `users_field_data` (`revision_uid`). Não criada View de teste. |
| 4a | `skip comment approval` define o status padrão de comentário por papel | `web/core/modules/comment/src/CommentForm.php:163`; `web/core/modules/comment/src/Entity/Comment.php:250` (callback do status) e `:551-552` (`getDefaultStatus`) | **CONFIRMADO**. É exceção por papel, não por pessoa. |
| 4b | A permissão bloqueia **só o primeiro** comentário, sem hook | A permissão não tem contagem. Não há regra nativa de "primeiro comentário" no Core (as três ocorrências acima são as únicas que decidem o status) | **CONTRARIADO**. Bloquear só o primeiro exige lógica de entidade (presave OOP). Hook específico a definir em U1; não verificado. |
| 5a | Existe discussão ou comentário associado a `lms_activity` | `lms_activity` = `web/modules/contrib/lms/src/Entity/Activity.php:18`. Sem campo `comment` em `Activity.php` nem em `Lesson.php` (busca por `comment`/`forum` sem ocorrência). Runtime: nenhum campo `comment` fora de `node.comment_forum` e `comment_body` | **NÃO EXISTE** (ver seção 2: parada). |
| 5b | Existe campo de comentário em curso ou em lição | Curso = tipo de grupo `lms_course` (`config/install/group.type.lms_course.yml` do LMS), sem campo de comentário. Lição (`Lesson.php`) sem campo de comentário. Runtime sem campo de comentário nesses tipos | **NÃO EXISTE**. |
| 5c | O LMS tem algum campo de comentário | Único campo de comentário do LMS: `comments` em `lms_answer` (`web/modules/contrib/lms/modules/lms_answer_comments/src/Hook/AnswerCommentsHooks.php:40-41`), no submódulo `lms_answer_comments`. Esse submódulo **não está habilitado** no Runtime (`core.extension`, lista de módulos ativos) | **CONFIRMADO**: existe comentário em **resposta** (`lms_answer`), não em atividade, e o submódulo está inativo. |
| 5d | `lms_activity` é a "atividade" do LMS, distinta do nó `activity` do Portal | `lms_activity` em `Activity.php:18`. Nó `activity` em `config/sync/node.type.activity.yml` (padrão de URL `aculta_activity` em `config/sync/pathauto.pattern.aculta_activity.yml`). Tipos de atividade do LMS: só `no_answer` e `true_false` (`config/sync/lms.lms_activity_type.*.yml`) | **CONFIRMADO**: são entidades diferentes. |
| 6a | Comentário em tópico `forum` atualiza `forum_index` | `web/modules/contrib/forum/src/Hook/ForumHooks.php:343-356` (`comment_update`/`comment_insert` chamam `updateIndex` quando o comentado é `node`). `web/modules/contrib/forum/src/ForumIndexStorage.php:93-118` (atualiza `comment_count` e `last_comment_timestamp`) | **CONFIRMADO** (código). Não executado no Runtime. |
| 6b | Comentário em nó que não é `forum` gera erro | O hook não checa o tipo do nó. A consulta de `updateIndex` filtra `field_name = 'comment_forum'` e junta `forum_index` (`ForumIndexStorage.php:95`), então sem linha em `forum_index` o `UPDATE` (`:117`) não afeta linhas | **CONFIRMADO por leitura**: sem erro esperado. Não testado com comentário real no Runtime. |
| 7 | `DT-P13`: "sem tipo de flag para `comment`" (`docs/operations/DEBT-REGISTER.md`) | `web/modules/contrib/flag/src/Plugin/Flag/CommentFlagType.php:14` (`id = "entity:comment"`) | **CONTRARIADO**. O tipo existe no Flag 5.1.1. Não há flag de comentário configurada no Runtime. O registro de dívida deve ser corrigido. |

## 2. Parada pedida pela tarefa

A tarefa pede parar e reportar, antes de propor código, se não houver discussão nem comentário em `lms_activity`. **Essa condição ocorreu** (itens 5a e 5b). Não foi proposto código.

O que isso muda no plano:

- Seção E7 (`Discussão do LMS`) e a decisão 34 ("a discussão de cada atividade do LMS é um comentário no Fórum") partem de uma premissa que o LMS 1.2.3 instalado não fornece. A atividade não tem campo de comentário. Para existir discussão por atividade, é preciso criar um campo de comentário (ou um tipo de discussão) em `lms_activity`, o que é uma decisão de desenho do responsável, não uma implementação de U1.
- Alternativas que dependem de decisão do responsável: (a) criar campo de comentário `comment_forum` em `lms_activity` e ligá-lo ao tópico de Fórum; (b) usar o tópico de Fórum vinculado ao curso ou à lição, sem campo na atividade; (c) manter a discussão do LMS fora desta etapa.
- Decisão 24 (tipos de conteúdo com discussão vinculada) continua em aberto.

## 3. Gates

| Gate | Comando | Resultado |
| --- | --- | --- |
| Padrão Drupal 11+ do Portal | `php scripts/validate-aculta-portal-drupal11.php` | **PASS** (659 verificações; exit 0). |
| Slugs públicos | `php scripts/validate-public-slugs.php` | **PASS** (338 rotas; 20 violações de contrib na linha de base DT-P20; exit 0). |

Nenhum gate já falhava antes da verificação. Nenhuma correção foi feita.

## 4. Limites desta verificação

- Itens 2d, 3 (consulta real), 6b e a geração de alias não foram executados no Runtime. Foram verificados por leitura de código.
- PHP 8.4 no host. A verificação de runtime em PHP 8.5 segue pendente (`DT-P04`).
- Não foi criada View nem alias de teste, e nenhum comando de escrita foi executado no banco do Runtime.
- Nenhum `drush updb`, `drush cim` ou alteração de configuração foi feita.

## 5. Próxima fase

- **U1** (submódulo `aculta_user`: campos `field_bio` e `field_visibilidade_publica`; apelido único em `field_nickname` como caminho `/u/<apelido>`; `PublicProfilePolicy`; `PublicProfilePresenter`; fallback sem perfil público). A decisão 3 foi respondida (apelido é o caminho; não há `field_slug`), e a decisão 2 está parcial. A **rota** `/u/<apelido>` depende da decisão 5 (purpose e host). Sem ela, a U1 pode implementar só dados, política de privacidade, presenter e testes de Kernel, sem registrar rota pública.
- **U2 e a integração de discussão do LMS** dependem da decisão sobre o item 5 (seção 2) antes de qualquer código.
- Pendências de decisão: decisão 5 (purpose de `/u/`); decisão 2 (campos públicos, cidade e UF); decisão 24 (tipos com discussão vinculada); alternativa para discussão de atividade do LMS (seção 2); autorização do update hook de contas existentes (decisão 43).

## 6. Atualizações após U0 (2026-10-13)

Registradas no mesmo relatório para que ele não fique desatualizado.

- **Slug do perfil:** `/u/<apelido>` está no `main` (commits `7cda8e8`, `c3e2969`, `a0863c5`), como exceção registrada em `FRIENDLY-PORTUGUESE-SLUGS.md`. Não existe rota no código ainda.
- **Unicidade do apelido:** `config/sync/field.field.profile.participante.field_nickname.yml` não tem restrição de unicidade; `aculta_portal` não valida unicidade (`PortalHooks.php:450-490` só restringe campos do formulário). Decisão de desenho: a validação fica no formulário e no cadastro, antes do Pathauto.
- **Correções ao plano:** `/autores/<nome>` removido de `FRIENDLY-PORTUGUESE-SLUGS.md`; decisões 41 a 44 movidas para a seção 7; seções 8 e 9 atualizadas (o bloqueio de 2026-10-13 não existe mais).
- **Passo 2 da ordem de implementação (papéis), parcial:** branch `aculta-user-papeis`, commit `0fc367a`, `RUNTIME STATUS: DEFERRED`.
  - `content_editor` recebe as permissões de `wiki_editor`, `administer comments` e `edit any forum content`; não recebe `administer forums`.
  - `wiki_editor` recebe `revert wiki_entry revisions`.
  - Gate novo `scripts/validate-aculta-user-roles.php`: PASS no `config/sync`; teste negativo em cópia (sem `revert wiki_entry revisions` em `wiki_editor`) reprovou com código 1.
  - Permissões conferidas contra o registro de permissões do Runtime (leitura via `php:eval`): nenhuma ausente.
  - Não importado: não há `drush cim` nem `updb` sem autorização. O `config:status` mostra drift antigo já registrado (DT-O10), que não foi tratado.
- **Pendente no passo 2:** `wiki_contributor` para quem tem `email_confirmed`. O papel `email_confirmed` tem só `skip CAPTCHA` (`config/sync/user.role.email_confirmed.yml`). Drupal não mapeia papel para papel, e a E5 do plano diz "não há sincronização automática entre papéis". Conceder exige código (por exemplo, hook OOP na atribuição do papel) ou outra decisão do responsável. Não implementado.

## 7. Gates desta atualização

| Gate | Resultado |
| --- | --- |
| `validate-aculta-portal-drupal11.php` | PASS (659) |
| `validate-public-slugs.php` | PASS (338 rotas; 20 da linha de base DT-P20) |
| `validate-module-theme-boundary.php` | PASS (704) |
| `validate-environment-addresses.php` | PASS (1336 arquivos) |
| `validate-aculta-user-roles.php` | PASS (14 permissões de `wiki_editor` replicadas em `content_editor`) |
