# Papéis e hierarquia

As permissões e hooks listados abaixo descrevem o estado atual do Forum contrib
e dos conteúdos instalados. A reconstrução `aculta_forum` deve aplicar as regras
de participação ao provider/contexto correspondente e revalidá-las pela Access
API; não converter matrícula em papel global nem depender somente de esconder
links. Migração/retirada é documentada em
`aculta_forum/docs/MIGRATION-REMOVAL.md`.

Status: **hierarquia atualizada pelo responsável (2026-10-17)**. Pendências na seção 7.
Escopo: visualização, participação e moderação no Fórum e na Wiki, além da área de contas. Não substitui as políticas
de cursos, loja e wiki em `EMAIL-CONFIRMATION-POLICY.md`.

## 1. Hierarquia de papéis

Os números seguem a ordem do Drupal no projeto: **quanto menor o número, maior o privilégio**.

| Nível | Papel (machine name) | Rótulo | Como é concedido |
|---|---|---|---|
| 7 | `authenticated` | Usuário autenticado | Todo login |
| 6 | `email_confirmed` | E-mail confirmado | Automática ao confirmar o e-mail (irreversível), ou manual pelo administrador |
| 5 | `estudante` | Estudante | Usuário inscrito em algum curso; acesso a fóruns exclusivos. Concedido automaticamente na matrícula (`EnrollmentRoleHooks`) |
| 4 | `associado` | Associado | Associado ao ACULTA; acesso a fóruns exclusivos. Atribuído manualmente |
| 3 | `lms_teacher` | Professor | Professores dos Cursos Aculta (papel existente, rótulo atual "Educador") |
| 2 | `gerente_loja` | Gerente de Loja | Gerentes da loja Aculta. Cria e edita produtos (tipo `produto_loja`), variações, atributos e categorias da loja, vê pedidos e relatórios do site. Financeiro (pagamentos, gateways, envios, administração de pedidos e da loja) só com administrador. Relatório próprio de vendas previsto |
| 1 | `content_editor` | Editor de conteúdo | Administrativa, só com `email_confirmed`; moderador do Fórum, revisor e moderador da Wiki |
| 0 | `administrator` | Administrador | Administrativa; bypass total |

Um usuário pode ter mais de um papel. `estudante` é concedido na matrícula mesmo antes de confirmar o e-mail, mas só herda as permissões de `email_confirmed` depois que `EmailConfirmationPolicy` registra a confirmação real. Participar de um curso não exige confirmação de e-mail: o acesso continua condicionado à matrícula/permissão `take` do Group e ao aceite vigente dos termos.

## 2. Papel `content_editor`

- Acumula as funções de **moderador do Fórum**, **revisor da Wiki** e **moderador da Wiki**.
- **Precisa ter `email_confirmed`.** Sem ele, não recebe acesso aos níveis restritos nem poderes de moderação. Decisão de
  2026-10-16.
- Como moderador do Fórum, pode **ocultar, despublicar ou excluir** tópicos e comentários.
- Conteúdo ativo novo segue a fila de moderação e exige e-mail confirmado. O acervo histórico importado do Growroom é congelado: não pode ser editado, excluído, aprovado/desaprovado ou reaberto, inclusive por `content_editor` e `administrator` (F-D31).
- Concedido somente pelo administrador.

## 3. Papel `administrator`

- Concedido somente pelo administrador.
- Tem bypass administrativo de modo geral. A restrição F-D31 do acervo Growroom é exceção: conteúdo histórico importado permanece imutável para qualquer papel.
- É o único que atribui papéis administrativos, edita contas e confirma e-mail manualmente. Essas ações não ficam com
  nenhum outro papel.

## 4. Níveis de visibilidade do Fórum

| Nível | Quem vê |
|---|---|
| Público | Todos |
| E-mail confirmado | Papéis `email_confirmed` e acima |
| Estudante | `estudante` e moderadores (`content_editor` com `email_confirmed`) |
| Associado | `associado` e moderadores (`content_editor` com `email_confirmed`) |

Fóruns exclusivos de estudantes e de associados são acessíveis somente a quem tem o papel do nível (ou o papel superior
de moderação). O nível de associado não dá nenhuma outra função além do acesso aos seus fóruns.

## 5. Participação no Fórum

| Ação | Papel mínimo | Situação |
|---|---|---|
| Ler tópicos públicos | Anônimo | Aplicado |
| Seguir e salvar tópico | `authenticated` | Aplicado |
| Criar tópico, editar e excluir o próprio, comentar, denunciar | `email_confirmed` | Aplicado |
| Ver contêiner ou tópico de nível "e-mail confirmado" | `email_confirmed` (ou `content_editor` com `email_confirmed`) | Aplicado |
| Ver contêiner ou tópico de nível "estudante" | `estudante` (ou `content_editor` com `email_confirmed`) | Aplicado; testes unitários e smoke no Runtime de testes OK |
| Ver contêiner ou tópico de nível "associado" | `associado` (ou `content_editor` com `email_confirmed`) | Aplicado: realm pelo papel `associado`; o marcador de conta segue como fallback |
| Denunciar e ver a fila de denúncias | `content_editor` com `email_confirmed` | Aplicado |
| Ocultar, despublicar ou excluir tópico e comentário (exceto histórico) | `content_editor` com `email_confirmed` | Aplicado |

Para o bundle novo `aculta_forum_topic`, Config Sync declara as permissões Node
por bundle: `email_confirmed` pode criar, editar e excluir o próprio tópico;
`content_editor` herda as permissões próprias quando a confirmação de e-mail é
real. Moderação de tópicos por editores ainda depende de access check explícito
com essa confirmação; não conceder `edit any`/`delete any` diretamente ao papel.
As regras configuráveis de participação continuam aplicadas pelo access check
de criação. Administrador mantém o bypass nativo do Drupal.

### Moderação inicial de discussões

- O primeiro comentário novo do participante fica não publicado até aprovação.
  Enquanto a pessoa não tiver um comentário aprovado, os comentários seguintes
  em qualquer entidade Node com `field_aculta_discussion` também aguardam
  aprovação. Isso cobre Fórum público, Revista, Wiki e demais contextos que
  adotem o campo compartilhado.
- Comentários aprovados são contados entre esses contextos. Um comentário
  publicado no LMS não é moderado, mas conta como participação aprovada para a
  moderação inicial nos demais contextos e remove o bloqueio temporário de
  criação de tópico enquanto houver comentário não LMS pendente.
- A opção administrativa **Moderar comentários iniciais nos contextos de
  discussão, exceto LMS** fica em
  `/painel-administrativo/configuracoes/aculta/portal/participacao` e vem
  ativada por padrão. O bloqueio de tópico considera pendências em qualquer
  contexto não LMS, mas deixa de bloquear depois de uma participação publicada
  no LMS. O limite mínimo de criação continua contando somente comentários do
  Fórum público.
- O controle legado do bundle `comment_forum` permanece separado durante a
  retirada de `drupal/forum`. Comment direto está instalado em `article` e
  `blog` no Runtime de teste; formulário foi renderizado em memória para a
  conta de teste, mas HTTP/envio de comentário segue sem homologação.

## 6. Visitante e bots

- Quem não tem o nível exigido recebe **404**, inclusive bots. O conteúdo restrito não aparece em listagens, contagens,
  sitemap nem busca (decisão F-D15).
- Visitante **não vê o nome** do fórum restrito: o contêiner não aparece no índice do fórum nem tem página.

## 7. Confirmação manual de e-mail pelo administrador

Quem tem a permissão **confirmar email de conta manualmente** (administrador) acessa
`/painel-administrativo/contas/nao-confirmadas`, lista as contas ativas sem e-mail confirmado e confirma cada uma
em `/painel-administrativo/contas/{uid}/confirmar-email`, com confirmação explícita (formulário com token).

A ação usa a mesma política do fluxo normal: grava a confirmação do Portal e concede o papel `email_confirmed`.
Não envia nenhum e-mail. Cada confirmação é registrada no log do canal `aculta_portal` com o administrador e a conta,
**sem o endereço de e-mail**.

## 8. Autores históricos do Fórum (acervo do Growroom)

Autores legados **não são usuários do Drupal**. Não têm conta, login, perfil nem aparecem na lista de usuários do
administrador. A autoria fica na entidade própria `aculta_legado_autor`, acessível só pela permissão
**administer aculta legado**. Tópicos e comentários históricos têm uid 0 e apontam para a autoria pelo campo
`field_autor_legado`. No novo modelo, somente os bundles `aculta_forum_topic` e `aculta_discussion_post` com essa referência pertencem ao acervo Growroom imutável: não podem ser editados, excluídos, aprovados/desaprovados ou reabertos por nenhum papel. `aculta_user` nega access a update/delete/approve, fecha a discussão em todo presave e protege contra a moderação em lote do Core. A mesma referência já presente nos bundles contrib `forum` e `comment_forum` não os converte em importação Growroom; esses bundles são uma base separada, a ser removida pelo plano seguro de retirada.

## 9. Mudanças em relação à versão anterior e pendências

Mudanças em relação ao documento de 2026-10-16 anterior:

- **Associado passa a ser papel** (`associado`, nível 4), atribuído manualmente. Substitui a decisão F-D19 (associado como
  marcador sem acesso a conteúdo). O marcador de conta continua como fallback; no Runtime não há marcas a migrar.
- **Novos papéis:** `estudante`, `associado`, `lms_teacher` (Professor) e `gerente_loja` (Gerente de Loja). Não existiam antes.
- **Wiki:** `content_editor` passa a ser revisor e moderador da Wiki. A configuração atual da Wiki (`wiki_contributor`,
  concedido a `email_confirmed`) não foi alterada.

Pendências:

1. ~~Forma de concessão do `estudante`~~: decidido em 2026-10-17 — automática ao se inscrever em qualquer curso (`EnrollmentRoleHooks`).
2. ~~Forma de concessão do `associado`~~: decidido em 2026-10-17 — atribuído manualmente pelo administrador.
3. ~~Permissão de visibilidade do nível `estudante`~~: criada e aplicada no Runtime de testes (realm `aculta_estudante`).
4. ~~Migração do marcador de associado para papel~~: papel `associado` criado (`config/sync/user.role.associado.yml`) e concedendo o realm de associados. O Runtime não tinha marcas (`aculta_associado` vazia), então não há contas a migrar. O marcador segue como fallback até decisão de remoção.
5. ~~Permissões de Wiki do `content_editor`~~: a configuração atual já concede revisão e moderação da Wiki (transições de `wiki_editorial`); nada a alterar.
6. ~~Se `estudante` e `associado` exigem `email_confirmed`~~: matrícula não exige confirmação, decisão F7. Como `estudante` recebe permissões de fórum e a cadeia inclui `email_confirmed`, a herança condicional consulta `EmailConfirmationPolicy`; matrícula não marca nem herda confirmação de e-mail.
7. **Fase 2 (herança de papéis, 2026-10-17), atualizada:** cadeia unilateral e transitiva, de baixo para cima: `authenticated` < `email_confirmed` < `estudante` < `associado` < `lms_teacher` < `content_editor` < `gerente_loja` < `administrator`. `administrator` herda de todos os papéis de negócio. A herança do papel `email_confirmed` depende do marcador confirmado em `EmailConfirmationPolicy`; os demais papéis mantêm a cadeia. `wiki_contributor` e `lms_admin` herdam de `authenticated`. Edição pelo painel: ACULTA > Configurações > Papéis e perfis (`/painel-administrativo/configuracoes/aculta/papeis`), permissão `administer aculta role hierarchy`. Na mesma tela, cada tipo de perfil (`participante`, `customer`) declara os papéis que o usam (`aculta_portal.profile_roles`); o vínculo não move nem altera dados de perfil, e `customer` segue no checkout. Gate: `php scripts/validate-role-hierarchy.php`.

## 10. Implementação (hooks e classes)

Hooks são classes com `#[Hook]`, descobertas automaticamente pelo Core; não são serviços com tag de Status. Por isso o inventário de Status não as enumera (docs/portal/IMPLEMENTATIONS.md).

| Papel ou regra | Classe | Hook ou ponto de entrada |
|---|---|---|
| Estudante por matrícula em curso/turma, só alunos | `Hook\EnrollmentRoleHooks` | `group_relationship_insert` (plugin `group_membership`, tipo `lms_course` ou `lms_class`) |
| Wiki para quem confirmou o e-mail | Papel `email_confirmed` (`wiki_contributor` mesclado; sem hook) | Configuração do papel |
| Realms de visibilidade dos fóruns (papel, permissão, marcador) | `Forum\RestrictedForumGrants` | `grantsFor()` consumido pelos hooks de acesso de fórum |
| Acesso e listagens dos fóruns restritos | `Hook\RestrictedForumHooks` | node access e entity access |
| Associado: concessão e marcador de conta | `Associado\AssociadoMarker`, `Form\AssociadoMarkForm` | fallback do realm de associados |
| Relatório de associados (só administrador) | `Controller\AssociadosController` | `/painel-administrativo/associados/relatorio` |
| Relatório de vendas (gerente de loja) | `aculta_portal` `Controller\SalesReportController` | `/painel-administrativo/loja/vendas`, permissão `view commerce_order` |
| Herança de papéis (Fase 2) | configuração `role_inheritance.settings` | mapa em `config/sync/role_inheritance.settings.yml` |

Testes: `aculta_user/tests/src/Unit/EnrollmentRoleHooksTest.php`, `RestrictedForumGrantsTest.php`; `aculta_portal/tests/src/Unit/Controller/SalesReportControllerTest.php`.

## 8. Painel de papéis (tela única)

- **Página:** `/painel-administrativo/configuracoes/pessoas/papeis` é a página "Papéis e perfis" do ACULTA. Substituiu a lista de papéis do Core: cadeia de papéis (arrastar ou setas), coluna "Herda de (efetivo)", coluna "Operações" (editar, permissões e excluir, com o mesmo menu do Core), botão "Adicionar papel" e tabela dos papéis fora da cadeia.
- **Permissão:** `administer permissions` (Core) ou `administer aculta role hierarchy`.
- **Herança sem contrib:** o módulo `role_inheritance` foi desinstalado e removido do `composer.json`. A herança é do `aculta_user` (`HerancaDePapeis`, `RoleInheritanceHooks`, `RoleInheritanceCurrentUserSubscriber`), calculada a partir de `aculta_portal.role_hierarchy` (só `cadeia`). Documentação: `aculta_user/docs/HIERARQUIA-DE-FUNCOES.md`.
- **Herança efetiva:** a coluna "Herda de (efetivo)" mostra a herança transitiva calculada por `RoleHierarchy::efetivos`.
- **Tela de herança do contrib:** a rota `role_inheritance.config_role_inheritance` sai da coleção de rotas (sem aba nem link).
# Participação e discussões contextuais

As permissões e hooks listados abaixo descrevem o estado atual do Forum contrib
e dos conteúdos instalados. A reconstrução `aculta_forum` deve aplicar as regras
de participação ao provider/contexto correspondente e revalidá-las pela Access
API; não converter matrícula em papel global nem depender somente de esconder
links. Remoção/migração fica em `aculta_forum/docs/MIGRATION-REMOVAL.md`.

