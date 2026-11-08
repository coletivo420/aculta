---
name: sdc
description: Use para criar ou revisar componentes Single Directory Components (SDC) do tema aculta420_v2 (component.yml, .twig, .css, stories, include com props). Aplica as convenções do ACULTA: schema estrito, tokens semânticos, modo de cor por data-theme, sem CSS de rota.
---

# SDC no ACULTA (aculta420_v2)

Estrutura de cada componente em `web/themes/custom/aculta420_v2/components/<nome>/`:
- `<nome>.component.yml`: metadados e schema de `props` (obrigatório).
- `<nome>.twig`: marcação (obrigatório).
- `<nome>.css`: estilo; anexado automaticamente quando o componente é renderizado.
- `<nome>.default.story.yml`: story para a UI Library (uma por variante, quando fizer sentido).

Referência oficial: https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components

## Schema (component.yml)
- Toda prop tem `type`. Use `enum` e `default` quando houver conjunto fechado.
- `additionalProperties: false` no objeto de props.
- Valor opcional aceita `[string, 'null']` quando faz sentido.
- Os valores padrão do schema são documentação. No Twig, use `|default(...)`.

## Twig
- Chame outro componente com `include('aculta420_v2:<nome>', {...}, with_context = false)`. Não passe entidade, usuário ou render array de conteúdo inteiro como prop.
- Para classes e atributos extras use `attributes` (`create_attribute({...})`). Não escreva `class` fixo que o chamador precise sobrescrever.
- Escape é padrão do Twig: não use `|raw` sem motivo verificado.

## CSS
- Só tokens semânticos: `--color-text-primary`, `--color-surface-raised`, `--button-*` e afins. Nenhum hexadecimal no componente.
- Modo claro e escuro saem dos tokens, pelo `data-theme` do contêiner. Não crie bloco de modo escuro por componente.
- Não use seletor de rota ou de classe de `body` para o componente. O componente tem de funcionar isolado.
- Alvo de toque de 44 px em controle interativo, salvo exceção registrada.

## Exemplo real
O componente `button` (`aculta420_v2:button`) é a referência: props `text`, `url`, `variant` (primary, secondary, outline, ghost, danger), `size`, `icon_before`, `icon_after`, `aria_label`, `disabled`, `is_submit`.

## Verificação antes de publicar
1. `php scripts/validate-aculta420-ui-styles.php` e `php scripts/validate-aculta420-v2-foundations.php` passam.
2. O componente aparece na UI Library, com story, nos dois modos.
3. Renderização conferida (Drush, `renderInIsolation` ou teste Kernel quando o tema estiver habilitado).
