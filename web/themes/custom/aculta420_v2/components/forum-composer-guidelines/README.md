# forum-composer-guidelines

Painel de diretrizes e boas práticas para publicação de novos tópicos (`/criar-topico`). Apresenta duas colunas no desktop (dicas práticas e regras da comunidade) e uma coluna no mobile, com faixa de destaque amarela no topo e suporte opcional a `<details>` nativo sem JavaScript.

## Props
- `collapsed` (boolean, opcional, default `false`): se verdadeiro, encapsula as regras em um elemento `<details>` nativo.
- `tips` (array de strings, opcional): lista de dicas úteis para redação do tópico.
- `community_rules` (array de `{title, text}`, opcional): normas da comunidade.

## Conformidade
- Sem dependência de JavaScript obrigatório.
- Responsivo de 320 px a 1440 px.
- Story: `forum-composer-guidelines.default.story.yml`.
