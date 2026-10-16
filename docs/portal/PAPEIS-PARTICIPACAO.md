# Papéis e hierarquia

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
| 2 | Gerente de Loja | Gerente de Loja | Gerentes da loja Aculta. Papel ainda não criado: aguarda a definição das permissões (seção 9) |
| 1 | `content_editor` | Editor de conteúdo | Administrativa, só com `email_confirmed`; moderador do Fórum, revisor e moderador da Wiki |
| 0 | `administrator` | Administrador | Administrativa; bypass total |

Um usuário pode ter mais de um papel. Se `estudante` e `associado` exigem ou não `email_confirmed`, é pendência (seção 9).

## 2. Papel `content_editor`

- Acumula as funções de **moderador do Fórum**, **revisor da Wiki** e **moderador da Wiki**.
- **Precisa ter `email_confirmed`.** Sem ele, não recebe acesso aos níveis restritos nem poderes de moderação. Decisão de
  2026-10-16.
- Como moderador do Fórum, pode **ocultar, despublicar ou excluir** tópicos e comentários.
- **Exceção**: conteúdo de autoria histórica (acervo do Growroom, seção 6) não pode ser moderado pela fila atual. A regra
  é aplicada no ponto único de acesso (`ForumModerationHooks`).
- Concedido somente pelo administrador.

## 3. Papel `administrator`

- Concedido somente pelo administrador.
- Tem **bypass total**: pode tudo, inclusive moderar conteúdo histórico e ver restritos.
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
| Ver contêiner ou tópico de nível "associado" | `associado` (ou `content_editor` com `email_confirmed`) | Aplicado no código como marcador; migrar para papel pendente |
| Denunciar e ver a fila de denúncias | `content_editor` com `email_confirmed` | Aplicado |
| Ocultar, despublicar ou excluir tópico e comentário (exceto histórico) | `content_editor` com `email_confirmed` | Aplicado |

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
`field_autor_legado`. Conteúdo com esse campo preenchido não é moderado pela fila atual.

## 9. Mudanças em relação à versão anterior e pendências

Mudanças em relação ao documento de 2026-10-16 anterior:

- **Associado passa a ser papel** (nível 2), não marcador. Isso **substitui a decisão F-D19** (associado como marcador
  sem acesso a conteúdo). Confirmar antes de implementar.
- **Novos papéis:** `estudante` (nível 3). Não existia antes.
- **Wiki:** `content_editor` passa a ser revisor e moderador da Wiki. A configuração atual da Wiki (`wiki_contributor`,
  concedido a `email_confirmed`) não foi alterada.

Pendências:

1. ~~Forma de concessão do `estudante`~~: decidido em 2026-10-17 — automática ao se inscrever em qualquer curso (`EnrollmentRoleHooks`).
2. ~~Forma de concessão do `associado`~~: decidido em 2026-10-17 — atribuído manualmente pelo administrador. Ainda é marcador administrativo em `/painel-administrativo/associados`; migração para papel segue na pendência 4.
3. Permissão de visibilidade do nível `estudante`: criada no código (realm `aculta_estudante`).
4. Migração do marcador de associado para papel, e o tratamento das contas já marcadas.
5. Permissões de Wiki de revisão e moderação do `content_editor`.
6. Se `estudante` e `associado` exigem `email_confirmed`, como o `content_editor`.
