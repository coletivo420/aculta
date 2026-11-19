---
name: ui-suite-integrations
description: Coordena tarefas que atravessam integrações UI Suite no ACULTA e identifica dependências, estado e riscos sem duplicar Skills especializadas.
---

# Coordenação das integrações UI Suite

Use quando uma mudança cruza mais de uma integração de apresentação Drupal, ou quando a decisão envolve blocos, Layout Builder, Views, fields, bibliotecas, segurança ou tecnologia UI Suite opcional. Para tarefa focada, carregue somente a Skill especializada (`sdc`, `ui-patterns`, `ui-styles`, `ui-icons`, `css`).

## Roteamento

- Criar contrato e template de componente: `sdc`.
- Publicar SDC em bloco/layout/field/formatter/Views/Library: `ui-patterns`.
- Opções de classe reutilizáveis: `ui-styles`.
- Pack/seleção/renderização de ícone: `ui-icons`.
- Tokens, purpose e tema: `css` e `aculta-data-portal-ui`.
- Aliases administrativos para UI Patterns e rotas/permissões: consulte gates de `aculta-gates-por-escopo`.

## Governança

Antes de recomendar API, separe `composer.json`, lock, filesystem, `core.extension.yml` e runtime. Este checkout pode não conter `web/core`, `web/modules/contrib` ou `vendor`. Confirme machine names, classes e versões na instalação alvo. Não instale, habilite, migre displays nem altere Composer/configuração/runtime por inferência.

Preserve Layout Builder, Views, os layouts existentes, ADR-054, as cinco regiões do tema e o roteamento multidomínio/purposes. Avalie permissões, cache contexts/tags/max-age, traduções, revisões e carregamento condicional de assets. A UI Patterns Library é o catálogo único de SDCs/stories; não recrie catálogos ou previews próprios em módulos ACULTA.

## Tecnologias opcionais

- **UI Patterns Field Group:** declarado como `2.0.x-dev@dev` em `composer.json`, travado em `dev-2.0.x` no lock, e **não habilitado** no Config Sync. Dependência de desenvolvimento: não habilite nesta tarefa.
- **UI Styles Page e demais submódulos:** `drupal/ui_styles` 1.22.0 no lock; `ui_styles_page`, `ui_styles_ckeditor5` e `ui_styles_entity_status` habilitados no Config Sync. `ui_styles_library` não consta. Confirme comportamento no código instalado.
- **UI Icons:** `drupal/ui_icons` 2.1.0 no lock e submódulos habilitados no Config Sync. Preserve Phosphor/Fluent e não crie seletor paralelo. Detalhes em `ui-icons`.
- **UI Skins:** `drupal/ui_skins` 1.2.1 no lock e habilitado no Config Sync. Pode sobrepor modos e variáveis CSS ao `data-theme`, purposes e tokens ACULTA. Não migrar o tema sem decisão.
- **UI Examples:** não consta em `composer.json`, lock ou Config Sync. Exemplos declarativos/YAML não são stories de componente nem preview real.
- **Display Builder:** não consta em `composer.json`, lock ou Config Sync. Projeto em beta no momento da pesquisa, com potencial de substituir/migrar Layout Builder, displays de Views e entity displays. Não instalar nem migrar; avaliar maturidade, compatibilidade e ADR-054 antes de qualquer proposta.
- **SDC Devel:** habilitado no export; confirme runtime e uso de previews conforme a instalação.

## Bibliotecas e segurança

UI Patterns Library cataloga os SDCs do tema e suas stories, inclusive internos e editoriais. Não ocultar componentes para resolver performance. Por decisão do responsável registrada na ADR-056, remover `aculta_ui_library` por completo, inclusive o redirect remanescente, após desinstalação estrutural no Runtime. Não manter catálogos/previews paralelos.

## Referência

Leia `docs/operations/AUDITORIA-SKILLS-DRUPAL-2026-11.md` para a matriz e `docs/operations/TESTES-SKILLS-UI-SUITE.md` para avaliações. Não carregue essas referências em tarefa focal sem necessidade.
