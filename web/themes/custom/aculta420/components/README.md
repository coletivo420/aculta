# Components

ACULTA420 organiza SDCs por responsabilidade visual.

Estrutura alvo:

```text
components/
├── primitives/
├── content/
├── navigation/
├── sections/
└── feedback/
```

O Drupal Core permite subdiretórios arbitrários dentro de `components/`.

## 0.7.0-dev.1 — SDCs de Meu Perfil (pré-S-R4)

- `patterns/profile-editor`: layout do editor, com slots `intro`, `identity`, `biography`, `social` e `actions`.
- `content/profile-identity`: nome privado textual, avatar autorizado, dica, ações e link público opcional. Distinto de `author-card`, dedicado à autoria pública.
- `content/profile-bio`: recebe bio/formulário somente leitura e ações do User pelos slots `body` e `actions`.
- `content/profile-social`: recebe os itens normalizados pelo User nos slots `items` e `actions`.

Cada componente é experimental, possui schema, Twig, CSS e duas stories
estáticas. Não consulta entidades, decide acesso, cria links ou altera Form
API. Nenhum consumidor Studio foi migrado; a integração pertence à S-R4.
Requer verificação de descoberta SDC, render real e responsividade.

## 0.1.0

Disponível:

- `content/editorial-card` -> `aculta420:editorial-card`.
- `content/course_card` -> `aculta420:course_card`, substituto SDC compatível para `lms:course_card`.
- `content/messenger-item`, `content/messenger-compose`, `content/messenger-notification` e `content/institutional-notice` -> componentes experimentais da central; `messenger-item` recebe avatar renderizado, direção de envio e corpo nativo como slots/props neutros.
- `content/author-card` -> apresentação experimental compartilhada de autoria; recebe nome/link, flag explícita de avatar presente, avatar autorizado renderizado pelo `aculta_user` e dados editoriais opt-in, sem carregar entidade ou decidir acesso. `author_forum_stacked` altera só o layout; autoria histórica não recebe perfil ou ações de conta.
- `patterns/messenger-list` -> moldura de listas do Private Message/Message.
- `feedback/unread-badge` e `feedback/messenger-state` -> contagem e estados da central.
- `patterns/card` -> cartão composto reutilizável com slots de mídia, cabeçalho, conteúdo e rodapé; variantes vertical e horizontal usam Bootstrap e tokens ACULTA420.
- `patterns/figure` -> figura de mídia já renderizada com slots opcionais para legenda e crédito; usado na capa da Revista.
- `patterns/quote` -> citação editorial com autoria e fonte; o mesmo tratamento visual se aplica a `<blockquote>` existente no corpo da Revista.

O cartão composto foi adaptado após análise do SDC Card do UI Suite Bootstrap
5.2.3 (GPL-2.0-or-later). O markup é próprio do ACULTA420 e não importa assets,
templates, bibliotecas ou dependências do tema de origem.

Os patterns `figure`, `quote` e a variante `card:overlay` adaptam estruturas
editoriais úteis do mesmo catálogo à apresentação e aos dados Drupal existentes.
As capas da Revista mantêm seus render arrays, `<blockquote>` preserva o HTML
editorial armazenado, e a listagem de álbuns continua usando campos da View.

Não criar placeholders vazios. Novos diretórios entram com o primeiro componente
real da categoria.

Regras completas: [../docs/components.md](../docs/components.md).
