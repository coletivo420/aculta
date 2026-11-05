# Tópico do Fórum

Apresenta teasers e tópicos completos preservando os campos e ações nativos.
O template Drupal encaminha conteúdo, data e flag ao SDC; autoria continua no
render array público do `aculta_user`.

- Props: `variant`, `has_title`, `has_meta`, `has_bookmark`.
- Slots: `title`, `meta`, `bookmark`, `content`.
- Story: `forum-topic.default.story.yml`.
- JavaScript: nenhum.
- Nunca consulta usuário, inventa avatar/inicial ou decide acesso.
- CSS mobile-first com tokens; o consumer mantém a estrutura de cinco posições
  da barra administrativa local do módulo fora do SDC.

O adapter `node--aculta-forum-topic.html.twig` consome o componente nos modos
teaser e full. A View pública preserva seu render array pelo adapter em
`templates/views/` e reaproveita o layout de atividade.

A folha `src/styles/forum.css` porta a listagem pública, formulário de busca,
estados vazios e controles de discussão compartilhados. O template público foi
conferido em Firefox ESR no Runtime; o SDC de comentário é validado isoladamente
porque as páginas consultadas não contêm comentários desse bundle.
