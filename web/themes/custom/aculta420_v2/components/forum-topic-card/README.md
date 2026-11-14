# forum-topic-card

Variante do tópico em formato de cartão com miniatura visual (imagem ou ícone ilustrativo da categoria), metadados e contadores. Implementa o padrão Discourse Topic Cards.

## Props
- `pinned` (boolean, obrigatório): indica se o tópico está fixado.
- `thumbnail_src` (string, opcional): URL da imagem de capa/miniatura.
- `thumbnail_alt` (string, opcional): texto alternativo da imagem.
- `category_icon` (string, opcional): ícone Phosphor neutro quando não houver miniatura enviada. Nunca utiliza iniciais ou monogramas como avatar.
- `badges` (array de `{label, tone}`, opcional): etiquetas de status (`neutral`, `highlight`, `success`, `official`).
- `replies` (inteiro/string, opcional): total de respostas. Se vazio, exibe `—`.
- `views` (inteiro/string, opcional): total de visualizações. Se vazio, exibe `—`.
- `last_activity` (string, opcional): tempo relativo da última atividade. Se vazio, exibe `—`.

## Slots
- `title`: link para o tópico.
- `meta`: autor e categoria.
- `preview`: trecho da mensagem.

## Conformidade
- Responsivo de 320 px a 1440 px.
- Story: `forum-topic-card.default.story.yml`.
