# forum-tabs

Abas de navegação horizontal do fórum Growroom420, com indicador ativo verde, contadores de não lidos e slot de filtros.

## Props
- `label` (string, obrigatória): descrição acessível para o container `<nav>`.
- `items` (array de objetos `{label, href, active, count}`, obrigatório): itens de navegação.

## Slots
- `filters`: slot para filtros adicionais (select nativo de ordenação ou categorias).

## Conformidade
- Rolagem horizontal restrita internamente a `.forum-tabs__bar` com `max-inline-size: 100%` e `overflow-x: auto`. Sem quebra ou alargamento da página em 320 px.
- Alvos de clique de no mínimo 44 px.
- Story: `forum-tabs.default.story.yml`.
