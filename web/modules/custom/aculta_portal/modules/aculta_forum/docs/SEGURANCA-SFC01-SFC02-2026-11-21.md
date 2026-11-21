# Correção independente de segurança SFC-01/SFC-02

**Data:** 21/11/2026
**Repo:** `coletivo420/aculta`
**Branch exclusiva:** `fix/forum-cgu-sfc01-sfc02-20261121`
**Base original:** `8275368f2c251916e50e32d4fdb8e5e2babfb821`, da `feat/forum-paridade-discourse` (PR #394)
**Auditoria de origem:** `test/forum-marley-boundaries-20261121`, arquivo `aculta_cgu/docs/AUDITORIA-SEGURANCA-FORUM-CGU-2026-11-21.md`

## Escopo isolado

Somente SFC-01 e SFC-02. Nenhum patch é aplicado na PR #394 ou `dev`, nenhum Runtime é sincronizado e não se alteram regras de `ForumPostMover`, enquetes ou outros serviços em construção pelo agente local.

## SFC-01 - Comments

`CommentModerationAccess::access()` preserva o cabeçalho HTMX como requisito de apresentação, **sem tratá-lo como autorização**.

`canModerate()` exige:
- Conta autenticada e permissão administrativa de Comments ou permissão específica de Comments do Fórum.
- Bundle `aculta_discussion_post` hospedado em `node`, campo `field_aculta_discussion`.
- Exata correspondência entre o host da discussão e o domínio funcional da requisição: `aculta_forum_topic/forum`, `wiki_entry/wiki`, `revista/magazine`, `aculta_lms_topic/courses`.
- Moderação da role Fórum limitada a conteúdo do Fórum; em Wiki/Revista/LMS, a rota só considera `administer comments` **e** o grant de moderação do respectivo `DiscussionContext`.
- Origem `CURRENT` no Fórum e `CONTEXTUAL` nos demais hosts, negando Growroom/arquivo e `UNKNOWN`.
- No Fórum, `field_domain_source` vinculado ao purpose `forum`.
- `comment->access('view')`, `host->access('view')` e `context->getReadAccess()`, com propagação de cacheability da entidade, origem, política e Group via entity access.
- Falha fechada quando o resolvedor de contexto encontra providers conflitantes.

`DiscussionCommentModerationForm::buildForm()` revalida `canModerate()` antes de mostrar os controles. `submitForm()` faz `loadUnchanged()` do Comment e revalida **antes** de despublicar, restaurar, marcar tombstone ou excluir. Isso impede reutilizar um formulário já aberto após revogação de Group, sempre que o access system refletir a revogação.

## SFC-02 - Popup de tópicos

A rota `aculta_forum.topic_admin_popup` agora combina a permissão de movimentação existente com `_custom_access: aculta_forum.topic_admin_popup_access:access`, `_aculta_site_key: forum`, `no_cache: true` e GET/HEAD apenas.

`TopicAdminPopupAccess::access()` exige usuário autenticado, o grant de movimentação, bundle `aculta_forum_topic`, domínio ativo Forum, `field_domain_source` de Forum e `node->access('view', account, TRUE)` para respeitar grants de Group.

`TopicAdminPopupController::view()` revalida a autorização antes de carregar autor, título, contadores ou histórico. Pareceres CGU só são consultados quando `ModerationScope::allows(account, 'forum')`, filtrados por `purpose='forum'`, e cada entidade exige `report->access('view')`. O render sensível é não-cacheável para evitar mistura entre contas, domínios e membership.

## Testes publicados (código, execução pendente)

- `CommentModerationBoundaryTest`: permissão Fórum apenas no purpose certo; Wiki/Revista/LMS; papéis mistos; Group revogado, comment inacessível, Domain errado, Growroom/arquivo; HTMX forjável e administrador contextual.
- `TopicAdminPopupBoundaryTest`: role, UID, contexto, Domain, bundle, view access e contrato de rota/cache.
- `CommentModerationFormBoundaryTest`: contrato de injeção de serviço, reload e validação de POST.
- `tests/browser/qa-security-forum-cgu/`: especificação read-only para verificação HTTP dos mesmos cenários, com cookies e IDs somente em manifesto privado e ambiente descartável.

### Rodar no worktree isolado (não na branch do agente)

```bash
git fetch origin
git worktree add ../aculta-sfc-qa origin/fix/forum-cgu-sfc01-sfc02-20261121
cd ../aculta-sfc-qa
vendor/bin/phpunit web/modules/custom/aculta_portal/modules/aculta_forum/tests/src/Unit/Security/CommentModerationBoundaryTest.php
vendor/bin/phpunit web/modules/custom/aculta_portal/modules/aculta_forum/tests/src/Unit/Security/TopicAdminPopupBoundaryTest.php
vendor/bin/phpunit web/modules/custom/aculta_portal/modules/aculta_forum/tests/src/Unit/Security/CommentModerationFormBoundaryTest.php

cd tests/browser
ACULTA_SECURITY_QA_FIXTURE=/caminho/privado/security-fixture.json \
  npx playwright test --config qa-security-forum-cgu/playwright.config.mjs
```

**Não use** este comando Playwright contra banco compartilhado ou contendo identidades reais. A ausência de fixture produz SKIP, não PASS.

## Aceite obrigatório antes de integrar na PR #394

| Caso | Gate |
| --- | --- |
| GET HTMX e POST para Comment do Fórum autorizado | Permitido somente com grant, leitura e contexto corretos |
| GET HTMX/POST para Comment Wiki/Revista/LMS com grant só Fórum | Negado, sem efeito colateral |
| Group revogado entre abrir e enviar formulário | Negado no POST, sem tombstone ou mudança de status |
| Comment Growroom/legacy | Negado na rota moderna |
| Tópico restrito, domínio incorreto ou bundle não Forum | Popup 403/404 |
| Usuário tem mover, mas não pode moderar CGU | Popup sem `decision_notes` ou PII CGU |
| Dois usuários com grupos distintos | Respostas e cache não compartilham metadados |
| PHPUnit/Browser e static quality | PASS documentado, não apenas código de testes |

## Limites e riscos residuais

- **Sem execução verificada:** código e testes foram commitados via GitHub; nesta sessão não houve PHPUnit/Browser, banco Drupal ou análise de logs do Runtime. Exigir execução real no Homelab antes de merge.
- O controle se aplica ao **Comment** original e ao host. Para comentários não publicados, o grant `view` precisa estar configurado para a moderação legítima; caso contrário o comportamento será negar por segurança.
- O site pode permitir outros mecanismos de Group/Domain; a suíte Browser deve conferir que `node->access('view')` aplica as regras concretas do ambiente.
- O arquivo RED da auditoria original também trata SFC-04, ainda pendente; não incorporá-lo como gate do pacote SFC-01/02.
- Nada aqui corrige SFC-03 a SFC-09. Rebase/cherry-pick sobre a PR #394 só após revisão de concorrência dos arquivos.

**Política:** enquanto o agente local estiver ativo, manter PR de segurança em draft e **não efetuar merge**.
