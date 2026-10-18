# Catálogo de componentes ACULTA420

O catálogo visual completo e navegável fica no [Storybook ACULTA420](../../../../../storybook/README.md). Cada SDC deve ter um arquivo `<machine-name>.stories.twig` na própria pasta, renderizando o componente real e apresentando exemplos/estados relevantes.

O catálogo técnico e as regras normativas permanecem em [docs/components.md](../docs/components.md), `*.component.yml`, Twig e CSS dos componentes. Os `.story.yml` são demonstrações da UI Patterns Library; são diferentes de `*.stories.twig` do Drupal Storybook e permanecem enquanto a biblioteca for usada.

Gate obrigatório: `php scripts/validate-aculta420-storybook.php`.
