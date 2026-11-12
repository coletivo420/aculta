# Janela estrutural do Runtime — 2026-11-12 (plano)

**Estado: PLANO. Nada deste documento foi executado no Runtime.**

- **Objetivo:** zerar o drift de configuração entre `config/sync` e o Runtime de
  desenvolvimento (`aculta_runtime`) e fechar o delta estrutural R3 pendente.
- **Base do delta:** `bf37cabf30259e8b19dfb976031794feeb85ed5d`.
- **Alvo:** `0b847baf50062528ffc834e245bd7e4679ed6092` (`origin/dev` conferido).
- **Classificação:** R3 (`config/sync/**`). Execução autônoma: **não**.
  `structural-plan` gerado em 2026-11-12T13:53:57Z.
- **Ambiente:** servidor de desenvolvimento `test`, banco `aculta_runtime`,
  remoto `coletivo420/aculta`, checkout servido compartilhado com o Apache (F4
  ainda não isolado).

## 1. O que o plano não faz

- Não instala nem desinstala módulos: `core.extension` está com o mesmo
  conteúdo no Sync e no Runtime (a diferença é só ordem de chaves).
- Não remove conteúdo, arquivos ou entidades.
- Não faz `composer install`/`update`, `pm:uninstall`, `updatedb`, `DROP` ou
  `git reset`/`clean`/`stash`.
- Não altera o checkout servido além do fast-forward já aplicado.

## 2. Inventário do drift (20 itens)

Conferido com `drush config:status --format=json` e comparação de storage
ativo × `config/sync`. Não há item **Only in DB**: o import não apaga
configuração existente só no banco.

### 2.1. Só ordem de chaves (valor equivalente)

| Config | Observação |
| --- | --- |
| `aculta420_v2.settings` | `==` verdadeiro entre ativo e Sync; `StorageComparer` usa `!==`, que considera a ordem. |
| `core.extension` | Conteúdo e módulos idênticos; listado pela comparação estrita. |
| `editor.editor.usuario_aculta` | `allowed_tags` com o mesmo conjunto, em ordem diferente. |
| `editor.editor.wiki` | Idem. |
| `views.view.aculta_albuns` | Idem. |
| `views.view.aculta_forum_admin_topics` | Idem. |
| `views.view.aculta_forum_categories` | Idem. |
| `views.view.eventos_publicos` | Idem. |
| `views.view.media_galleries` | Idem. |

Risco: baixo. O import reescreve a config na ordem do Sync e ela passa a
`Identical`.

### 2.2. Mudança de apresentação (campos visíveis/ocultos)

| Config | Mudança vinda do Sync |
| --- | --- |
| `core.entity_form_display.node.page.default` | Remove `layout_builder__layout` de `hidden` (o campo volta a aparecer no formulário). |
| `core.entity_view_display.aculta_forum_post.aculta_forum_post.default` | Remove componentes `content_moderation_control`, `entity_print_view_*` e `search_api_excerpt`; oculta `moderation_state`. |
| `core.entity_view_display.aculta_forum_post.aculta_forum_post.forum_search_result` | Idem, mais `flag_sinalizar_post_forum`. |
| `core.entity_view_display.group.lms_course.default` | Remove `field_lms_videos` de `hidden`. |
| `core.entity_view_display.node.aculta_forum_topic.full` | Remove `entity_print_view_*` de `hidden`. |
| `core.entity_view_display.node.aculta_forum_topic.teaser` | Idem. |
| `core.entity_form_display.aculta_forum_post.aculta_forum_post.default` | Remove `hidden.revision_log`; cria sem `uuid`. |

Risco: médio-baixo — altera apenas apresentação; os módulos que fornecem os
componentes removidos continuam instalados. Reversível pelo snapshot.

### 2.3. Ajuste de campo legado

| Config | Mudança |
| --- | --- |
| `field.field.node.aculta_forum_topic.field_autor_legado` | Remove `settings.handler=default` e `handler_settings`, alinhando ao `config/install` do `aculta_forum` (commit `44bdfaf57`). |

Risco: médio. O campo `entity_reference` passa a usar o handler padrão. O
valor armazenado não é alterado; conferir a autoria de um tópico após a janela.

### 2.4. Só no Sync (serão criadas)

| Config | Dependências conferidas no Runtime |
| --- | --- |
| `workflows.workflow.aculta_forum_post` | `workflows` on, `content_moderation` on, `aculta_forum` on. |
| `core.entity_form_mode.aculta_forum_post.public_submission` | Entidade `aculta_forum_post` existe (provider `aculta_forum`). |
| `core.entity_form_display.aculta_forum_post.aculta_forum_post.default` | Entidade existe; UUID novo gerado no import. |
| `core.entity_form_display.aculta_forum_post.aculta_forum_post.public_submission` | Idem. |

Risco: baixo. Configs novas, sem deleção.

## 3. Pré-requisitos e autorizações

Autorização **nominal e por operação** do responsável. Push não autoriza.

- [ ] Congelar escritores: agentes, cron e filas que gravem config, conteúdo
      ou cache durante a janela.
- [ ] Confirmar `git status` limpo e `HEAD == origin/dev` no checkout servido.
- [ ] Confirmar host, banco `aculta_runtime` e URI HTTPS de desenvolvimento
      (`bash scripts/runtime-sync.sh doctor`).
- [ ] Autorizar o dump do banco.
- [ ] Autorizar a restauração de prova em `aculta_runtime_copia`.
- [ ] Autorizar `drush config:import` no Runtime.
- [ ] Confirmar que nenhum outro agente está sincronizando o Runtime.

## 4. Ordem de execução

### Passo 1 — Congelar e reconferir (somente leitura)

```bash
bash scripts/runtime-sync.sh doctor
bash scripts/runtime-sync.sh plan --refresh
drush status
drush updatedb:status
drush deploy:hook-status
drush config:status
```

Esperado: `updatedb` 0, `deploy:hook-status` vazio, `config:status` com os 20
itens da seção 2. Abortar se houver update de banco ou deploy hook pendente.

### Passo 2 — Snapshot e prova de restauração (autorização obrigatória)

```bash
# Credenciais por option-file 0600, sem imprimir valores em log ou relatório.
# Diretório privado fora do Git e do DocumentRoot, modo 0700; arquivos 0600.
mkdir -p <backup_privado_0600>
mysqldump --single-transaction --routines --triggers --hex-blob aculta_runtime > <backup_privado_0600>/runtime.sql
sha256sum <backup_privado_0600>/runtime.sql > <backup_privado_0600>/runtime.sql.sha256

# Arquivos persistentes: sites/default/files e sites/default/settings (sem segredos no relatório).
tar -czf <backup_privado_0600>/files.tgz -C web/sites/default files
sha256sum <backup_privado_0600>/files.tgz > <backup_privado_0600>/files.tgz.sha256
```

Prova de restauração no banco descartável **pré-provisionado e autorizado**
`aculta_runtime_copia` (nunca `aculta_simpletest`):

```bash
mysql aculta_runtime_copia < <backup_privado_0600>/runtime.sql
drush --uri=<URI_TEST> sql:query "SELECT COUNT(*) FROM config;"
drush --uri=<URI_TEST> updatedb:status
```

Comparar contagens de tabelas principais (`node`, `config`, `cache_config`)
entre `aculta_runtime` e `aculta_runtime_copia` e registrar o resultado. Sem
essa prova, os passos 3 e 4 não podem começar.

### Passo 3 — Import da configuração (autorização obrigatória)

```bash
drush --uri=<URI_TEST> config:status
drush --uri=<URI_TEST> config:import -y
drush --uri=<URI_TEST> config:status
```

Critério de aceite: `config:status` sem itens `Different`, `Only in sync dir`
ou `Only in DB` para os 20 nomes da seção 2. Se o import falhar, parar em
`PARTIAL` e restaurar o snapshot; não tentar importações parciais improvisadas.

### Passo 4 — Cache, homologação e smoke

```bash
drush --uri=<URI_TEST> cr
bash scripts/runtime-sync.sh verify
```

Smoke HTTP nos dez `site_keys` (main, conta, apoio, revista, wiki420, loja,
cursos, links, eventos e forum/growroom420): status 200, um único `h1`,
`/categorias` com as categorias renderizadas e a trilha com
`aculta-u-block-start`.

Conferir também: um tópico do fórum com autoria legada, uma exibição de
`aculta_forum_post` e a página de conteúdo `page` com o campo de Layout
Builder visível no formulário.

## 5. Pós-janela e baseline

O CLI do Runtime Sync **não tem** `structural-apply` nem comando de avanço de
baseline (decisão da skill `aculta-runtime-migracoes`). Depois do import, o
`plan` continuará classificando o intervalo `bf37cabf..alvo` como R3 por
caminho, e `verify` continuará `BLOCKED` enquanto
`last_cache_success_sha != HEAD`.

Decisão necessária do responsável, em uma destas frentes:

1. Autorizar uma evolução do CLI (ex.: comando `structural-complete` que exige
   janela aprovada, snapshot, import reconciliado e registra o novo baseline).
2. Manter o R3 explícito no estado até uma janela futura que já nasça com
   baseline novo (ex.: após o cutover F4).
3. Autorizar, com registro auditável, o avanço manual do baseline apenas após
   `config:status` zerado e smoke aprovado.

Nenhuma dessas opções foi executada nesta sessão.

## 6. Rollback

- Qualquer falha em `config:import` interrompe a janela.
- Restaurar integralmente o snapshot do passo 2 (banco + `files`) e o código
  correspondente ao SHA do snapshot.
- Proibido `git reset --hard`, `git clean`, `git stash` ou SQL improvisado.
- Registrar `PARTIAL`/`FAILED` no relatório do Runtime Sync e no handoff.

## 7. Evidências a arquivar

- Hashes SHA-256 do dump e do `files.tgz`.
- Saída de `config:status` antes e depois (sem valores sensíveis).
- Saída de `updatedb:status`, `deploy:hook-status` e `verify`.
- Contagens comparadas entre `aculta_runtime` e `aculta_runtime_copia`.
- Smoke dos dez `site_keys` e capturas em
  `tests/browser/capturas/<frente>/<AAAA-MM-DD>/manifest.json`.

## 8. Fora do escopo desta janela

- Isolamento F4 (checkout de agentes × worktree do Apache).
- Composer, dependências, patches e `web/libraries`.
- Remoção de módulos ou conteúdo.
- Interface de homologação visual além do smoke acima.
