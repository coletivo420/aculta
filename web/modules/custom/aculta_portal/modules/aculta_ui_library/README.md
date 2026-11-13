# ACULTA UI Library

Este módulo não mantém catálogo nem prévias próprias. A antiga rota de entrada
da biblioteca ACULTA é preservada como redirecionamento para a UI Patterns
Library, fonte única para componentes SDC e stories.

## Compatibilidade

| Rota | Destino | Acesso |
| --- | --- | --- |
| `aculta_ui_library.blocks` (`/painel-administrativo/aculta/ui-library/blocos`) | `ui_patterns_library.overview` | Administrador com `administer blocks`, somente no domínio MAIN |

A rota antiga é administrativa, não cacheável e fica restrita ao domínio MAIN.
O acesso ao catálogo de destino também passa pelas regras próprias do UI
Patterns. A rota individual de prévia de bloco foi removida.

## Limites

- Componentes e stories pertencem à UI Patterns Library.
- Composição e posicionamento de blocos continuam nas ferramentas Drupal/UI
  Suite apropriadas; este módulo não lista nem renderiza blocos.
- O módulo permanece apenas para manter um destino compatível para a rota
  administrativa ACULTA existente.

## Gate

`php scripts/validate-aculta-ui-library.php` confere a rota de compatibilidade,
seu escopo MAIN e administrativo, acesso por perfil, existência da rota oficial
de destino e ausência de rota/código próprio de catálogo ou prévia.
