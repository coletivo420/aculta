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
- `drupal/ui_icons` e seus submódulos não aparecem no `composer.json`, `composer.lock` nem no export `core.extension.yml` auditado. Portanto UI Icons Picker, UI Icons for Menu, UI Icons for UI Patterns e UI Icons Library não estão comprovados como disponíveis neste checkout.

O Drupal Core Icon API e o módulo contrib UI Icons são coisas diferentes. Core fornece descoberta/renderização de packs; UI Icons adiciona controles de seleção e integrações. Não diga que há picker só porque o tema declara `*.icons.yml`.

O módulo contrib UI Icons e integrações para fields/links, menu, UI Patterns, CKEditor 5, Media, Picker, Popover e Library não estão comprovados como instalados. Trate esses rótulos como categorias documentais; confirme machine names em `.info.yml` do release instalado antes de recomendar configuração. O mesmo vale para extratores SVG e web fonts.

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
