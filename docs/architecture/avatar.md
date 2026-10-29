# Avatar e identidade de autoria

Dono: `aculta_user`. Revisão: 2026-10-27. Regra normativa; histórico de fases
fica no Git. A implementação detalhada do módulo está em
[`AVATAR-PADRAO.md`](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/AVATAR-PADRAO.md)
e o estado da foto em
[`FOTO-PERFIL.md`](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/FOTO-PERFIL.md).

## 1. Regra única

Em todo ponto que mostra uma pessoa, a ordem é:

1. **Foto pessoal** da conta, quando a política pública a libera;
2. **Avatar do acervo selecionado** (`public://pictures/acervo/`), sempre
   visível por ser institucional e escolhido no editor de foto;
3. **Avatar ilustrativo estável do acervo** (`AvatarPool`), como fallback
   obrigatório;
4. `NULL` apenas como contrato defensivo de teste/base sem acervo. Não existe
   estado "sem acervo" em produção.

**A inicial do nome nunca é usada.** Não crie letra, monograma, `slice(0, 1)`,
`account-avatar-empty` nem fallback equivalente em módulo, tema ou JavaScript.
O acervo é componente obrigatório do site; removê-lo exige decisão do
responsável e passa pelo gate de foto.

Esta regra substitui o fallback por inicial registrado em
`0.4.0-dev.9` do [histórico arquivado de aculta_user](../history/changelog/aculta_user.md).

A mesma regra vale para Conta/perfil, cartões de autor, Fórum, Revista, LMS
(roster, sala e feedback), Wiki, Mensagens privadas, memorial histórico e
Views que mostrem pessoas.

**Decisão permanente (2026-10-29):** a foto ou avatar selecionado tem uma única
fonte persistida por conta: `user_picture`. `user.data.avatar_id` identifica a
entrada escolhida no acervo, e `sorteio_pendente` guarda uma prévia não salva;
nenhum deles é uma segunda fonte de apresentação. `field_exibir_foto` é somente
uma regra de visibilidade pública. Módulos, purposes e perfis não criam cópias
paralelas de campos de avatar; todos consomem a identidade central.

## 2. Fronteiras e donos

| Capacidade | Dono | Onde |
| --- | --- | --- |
| Catálogo, escolha, persistência e fallback do avatar | `aculta_user` | `src/Avatar/`, `src/Identity/`, hooks de conta e foto |
| Estilos `aculta_avatar` e `aculta_avatar_pequeno`, renderer e remoção de arquivo | `aculta_image` | `ImageCatalog`, `ImageRenderer`, `FileRemover` |
| Política de visibilidade do perfil | `aculta_user` | `PublicProfilePolicy`, `PublicProfileController` |
| Cartão de autoria neutro e variantes | `aculta_user` | `AuthorIdentityResolver`, `AuthorCardBuilder`, `AuthorCardVariantResolver` |
| Apresentação do cartão e do avatar | ACULTA420 | SDC `author-card`, templates e CSS |
| Consumo em contexto | Fórum, Revista, LMS, Wiki, Messenger | hooks e builders próprios, sem duplicar a regra |

O módulo entrega dados, policy e render arrays neutros; o tema não decide
visibilidade nem fallback. Consumidores não leem `user_picture` nem Profile
diretamente para montar avatar: usam `aculta_user.avatar_resolver` ou
`aculta_user.author_card_builder`.

Fonte de verdade dos dados: [data-ownership.md](data-ownership.md).

## 3. Componentes

### 3.1 Núcleo do avatar (`aculta_user`)

| Componente | Serviço/arquivo | Responsabilidade |
| --- | --- | --- |
| `AvatarResolver` | `aculta_user.avatar_resolver` / `src/Avatar/AvatarResolver.php` | Regra única de apresentação: foto pessoal quando `$showPhoto` é verdadeiro; avatar escolhido no acervo mesmo sem opt-in de foto; senão `AvatarPool::fallbackEntry($uid)` com URL de prévia do acervo; senão `NULL` defensivo. Não decide policy da foto pessoal: recebe `$showPhoto` já calculado. |
| `AvatarPool` | `aculta_user.avatar_pool` / `src/Avatar/AvatarPool.php` | Catálogo `assets/avatars/meta/avatars.json` (entradas ativas com arquivo). Atribui uma cópia do acervo a `user_picture` na criação/remoção/reroll (`public://pictures/acervo/`, `user.data.avatar_id`); oferece prévia pendente (`sorteio_pendente`), `reroll`, `applyPending`, `fallbackEntry` estável por UID e `previewUrl`. |
| `AvatarAssetCleanup` | `src/Avatar/AvatarAssetCleanup.php` | Remove uploads temporários vencidos, cópias do acervo sem conta e recortes órfãos. |
| `AvatarRerollController` | `src/Controller/AvatarRerollController.php` | AJAX do botão "Sortear novo avatar": grava apenas a escolha pendente e troca a prévia no modal. |
| `UserPictureForm` | `src/Form/UserPictureForm.php` | Popup da foto: distingue avatar do acervo de foto personalizada, remove botão de remoção no acervo e oferece sorteio. |
| `AvatarCropForm` | `src/Form/AvatarCropForm.php` | Recorte da foto personalizada recém-enviada; não recorta foto já salva nem avatar do acervo. |
| `PhotoEditorGuard` | `src/Form/PhotoEditorGuard.php` | Aplica as regras de estado do popup (acervo x foto pessoal). |
| `AccountCreationHooks` | `src/Hook/AccountCreationHooks.php` | Na conta nova, torna permanente a foto do OAuth ou atribui uma entrada do acervo a `user_picture` (`assignPresave`); cria o Profile de papel no `user_insert`. |
| `PictureReplacementHooks` | `src/Hook/PictureReplacementHooks.php` | Ao remover a foto personalizada, devolve a conta ao acervo; apaga a foto pessoal substituída quando ninguém mais a usa e nunca apaga cópias do acervo. |
| `AvatarCronHooks` | `src/Hook/AvatarCronHooks.php` | Executa a limpeza de assets no cron. |
| `PhotoEditorHooks` / `PhotoFlowPageHooks` | `src/Hook/` | Montam o editor de foto no painel e anexam `js/photo-flow.js`; o recorte abre só para arquivo recém-enviado. |

### 3.2 Identidade e cartões (`aculta_user`)

| Componente | Serviço/arquivo | Responsabilidade |
| --- | --- | --- |
| `PublicProfilePolicy` | `src/PublicProfilePolicy.php` | Decide, campo a campo, o que aparece: perfil privado mostra foto e apelido; perfil público segue opt-in de foto, bio, e-mail e redes; `content_editor` é obrigatoriamente público; sem apelido não há identidade. |
| `PublicProfileController` | `src/Controller/PublicProfileController.php` | Página `/u/{apelido}` no purpose `main`; usa `AvatarResolver` com o item `foto` da policy. |
| `AuthorIdentity` | `src/Identity/AuthorIdentity.php` | View-model escalar e cacheável: variante, nome, link opcional, avatar, ano, bio e redes autorizadas. |
| `AuthorIdentityResolver` | `aculta_user.author_identity_resolver` / `src/Identity/AuthorIdentityResolver.php` | `resolve()` para identidade pública (apelido/link/policy/avatar); `resolveFallback()` para quem não tem perfil público (nome de exibição + acervo, sem link/foto/bio/redes); `publicDisplayName()` para consumidores que só podem ver o apelido. |
| `AuthorCardBuilder` | `aculta_user.author_card_builder` / `src/Identity/AuthorCardBuilder.php` | `build()` devolve `NULL` sem identidade pública; `buildFallback()` monta o cartão nome + acervo para contextos que já mostram o nome; `buildLegacy()`/`buildLegacyForum()` montam a autoria histórica Growroom sem User. |
| `AuthorCardVariantResolver` | `aculta_user.author_card_variant_resolver` / `src/Identity/AuthorCardVariantResolver.php` | Mapeia o bundle do host para `author_forum`, `author_comment`, `author_lms` ou `author_wiki`; bundle desconhecido não herda cartão. |
| `PublicMemberIdentityInterface` | `src/Identity/PublicMemberIdentityInterface.php` | Expõe somente o apelido aprovado pela policy; o nome interno da conta não é fallback público. |

### 3.3 Hooks, plugins e Views

| Ponto | Implementação | Responsabilidade |
| --- | --- | --- |
| `hook_theme` | `AuthorCardThemeHooks` | Declara `aculta_user_author_card` e mantém render neutro quando o tema não estiliza. |
| `views_data_alter` | `AuthorCardViewsDataHooks` | Declara o campo virtual `aculta_user_author_card` em `users_field_data`. |
| Campo de Views | `Plugin\views\field\AuthorCard` (`aculta_user_author_card`) | Renderiza o cartão por linha; `query()` não altera a consulta e `render()` carrega a conta pelo `uid` quando o cache da query não traz `_entity`. Opções: variante e tamanho. |
| `entity_view` (Comment) | `DiscussionCommentAuthorHooks` | Cartão em comentários de discussão e posts do Fórum; usa `author_wiki` em `wiki_entry`, `author_lms` em `aculta_lms_topic`, `author_comment` em `revista` e `author_forum` no Fórum. Autor histórico usa `buildLegacy*`. |
| `entity_view` (tópico) | `PublicForumTopicAuthorHooks` | Cartão do autor do tópico público; autor histórico não vira User. |
| `preprocess_views_view_fields` (Fórum) | `aculta_forum` `PublicForumLastActivityAuthorHooks` | Identidade da última atividade do Fórum com o mesmo builder. |
| View "Perfis públicos" | `config/sync/views.view.perfis_publicos.yml`, `/membros` | Base `users_field_data`, perfil público e apelido preenchidos; campo `aculta_user_author_card` (`profile_compact`, `sm`). |

### 3.4 Consumidores fora do `aculta_user`

| Consumidor | Onde | Regra |
| --- | --- | --- |
| Revista | `aculta_magazine` `ArticleAuthorsHooks` | `author_editorial` para autor e coautores; designação vem do Magazine; bio/redes só com opt-in. |
| LMS — roster | `aculta_lms` `ClassRosterManager` | Perfil público → cartão; sem perfil público → `buildFallback()` com nome de exibição + acervo. Ninguém sai da turma. |
| LMS — feedback | `aculta_lms` `EducatorFeedbackAuthorCardHooks` + `EducatorFeedbackPolicy` | `lms_answer_comment` pode ser do aluno dono da resposta ou de educador; só educador recebe "Educador(a)" e notifica. Aluno usa cartão comum, sem rótulo. |
| Mensagens privadas | `aculta_messenger` `PrivateMessagePresentationHooks` | `AvatarResolver` para o remetente: foto quando existir, senão acervo. |
| Wiki | `aculta_wiki`, template do tema, `DiscussionCommentAuthorHooks` | A entrada não exibe autor; a autoria aparece somente na discussão (`author_wiki`). |
| Fórum | `aculta_forum` / `aculta_user` | Tópicos, comentários, posts revisionáveis e última atividade usam o cartão; autoria Growroom histórica usa `LegacyAuthor`. |
| Tema | ACULTA420 SDC `author-card` | Variantes, tamanhos `sm`/`md`/`lg`, UI Styles e `aculta-user-author-card.html.twig`; sem o tema, o módulo entrega o render neutro. |

## 4. Fluxos

### 4.1 Conta nova e OAuth

1. `AccountCreationHooks::userPresave`: foto importada do Google/OAuth vira
   permanente e fora do acervo; sem foto, `AvatarPool::assignPresave()` copia uma
   entrada do acervo para `user_picture`.
2. `AccountCreationHooks::userInsert` cria o Profile de papel com apelido.
3. O cartão público usa o apelido; sem perfil público, `resolveFallback()` usa o
   nome de exibição + acervo.

### 4.2 Edição da foto

- O popup distingue **avatar do acervo** (sem "Remover", com "Sortear novo
  avatar") de **foto personalizada** (com "Remover", sem sorteio). A tabela de
  estados e os invariantes estão em `FOTO-PERFIL.md`.
- **Sortear** grava apenas `user.data.sorteio_pendente` e troca a prévia no modal.
  **Salvar** aplica a escolha (`applyPending`), copia para o acervo e apaga a
  cópia anterior se ninguém mais a usa.
- **Remover foto** pessoal é imediato: `PictureReplacementHooks::userPresave`
  devolve a conta ao acervo. A foto anterior é apagada quando não é compartilhada.
- **Recortar** só vale para o arquivo recém-enviado; depois do recorte a foto é
  gravada e o painel recarrega.

### 4.3 Apresentação pública

- `PublicProfileController` monta `/u/{apelido}` e pede `AvatarResolver` com o
  item `foto` da `PublicProfilePolicy`.
- Cartões usam `AuthorIdentityResolver` → `AuthorCardBuilder` →
  `aculta_user_author_card` → SDC/tema.
- Sem identidade pública, o cartão comum é omitido; somente contextos que já
  mostram o nome (roster e feedback do LMS) usam `buildFallback()`.
- Views usa o campo `aculta_user_author_card`; o fallback pelo `uid` evita cartão
  vazio quando a linha vem do cache da query.

## 5. Privacidade e cache

- **Foto pública é opt-in** no perfil público (`field_exibir_foto`, padrão
  desligado). No perfil privado, a política atual mostra foto e apelido. O
  `content_editor` é obrigatoriamente público.
- **Nunca públicos:** CPF, RG, endereço, WhatsApp, data de nascimento, cidade,
  UF e nome de login como fallback de identidade pública.
- **Cartão de autor** não expõe e-mail privado, documentos, relações LMS, campos
  ocultos, ID interno nem entidade Drupal; recebe só strings/render arrays
  autorizados e cacheáveis.
- O avatar do acervo é imagem institucional, não dado pessoal; por isso pode
  aparecer sem opt-in.
- Cache: `AvatarResolver` inclui as tags da conta e do arquivo resolvido no
  próprio render array do avatar e na metadata de identidade, de modo que
  Fórum, perfil público e demais consumidores invalidem a apresentação quando
  `user_picture` mudar. O cartão também incorpora o Profile e os contextos da
  identidade. Feedback do LMS acrescenta a resposta e o curso
  (e a tag de lista de membros do curso) para o rótulo de educador não ficar
  preso em cache.
- A foto personalizada importada do Google é tratada como foto pessoal; a
  remoção devolve a conta ao acervo. Reimportar a foto do Google depois da
  criação e importar avatares do Growroom são decisões operacionais registradas
  em `docs/integrations/GOOGLE.md` e no registro de dívidas.

## 6. Decisões vigentes (2026-10-27)

- A inicial do nome **nunca** é usada; o acervo é o último fallback.
- A entrada da Wiki não exibe autor; a autoria aparece somente na discussão do
  verbete (`author_wiki`).
- O roster mantém quem não tem perfil público, com nome de exibição + acervo.
- `lms_answer_comment` não é necessariamente de educador: o aluno dono da
  resposta também comenta. Só educador recebe o rótulo "Educador(a)" e dispara a
  notificação; o critério é autor diferente do dono da resposta + permissão
  `grade students` no curso.
- A foto do Google é importada na criação; reimportação sob demanda e avatares
  do Growroom continuam pendentes de decisão operacional.

## 7. Testes e gates

- `php scripts/validate-aculta-user-photo.php` — invariantes INV-FOTO-1 a 10,
  incluindo catálogo válido e ao menos uma entrada ativa com arquivo.
- `php scripts/validate-aculta-portal-drupal11.php` — fronteiras e status.
- `php scripts/validate-aculta420-sdc-schemas.php` e
  `validate-aculta420-design-foundations.php` — SDC, tokens e contraste.
- Unit: `AvatarResolverTest`, `AuthorIdentityTest`,
  `PublicForumTopicAuthorHooksTest`, `AuthorCardVariantResolverTest`.
- Kernel: `ClassRosterVisibilityTest` (roster e fallback) e os testes de
  participação/visibilidade do Fórum e da Wiki.
- Navegador: [`tests/browser/aculta-avatar/`](../../tests/browser/aculta-avatar/README.md),
  Firefox ESR visível em `DISPLAY=:0`; cobre `/membros`, `/u/{apelido}`, Conta,
  Mensagens, Revista, Cursos e Wiki, com ACULTA420 e Olivero. Roster e feedback
  do LMS dependem de turma; sem turma no Runtime, ficam DEFERRED.

## 8. Leitura relacionada

- [Perfil público e cartão de autor](../modules/PERFIL-PUBLICO-E-CARTAO-DE-AUTOR.md)
- [Foto de perfil: regras por estado](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/FOTO-PERFIL.md)
- [Contrato da API de cartões](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARD-API.md)
- [Variantes de cartão](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/AUTHOR-CARD-VARIANTS.md)
- [Classes e hooks do aculta_user](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/CLASSES-E-HOOKS.md)
- [Google: foto do perfil](../integrations/GOOGLE.md)
- [Inventário LGPD](../compliance/LGPD-INVENTARIO.md)
