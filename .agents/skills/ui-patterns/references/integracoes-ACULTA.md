# Integrações UI Patterns verificadas no ACULTA

Estado conferido em `composer.json`, `composer.lock` e `config/sync/core.extension.yml` no checkout de auditoria. O checkout não possui `web/core`, `web/modules/contrib` ou `vendor`, portanto este inventário prova dependências e configuração exportada, não estado de runtime.

| Pacote | Constraint no Composer | Lock | Módulo indicado no export |
| --- | --- | --- | --- |
| `drupal/ui_patterns` | `2.0.x-dev@dev` | `dev-2.0.x` | `ui_patterns` |
| `drupal/ui_patterns_field_group` | `^2.0@beta` | `2.0.0-beta2` | validar submódulo/módulo no código instalado |
| `drupal/ui_patterns_layouts` | `^2.0` | `2.0.23` | `ui_patterns_layouts` |
| `drupal/ui_styles` | `^1.22` | `1.22.0` | `ui_styles` |

O export lista `ui_patterns_blocks`, `ui_patterns_field`, `ui_patterns_field_formatters`, `ui_patterns_layouts`, `ui_patterns_library` e `ui_patterns_views`, além de `ui_styles_block`, `ui_styles_layout_builder`, `ui_styles_ui_patterns` e `ui_styles_views`. Confirme esses nomes no código da versão efetivamente instalada antes de criar configuração.

Pacote `drupal/ui_patterns_settings` não aparece em `composer.json` nem em `composer.lock`. Não documente seus sources, widgets de formulário ou transformações como disponíveis no site.

## Recursos próprios ACULTA

- UI Patterns Library é usada pelo tema e possui catálogo de componentes e stories. As rotas e page presentation são tratadas no tema V2.
- `web/modules/custom/aculta_portal/modules/aculta_ui_library` é um módulo ACULTA separado para preview de blocos configurados. Seu controller verifica permissão/acesso e usa cache `max-age 0` para a preview. Ele não substitui a Library de SDCs.
- O projeto preserva todos os SDCs, inclusive itens internos, e evita esconder componentes universalmente. Visibilidade editorial depende dos mecanismos existentes e da intenção do componente.

Fonte upstream: https://project.pages.drupalcode.org/ui_patterns/ e https://www.drupal.org/project/ui_patterns. Verifique documentação 2.x e releases compatíveis com a versão lockada antes de aconselhar mudanças de integração.
