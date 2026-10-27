# Avatar padrão no `aculta_user`

Dono: `aculta_user`. Revisão: 2026-10-27. Regra transversal e inventário de
classes/hooks em
[`docs/architecture/avatar.md`](../../../../../../../docs/architecture/avatar.md).
Estado da foto e invariantes do editor em
[`FOTO-PERFIL.md`](FOTO-PERFIL.md).

## Regra

1. **Foto** da conta, quando a política pública a libera;
2. **Avatar ilustrativo do acervo**, que é o padrão e o último fallback
   obrigatório;
3. `NULL` apenas no contrato defensivo de teste. O site não tem estado "sem
   acervo" em produção.

**A inicial do nome nunca é usada** em nenhum ponto. Não crie monograma,
`slice(0, 1)`, `account-avatar-empty` nem fallback equivalente. O acervo é
componente obrigatório e sua remoção exige decisão do responsável e passa pelo
gate de foto.

## Componentes no módulo

| Componente | Papel |
| --- | --- |
| `Avatar\AvatarResolver` (`aculta_user.avatar_resolver`) | Regra única de apresentação: foto quando `$showPhoto` e `user_picture` existem; senão `AvatarPool::fallbackEntry($uid)` com `previewUrl`; senão `NULL` defensivo. Não decide policy. |
| `Avatar\AvatarPool` (`aculta_user.avatar_pool`) | Catálogo versionado `assets/avatars/meta/avatars.json`. Atribui uma cópia do acervo a `user_picture` (`public://pictures/acervo/`, `user.data.avatar_id`), guarda escolha pendente (`user.data.sorteio_pendente`), faz `reroll`/`applyPending` e fornece `fallbackEntry`/`previewUrl` para apresentação sem foto. |
| `Avatar\AvatarAssetCleanup` + `Hook\AvatarCronHooks` | Limpeza de uploads temporários, cópias do acervo sem conta e recortes órfãos. |
| `Hook\AccountCreationHooks` | Conta nova: foto OAuth vira permanente; sem foto, atribui acervo (`assignPresave`); cria o Profile de papel. |
| `Hook\PictureReplacementHooks` | Remover a foto personalizada devolve a conta ao acervo; apaga a foto antiga se ninguém mais a usa; nunca apaga cópia do acervo. |
| `Controller\AvatarRerollController` | AJAX de "Sortear novo avatar": grava só a escolha pendente e troca a prévia no modal. |
| `Form\UserPictureForm`, `Form\AvatarCropForm`, `Form\PhotoEditorGuard`, `Hook\PhotoEditorHooks`, `Hook\PhotoFlowPageHooks`, `js/photo-flow.js` | Popup de foto, estados acervo x foto pessoal, recorte do arquivo recém-enviado e fluxo sem JavaScript. A tabela de estados está em `FOTO-PERFIL.md`. |
| `PublicProfilePolicy` + `Controller\PublicProfileController` | Visibilidade campo a campo e `/u/{apelido}`; a foto é opt-in no perfil público e aparece no perfil privado conforme a policy. |
| `Identity\AuthorIdentityResolver` | `resolve()` para identidade pública; `resolveFallback()` para quem não tem perfil público (nome de exibição + acervo, sem link/foto/bio/redes); `publicDisplayName()` para consumidores que só podem ver o apelido. |
| `Identity\AuthorCardBuilder` (`aculta_user.author_card_builder`) | `build()` devolve `NULL` sem identidade pública; `buildFallback()` monta nome + acervo para contextos que já mostram o nome; `buildLegacy*()` monta a autoria histórica Growroom. |
| `Identity\AuthorCardVariantResolver` | Mapeia o host do Comment para `author_forum`, `author_comment`, `author_lms` ou `author_wiki`; bundle desconhecido não herda cartão. |
| `Hook\AuthorCardThemeHooks` | Declara `aculta_user_author_card` e mantém render neutro sem o tema. |
| `Hook\AuthorCardViewsDataHooks` + `Plugin\views\field\AuthorCard` | Campo virtual `aculta_user_author_card` em `users_field_data`; renderiza por `uid` e evita cartão vazio quando o cache da query não traz `_entity`. |

## Persistência do avatar

Há dois caminhos, ambos com a mesma regra visual:

- **Conta/editor:** o acervo é copiado para `public://pictures/acervo/` e
  gravado em `user_picture`; `user.data.avatar_id` guarda a entrada sorteada.
  Sortear troca a escolha; remover a foto pessoal volta ao acervo.
- **Apresentação pública sem foto:** `AvatarResolver` renderiza uma entrada
  estável do catálogo (`fallbackEntry($uid)` + `previewUrl`) sem gravar em
  `user_picture`. É o fallback de cartões, perfil, roster, feedback e mensagens.

Não use `user_picture` diretamente em consumidores. Use o resolver ou o builder.

## Consumidores

- **Conta/perfil:** `PublicProfileController`.
- **Fórum:** `DiscussionCommentAuthorHooks`, `PublicForumTopicAuthorHooks`,
  `PublicForumLastActivityAuthorHooks`.
- **Revista:** `aculta_magazine` `ArticleAuthorsHooks` (`author_editorial`).
- **Wiki:** a entrada não exibe autor; a discussão usa `author_wiki` por
  `DiscussionCommentAuthorHooks`.
- **LMS:** `ClassRosterManager` usa `buildFallback()` para quem não tem perfil
  público; `EducatorFeedbackAuthorCardHooks` usa `EducatorFeedbackPolicy` para
  só rotular educador como "Educador(a)".
- **Mensagens:** `aculta_messenger` `PrivateMessagePresentationHooks` usa o
  resolver para o remetente.
- **Views:** `views.view.perfis_publicos` (`/membros`) usa o campo
  `aculta_user_author_card` (`profile_compact`, `sm`).

## Privacidade

- Foto pública é opt-in (`field_exibir_foto`); perfil privado mostra foto e
  apelido; `content_editor` é obrigatoriamente público.
- Nunca públicos: CPF, RG, endereço, WhatsApp, data de nascimento, cidade, UF e
  nome de login como identidade pública.
- O avatar do acervo é imagem institucional, não dado pessoal; por isso pode
  aparecer sem opt-in.
- O cartão não recebe entidade Drupal, e-mail privado, documentos ou relações
  LMS; recebe strings/render arrays autorizados e cacheáveis.

## Verificação

- Gate `php scripts/validate-aculta-user-photo.php`: INV-FOTO-1 a 10, incluindo
  catálogo válido e ao menos uma entrada ativa com arquivo.
- Unit: `AvatarResolverTest`, `AuthorIdentityTest`,
  `PublicForumTopicAuthorHooksTest`, `AuthorCardVariantResolverTest`.
- Kernel: `ClassRosterVisibilityTest` e testes de participação/visibilidade.
- Navegador: `tests/browser/aculta-avatar/` (Firefox ESR em `DISPLAY=:0`, com
  ACULTA420 e Olivero). Roster e feedback do LMS exigem turma; sem turma no
  Runtime, a verificação visual fica DEFERRED; o Kernel cobre o roster.
- Regressão de foto: `FOTO-PERFIL.md` e a seção P de `TESTES-NAVEGADOR.md`.

## Decisões de 2026-10-27

- A inicial do nome nunca é usada; o acervo é o último fallback.
- A entrada da Wiki não exibe autor; a autoria aparece só na discussão.
- O roster mantém quem não tem perfil público com nome + acervo.
- `lms_answer_comment` pode ser do aluno dono da resposta ou de educador; só o
  educador recebe o rótulo e notifica.
- Google: foto importada na criação; reimportação sob demanda pendente.
  Growroom: importação dos avatares legados pendente.
