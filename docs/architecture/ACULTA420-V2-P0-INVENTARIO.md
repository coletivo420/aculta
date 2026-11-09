# ACULTA420 V2 — P0: inventário e baseline (ADR-054)

**Executado em:** 2026-11-09 · **Branch:** `dev` · **Base:** `90c1a9a396c5a8f4f7745817d070bfa83333be83`
**Reprodução:** `php scripts/audit-aculta420-v2-inventory.php` (ou `--json`).

Este documento registra o estado de partida do pacote P0. Nenhum arquivo de
estilo ou template foi alterado para produzi-lo.

## 1. Ambiente

| Item | Valor |
| --- | --- |
| Drupal | 11.5.0-beta1 |
| Tema padrão | `aculta420_v2` (V1 intocada) |
| Tema administrativo | `gin` |
| PHP | 8.5.12 |
| Drush | 13.8.0 |
| `ui_patterns` | dev-2.0.x |
| `ui_patterns_layouts` | 2.0.23 |
| `ui_patterns_blocks`, `ui_patterns_library` | habilitados no Config Sync |
| `layout_builder` | habilitado no Config Sync |
| Domain | `domain`, `domain_alias`, `domain_config`, `domain_config_entity_ui`, `domain_config_switcher`, `domain_config_ui`, `domain_source` |
| Ponte ACULTA | `aculta_domain_config` |

## 2. Blocos — 36 em `block.block.aculta420_v2_*`

| Região | Blocos | Situação |
| --- | --- | --- |
| `institution` | 0 no Config Sync | **Drift:** `block.block.aculta420_v2_institution_bar` existe só na config ativa (plugin `aculta_institution_bar`, peso -20). Exportação para `config/sync` depende de ambiente isolado (DEFERRED) |
| `header` | 6 | Final |
| `navigation` | 0 | Sem consumidores; pode sair após o corte |
| `highlighted` | 5 | **Precisa migrar** |
| `help` | 0 | Sem consumidores; variável `aculta_help` já compõe no template |
| `breadcrumb` | 0 | Sem consumidores; o bloco está em `header` e a variável `aculta_breadcrumb` já compõe |
| `content` | 19 | Final |
| `sidebar_first` | 3 | Final (fallback nativo) |
| `footer` | 3 | Final |

**Únicos blocos fora das cinco regiões finais (5):**

| Bloco | Plugin | Destino na nova estrutura |
| --- | --- | --- |
| `aculta420_v2_aculta_messages` | `system_messages_block` | Área contextual do conteúdo (`page__contextual`) |
| `aculta420_v2_aculta_primary_admin_actions` | `local_actions_block` | Área contextual do conteúdo |
| `aculta420_v2_aculta_primary_local_tasks` | `local_tasks_block` | Área contextual do conteúdo |
| `aculta420_v2_aculta_secondary_local_tasks` | `local_tasks_block` | Área contextual do conteúdo |
| `aculta420_v2_aculta_donation_quick_apoio` | `aculta_donation_quick` | Área contextual/apoio no conteúdo |

Enquanto a configuração não for importada, o shell paralelo renderiza
`page.highlighted` dentro da área contextual. Assim não há bloco órfão no
período de migração nem necessidade de `drush cim` antecipado.

### 2.1. Blocos `header` (6)

`system_menu_block:account`, `system_breadcrumb_block`, `help_block`,
`system_menu_block:main`, `page_title_block`, `system_branding_block`.

### 2.2. Blocos `content` (19)

`views_block:aculta_activities-block_1`, três instâncias de
`block_content:80f3fc02-…` (dados institucionais/contato/registro),
`system_main_block`, `views_block:aculta_documents-block_1`,
`aculta_donation_donate`, `block_content:a7dc6752-…` (cidade),
`views_block:home_editorial_highlights-block_1`, `block_content:c86786af-…`
(missão), `block_content:8bb4b286-…` (parceria),
`block_content:1a8bd16f-…` (apoio),
`block_content:70773914-…` (transparência),
`block_content:9e930892-…` (quem somos), `block_content:bfc5d412-…` (trabalho),
`block_content:990079a5-…` (seções de transparência),
`views_block:aculta_news-block_1`,
`views_block:aculta_activities-block_upcoming`.

### 2.3. Blocos `sidebar_first` (3)

`aculta_events_upcoming`, `views_block:aculta_forum_categories-block_landing`,
`aculta_lms_agenda_proximos`.

### 2.4. Blocos `footer` (3)

`system_menu_block:aculta-footer-content`,
`system_menu_block:aculta-footer-institution`,
`block_content:80f3fc02-…` (dados do rodapé).

### 2.5. Visibilidade e cache

- 14 blocos sem condição de visibilidade.
- 18 blocos com `request_path` + `aculta_domain_purpose`.
- 3 blocos com `aculta_domain_purpose` + `request_path`.
- 1 bloco (`aculta_lms_agenda_proximos`) só com `request_path`.
- Nenhum `block.block.aculta420_v2_*` declara `cache.contexts` próprio; os
  contextos vêm do plugin (Views/Block Content) e do Domain Config.

## 3. Displays — 95, Layout Builder habilitado em **0**

| Entidade | Displays |
| --- | --- |
| `node` | 23 |
| `message` | 20 (todos `mail_*`: não são candidatos a Layout Builder) |
| `block_content` | 9 |
| `media` | 9 |
| `profile` | 6 |
| `commerce_order_item` | 5 |
| `commerce_product_variation` | 5 |
| `commerce_product` | 4 |
| `group_relationship` | 3 |
| `aculta_forum_post` | 2 |
| `commerce_order` | 2 |
| `user` | 2 |
| `comment`, `commerce_store`, `group`, `media_gallery`, `taxonomy_term` | 1 cada |

**Classificação de partida:**

- `COMPATÍVEL (candidato):` displays de conteúdo editorial sem controller
  próprio (`node.page.*`, `node.revista.*`, `node.wiki_entry.*`,
  `block_content.*`, `taxonomy_term.editorial_author.default`).
- `COMPATÍVEL COM ADAPTADOR:` `node.aculta_forum_topic.*`,
  `node.aculta_lms_topic.*`, `aculta_forum_post.*`, `comment.*`,
  `commerce_*`, `group*`, `profile.*` — há template/controller próprio ou
  regra de acesso/carrinho; exigem validação por display.
- `INCOMPATÍVEL:` os 20 displays `message/*/mail_*` (render de e-mail, não
  página) e `media/*/media_library` (interface administrativa Core).
- `PENDENTE DE VALIDAÇÃO:` todo o restante até teste em navegador.

Nenhum display será habilitado neste pacote. O P4 habilita por lotes com teste
de renderização e rollback.

## 4. CSS/JS no `global-styling` — 21 entradas

| Arquivo | Situação atual | Destino proposto |
| --- | --- | --- |
| Google Fonts (Inter/Oswald) | global, terceiro | manter até medição de fontes |
| `tokens.css` | global | manter (fundação) |
| `base.css` | global | manter (fundação) |
| `layout.css` | global, inclui resolução de container | fragmentar no corte |
| `events.css` | global | biblioteca de aplicação `events` |
| `wiki.css` | global | biblioteca de aplicação `wiki` |
| `forum.css` | global | biblioteca de aplicação `forum` |
| `portal-account.css` | global | biblioteca de aplicação `account` |
| `messenger-drawer.css` | global | biblioteca de aplicação `messenger` |
| `public-profile.css` | global | biblioteca `profile` |
| `utilities.css` | global | manter (utilitários essenciais) |
| `studio.css` | global | biblioteca de aplicação `studio` |
| `event-organization.css` | global | biblioteca `events` |
| `content.css` | global | manter ou fragmentar por SDC |
| `search.css` | global | biblioteca `search` |
| `statistics.css` | global | biblioteca `statistics` |
| `studio-identity.css` | global | biblioteca `studio` |
| `buttons.css` | global | substituir pelo SDC `button` |
| `components/button/button.css` | global (asset de SDC) | remover do global; SDC já carrega |
| `portal-ui-extras.css` | global | fragmentar |
| `portal-ui-map.css` | global | biblioteca `maps` |

**JavaScript:** `htmx-dialogs` está no `libraries:` do `info.yml` (global);
`studio-htmx`, `utility-drawers`, `topic-dialog`, `component-library-mode` são
contextuais. jQuery não é carregado globalmente pelo tema.

### 4.1. Consumidores de bibliotecas (auditoria)

`php scripts/audit-aculta420-library-consumers.php` lista os consumidores
declarados nos módulos do Portal. Destaques: `aculta_studio/*` usam
`core/drupal.htmx`; `aculta_pagamentos_mercadopago/checkout_pro_review` usa
`core/jquery`; `aculta_user/photo-flow` ainda usa AJAX legado (dívida do
ADR-050, fora do escopo visual).

## 5. Riscos

| Risco | Mitigação |
| --- | --- |
| Layout Builder habilitado em display com controller próprio quebra a renderização | Habilitar por lote, com teste de rota e rollback do YAML |
| Domain Config não aplica override de `core.entity_view_display`/`layout_builder__layout` | Não prometer landing por domínio via Domain Config; validar mecanismo próprio no P5 |
| Remover região antes de migrar os 5 blocos de `highlighted` | Shell paralelo renderiza `page.highlighted` na área contextual; regiões antigas permanecem no `info.yml` até o P9 |
| CSS global removido sem consumidor mapeado | Só remover após `audit-aculta420-library-consumers.php` + teste da página consumidora |
| Prévia real exibir conteúdo privado | Rota administrativa + checagem de acesso ao bloco/entidade + `max-age 0` |
| `drush cim` com drift no Runtime | Não importar; configuração preparada em arquivo e validada em staging |

## 6. Baseline de gates

Resultados registrados no P8. Neste pacote executam-se apenas leitura e gates
sem efeito no Runtime.
