# Retirada de `aculta_ui_library`

Por decisão do responsável em 2026-11-13, este módulo será removido por completo.
A UI Patterns Library oficial permanece como único catálogo de SDCs e stories.
O Config Sync já não habilita este módulo. Seu código deve ficar disponível até
a desinstalação estrutural no Runtime, pois o checkout é compartilhado com o
Apache.

## Estado transitório até a desinstalação

| Rota | Destino | Acesso |
| --- | --- | --- |
| `aculta_ui_library.blocks` (`/painel-administrativo/aculta/ui-library/blocos`) | `ui_patterns_library.overview` | Administrador com `administer blocks`, somente no domínio MAIN |

A rota de compatibilidade ainda existe somente para evitar remover arquivos de
um módulo possivelmente ativo antes da desinstalação. Ela será eliminada junto
com o módulo após a janela aprovada; não mantê-la como parte do desenho final.

## Limites

- Componentes e stories pertencem à UI Patterns Library.
- Composição e posicionamento de blocos continuam nas ferramentas Drupal/UI
  Suite apropriadas; este módulo não lista nem renderiza blocos.
- `ui_patterns_library` é uma extensão independente e deve permanecer habilitada.
- Remover a pasta, testes, rota e gates específicos depois da desinstalação no Runtime.

## Gate

O gate `validate-aculta-ui-library.php` é transitório e será removido com este
módulo depois da desinstalação estrutural.
