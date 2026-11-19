# forum-sidebar-panels

Painéis laterais em coluna para o Fórum Growroom420. Apresenta cards informativos com faixas vermelhas e amarelas alternadas:
1. Estatísticas da comunidade.
2. Tópicos em destaque com miniatura ou ícone.
3. Moderadores (com badge "Em breve" e placeholder neutro quando ausente no Portal).
4. Diretrizes e regras da comunidade.
5. Categorias relacionadas com indicador de cor.

## Props
- `stats` (array de `{label, value}`, opcional): estatísticas gerais.
- `featured` (array de `{title, url, thumbnail, replies}`, opcional): tópicos destacados.
- `moderators` (array de `{name, role}`, opcional): moderadores.
- `rules` (array de strings, opcional): regras de conduta.
- `related` (array de `{name, url, count}`, opcional): categorias relacionadas.

## Conformidade
- Dados vazios viram placeholder neutro `—<span class="visually-hidden">Dado indisponível</span>`.
- Totalmente compatível com telas a partir de 320 px.
- Story: `forum-sidebar-panels.default.story.yml`.
