# Correção de segurança SFC-01/SFC-02 - integrada à feature

**Data:** 21/11/2026
**Repo:** `coletivo420/aculta`
**Branch atual:** `feat/forum-paridade-discourse` (PR #394)
**Base original:** `8275368f2c251916e50e32d4fdb8e5e2babfb821`, da `feat/forum-paridade-discourse` (PR #394)
**Auditoria de origem:** antiga branch `test/forum-marley-boundaries-20261121` (já integrada e excluída), arquivo `aculta_cgu/docs/AUDITORIA-SEGURANCA-FORUM-CGU-2026-11-21.md`

## Escopo integrado

Somente SFC-01 e SFC-02. As correções e testes foram incorporados à `feat/forum-paridade-discourse` em 21/11/2026, após merge da PR #395. A branch `dev` e o Runtime não foram sincronizados por esta entrega. Não se alteram `ForumPostMover`, enquetes ou serviços do agente local.

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

### Rodar na worktree existente da feature

Não criar outra branch ou worktree. Entrar na worktree já designada para `feat/forum-paridade-discourse` e executar:

```bash
scripts/tests/verify-forum-cgu-sfc01-sfc02.sh --unit

# Apenas com SIMPLETEST_DB explícito, apontando a banco descartável:
scripts/tests/verify-forum-cgu-sfc01-sfc02.sh --kernel

# Apenas com fixture isolada e estados de autenticação sintéticos:
ACULTA_QA_ISOLATED=yes \\
ACULTA_SECURITY_QA_FIXTURE=/caminho/seguro/security-fixture.json \\
  scripts/tests/verify-forum-cgu-sfc01-sfc02.sh --browser
```

A ausência de fixture produz SKIP, não PASS. Nenhuma conta ou banco do Runtime compartilhado deve ser usado.

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

- **Sem homologação local verificada:** as correções já estão na feature, mas PHPUnit/Kernel/Browser autenticado exigem execução em worktree e banco isolados antes da incorporação da feature em `dev`.
- O controle se aplica ao **Comment** original e ao host. Para comentários não publicados, o grant `view` precisa estar configurado para a moderação legítima; caso contrário o comportamento será negar por segurança.
- O site pode permitir outros mecanismos de Group/Domain; a suíte Browser deve conferir que `node->access('view')` aplica as regras concretas do ambiente.
- A suíte RED antiga foi removida da feature no commit `9bc91475` após a integração, pois instanciava um construtor obsoleto; suas verificações SFC-01/02 foram substituídas por três suítes Unit dedicadas. SFC-04 continua pendente.
- Nada aqui corrige SFC-03 a SFC-09. Não realizar novo merge/rebase/cherry-pick destes patches já integrados.

**Política atual:** trabalhar diretamente em `feat/forum-paridade-discourse`, preservar commits concorrentes e registrar gates de execução antes de integrar com `dev`.
