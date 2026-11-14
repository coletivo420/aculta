# discussion-reaction-bar

Barra interativa de reações por emojis para comentários de discussão e postagens do fórum, seguindo o padrão e ergonomia do Discourse Reactions.

## Estrutura do Componente
- `discussion-reaction-bar.component.yml`: Esquema estrito do componente SDC.
- `discussion-reaction-bar.twig`: Barra interativa e paleta integrada, com suporte a HTMX e formulário POST de fallback.
- `discussion-reaction-bar.css`: Estilização das badges, botões e paleta flutuante de emojis.
- `discussion-reaction-bar.js`: Posicionamento do popover nativo e sincronização do estado `aria-expanded`; Escape e clique fora usam o comportamento nativo do popover.
- `discussion-reaction-bar.default.story.yml`: História para catálogo de UI Patterns.
