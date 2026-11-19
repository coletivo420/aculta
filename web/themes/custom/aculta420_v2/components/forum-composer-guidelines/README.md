# forum-composer-guidelines

Painel reutilizável de diretrizes e boas práticas para criação de tópicos. A story o apresenta em duas colunas no desktop e uma no mobile, com faixa amarela e suporte opcional a `<details>` nativo. Nenhum consumidor de produção está confirmado após a remoção do Twig órfão de `node_add_form`.

## Props
- `collapsed` (boolean, opcional, default `false`): se verdadeiro, encapsula as regras em um elemento `<details>` nativo.
- `tips` (array de strings, opcional): lista de dicas úteis para redação do tópico.
- `community_rules` (array de `{title, text}`, opcional): normas da comunidade.

## Conformidade
- Sem dependência de JavaScript obrigatório.
- Responsivo de 320 px a 1440 px.
- Story: `forum-composer-guidelines.default.story.yml`.
- Manter o SDC na UI Patterns Library; a ausência de consumidor de produção não autoriza removê-lo do catálogo.
