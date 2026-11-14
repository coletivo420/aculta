# forum-topic-row

Linha de tópico em lista tabular de discussões do Fórum Growroom420.

## Props
- `pinned` (boolean, obrigatório): indica se o tópico está fixado (ganha fundo destacado e barra lateral verde).
- `badges` (array de `{label, tone}`, opcional): etiquetas de status (`neutral`, `highlight`, `success`, `official`).
- `replies` (inteiro/string, opcional): contagem de respostas. Se vazio, exibe `—`.
- `views` (inteiro/string, opcional): contagem de visualizações. Se vazio, exibe `—`.
- `last_activity` (string, opcional): tempo relativo da última atividade. Se vazio, exibe `—`.

## Slots
- `title`: link para o tópico.
- `meta`: autor e categoria.
- `preview`: trecho da mensagem mais recente (opcional).
- `thumbnail`: imagem ou ícone opcional.
- `pinned_content`: campo renderizado pela View quando o tópico está fixado.
- `replies_content`: contagem renderizada pela View, preservando o formatter.
- `activity_content`: última atividade renderizada pela View.

Os slots `pinned_content`, `replies_content` e `activity_content` permitem que
UI Patterns Views forneça saídas renderizadas com metadados de cache. Quando
presentes, eles prevalecem sobre as props simples correspondentes.

## Conformidade
- Desktop: layout de linha com colunas claras para estatísticas.
- Mobile: contadores alinhados horizontalmente abaixo dos metadados, sem overflow lateral em 320 px.
- Story: `forum-topic-row.default.story.yml`.
