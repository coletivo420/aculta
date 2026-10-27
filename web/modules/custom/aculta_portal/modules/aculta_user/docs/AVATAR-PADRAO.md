# Avatar padrão e caixas de usuário — auditoria (Fase 0)

Status: auditoria somente de leitura, feita sobre `main` (commit 805fb7c) na branch
`feat/avatar-padrao`. Nenhuma alteração de código. Verificação visual no servidor de
testes: DEFERRED (não executada nesta fase).

## Regra alvo (decisão do responsável)

Sem foto, todo o site se comporta como Conta/perfil:

1. foto, quando a política pública permitir;
2. avatar ilustrativo do acervo (`AvatarPool::fallbackEntry`);
3. inicial do nome, como último recurso.

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
| Wiki: entrada (`node--wiki-entry--full` / `--teaser`) | template | — | **não há** autor exibido | — | — | **lacuna** (nenhum autor) |
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
- wiki: confirmar que nenhuma entrada exibe autor. (Fase 4)

## Próxima fase

Fase 1: serviço único de avatar em `aculta_user`, com a ordem foto → acervo →
inicial, consumido por `AuthorIdentityResolver`, `AuthorCardBuilder` e
`PublicProfileController`.

Gates aplicáveis: `validate-aculta-portal-drupal11.php`, `validate-aculta-user-photo.php`.

## Fase 1 — implementada (aculta_user 0.4.0-dev.76)

- `Avatar\AvatarResolver` (serviço `aculta_user.avatar_resolver`) é a regra única:
  foto liberada pela policy → acervo ilustrativo (`AvatarPool::fallbackEntry`) → NULL,
  que vira inicial no chamador.
- `Identity\AuthorIdentityResolver` usa o resolver: cartões de autor de fórum,
  revista, LMS, wiki e comentários passam a mostrar o acervo quando não há foto.
- `Controller\PublicProfileController` usa o mesmo resolver em Conta/perfil. O marcador
  `data-portal-ui="public-profile-avatar-image"` foi mantido para o tema.
- O construtor de `AuthorIdentityResolver` ganhou o último argumento opcional
  (`AvatarResolver`), para não quebrar os testes existentes.

Pendências das Fases 2 a 5: SDC `author-card` único com UI Styles, Views de perfil,
aplicação por sessão (roster e sala do LMS sem avatar hoje, wiki sem autor) e verificação visual.
`LegacyMemorialController` mantém seu bloco próprio de avatar (memorial coletivo, fora desta regra).
