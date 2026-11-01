# Hierarquia de funções (papéis) — aculta_user

Status: em vigor desde 0.4.0-dev.22 (herança portada do módulo contrib `role_inheritance` 2.1.1; contrib removido).
Decisões do responsável: `PAPEIS-PARTICIPACAO.md` (seções 7 e 8). Este documento descreve como o sistema funciona.

## 1. O que a hierarquia faz

Um papel herda as permissões de todos os papéis abaixo dele na cadeia. A herança é transitiva, com uma condição de segurança para o papel `email_confirmed`: a herança automática desse papel só ocorre quando `EmailConfirmationPolicy` registra a confirmação real da conta. Um papel `email_confirmed` atribuído diretamente pelo administrador continua explícito.
Exemplo: `gerente_loja` herda de `content_editor`, que herda de `lms_teacher`, e assim por diante até `authenticated`.
Quem tem `gerente_loja` recebe, sem gravação extra, as permissões de todos os papéis abaixo.

## 2. Onde fica a configuração

- Fonte: `aculta_portal.role_hierarchy` (`cadeia`). Exportada em `config/sync/aculta_portal.role_hierarchy.yml`.
- `cadeia`: lista do mais alto ao mais baixo. Cada papel herda do papel imediatamente abaixo.
- Não há papel lateral nem categoria (removidos em 0.4.0-dev.98): papel fora da cadeia herda do autenticado.
- Administrador (`administrator`) herda de todos os papéis do site. Não aparece na cadeia.
- Anônimo (`anonymous`) não entra na hierarquia.
- O mapa efetivo não é gravado: é derivado a cada uso (`HerancaDePapeis::mapa()`).

Cadeia vigente (2026-10-17):
`gerente_loja > content_editor > lms_teacher > email_confirmed > authenticated`

`estudante` e `associado` são categorias de usuário representadas por perfis; não participam da herança de papéis. Cada conta pode ter ambos.
Os papéis `wiki_contributor` e `lms_admin` foram mesclados em `email_confirmed` e `lms_teacher` (ver PAPEIS-PARTICIPACAO.md).
A confirmação real do e-mail e a matrícula em curso ou turma criam a categoria de perfil `estudante`; isso não concede permissões de `email_confirmed`. O marcador de `EmailConfirmationPolicy` é a fonte de verdade da confirmação.

## 3. Como se edita

`/painel-administrativo/configuracoes/pessoas/papeis`, página "Papéis e perfis" do ACULTA (substitui a lista de papéis do Core).
Permissão: `administer permissions` ou `administer aculta role hierarchy`.
- Ordem: arraste pelas alças ou use as setas (`#tabledrag` do Drupal, mesmo padrão da tela de blocos). Os pesos definem a ordem.
- A coluna "Herda de (efetivo)" mostra a herança transitiva calculada.
- Operações de cada papel (editar, permissões, excluir): mesmo menu do Core. "Adicionar papel" leva à criação de papel do Core.
- Papéis fora da cadeia (anônimo, administrador e qualquer papel novo) aparecem na tabela "Fora da cadeia".
- Ao salvar, a configuração é validada: ciclo ou autorreferência é recusado.

## 4. Ciclo de vida de um papel

| Evento | Comportamento | Onde |
|---|---|---|
| Carregar conta (`user_load`) | Soma à conta, em memória, os papéis herdados. | `RoleInheritanceHooks::userLoad` |
| Salvar conta (`user_presave`) | Retira os herdados que não estavam gravados no banco. Não grava herança. | `RoleInheritanceHooks::userPresave` |
| Formulário de usuário | Não marca como selecionado um papel herdado. | `RoleInheritanceHooks::formUserAlter` |
| Usuário logado (requisição) | Estende os papéis da sessão, com prioridade 299. | `RoleInheritanceCurrentUserSubscriber` |
| Criar papel novo | Sem gravação: papel fora da cadeia herda do autenticado. | `RoleHierarchy::derivarMapa` (via `mapa()`) |
| Excluir papel | Sai da cadeia. | `RoleInheritanceHooks::userRoleDelete` → `HerancaDePapeis::removerPapel` |

## 5. Comparação com o módulo contrib (`role_inheritance` 2.1.1)

| Função do contrib | Equivalente no aculta_user | Teste |
|---|---|---|
| `RoleInheritanceMap` (árvore, mapa achatado, `extendRoles`) | `HerancaDePapeis::mapa`, `herdados` + `RoleHierarchy::efetivos`, `herdados` | `RoleHierarchyTest`, `HerancaDePapeisTest` |
| `RoleInheritanceMap::pruneInheritedRoles` / `pruneParentRoles` | Não portado: nenhuma chamada no site | — |
| `role_inheritance_user_load` | `RoleInheritanceHooks::userLoad` | `RoleInheritanceHooksTest::testCarregarAdicionaOsHerdados` |
| `role_inheritance_user_presave` | `RoleInheritanceHooks::userPresave` + `herdadosNaoGravados` | `RoleInheritanceHooksTest::testSalvarRetiraSoOHerdadoNaoGravado`, `HerancaDePapeisTest::testHerdadosNaoGravadosIgnoraOQueEstaNoBanco` |
| `role_inheritance_form_user_form_alter` / `_submit` | `RoleInheritanceHooks::formUserAlter`. O submit do contrib não foi portado: o presave já trata o caso | — |
| `role_inheritance_user_role_insert` | Sem hook: o papel novo entra como lateral do autenticado em `completar` | `RoleHierarchyTest::testCompletarMantemPapelNovoComoLateralDoAutenticado`, `HerancaDePapeisTest::testPapelNovoSemCategoriaHerdaDoAutenticado` |
| `role_inheritance_form_user_role_delete` | `RoleInheritanceHooks::userRoleDelete` → `removerPapel` → `completar` | `RoleHierarchyTest::testCompletarRemoveOPapelExcluidoEDeixaCategoriaOrfaNoAutenticado`; teste de integração no Runtime (seção 6) |
| `role_inheritance_form_user_role_form_alter` (opções de herança no formulário do papel) | Substituído pela tela Papéis e perfis | `RoleHierarchyForm` (teste HTTP no Runtime) |
| `CurrentUserRoleShim` (REQUEST 299) | `RoleInheritanceCurrentUserSubscriber` | verificado no Runtime (página administrativa como administrador e como gerente) |
| `role_inheritance_form_user_admin_permissions_alter` (mostra permissões herdadas) | **Não portado**: é só apresentação, não afeta acesso | — |
| `role_delegation` (alterações de formulário) | Não aplicável: módulo não usado | — |
| Funções `_role_inheritance_*` marcadas como deprecadas | Não portadas | — |

## 6. Testes

Unitários (rodar sem o Runtime):
- `web/modules/custom/aculta_portal/tests/src/Unit/Role/RoleHierarchyTest.php` — cadeia, laterais, administrador, reordenação, herdados, completar (exclusão e papel novo).
- `web/modules/custom/aculta_portal/modules/aculta_user/tests/src/Unit/Role/HerancaDePapeisTest.php` — mapa, herança transitiva, não gravados.
- `web/modules/custom/aculta_portal/modules/aculta_user/tests/src/Unit/Hook/RoleInheritanceHooksTest.php` — carregar e salvar.

Comando: `php vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_user/tests/src/Unit`

Gates: `validate-role-hierarchy.php` (cadeia decidida, sem ciclo, administrador herda de todos, linha de base de `hasRole`),
`validate-aculta-user-roles.php`, `validate-aculta-portal-drupal11.php`.

Integração (Runtime de teste, `aculta.toca.net.br`, banco `aculta_runtime`): ver registro na CHANGELOG do aculta_user, 0.4.0-dev.23.

## 7. Como estender

- Novo papel: crie em Papéis do Drupal; ele entra como lateral do autenticado até ser colocado na cadeia ou em uma categoria.
- Mudança de cadeia: sempre pela tela; depois atualizar `PAPEIS-PARTICIPACAO.md` e o gate no mesmo commit.
- Acesso novo: conceda permissão ao papel certo. Não use `hasRole('nome')` para liberar funcionalidade (ver `GUARDRAILS.md`).

## 8. Limitações conhecidas

- Não há destaque visual de permissões herdadas na tela de permissões do Drupal (contrib tinha).
- Alterações feitas direto em `config/sync` dos papéis não recalculam nada até o próximo carregamento; use a tela.
