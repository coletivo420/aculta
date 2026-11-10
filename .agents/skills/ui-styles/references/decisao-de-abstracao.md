# UI Styles e abstrações visuais

## Declaração do ACULTA

O arquivo `web/themes/custom/aculta420_v2/aculta420_v2.ui_styles.yml` declara grupos com `category`, `label`, `description`, `options` e `previewed_as`. Exemplos existentes são `button`, `card_accent`, `revista_accent`, `purpose_palette`, `spacing_section`, `surface_style`, `surface_accent_style`, `surface_elevation_style` e `surface_radius_style`.

As classes correspondentes são definidas nos CSS do tema e devem consumir tokens semânticos. O arquivo não deve ser usado para inventar classes que não existem nem como cópia da prop de um SDC.

## Critérios práticos

| Pergunta | Escolha provável |
| --- | --- |
| Precisa de markup, slots, hierarquia ou comportamento próprio? | SDC |
| É um estado intrínseco de um único componente? | Prop/variante do SDC |
| É uma classe independente, reaproveitável e selecionável por editores em vários alvos? | UI Style |
| É regra interna para espaçamento/organização sem valor editorial? | Utilitário CSS privado |
| O módulo precisa de apresentação no tema, sem controlar classe diretamente? | `data-portal-ui` |

Antes de adicionar ou agrupar opções, confira CSS, responsividade, temas claro/escuro, nomes de classes permitidos no tema e `validate-aculta420-ui-styles.php`. Só use grupos mutuamente exclusivos. Não misture opções incompatíveis no mesmo controle.

## Integrações

O Composer trava `drupal/ui_styles` 1.22.0. A configuração exportada lista `ui_styles_block`, `ui_styles_layout_builder`, `ui_styles_ui_patterns` e `ui_styles_views`. O código contrib não está presente neste clone. Confirme o alvo e a UI real na versão instalada; não presuma integração só porque o módulo aparece no lock ou no export.

Referência upstream: https://project.pages.drupalcode.org/ui_styles/.
