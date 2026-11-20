# reaction-emoji

Renderiza um emoji vetorial do Microsoft Fluent Emoji a partir dos assets locais do tema.

- **Props:**
  - `name` (string, obrigatório): identificador do emoji (ex: `broccoli`, `seedling`, `fire`).
  - `variant` (enum: `flat`, `color`, default: `flat`): estilo visual.
  - `size` (enum: `xs`, `sm`, `md`, `lg`, default: `sm`): dimensão do ícone.
  - `label` (string, opcional): texto acessível para leitor de tela quando usado como imagem com significado.
- **Story:** `reaction-emoji.default.story.yml`.

- **Thumbs down:** `name: thumbs-down` é um emoji oficial Fluent colorido
  integrado ao pack `aculta_reacoes`, com variantes também em
  `aculta_fluent_emoji` (color/flat). Na UI de votação, o ID salvo é
  `dislike`, e segundo clique no mesmo botão remove a reação ativa.
  O pack não cria outro mecanismo de desfazer ou contador.

- **Descurtir do Fórum:** utiliza `thumbs-down-medium` (👎🏽, Fluent Emoji Medium) a partir do pack `aculta_reacoes`; valor do POST `dislike`. Nunca incluir esse controle na paleta positiva; clicar outra vez remove a reação, sem botão Undo separado.
