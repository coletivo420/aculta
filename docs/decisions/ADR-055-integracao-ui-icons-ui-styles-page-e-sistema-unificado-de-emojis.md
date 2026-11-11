# ADR-055 - Integração integral UI Suite e sistema unificado de emojis

Status: decisão ampliada pelo responsável em 2026-11-10. Eixo Fluent Emoji / UI Icons parcialmente implementado em código; UI Suite integral, MIGRAÇÃO DO LEGADO, UI Skins substituindo purposes visuais e RENOMEAÇÃO FUNCIONAL purpose -> site_key no Portal permanecem em planejamento (US-00 a US-14 + RN-00 a RN-05). NÃO considerar encerrada.
Preserva: ADR-050 (HTMX antes de AJAX), ADR-053 (Barra Institucional), ADR-054 (SDC First e Layout Builder) e contratos do Portal.
Plano de pesquisa e fases: `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md`.
Plano vinculante de substituição visual e classificação: `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`.
Plano vinculante de renomeação do Portal: `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`.

---

## 1. Contexto e Motivação

Com a conclusão da fundação de SDCs, Layout Builder e superfícies visuais (ADR-054), identificou-se a necessidade de unificar e padronizar o consumo de iconografia, estilos de página e seleção de emojis em todo o ecossistema ACULTA.

Anteriormente:
- O catálogo Fluent Emoji (`aculta_editor`) continha referências com inconsistências no container e formato de substituição regex sensível a quebras de HTML.
- O seletor de emojis no fórum (`aculta_forum`) utilizava uma lista estática de reações sem categorização, busca ou desacoplamento do motor de ícones.
- Os ícones da Icon API (`ui_icons`) estavam presentes em `aculta420_v2.icons.yml`, mas faltavam testes automatizados rigorosos de verificação da presença física de todos os SVGs dos icon packs e integração estruturada com menus e componentes.
- O módulo `ui_styles_page` carecia de documentação e mapeamento de rotas administrativas para governança de regiões.

---

## 2. Decisões Arquiteturais

### 2.1. Arquitetura do Sistema Unificado de Emojis (Fluent Emoji)
1. **Separação de Responsabilidades:**
   - **UI Icons / Icon API:** Descoberta e renderização de icon packs (`aculta_fluent_emoji`, `aculta_phosphor_regular`, `aculta_phosphor_duotone`).
   - **`aculta_editor`:** Catálogo central de metadados dos emojis (`FluentEmojiCatalog`), resolvedor de assets SVG e adaptador de integração (`FluentEmojiIconAdapter`).
   - **`aculta_forum`:** Regras de negócio de reações, endpoints HTMX, CSRF, persistência e construção de reações do tópico/comentário.
   - **`aculta420_v2`:** Renderização de templates, acessibilidade, temas claro/escuro e SDC `aculta420_v2:reaction-emoji`.

2. **Adaptador Centralizado (`FluentEmojiIconAdapter`):**
   - Criação da interface `FluentEmojiIconAdapterInterface` e serviço `aculta_editor.fluent_emoji_icon_adapter`.
   - Mapeamento bidirecional seguro entre IDs do fórum (`herb`), IDs do UI Icons (`aculta_fluent_emoji:herb`) e glifos Unicode (`🌿`).
   - Validação estrita de identificadores permitidos, prevenindo injeção de nomes de arquivos arbitrários.
   - Agrupamento em categorias semânticas (`cultivo`, `reacao`, `celebracao`, `comunidade`) consumíveis por interfaces frontend.

3. **Substituição de Texto e Segurança HTML (`FluentEmojiFilter`):**
   - O filtro `fluent_emoji` foi refatorado para usar parsing DOM via `\DOMXPath` exclusivamente sobre nós de texto (`//text()`).
   - Proteção de elementos sensíveis: `script`, `style`, `code`, `pre`, `svg`, `textarea` e elementos já marcados com `.reaction-emoji` são intocados.
   - Resolução robusta de assets injetando `FluentEmojiAssetResolverInterface` via container Drupal.

### 2.2. Interface de Seleção e Reações do Fórum
1. **Frontend do Seletor (`discussion-reaction-picker`):**
   - Inclusão de campo de busca instantânea no cliente por nome, ID e palavras-chave.
   - Organização em abas/seções por categorias de emoji.
   - Botão dedicado de fechamento e suporte completo à navegação por teclado (Escape fecha o popover e restaura o foco no acionador).
   - Compatibilidade com ciclo de vida do HTMX (delegação de eventos e auto-foco).
   - Estilização em `discussion-reaction-bar.css` aderente ao Stylelint, com tokens de superfície e modo claro/escuro.

2. **Limites Módulo vs. Tema:**
   - O template do módulo `modules/aculta_forum/templates/discussion-reaction-picker.html.twig` é estritamente neutro, sem menção a SDCs do tema.
   - O template de substituição em `themes/custom/aculta420_v2/templates/discussion-reaction-picker.html.twig` faz a ponte com os componentes SDC `aculta420_v2:reaction-emoji` e botões de ação.

### 2.3. Integração UI Suite (UI Icons, UI Patterns e UI Styles Page)
1. **Icon Packs Verificados:**
   - `aculta_phosphor_regular` (1512 ícones).
   - `aculta_phosphor_duotone` (1512 ícones).
   - `aculta_fluent_emoji` (66 SVGs em `assets/icons/fluent-emoji/color`; o catálogo editorial consome 48 deles e o teste exige mínimo de 48).
   - Teste unitário `UiIconsPackTest` valida registro e quantidades mínimas dos três packs; correspondência exaustiva ID -> SVG ainda precisa de gate próprio.
2. **UI Icons Menu e UI Patterns:**
   - Alvo: menus `main`, `account` e `admin` configuráveis via integração de ícones. O Config Sync consultado não confirma ativação de `ui_icons_menu`; conferir Runtime, dependências e exportação na US-00/US-08.
   - Propriedades de ícones integradas aos SDCs do tema (ex: `button` via `$ref: 'ui-patterns://icon'`).
3. **UI Styles Page e UI Library:**
   - Alvo: `ui_styles_page` para estilos de regiões por tema (`/admin/appearance/ui-styles/page/theme/{theme}`). O módulo está habilitado no Runtime e no Config Sync, mas `aculta420_v2.settings` não registra mapeamento `ui_styles_page.regions`; atributos Twig seguem pendentes na US-03.
   - Rota da UI Library (`/painel-administrativo/aparencia/ui/patterns`, `/icons`, `/styles`) com links dinâmicos e controle de acesso exclusivo para administradores.

---

## 3. Consequências e Resultados

- **Manutenibilidade:** Eliminação de tabelas divergentes de emojis entre módulos; qualquer novo consumidor (Messenger, Wiki, Marley) utiliza o serviço `FluentEmojiIconAdapter`.
- **Acessibilidade:** Navegação por teclado, atributos `aria-*` dinâmicos e isolamento de foco no seletor de reações.
- **Conformidade registrada na entrega original:** 0 violações em `validate-module-theme-boundary.php`, 0 violações em `validate-aculta420-sdc-schemas.php`, 0 erros em `npm run lint:css`. Isso NÃO equivale a testes dos novos submódulos da ampliação US-00 a US-11.


---

## 4. Adendo de escopo aprovado - UI Suite integral (2026-11-10)

A ADR-055 passa a cobrir o ecossistema **UI Patterns + UI Styles + UI Icons + UI Skins** como camada visual do `aculta420_v2`. O objetivo não é habilitar todos os submódulos indiscriminadamente: é permitir que todos os pontos de integração **tecnicamente compatíveis, necessários e seguros** reutilizem o design system do ACULTA. Todo SDC continua presente no catálogo universal; exposição a editores, escrita, dados reais e permissões são decisões separadas e estritas.

| Pilar | Integrações normativas | Visual ampliado |
| --- | --- | --- |
| UI Patterns | Blocks, Field/Field Formatters, Layouts, Views, Library e **Field Group 2.0.x-dev**; **excluído CKEditor 5** | SDC em cartões, Views, displays, Layout Builder, agrupamento de campos e substituição de modelos visuais legados |
| UI Styles | Block, Layout Builder, UI Patterns, Views, Page, CKEditor 5, Entity Status | Faixa de acento, sombra, raio, espaçamento, tipografia e estilos de conteúdos não publicados sem novos templates |
| UI Icons | Packs da Icon API, integração de menus, seletor Picker/Popover, propriedades SDC, campos/links e CKEditor sob aprovação | Iconografia consistente e selecionável; Fluent Emoji mantém os donos de catálogo e regras de reação |
| UI Skins | CSS variables e themes/modes/schemes + ponte neutra domínio -> skin | **Substituição integral do motor VISUAL por purpose** com identidade por domínio, modos e paletas, sem alterar a função de rota/acesso do DomainPurposeManager |

**Benefícios por frente:** Revista (teasers, grid, citações, destaques editoriais); Fórum (cards, reações, rankings e estados); Wiki (infobox e organização de campos); GanjaLab (jornadas, progresso e cards); Culturas (agenda, eventos e locais); Loja (grades e cartões); Conta (painéis privados); Apoio, MAIN e Links (landings e superfícies). A aparência continua móvel, coerente em claro/escuro, com molduras tricolores e tokens semânticos.

### Limitações confirmadas que mudam a execução

1. **UI Skins 1.2.1**, compatível com Drupal 11.4+ e 12, deve mapear os tokens existentes. Não substitui automaticamente os contratos multidomínio nem o `data-theme`.
2. **UI Styles CKEditor 5** oferece estilos inline e em blocos, mas os formatos de texto e filtros devem autorizar só classes seguras; editores comuns continuam sem `full_html`.
3. **UI Styles Entity Status** estiliza entidades não publicadas; não concede acesso nem substitui workflow/moderação.
4. **UI Patterns Views 2** pode configurar row, style e display. Preservar Views, pagers, filtros, access/cache, engines VVJB/VVJS.
5. **UI Patterns Layouts** usa slots como regiões e props como opções; manter 22/24 displays compatíveis e as duas exclusões até que sejam corrigidas.
6. **UI Patterns Field Group:** preferir `2.0.x-dev@dev`, travar SHA no Composer lock. Beta2 serve só como evidência dos riscos: o projeto não está coberto pela política de segurança do Drupal e não anuncia Drupal 12. Avaliar HEAD dev, Field Group 4.x e segurança antes da ativação.
7. **UI Patterns CKEditor 5 está EXCLUÍDO deste ciclo:** não instalar, habilitar, prototipar ou criar filtros/widgets. A integração **UI Styles CKEditor 5** permanece prevista para classes editoriais seguras.
8. **UI Icons 2.1** (Drupal 11.3+/12) é referência preferencial; `.icons.yml` do Core sozinho não comprova que UI Icons Menu, Picker, Popover ou Patterns estejam habilitados.
9. **UI Examples** pode demonstrar páginas sintéticas, mas não é um catálogo concorrente. Display Builder/Canvas não substituem Layout Builder nesta ADR sem nova decisão.

### Estado de implementação e requisitos de aceite

- Entregue: ícones declarados pelo tema, adaptador Fluent Emoji, frontend do seletor do Fórum, superfícies SDC, integração de UI Patterns Layouts e Views registrada no Config Sync, biblioteca progressiva. UI Icons Menu, Picker, Fields e Patterns estão em Config Sync; menus principal/conta limitam seleções aos packs Phosphor. Import parcial isolado de `ui_icons_patterns.settings` alinhou Runtime ao Picker após snapshot; Drush confirma o element registrado e menus sem mudanças. Submódulos opcionais seguem diferentes entre Runtime e Sync. Sem aceite visual/accessibilidade dos controles.
- `ui_patterns_ckeditor5` está fora do projeto: desabilitado no Runtime e ausente do Config Sync (`core.extension`), sem consumidores. O diretório permanece apenas como submódulo do pacote `ui_patterns` no vendor tree; não instalar, habilitar, prototipar nem criar configuração. A integração permanece `LEGACY_OBSOLETE`, proibida nesta implementação e sem substituto; remoção física do tree é operação de Composer separada e requer autorização explícita.
- Cutover Runtime executado após snapshot e autorizações explícitas: post-updates migraram settings de flood e confirmação para módulos proprietários; import parcial ativou `ui_styles_ckeditor5`, scopes UI Skins e Views. Mapa ativo usa `links_aculta_org: ruderalis`; os outros domínios resolvem `skunk` por default. Gates UI Skins 134, theme settings 16, foundations 83 pares de contraste e Portal 1.243 PASS. A/B/A anônimo confirmou valores distintos sem carryover entre hosts; contraste e aceite visual amplo/cache-hit continuam DEFERRED. O gate de comércio ainda falhou no rótulo público de login, independente do cutover.
- Atualização US-06 (2026-11-10): 22 configs elegíveis usam UI Patterns ComponentLayout no Runtime/Sync; 21 saídas exibem `layout-section`, e Wiki full usa seu adapter SDC `wiki-entry`. Gate 276 checks PASS; duas exclusões preservadas.
- Atualização Views (2026-11-10): piloto `aculta_albuns:page_1` migrou row/style/display para UI Patterns e SDCs `revista-album-card`, `layout-grid` e `views-display`. Gates de contrato (40 checks), SDC schema (82 componentes/1774 checks) e render/runtime confirmam 3 resultados, pager mini, acesso anônimo e HTTP 200 antes/depois. Firefox ESR 1280/320 claro/escuro confirmou 3 cartões e alvos de 44 px. O plugin de display compõe title, header, exposed form, rows, empty, pager, more, footer e anexos; sources usam handlers nativos da View. Manifesto de capturas: `tests/browser/capturas/ui-patterns-views/2026-11-10/manifest.json`. Navegação de múltiplas páginas, zoom, teclado e cache-hit seguem DEFERRED; restante das Views ainda requer migração.
- Atualização Views (2026-11-11): `aculta_forum_categories:page_categories` usa `views-display` e mantém `forum-category` no row e agrupamento nativo por `parent_name`. Import parcial após snapshot manteve 12 resultados, acesso anônimo e HTTP 200. Firefox ESR validou cinco larguras claras, duas escuras, um H1/main, foco de teclado e alvos ≥44 px. O wrapper duplicou o título na primeira execução; o mapping foi removido antes da captura final. O bloco `block_landing` segue com row SDC, mas seu placement está oculto na home. Cache-hit, zoom 200% e outras Views continuam pendentes.
- Atualização Views (2026-11-11): `eventos_publicos:block_1` agora usa UI Patterns `layout-grid` como style, envolvendo o plugin de row funcional `aculta_events_catalogue_card`; ids com acesso validado, labels e ordenação nativa foram preservados. Import parcial Runtime limitado a um YAML após snapshot SHA-256 `37a007b412a411f33d6b39c130ee98083aa3dbe8f836a3f9dd284dcc1c139d23`; HTTP 200 e dois resultados antes/depois. Firefox ESR confirmou dois `card-cultural` dentro da grade, foco visível e zero overflow em 320/1440 claro/escuro. Evidências: `tests/browser/capturas/ui-patterns-eventos/2026-11-11/manifest.json`. Zoom 200%, cache-hit e revisão visual humana pendem; outras Views continuam no inventário.
- Atualização UI Icons (2026-11-11): versão 2.1.0 e APIs do menu/source confirmadas; packs Phosphor e Fluent seguem pertencendo ao tema/editorial. Import parcial apenas de `ui_icons_patterns.settings` alinhou Runtime/Sync em `icon_picker`; hash do snapshot e evidência constam no inventário Runtime. Não importou `core.extension`: submódulos opcionais ainda diferem. `UiIconsPackTest` PASS (2 testes/47 assertions); aceite de formulários/menus/SDC pendente.
- Atualização 2026-11-11 — UI Styles Entity Status está habilitado no Runtime e Sync, com estilo `unpublished_preview` e classe `aculta-content-status--unpublished`. A API instalada apenas adiciona classe visual a entidades não publicadas e seções Layout Builder; não concede acesso nem altera moderação. Configuração aplicada por import parcial após snapshot. Teste funcional upstream isolado passou (1/7), com 54 deprecações; homologação de saída visual, acesso por perfil e cache permanece DEFERRED. UI Styles Page/CKEditor 5 continuam ativos conforme registro do cutover; homologação visual ainda pendente. **Field Group:** Runtime preexistente habilitado, ausente do Config Sync. Composer lock: `ui_patterns_field_group` `dev-2.0.x`, SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1`, requer Core `^9 || ^10 || ^11`, `field_group:*` e `ui_patterns:^2`; `field_group` 4.0.0 declara Core `^10.3 || ^11`. O Runtime é Drupal 11.5.0-beta1, mas compatibilidade Drupal 12 não está declarada nem demonstrada. [Drupal.org informa que UI Patterns Field Group não é coberto pela Security Advisory Policy](https://www.drupal.org/project/ui_patterns_field_group); `composer audit --locked` não encontrou advisory Drupal para esses pacotes, resultado que não substitui cobertura upstream. Classificação `BLOCKED_UPSTREAM`: não expandir consumidores nem remover o módulo preexistente nesta fase. UI Patterns base `dev-2.0.x` SHA `a7e9c28c522a5b0c8b71c731cab5482d60164e1e` declara Drupal `^10.6 || ^11.3 || ^12`; essa compatibilidade da base não se transfere à integração Field Group.
- Atualização UI Styles Block (2026-11-11): `aculta420_v2_aculta_documents`, restrito a `/transparencia` no MAIN, usa `surface_style` raised e `spacing_section` normal pela API third-party do bloco. Import parcial limitado a um YAML após snapshot SHA-256 `5760883484d6b9c30031ea5cfb427d8de09d1f1a1760a26e34c65e8b0e0b7745`. Runtime anônimo preservou três rows; Firefox verificou computed background claro/escuro, padding responsivo, foco, H1/main e zero overflow em 320/390/768/1024/1440. Manifesto: `tests/browser/capturas/ui-styles-block/2026-11-11/manifest.json`. Zoom 200%, cache-hit e revisão humana pendem.

### UI Patterns Views — lista pública de documentos (2026-11-11)

`aculta_documents:block_1` passou a compor o SDC `document-card` em `layout-grid`, mantendo teaser nativo de node e os render arrays da Field API. A importação focal autorizada após snapshot manteve três resultados no Runtime anônimo. A validação visual da listagem, demais perfis, zoom/cache e presença de links de arquivo nos fixtures atuais continuam pendentes; ADR-055 permanece aberta.

### Catálogo Commerce — UI Patterns Views (2026-11-11)

`aculta_shop_catalog` usa UI Patterns `layout-grid`; o adapter Twig do view mode Commerce compõe o SDC `product-card` com render arrays nativos de preço e compra. Firefox ESR validou 10/10 cenários anônimos em cinco larguras e claro/escuro, sem overflow, com foco, formulário Commerce e contraste mínimo 12,4:1. Cache-hit, zoom, outros perfis e aceite humano permanecem pendentes.
- UI Styles Page está habilitado no Runtime e no Config Sync e oferece `third_party_settings.ui_styles_page.regions` em `aculta420_v2.settings`, aplicável por nome de região a todas as páginas do tema; hoje não há mapeamento de regiões configurado. A API 1.22 inspecionada não oferece seleção por Domain ID. Não usar para skins específicas por domínio nem inferir config por requisição; integração Page ainda requer solução que respeite a fronteira multidomínio.
- Nova execução: **US-00 a US-14** no plano técnico canônico, incluindo substituição integral do legado visual e fechamento conjunto das ADRs. Não avançar sem baseline versionado, testes e rollback, incluindo configuração, 10 purposes, light/dark, 320 a 1440px, zoom 200%, access/cache entre hosts e avaliação editorial humana.
- Documento de planejamento **não equivale** a autorização para `drush cim`, `updatedb`, alterações de dados ou instalação cega. Branch `dev`, commits atômicos, preservar trabalho paralelo e não alterar Core/contrib.
- Estado de conclusão: **ADR-054: cutover implementado, homologação residual**; **ADR-055: implementação parcial comprovada em Git, integração integral planejada, homologação DEFERRED**.

### Fontes primárias

- https://www.drupal.org/project/ui_suite
- https://www.drupal.org/project/ui_skins
- https://www.drupal.org/project/ui_styles
- https://www.drupal.org/project/ui_icons
- https://www.drupal.org/project/ui_patterns
- https://www.drupal.org/project/ui_patterns_field_group
- https://project.pages.drupalcode.org/ui_patterns/
- https://project.pages.drupalcode.org/ui_patterns/1-users/4-with-views/
- https://project.pages.drupalcode.org/ui_patterns/1-users/7-in-ckeditor/


## 5. Decisões complementares vinculantes - migração integral (2026-11-10)

1. **Escopo total:** migrar todo modelo de apresentação que ainda não consome UI Suite, em todos os dez domínios e na administração ACULTA. SDCs, Views, Field Group, Layout Builder, formulários, blocos e templates precisam ter mapeamento de consumidores. Não substituir motores funcionais Core/contrib por marcação estática.
2. **UI Skins como motor visual único:** depois do cutover, paletas e valores de aparência deixam de ser selecionados por `aculta_purpose_palette`, `data-purpose-scheme` e `--aculta-purpose-*`. O Portal renomeia o identificador funcional de `purpose` para `site_key`, entrega um `skin_id` e modo de cor por contrato neutro, e preserva a resolução de `domain_id`, URL, acesso e cache. UI Skins fornece definições visuais; nenhuma config global do tema é mutada por requisição.
3. **Depreciação vs obsolescência:** `LEGACY_DEPRECATED` = visual ainda consumido, substituto e prazo de migração rastreados; `LEGACY_OBSOLETE` = visual já substituído, sem consumidores, pronto para limpeza comprovada. `FUNCTIONAL_NATIVE_EXCEPTION` protege Form API, Views, Commerce, VVJ etc. Não esconder SDCs do catálogo.
4. **UI Patterns Field Group:** preferir `2.0.x-dev@dev`, com pin, auditoria de segurança, compatibilidade Drupal 11.5/12, Field Group e rollback.
5. **UI Patterns CKEditor 5:** expressamente NÃO usar por agora; não criar piloto nem tarefa bloqueante de implementação. UI Styles CKEditor 5 continua.
6. **Aceite:** ADR-054 e ADR-055 apenas encerradas após US-14 **e RN-05**, inventário integral, 10 domínios em 2 modos, testes de cache/permissão/configuração, revisão visual humana e rollback verificável.
7. **Documento normativo da execução:** `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md`. Este adendo prevalece sobre qualquer menção anterior a protótipo UI Patterns CKEditor 5 ou preservação do motor de paletas por purpose.

## 6. Adendo aprovado - Renomeação interna `purpose` para `site_key`

A distinção entre identidade funcional e identidade visual passa a ser obrigatória em todo o ACULTA: **`site_key` é a chave funcional canônica**, **`domain_id` é a entidade Drupal Domain** e **`skin_id` é a identidade visual UI Skins**. Não preservar `purpose` como nomenclatura permanente do Portal. Os valores estáveis `main`, `account`, `support`, `magazine`, `wiki`, `shop`, `courses`, `forum`, `links` e `eventos` não mudam.

A renomeação cobre classes, serviços, métodos, route options, subscribers, form builders, listas de sites ativos, contratos de apresentação e Twig, metadata de cross-domain, retorno ao login em sessão, State e testes. **Não modificar** Domain IDs, slugs/URLs públicas, `field_domain_source`, entidades, conteúdo, regras de acesso ou fluxos de pagamento. Os nomes antigos recebem aliases *internos* temporários e fail-closed quando metadados de rota novo e antigo conflitam; não criar aliases de rota públicos.

Execução em RN-00 a RN-05 conforme `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`. Integrar RN-01 com a ponte visual UI Skins (US-02), migrar todos os consumidores antes de remover APIs obsoletas e exigir RN-05 PASS antes de encerrar US-14. A exclusão de UI Patterns CKEditor 5 e a preferência pelo Field Group `2.0.x-dev@dev` permanecem inalteradas.


## Atualização — UI Patterns Views na Revista (2026-11-11)

`aculta_news:block_1` usa o SDC editorial existente `revista-listing` como style UI Patterns, com `view_rows` no slot `items`; quatro rows `node:revista`/teaser permanecem sob Views e UI Patterns wrapper não altera consulta. A/B/A importou apenas `views.view.aculta_news`; HTTP 200, IDs 599–602, acesso anônimo, filtros/ordenação/pager/cache invariantes. Firefox ESR passou 10/10 antes e depois em cinco larguras e dois modos, com H1/main visíveis, foco, contraste ≥7.16:1 e sem overflow. Config Sync foi alinhada ao Runtime, inclusive source boolean `checkbox`. Zoom 200%, cache-hit e revisão humana DEFERRED. Evidências versionadas no manifesto `tests/browser/capturas/ui-patterns-revista-news/2026-11-11/manifest.json`.

## Atualização — Home editorial em UI Patterns Views (2026-11-11)

`home_editorial_highlights:block_1` usa o SDC `layout-grid` no style UI Patterns; `views_vvjb` foi removido da configuração da View. Permanecem as três rows editoriais, IDs 14–16, ordem, links, View access, filtros, pager, query e Layout Builder de entidade existentes. A/B no Runtime: HTTP 200 e contagem 3 antes/depois; gates focais da View e SDC passaram. Firefox ESR passou 10/10 por fase em cinco larguras e claro/escuro; H1/main únicos, foco visível, contraste ≥8.80:1, sem overflow. Cache-hit é DEFERRED porque a página responde private/no-cache e dynamic cache `UNCACHEABLE`; zoom 200% e homologação ampla também pendem. Inventário CSV/Runtime e rollback em `tests/browser/capturas/ui-patterns-home-editorial/2026-11-11/manifest.json`. A busca no Sync e no Runtime confirmou zero displays `views_vvjb`; seus diagnósticos específicos foram aposentados. A extensão permanece habilitada; desinstalação requer autorização operacional própria.

## Atualização — galeria editorial em UI Patterns Views (2026-11-11)

`aculta_album_fotos:block_1` usa o style UI Patterns com `layout-grid`, substituindo VVJS somente na galeria editorial. A View mantém o row `fields`, PhotoSwipe, 63 imagens em ordem, pager, acesso de mídia e cache. O ramo separado `aculta_forum_gallery_slides` ainda usa VVJS; por isso a extensão tem consumidores e permanece instalada. Firefox ESR passou 10/10 antes e 11/11 depois em cinco larguras e dois modos, com teclado no PhotoSwipe. Zoom 200%, cache-hit, revisão humana e demais perfis seguem DEFERRED. Snapshot, rollback e capturas estão no manifesto `tests/browser/capturas/ui-patterns-gallery/2026-11-11/manifest.json`; a ADR permanece aberta.


## Atualização — galerias do Fórum em UI Patterns (2026-11-11)

`aculta_forum_gallery_slides:block_1` e `galeria_produto:default` foram migradas de VVJS para o SDC existente `layout-grid`, cada uma por importação parcial isolada após snapshot do Runtime. Configuração Sync e Runtime parseada coincide; a View executável usa `ui_patterns` e não retorna resultados porque não há galeria Fórum publicada. PhotoSwipe, rows Fields, query, argumento contextual, pager, filtro de acesso a Media e cacheability do módulo permanecem. Os controles de slideshow/miniaturas do Fórum e a dependência de VVJS foram removidos do `aculta_gallery`; o hook Commerce segue construindo a View sem mudança no contrato do render array. Aceite visual/access/cache do Fórum está `DEFERRED` até existir fixture publicada; aceite visual e revisão de acesso/cache da galeria de produto também estão `DEFERRED`. A ADR permanece aberta; `galeria_produto` e `vvjs_example` seguem no inventário como consumidores sem decisão de cutover nesta microfase.


## Atualização — View pública de tópicos do Fórum (2026-11-11)

A View `aculta_forum_public_topics` já compõe suas rows com o SDC existente `forum-topic-row` por um adaptador Twig do tema, sem duplicar o markup visual. Runtime/Sync coincidem; os cinco displays ativos retornam 10 resultados cada. Render anônimo no host Fórum produziu dez rows e manteve cache contexts de domain/query/node grants. O UI Patterns Views row plugin permanece pendente porque o source `view_field` instalado só fornece slots, enquanto o componente exige props de estado do tópico; sua adoção sem ampliar o mapeamento perderia pinned/badges. Isto é classificado `UI_SUITE_NATIVE` e requer revisão de browser/perfis antes do aceite, não uma migração visual de markup.


## Atualização — UI Patterns Views na Wiki (2026-11-11)

O style da View `wiki_entries` consome `aculta420_v2:layout-grid` via UI Patterns Views, com `max_columns=2` como source numérico e `view_rows` no slot. O componente SDC existente ganhou prop `max_columns` estrita para limites 2/3/4 sem criar outro grid. O wrapper herdado preserva o template teaser/SCD `wiki-entry`; a regra responsiva Wiki mantém uma coluna até 767px. Runtime/Sync coincidem e browser passou 215/215; cache-hit, zoom e aceite amplo seguem DEFERRED.


## Atualização — UI Patterns Views em categorias de taxonomia (2026-11-11)

`wiki_categories:block_1` compõe as rows de termo pelo `layout-grid` SDC existente com UI Patterns Views. A configuração não converte taxonomia em markup duplicado nem altera links; Views continua dona da consulta, filtro, acesso, ordenação e pager. Browser validou 15 categorias e reflow responsivo com 245/245 verificações.


## Atualização de homologação — UI Skins Links e catálogo (2026-11-11)

Firefox ESR autenticado confirmou o catálogo protegido de UI Patterns com 16 grupos, 84 SDCs e uma story carregada sob demanda em 320/1280; o acesso anônimo retorna 403. O diagnóstico de largura do catálogo agora mede somente `#aculta-components-container`, deixando separado o menu lateral do tema administrativo Claro. O viewport 320 ainda registra elementos da navegação Claro fora da viewport sem rolagem de documento; zoom 200% e teclado administrativo seguem DEFERRED. Captura: `tests/browser/capturas/ui-suite-library/2026-11-11/manifest.json`.

A matriz UI Skins passou 300/300 nos dez domínios em cinco larguras e claro/escuro. Links resolve `data-skin=ruderalis`; os outros nove `skunk`. H1/main/barra únicos, contraste ≥7,16:1 e sem overflow. Links mantém a apresentação standalone prevista em seu SDC. Manifesto com 40 capturas: `tests/browser/capturas/ui-skins-ten-domains/2026-11-11/manifest.json`. Cache-hit, zoom, teclado, perfis e aceite visual humano seguem pendentes; as ADRs não estão concluídas.


## Estado do motor visual por purpose — 2026-11-11

A busca focal em tema, Portal e Config Sync não encontra `aculta_purpose_palette`, `data-purpose-scheme`, `--aculta-purpose-*`, `PurposePaletteSchemes`, `purposePaletteStyle()`, `aculta_cores_purpose` nem `purpose_palette`. Formulário/schema, código, seletores e valores sincronizados antigos foram removidos; não há alias, dual-read, fallback ou cópia preservada. Estes itens passam a `LEGACY_OBSOLETE`. A preferência de modo do usuário e `DomainPurposeManager` permanecem funcionais.


## Atualização US-14 — homologação residual de UI Skins (2026-11-11)

Firefox ESR visível nos dez domínios, em 320/768/1440, fechou 70/70 verificações: teclado com foco visível (30), zoom 200% sem overflow com um único institution/main/H1 (30) e headers de cache observados em três requisições anônimas por domínio (10). Zoom foi emulado por `layout.css.devPixelsPerPx=2`; as homes são `private/no-cache` com `UNCACHEABLE`, então MISS/UNCACHEABLE é o esperado. Evidência versionada: `tests/browser/capturas/ui-skins-final-acceptance/2026-11-11/manifest.json`. Aceite visual humano e perfis editor/admin continuam DEFERRED; RN-05 permanece em execução e a ADR não está encerrada.

Nota de bloqueio (2026-11-11): rotas públicas sondadas respondem `Cache-Control: private, no-cache` e `X-Drupal-Dynamic-Cache: UNCACHEABLE`, então cache-hit não é mensurável no estado atual. `drush uli` responde 404 e `/entrar` só existe no host de conta; a verificação autenticada deve reutilizar `tests/browser/auth-login.mjs`.

Fechamento parcial de 2026-11-11 — login autenticado: a autenticação de `teste_administrativo` é aceita (`users.login` atualizado, cookie `SSESS…` emitido), mas a sessão não persiste na requisição seguinte — o Runtime espera `SESS…` e o cliente recebe `SSESS…` —, bloqueando o aceite de perfis. O teste também revelou H1 duplicado no dashboard do Studio, corrigido no componente `studio-menu` (topbar sem `<h1>`), com schema 1801 checks e boundary PASS.

## Atualização — UI Patterns na listagem de galerias públicas (2026-11-11)

A View `media_galleries:page_1` (`/galleries`, MAIN) foi migrada com o SDC existente `revista-album-card` para a row, mapeando o formatter nativo da miniatura e o título; UI Patterns Views `layout-grid` compõe `view_rows`. A importação parcial foi isolada somente em `views.view.media_galleries`, após snapshot do Runtime. Antes/depois a View entregou três resultados; depois são três cards, três títulos e duas imagens. Firefox ESR confirmou HTTP 200, um H1/main e grade sem overflow em 320/390/768/1024/1440, claro/escuro. Config de pager full preservada, porém não há volume suficiente para exercitar navegação. Cache-hit, zoom 200%, teclado, perfis e revisão visual humana seguem DEFERRED. Evidência: `tests/browser/capturas/ui-patterns-media-galleries/2026-11-11/manifest.json`.
