# Project card SDC

Status: **experimental** (0.3.0). Promove a `stable` só após consumo real estável e validação, conforme `docs/components.md`.

O SDC compõe `aculta420:card` na variante vertical. O presenter `node--project--teaser.html.twig` continua dono do `<article>`, dos attributes do node, de `title_prefix`/`title_suffix` e dos links (título e CTA), que são montados com `url` e `label` do Drupal.

## Slots

- `media`: imagem já renderizada; omitida quando o campo está vazio (ausência de mídia);
- `category`: categoria do projeto, quando houver;
- `title`: link do título;
- `summary`: corpo do projeto;
- `cta`: link de ação com o rótulo do projeto.

Não há props, JavaScript, storage, consulta de serviços, Commerce ou LMS.

## Diferenças conhecidas em relação ao teaser anterior

- o `title_attributes` do Drupal não é repassado ao `<h3>`, porque o teaser não usa contextual links nem Layout Builder;
- o CSS do card está em `project-card.css`, ao lado do componente (DT-T07, 0.4.0-dev.5). Mantém a moldura `.aculta-project` aplicada pelo presenter e adapta o pattern compartilhado para a mídia quadrada de projetos, com `object-fit: contain` para preservar logos.

## Pendente antes de `stable`

- variante para destaque (`featured`), quando houver consumidor;
- revisão de estado vazio e título longo em navegador com projetos reais.
