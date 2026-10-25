# Candidato RC — `aculta_forum 0.1.0-rc.2`

Data: 2026-10-25. Branch: `docs/aculta-forum-0.1.0-dev.1`.
Fluxo: [Dev → RC → deploy/validação Alpha → produção](../../../../../../../docs/operations/RELEASE-LIFECYCLE.md).

Este documento registra o que foi verificado **antes do deploy**. A validação
em Alpha continua sendo a etapa seguinte e depende de autorização operacional.

**Histórico:** o candidato `0.1.0-rc.1` (2026-10-24) foi retirado em
2026-10-25 porque a auditoria do roadmap encontrou fases abertas tratadas como
limites de Alpha. O componente regrediu para `0.1.0-dev.318` e só voltou a RC
depois do fechamento de F-M0–F-M7. O registro do pré-deploy anterior continua
no histórico do CHANGELOG.

## Escopo do candidato

Tópicos (`aculta_forum_topic`), respostas revisionáveis (`aculta_forum_post`),
Comments legados em coexistência durante a migração, moderação/workflow,
categorias, busca Search API access-aware, seguir/salvar, anexos e galeria,
mover tópico/comentário, painel administrativo, fila de revisões,
histórico/comparação, fóruns restritos (F-GROUP) e os produtores de aviso para
o `aculta_messenger`.

## Fechamento das fases

| Fase | Situação | Evidência principal |
| --- | --- | --- |
| F-M0 | Fechada | Matriz produtor → evento → destinatário → storage → canal → preferência → deduplicação → autorização → testes; F-D41/F-D42; OWNERSHIP |
| F-M1 | Fechada | Ledger e idempotência em Unit/Kernel do Messenger; aviso único no Runtime local |
| F-M2 | Fechada | Round-trip dos três eventos e comparação com o registro direto; sem dependência cíclica |
| F-M3 | Fechada | `follow-and-notices.py` 13/13 (seguir, aviso, leitura, filtro, teclado, 500 px, sem conversa) |
| F-M4 | Fechada | Matriz de perfis e homologação F-GROUP local (measure, busca, navegador) |
| F-M5 | Fechada em sintético e no banco local | Kernel de mapa/rollback/History/árvore; migração 2/2 e corte locais |
| F-M6 | Fechada no ambiente local | Controllers/forms em Kernel, dois temas, painel, anexo/mover, lock, busca |
| F-M7 | Fechada no pré-deploy | Functional 6/6, instalação limpa, desinstalação, gates |

A tabela detalhada e as portas operacionais estão em
[`ROADMAP.md`](ROADMAP.md#fechamento-das-fases--2026-10-25).

## Gates executados

| Gate | Resultado |
| --- | --- |
| Unit do Fórum | PASS — 184 testes / 1.332 asserções |
| Unit do Messenger (consumidores) | PASS — 261 / 2.023 |
| Kernel do Fórum (MariaDB) | PASS — 150 / 2.863 (42 depreciações de Core/contrib) |
| Functional do Fórum | PASS — 6/6, 33 asserções (rotas, formulário de tópico, lock, desinstalação) em site novo com o perfil standard |
| PHPCS Drupal/DrupalPractice no módulo | 0 erros; 1 aviso aceito (`version` no `info.yml`) |
| Rotas temporárias / Portal D11+ / slugs / Domain | PASS — 25 / 1.155 / 380 / 16 |
| `validate-project-status-index.php` | PASS — 41 componentes |
| `validate-own-module-packages.php` | FAIL global (3), todas em fixtures de Messenger e Wiki; nenhuma do Fórum |
| `drush updatedb:status` | Sem atualizações do Fórum; há updates pendentes do `aculta_messenger`, frente em andamento |

## Revisão de código e defeitos corrigidos

- **Instalação limpa:** configs de site usadas pelos módulos passam a viajar
  com eles (media type image, vocabulários, servidor Search API, body, tipos de
  perfil e `field_nickname`); `commerce_cart`/`commerce_store` e os
  submmódulos `schema_metatag` usados pelo site entram como dependência;
  schema de terceiros do `aculta_image`, dos plugins de View do Fórum,
  `image_upload` desligado, `tracker_settings` e o campo de título corrigidos.
  Antes, `drush en aculta_forum` em site novo falhava.
- **Limpeza concorrente:** `ForumAttachmentCleanup` adquire lock de banco
  (`aculta_forum_attachment_cleanup`), com release em `finally`.
- **Reconciliação de avisos ao mover subárvore:** agregado transferido e
  mesclado no destino; chaves de idempotência dos comentários movidos passam a
  apontar ao destino apenas para os destinatários transferidos; nenhum aviso
  novo.
- **Migração:** as três migrações desativam a validação de entidade no destino
  (que rodava como anônimo e recusava formato e transição) e as denúncias pulam
  linhas sem post migrado. O destino mantém as próprias checagens.

## Evidência de navegador (Firefox ESR 153.5.1 / geckodriver 0.37.1, X11)

| Suíte | Resultado |
| --- | --- |
| `follow-and-notices.py` | 13/13 |
| `accessibility-readonly.py` (ACULTA420 e Olivero, pré-deploy de 24/10) | 49/49 e 49/49 |
| `categorias-visual.py` (dois temas) | 9/9 e 9/9 |
| `authenticated-readonly.py` | 12/12 |
| `synthetic-preview-and-move.py` | 4/4 |
| `fgroup-runtime.py` (ativo e suspenso) | Ativo lê o restrito; suspenso recebe 403 |

## F-GROUP — homologação local de 2026-10-25

`fgroup-provision.php provision` criou tipo, role insider, grupo, tópico
restrito, tópico público e quatro contas. `node_access` recebeu o grant do
realm `aculta_forum_restricted`; o público ficou em `all:0`.
`fgroup-measure.php`: anônimo, ativo sem membership, suspenso e comum negados;
ativo com membership autorizado. `fgroup-search.php`: ativo vê 2, os demais 1,
com grants antes da paginação. `teardown` restaurou `restricted_group_type`
vazio e `node_access` em `all` (26 linhas). Nada foi ativado no servidor de
testes nem em produção.

## Migração e corte locais

- Backup verificado: `aculta-local-pre-cut-20261025.sql.gz` (4,4 MB).
- Migração: posts 2/2 criados, pais sem pendências, denúncias sem origem,
  reexecução sem duplicar.
- Corte: os 2 Comments legados do Fórum foram removidos; `comments=0`,
  `posts=5`; avisos permaneceram em 3→3. Rollback do lote provado em Kernel e
  restauração integral pelo dump.
- Defeito registrado em DT-F03: o wrapper do `migrate_tools` falha na migração
  de denúncias enquanto a API conclui.

## Dependências

Módulos declarados em `aculta_forum.info.yml` e habilitados no Runtime local:
`aculta_portal`, `aculta_pdf_viewer`, `aculta_image`, `aculta_user`,
`aculta_gallery`, `aculta_document_viewer`, Media, CKEditor 5, Editor, Filter,
Entity Embed, Entity Usage, CTools Views, Domain Source, Diff, Comment,
Migrate, Content Moderation, History, Flag, Group, Views, Node, Search API e
Search API DB, Taxonomy, Text, Workflows e Views Bulk Operations. Contratos
compartilhados: `aculta_messenger`, `aculta_mailer` e `aculta_user`. Sem pacote
Composer novo.

## Rollback

- Código: reverter os commits do candidato; as rotas, Views, blocos e menus do
  módulo deixam de existir.
- Configuração: `config/install` e os updates `9001`–`9005`, aditivos e
  idempotentes. O `config/sync` do site deve acompanhar as configs base
  corrigidas.
- Dados: tópicos, respostas, mídias e arquivos permanecem. Desinstalar remove a
  configuração do módulo e deixa as tabelas da entidade `aculta_forum_post`
  órfãs; só fazer com backup.
- Migração: lote revertido por `migrate:rollback`; o corte local só é
  reversível pelo dump (`aculta-local-pre-cut-20261025.sql.gz`).

## Limites conhecidos (portas operacionais, não pendência de código)

- Migração sobre dados reais: exige autorização operacional e backup no Alpha.
- Entrega de e-mail/digest e concorrência entre processos: pertencem ao
  `aculta_messenger`/`aculta_mailer`; validação no Alpha com transporte
  controlado.
- F-GROUP: homologado no local; ativação no servidor de testes e em produção
  fica para a janela autorizada (decisão de 2026-10-25).
- Aparelho físico e zoom não medidos; cobertura de 320–1440 CSS px e painel em
  1024/390 CSS px, com 500 px como mínimo real do Firefox deste ambiente.
- PHP CLI 8.4; baseline 8.5 sem validação no PHP-FPM.
- Updates pendentes do `aculta_messenger` são de outra frente e não foram
  aplicados nesta sessão.

## Checklist de Alpha

1. Autorizar e registrar o deploy do candidato no servidor de testes.
2. Rodar `drush updatedb` e importar a configuração; conferir `drush cr`,
   rotas, permissões, Views e blocos no `growroom420` e no `conta`.
3. Repetir a leitura autenticada do painel e as rotas de moderação com uma
   conta não-superusuária.
4. Validar aviso de resposta e de primeira publicação com duas contas, sem
   envio real de e-mail.
5. Autorizar e executar a migração controlada com backup, comparando contagens
   e avisos; depois o corte dos Comments legados.
6. Ativar o F-GROUP na janela autorizada, repetir a matriz de perfis e reverter
   se algum perfil não elegível receber acesso.
7. Registrar PASS/PARTIAL/FAIL e o rollback no CHANGELOG/ROADMAP do módulo.
