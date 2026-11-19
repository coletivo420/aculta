# forum-category-header

Cabeçalho de categoria do Fórum Growroom420. Apresenta o eyebrow "GROWROOM420 / COMUNIDADE", título da categoria, descrição explicativa e contadores de estatísticas com faixa decorativa na cor da marca/editorial (`--color-link-editorial`).

## Props
- `title` (string, obrigatória): título principal da categoria.
- `description` (string, opcional): texto de apoio ou descrição da categoria.
- `icon` (string, opcional): identificador de ícone Phosphor duotone.
- `stats` (array de objetos `{label, value}`, opcional): lista de estatísticas. Se `value` for nulo ou vazio, renderiza o placeholder acessível `—<span class="visually-hidden">Dado indisponível</span>`.

## Conformidade
- Sem contexto de entidade Drupal nem chamadas a serviços proprietários.
- Totalmente responsivo de 320 px a 1440 px.
- Modo escuro suportado exclusivamente via tokens.
- Story: `forum-category-header.default.story.yml`.
