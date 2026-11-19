---
name: ui-icons
description: Integra ícones ao tema ACULTA usando os packs e o componente existentes, após confirmar APIs e módulos disponíveis.
---

# Ícones Drupal e ACULTA

Use ao selecionar, adicionar ou revisar ícones em SDC, componentes de menu, campos ou UI editorial. Comece pelo pack atual do tema. Não instale nem introduza um segundo sistema de ícones sem pedido e revisão das dependências.

## Estado atual do tema

- `web/themes/custom/aculta420_v2/aculta420_v2.icons.yml` declara packs Core Icon API `aculta_phosphor_regular`, `aculta_phosphor_duotone` e `aculta_fluent_emoji`.
- Os SVGs estão em `assets/icons/phosphor/` e `assets/icons/fluent-emoji/color/`. O SDC `aculta420_v2:icon` é a interface visual atual e os arquivos de arquitetura descrevem seu uso.
- O tema também possui teste focal `UiIconsPackTest`, que confere metadados e presença dos SVGs.
- `drupal/ui_icons` está declarado em `composer.json` (`^2.1`) e travado em `composer.lock` (2.1.0). O Config Sync habilita `ui_icons`, `ui_icons_field`, `ui_icons_menu`, `ui_icons_patterns` e `ui_icons_picker`. Isso prova a declaração e a intenção de habilitação no código; o estado efetivo no Runtime exige consulta separada (Drush/`pm:list`), ainda não feita. Ver `docs/operations/testing/UI-SUITE-AUDITORIA.md`.

O Drupal Core Icon API e o módulo contrib UI Icons são coisas diferentes. Core fornece descoberta/renderização de packs; UI Icons adiciona controles de seleção e integrações. Não diga que há picker só porque o tema declara `*.icons.yml`.

Os submódulos `ui_icons_field`, `ui_icons_menu`, `ui_icons_patterns` e `ui_icons_picker` estão no Config Sync. Antes de recomendar configuração, confirme machine names, plugins e config no `.info.yml` do release instalado (`composer.lock` = 2.1.0) e no filesystem contrib do Runtime. Não presuma nomes de Library, CKEditor 5 ou extratores SVG/web fonts sem essa confirmação.

## Fluxo

1. Confirme ícone existente no pack e reúse `aculta420_v2:icon`; verifique o catálogo e a grafia exata do ID.
2. Se precisar de outro arquivo SVG, confira licença, nome, diretório, dimensão/viewBox, otimização e se já há ícone equivalente. Não copie asset sem licença compatível.
3. Para pack Drupal, confira `*.icons.yml`, schema e extrator aceitos na versão instalada. Reuse assets locais e dependências existentes.
4. Determine se é decorativo ou informativo. Decorativo fica oculto de tecnologia assistiva. Informativo precisa de nome acessível via controle ou texto próximo. Não use o SDC atual como informação única: ele emite `aria-hidden="true"`.
5. Preserve `currentColor`, tamanho e tokens do tema. Não fixe cor sem necessidade nem carregue bibliotecas globais quando o pack estático resolver.
6. Só implemente integração UI Icons de campo/menu/Patterns depois de o pacote e submódulo serem adicionados e habilitados por tarefa autorizada. Inspecione a versão instalada; não presuma nomes de plugins, rotas ou config.
7. Valide teste `UiIconsPackTest`, gate do tema aplicável e browser preview. Capture loading e foco se o seletor for interativo.
8. Preserve Phosphor e Fluent Emoji. Não crie seletor paralelo nem substitua o sistema de emojis do Fórum. Verifique origem, licença, sanitização e diretório permitido dos SVGs.
9. Em Twig/PHP, use a API Core da versão instalada e o SDC `icon`; não concatene SVG de entrada editorial. Ícone decorativo fica oculto; ícone informativo precisa de nome acessível e texto/contexto apropriado.

## Referências sob demanda

- Estado e packs ACULTA: `references/packs-e-limites.md`.
- Core Icon API: https://www.drupal.org/docs/develop/drupal-apis/icon-api.
- UI Icons: https://www.drupal.org/project/ui_icons.
- Contrato de SDC: skill `sdc`.
- Estilos e cores: skill `css`.
