# Avatar padrão e caixas de usuário — auditoria (Fase 0)

Status: auditoria somente de leitura, feita sobre `main` (commit 805fb7c) na branch
`feat/avatar-padrao`. Nenhuma alteração de código. Verificação visual no servidor de
testes: DEFERRED (não executada nesta fase).

## Regra alvo (decisão do responsável)

Sem foto, todo o site se comporta como Conta/perfil:

1. foto, quando a política pública permitir;
2. avatar ilustrativo do acervo (`AvatarPool::fallbackEntry`);
3. nenhum avatar: NULL. A inicial do nome não é usada (decisão de 2026-10-27); o
   acervo é componente obrigatório e o fallback de quem não tem foto.

## Comportamento atual por ponto de exibição

| Ponto | Fonte | Variante / componente | Foto | Acervo | Inicial | Situação |
| --- | --- | --- | --- | --- | --- | --- |
| Conta / perfil público (`/u/{apelido}`) | `PublicProfileController::avatar` | `profile-identity` | sim | sim | sim | **referência** |
| Campo `user_picture` (display default e compact) | config `core.entity_view_display.user.user.*` | estilo `aculta_avatar` | sim | não | não | parcial: sem fallback |
| Cartão de autor (qualquer contexto) | `AuthorIdentityResolver` → `AuthorCardBuilder` | `author-card` | sim | **não** | sim | **divergente** |
| Revista: autores do artigo | `ArticleAuthorsHooks` → `AuthorCardBuilder` | `author_editorial` | sim | não | sim | **divergente** |
| Fórum: tópico (autor) | `PublicForumTopicAuthorHooks` | `author_forum` | sim | não | sim | **divergente** |
| Fórum: comentário / post | `DiscussionCommentAuthorHooks` | `author_forum` / `author_comment` | sim | não | sim | **divergente** |
| Fórum: última atividade | `PublicForumLastActivityAuthorHooks` | `author_forum` | sim | não | sim | **divergente** |
| Fórum: co-autores do tópico | campo `co_authors` nas displays `aculta_forum_topic` | — | não verificado | não verificado | não verificado | a verificar na Fase 1 |
| LMS: comentários de discussão | `DiscussionCommentAuthorHooks` → `AuthorCardVariantResolver` | `author_lms` | sim | não | sim | **divergente** |
| LMS: feedback do educador (comentário em resposta) | display `comment.lms_answer_comment` | — | não verificado | — | — | a verificar na Fase 1 |
| LMS: roster da turma (`ClassRosterManager::build`) | nomes em `item_list` | — | **não há** | — | — | **lacuna** (nome apenas) |
| LMS: participantes da sala (`CourseRoomBuilder`, `participant_rosters`) | rosters | — | **não há** | — | — | **lacuna** (nome apenas) |
| LMS: alunos do curso (view `lms_course_students`) | view | — | a verificar | — | — | a verificar na Fase 3 |
| Wiki: entrada (`node--wiki-entry--full` / `--teaser`) | template | — | **não há** autor exibido | — | — | **conforme** (decisão 2026-10-27: autor só na discussão) |
| Wiki: comentários | `DiscussionCommentAuthorHooks` com `author_wiki` | `author_wiki` | sim | não | sim | **divergente** |
| Mensagens privadas | `PrivateMessagePresentationHooks`, `messenger-item` | — | a verificar | — | — | a verificar na Fase 4 |
| Growroom histórico (`legacy_author`) | `AuthorCardBuilder::buildLegacy` | `author_growroom_historico` | não (sem User) | não | não | conforme: rótulo de origem |

## Diagnóstico

- **Divergência principal**: o `AuthorIdentityResolver` só usa a foto. Sem foto,
  o cartão cai na inicial, enquanto Conta/perfil mostra o acervo. Corrigir isso num
  serviço único em `aculta_user` resolve todos os cartões de uma vez.
- **Estilo `aculta_avatar`** (256×256, crop 1×1) é o que já vale para foto. O
  `aculta_avatar_pequeno` (96×96) ainda não é usado nos cartões.
- **Lacunas de exibição**: roster e sala do LMS mostram só nomes; a wiki não mostra
  autor. Esses pontos precisam de consumidor novo, não só de ajuste.
- **Privacidade**: a foto só entra quando a política pública permite. O acervo
  ilustrativo não é dado pessoal, então pode aparecer como fallback para qualquer
  conta, como já faz Conta/perfil.
- **Sem alteração de config** nesta fase: nenhuma view ou display foi criada.

## Pontos a verificar nas próximas fases

- co-autores do tópico de fórum (`co_authors`): renderiza avatar? (Fase 1)
- feedback do educador no LMS: qual display e se mostra autor. (Fase 1)
- mensagens privadas: foto e fallback no `messenger-item`. (Fase 4)
- lista de alunos do curso (`lms_course_students`): avatar nas colunas. (Fase 3)
- wiki: confirmado em 2026-10-27 que a entrada não exibe autor; a autoria aparece
  somente na discussão do verbete, pelo cartão `author_wiki`. (Fase 4)

## Próxima fase

Fase 1: serviço único de avatar em `aculta_user`, com a ordem foto → acervo
(o acervo é o fallback obrigatório; a inicial não é usada), consumido por
`AuthorIdentityResolver`, `AuthorCardBuilder` e `PublicProfileController`.

Gates aplicáveis: `validate-aculta-portal-drupal11.php`, `validate-aculta-user-photo.php`.

## Fase 1 — implementada (aculta_user 0.4.0-dev.76)

- `Avatar\AvatarResolver` (serviço `aculta_user.avatar_resolver`) é a regra única:
  foto liberada pela policy → acervo ilustrativo (`AvatarPool::fallbackEntry`) → NULL.
  A inicial não é usada em nenhum chamador; o acervo é o fallback obrigatório.
- `Identity\AuthorIdentityResolver` usa o resolver: cartões de autor de fórum,
  revista, LMS, wiki e comentários passam a mostrar o acervo quando não há foto.
- `Controller\PublicProfileController` usa o mesmo resolver em Conta/perfil. O marcador
  `data-portal-ui="public-profile-avatar-image"` foi mantido para o tema.
- O construtor de `AuthorIdentityResolver` ganhou o último argumento opcional
  (`AvatarResolver`), para não quebrar os testes existentes.

Pendências das Fases 2 a 5: SDC `author-card` único com UI Styles, Views de perfil,
aplicação por sessão (roster e sala do LMS sem avatar hoje, wiki sem autor) e verificação visual.
`LegacyMemorialController` mantém seu bloco próprio de avatar (memorial coletivo, fora desta regra).

## Regra consolidada (decisão do responsável, 2026-10-27)

Ordem pública do avatar, em todo o site:

1. **Foto** da conta, quando a política pública a libera. Inclui a foto personalizada
   enviada pela pessoa, a foto importada do Google no cadastro e, no futuro, a foto
   importada do Growroom para contas legadas.
2. **Avatar do acervo** (`AvatarPool::fallbackEntry`) quando não há foto. O acervo é
   **componente obrigatório do site** e o **último fallback**; não existe estado
   "sem acervo" em produção.

**Nunca se mostra a inicial do nome.** A remoção da foto personalizada devolve a
conta ao acervo (`PictureReplacementHooks` + `AvatarPool::reroll`). A foto do acervo
não tem "Remover" (ver `FOTO-PERFIL.md`).

### Decisões de 2026-10-27 sobre os pontos pendentes

- **Wiki — autoria da entrada:** a entrada (`node--wiki-entry--full` e `--teaser`)
  não exibe autor. O autor aparece somente na discussão do verbete, pelo cartão
  `author_wiki` dos comentários.
- **Roster da turma:** quem não tem perfil público continua na lista, com o nome
  de exibição e o avatar do acervo (`AuthorCardBuilder::buildFallback`). Ninguém
  é removido da turma por falta de perfil público.
- **Feedback do educador:** `lms_answer_comment` pode ser escrito pelo aluno dono
  da resposta e por educadores do curso. Só o educador recebe o rótulo
  "Educador(a)" e dispara a notificação; o critério é o mesmo na apresentação e
  no evento (`EducatorFeedbackPolicy::isEducator`: autor diferente do dono da
  resposta e permissão `grade students` no curso). O comentário do aluno aparece
  como cartão comum, sem o rótulo.

### Pendências registradas, fora desta tarefa

- **Google a qualquer momento:** hoje a foto do Google é importada somente na criação
  da conta. A importação sob demanda (reimportar a foto do Google depois) fica pendente
  e deve ser documentada em `docs/integrations/GOOGLE.md`.
- **Growroom legado:** os avatares das contas legadas do Growroom serão importados como
  foto personalizada. A auditoria de 2026-10-19 não incluía avatar; esta decisão a
  substitui para esse campo. O mapeamento conta→arquivo e a migração continuam
  pendentes de decisão operacional.
- **Acervo obrigatório:** o gate `scripts/validate-aculta-user-photo.php` cobre a
  presença do catálogo e de ao menos uma entrada ativa com arquivo; o acervo é o
  último fallback e qualquer remoção do componente exige decisão do responsável.

## Verificação desta tarefa (2026-10-27)

- Gates: Portal 1112 checks, foto INV-FOTO-1 a 10, SDC 632, design foundations,
  browser validators 21, Foundation 345 e shell-contract 11: PASS.
- PHPUnit Unit do `aculta_user`: 142 testes / 552 assertions PASS (1 depreciação
  PHPUnit); Unit do `aculta_lms`: 88 testes / 453 assertions PASS (8 depreciações
  PHPUnit). Kernel `ClassRosterVisibilityTest`: 1 teste / 33 assertions PASS
  (37 depreciações upstream), agora verificando o cartão de fallback do roster.
- Navegador (worktree em 127.0.0.1:8080, Firefox ESR em `DISPLAY=:0`):
  `/membros` com 15 cartões e 0 iniciais; `/u/coletivo420` com avatar do acervo e
  0 iniciais; Conta (`/dados`), Mensagens (`/avisos-mensagens/mensagens`),
  Revista, Cursos e Wiki com ACULTA420 e Olivero. O cartão funciona sem o tema
  pelo fallback neutro do módulo.
- Follow-up de 2026-10-27 (decisões desta seção): o roster não foi verificado em
  navegador porque o Runtime não tem turma; o feedback do LMS também não, pela
  mesma falta de turma. O Kernel cobre a visibilidade do roster e o fallback
  nome + acervo (sem o pool no harness). A entrada da Wiki foi conferida nos
  templates: não há autor; a discussão usa `author_wiki`.
- DEFERRED: Fórum (não há alias local `growroom420.aculta.test:8080`), roster,
  sala e feedback do LMS (o Runtime só tem um curso, sem turma) e comentários da
  Wiki (não há comentários publicados). O Kernel cobre a visibilidade do roster.
- Runtime: snapshot do `aculta_runtime` antes da escrita (SHA-256 `eb9e55f7…`,
  56 MB); update 10013 aplicado; view importada e exportada; sem alteração de
  dados de conteúdo.

