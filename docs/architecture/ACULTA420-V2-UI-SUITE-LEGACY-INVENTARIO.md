# Inventário inicial UI Suite e legado visual — US-00

**Data:** 2026-11-10 · **Base documental:** `origin/dev` em `16ac6070974f3e01f16ab3661a7cbe025d80ad32` · **HEAD local:** divergente (`4cc0c0dfe2ea9da8e5d136f5826a039e6656f98e`).

**Estado: PARCIAL / DEFERRED para aceite US-00.** Este registro é uma fotografia inicial auditável, não afirma inventário integral de todas as superfícies visuais. Contagens abaixo foram obtidas no worktree local, cujos documentos e Composer diferem de `origin/dev`; conferir `git status` antes de usá-las como baseline remoto. Nenhuma configuração ou dado foi alterado.

> **Adendo de estado atual — 2026-11-11:** as afirmações abaixo sobre Runtime/Sync, ausência de imports e ausência de integrações são históricas da fotografia inicial. A execução autorizada desde então habilitou UI Skins no Runtime, importou seu mapa/scopes e migrou Views de forma parcial. O snapshot Runtime mais recente está em `docs/architecture/inventarios/ui-suite-runtime-2026-11-10.json`; o inventário de consumidores foi regenerado e contém 441 registros, incluindo Wiki. Este adendo prevalece em caso de divergência.

> **Adendo UI Skins Links — 2026-11-11:** Firefox ESR anônimo passou 300/300 nos dez domínios, em 320/390/768/1024/1440 e claro/escuro. Links resolveu `ruderalis`; os outros nove, `skunk`. H1/main/barra únicos, sem overflow e contraste mínimo 7,16:1. Header compartilhado é opcional na home; LinkWeed usa `social-links-page`. Quarenta capturas/hash em `tests/browser/capturas/ui-skins-ten-domains/2026-11-11/manifest.json`. Cache-hit, zoom, teclado, perfis e aceite humano seguem pendentes.

> **Adendo UI Library — 2026-11-11:** acesso administrativo autenticado carregou 16 grupos, 84 SDCs e uma story sob demanda em 320/1280, sem overflow do conteúdo do catálogo. A checagem anônima retorna 403 como esperado. A lista do drawer do tema Claro pode estar fora da viewport de 320 sem rolagem do documento e foi separada da métrica do conteúdo; zoom e teclado administrativo seguem pendentes. Evidência em `tests/browser/capturas/ui-suite-library/2026-11-11/manifest.json`.

> **Adendo UI Icons — 2026-11-11:** UI Icons 2.1.0 está habilitado em Sync e Runtime. Menus principal/conta usam o adaptador oficial, `icon_picker` e apenas os packs Phosphor Regular/Duotone; não têm atribuições de ícone identificadas. Fluent Emoji permanece no fluxo editorial/CKEditor do tema. UI Patterns Icons usa o source nativo; `icon_picker` foi importado em operação parcial limitada a `ui_icons_patterns.settings`, após snapshot, e Drush confirma o element disponível. Submódulos opcionais ainda diferem entre Runtime e Sync; nenhum módulo foi removido. Classificação `MIGRATION_IN_PROGRESS`: sem consumidor de prop SDC nem aceite visual/acessibilidade nesta fase.

> **Adendo UI Styles Entity Status — 2026-11-11:** habilitado em Sync/Runtime após snapshot e import parcial de `aculta420_v2.settings`. O tema fornece `unpublished_preview` → `aculta-content-status--unpublished`, usado pelo módulo apenas para sinal visual em conteúdo não publicado e seções Layout Builder. A API não altera acesso/moderação. Teste funcional upstream 1/7 passou; visual real, perfis, revisão e cache seguem DEFERRED. Classificação `MIGRATION_IN_PROGRESS`.

| Superfície atual | Owner / consumidores | Contrato e substituto | Status e evidência |
|---|---|---|---|
| Motor visual de paletas por purpose (`aculta_purpose_palette`, `PurposePaletteSchemes`, `purposePaletteStyle()`, `data-purpose-scheme`, `--aculta-purpose-*`) | tema V2; superfícies em domínios | Removido; UI Skins publica CSS variables por `data-skin`/modo, selecionadas pelo Portal via Domain ID | `LEGACY_OBSOLETE`; busca focal sem consumidores. Não manter alias, fallback nem cópia de configuração antiga, conforme instrução do responsável |
| `DomainPurposeManager` e `DomainSurfaceMap` | Portal; roteamento, URLs, ACL, sessão, conteúdo e cache | Mantidos por funções de domínio; UI Skins não assume essas responsabilidades | `FUNCTIONAL_NATIVE_EXCEPTION`; migração de nomenclatura para `site_key` segue RN-00–05 |
| UI Skins | Portal/tema; dez Domain IDs | 1.2.1 ativo; Links `ruderalis` medido em cinco larguras e dois modos, contraste ≥7,16:1; demais usam Skunk default | `MIGRATION_IN_PROGRESS`; gate automatizado dos dez domínios 300/300; cache-hit, zoom 200%, teclado, perfis e aceite humano pendentes |
| `aculta_albuns:page_1` | `aculta_gallery`; Revista | UI Patterns row `revista-album-card`, style `layout-grid`, display `views-display` | `UI_SUITE_NATIVE`; três resultados, anônimo/HTTP 200 e Firefox 1280/320 claro/escuro. Cache-hit, teclado, zoom e multi-page pendentes |
| `aculta_album_fotos:block_1` | `aculta_gallery`; Revista | UI Patterns style `layout-grid` compõe 63 rows PhotoSwipe; Gallery mantém filtro de acesso e metadados de cache | `UI_SUITE_NATIVE`; 63 imagens/ordem preservadas, HTTP 200; Firefox A/B 10/10 em cinco larguras e modos; PhotoSwipe Escape PASS. Cache-hit, zoom 200%, perfis e ramo Fórum pendentes |
| `revista_sessoes` páginas editoriais (6 displays) | `aculta_magazine`; Revista | View mantém row nativo `entity:node`/`teaser`; template ACULTA encaminha render arrays de campos para slots do SDC `revista-teaser` | `UI_SUITE_NATIVE` confirmado por Sync + template; `/revista` redireciona para `/`; smoke da home confirma quatro cards SDC, mas sem atribuição ao View. Acesso, pager/cache e aceite do View pendentes |
| `aculta_forum_categories:page_categories` | `aculta_forum`; Fórum | UI Patterns row `forum-category`, style nativo mantém agrupamento, display `views-display` | `UI_SUITE_NATIVE`; 12 resultados, anônimo/HTTP 200; Firefox cinco larguras claras, duas escuras, teclado e alvos ≥44 px |
| `aculta_forum_categories:block_landing` | `aculta_forum`; bloco de entrada | UI Patterns row `forum-category`; style/display nativos | `MIGRATION_IN_PROGRESS`; placement pai está oculto na home, sem aceite de saída visual |
| Displays `default` sem output próprio | Views/Drupal | Base funcional herdada por displays explícitos | `FUNCTIONAL_NATIVE_EXCEPTION`; não depreciar consultas, acesso, filtros ou ordenação nativos |

UI Patterns Field Group 2.0.x-dev permanece `BLOCKED_UPSTREAM` para novos consumidores: SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1`, sem cobertura Drupal Security Advisory Policy, metadata sem Drupal 12 e compatibilidade com Field Group 4.x ainda não demonstrada. O pacote instalado `field_group` 4.0.0 declara Core `^10.3 || ^11`; a integração declara Core `^9 || ^10 || ^11` e requer `ui_patterns:^2`. O Runtime Drupal 11.5.0-beta1 satisfaz parte dessas declarações, mas não prova Drupal 12. `composer audit --locked` não listou advisory Drupal para esses pacotes; isso não equivale a cobertura de segurança. O módulo está enabled preexistente; não ampliar uso nem remover sem operação autorizada. UI Patterns CKEditor 5 segue `LEGACY_OBSOLETE`, sem consumidores no repositório e proibido pela ADR-055; remoção do módulo preexistente do Runtime aguarda operação autorizada.

## Evidências de baseline

| Item | Evidência coletada | Estado / limite |
|---|---|---|
| Repositório | `origin/dev` avançou e divergiu do HEAD local; documentos de planejamento pedidos estão removidos no worktree | Consultados pelo `git show origin/dev:<path>`; não restaurados sobre remoções locais |
| Runtime | Drupal 11.5.0-beta1; conexão Drush disponível | `pm:list`, `cget`, `updatedb:status`, `config:status` somente leitura |
| Drift Runtime/Sync | `config:status` lista diferenças de menus, Views, tema, workflows e outras configurações; warnings de registros `system.schema` de módulos ausentes | **DEFERRED** para sincronização e homologação. Nenhum `cim`, `updatedb`, limpeza de cache ou alteração Runtime executada |
| Composer/lock local | UI Icons 2.1.0, UI Skins 1.2.1, UI Patterns `2.0.x-dev` SHA `a7e9c28c522a5b0c8b71c731cab5482d60164e1e`, UI Patterns Layouts 2.0.23, UI Patterns Field Group 2.0.0-beta2, UI Styles 1.22.0 | Lock local diverge de `origin/dev`; `composer.json` local adiciona Icons/Skins, mas Field Group segue beta2, não a preferência vinculante dev |
| Diretórios contrib locais | `ui_icons`, `ui_skins`, `ui_patterns`, `ui_patterns_field_group`, `ui_styles` presentes | UI Patterns Layouts é dependência do pacote Patterns nesta instalação, sem diretório separado |
| Módulos habilitados no Runtime | Drush lista 27 extensões entre base e submódulos relacionados a Icons, Patterns, Styles e Field Group; inclui `ui_patterns_ckeditor5` | Discrepância operacional com Sync e escopo aprovado. Nenhum módulo foi desabilitado; não ampliar uso de `ui_patterns_ckeditor5` |
| Config Sync no worktree | Entre extensões UI Suite: `field_group`, `ui_patterns`, `ui_patterns_field_group`, `ui_styles`; não constam Icons, Skins, UI Styles Page/CKEditor 5/Entity Status nem a maioria das integrações | Config Sync não prova estado Runtime |
| Tema | 80 diretórios SDC, 80 manifests `.component.yml`, 123 stories; 66 Views em `config/sync`; 216 arquivos de view display/form display/field group/layout/block candidatos no Sync | Contagem bruta, não cobertura funcional ou inventário por consumidor |
| Busca inicial | 44 arquivos correspondem à cadeia antiga de purpose no tema/Portal/Sync; 251 arquivos customizados contêm indicadores de renderização visual | Indicador bruto da busca documentada no plano. Cada ocorrência precisa triagem de uso e consumidor |
| Extração por consumidor | `docs/architecture/inventarios/ui-suite-consumidores-iniciais.csv`: 159 displays de 66 Views + 22 entity displays com Layout Builder + 80 SDCs com caminhos de referências literais detectadas; plugin ID scan não detectou Views com UI Patterns row/style/display | Candidatos; owners das Views ainda não resolvidos por extensão/implementação. CSV não prova render Runtime, acesso/cache ou adequação da substituição |
| Contratos funcionais RN-00 | `docs/architecture/inventarios/site-key-purpose-contracts.csv`, gerado por `php scripts/inventory-site-key-contracts.php`: 3.059 linhas com path/linha/owner e categoria | Indicador textual; 1.610 ocorrências genéricas de `purpose` precisam triagem semântica. Não equivale a migração RN-00 concluída |

### Matriz de integrações observadas

| Integração | Composer/FS | Config Sync local | Runtime (Drush) | Classificação atual |
|---|---|---|---|---|
| UI Patterns base | presente | habilitado | habilitado | `UI_SUITE_NATIVE` |
| UI Patterns Blocks / Field / Field Formatters / Layouts / Views / Library | módulos instalados | conferir configurações consumidoras individualmente | habilitados | `UI_SUITE_NATIVE` nos consumidores já ligados; restante **não classificado** |
| UI Patterns Field Group | `dev-2.0.x` SHA `4403e7b0e27679f47113e96fcdc5349c95e164d1`; exige Core `^9 || ^10 || ^11`, Field Group `*`, UI Patterns `^2`; sem Security Advisory Policy upstream | habilitado no snapshot local | enabled preexistente; Drupal 11.5.0-beta1 | `BLOCKED_UPSTREAM`: sem cobertura de advisory, declaração sem Drupal 12, compatibilidade Field Group 4.x não demonstrada; `composer audit` sem advisory Drupal não constitui cobertura. Não adicionar consumidores nem remover via esta fase |
| UI Patterns CKEditor 5 | módulo aparece no filesystem | ausente | habilitado segundo Drush | `BLOCKED_UPSTREAM`/fora do escopo aprovado; discrepância precisa reconciliação operacional. Não criar integrações novas nem alterar Runtime sem procedimento autorizado |
| UI Styles base e submódulos | 1.22.0 presente | base e Block/Layout Builder/Library/UI Patterns/Views habilitados | habilitados | `UI_SUITE_NATIVE` onde configurado |
| UI Styles Page / Entity Status / CKEditor 5 | UI Styles 8.x-1.22, confirmados no runtime | Page/CKEditor 5 ativos; Entity Status habilitado nesta fase | Entity Status aplica classe de pré-visualização não publicada configurada no tema; teste upstream 1/7; avaliação visual, acesso e cache pendem | `MIGRATION_IN_PROGRESS`; sem alteração de regras funcionais |
| UI Icons e Menu/Picker/Popover/Patterns | 2.1.0 e módulos presentes no filesystem local | ausentes | base e submódulos correspondentes listados como habilitados | `UI_SUITE_NATIVE` apenas nos consumidores verificados; configuração efetiva e mapeamento de ícones pendentes |
| UI Skins (baseline inicial) | 1.2.1 presente no filesystem local | ausente na fotografia inicial | ausente na fotografia inicial | Baseline histórico; ver estado atual e evidência Links no adendo de 2026-11-11 |

### API UI Skins e resolução por domínio

Nota histórica da inspeção da API 1.2.1: o hook `PageTop` publica CSS variables a partir de settings. A execução atual separa a resolução Portal Domain ID → `data-skin` e os valores UI Skins selecionados, sem alterar settings globais por request. Runtime agora tem UI Skins e o mapa ativos; piloto e limites atuais constam do adendo acima.

## Registro inicial de superfícies rastreadas

O plano vinculante exige uma linha para cada superfície ativa com owner, rota/consumidores, dados, substituto, acesso/cache, teste antes/depois, commit e rollback. A tabela abaixo preserva a trilha dos elementos identificados na busca inicial da cadeia de paletas; os itens visuais marcados obsoletos foram removidos após busca de zero consumidores. A tabela não representa cobertura site-wide.

| ID / caminho | Owner e consumidores conhecidos | Origem / risco | Alvo UI Suite | Status e retirada |
|---|---|---|---|---|
| `theme-settings.php` — controles `aculta_cores_purpose` / `aculta_purpose_palette` | tema V2; antigo formulário administrativo | Config global antiga removida; nenhum alias/dual-read/fallback | UI Skins + mapa Portal | `LEGACY_OBSOLETE`; controles/schema ausentes e zero consumidores em busca de tema, Portal e Sync (2026-11-11) |
| `src/Palette/PurposePaletteSchemes.php` | tema V2; antigo catálogo de cores | Arquivo/classe ausentes; zero referências em código/Sync | definições e scopes UI Skins | `LEGACY_OBSOLETE`; substituído e removido; busca focal sem consumidores (2026-11-11) |
| `src/Hook/SurfaceSettingsHooks.php::purposePaletteStyle()` | tema V2; antigo preprocess visual | Método ausente; classe restante mantém hooks não relacionados à paleta | Portal skin resolver + UI Skins | `LEGACY_OBSOLETE`; substituído e removido; zero referências em código/Sync (2026-11-11) |
| `src/styles/tokens.css` seletores `data-purpose-scheme` / `--aculta-purpose-*` | tema V2; antigos tokens visuais | Seletores/variables ausentes; busca focal sem ocorrências em código/Sync | Tokens semânticos alimentados por UI Skins | `LEGACY_OBSOLETE`; removidos, sem alias/fallback (2026-11-11) |
| `aculta420_v2.ui_styles.yml::purpose_palette` | tema V2; opções antigas do UI Styles | Seleção por paleta ausente em código e configuração sincronizada | UI Skins (identidade) + UI Styles (variações estruturais) | `LEGACY_OBSOLETE`; zero referências consumidoras em código/Sync (2026-11-11) |
| `AppearanceHooks` + `ColorModePreference` | Portal; páginas públicas e autenticadas em todos os domínios | preferência do usuário > default do site; cache `user`, `domain`, config e usuário | manter preferência no Portal; delegar a apresentação light/dark ao UI Skins | `MIGRATION_IN_PROGRESS`; não remover preferência nem domínio; exige teste de cache e modos |
| `DomainPurposeManager` / `DomainSurfaceMap` | Portal; rotas, URLs, conta, sessão, ACL, conteúdo e cache | Contrato funcional transversal, não apenas visual | preservar; novo serviço separado de resolução Domain ID → skin | `FUNCTIONAL_NATIVE_EXCEPTION`; não depreciar nem substituir pelo UI Skins |
| Views e displays do site | 66 Views/159 displays no inventário; pilotos row/style/display em Álbuns, Fórum, Eventos, Revista News, Home e `/galleries`, mais adapters SDC da Revista | rows e consultas nativas preservam dados, acesso, filtro, ordenação, pager e cache por display | UI Patterns Views row/style/display, Layouts e SDCs existentes | pilotos verificados `UI_SUITE_NATIVE`; `/galleries` usa `revista-album-card` + `layout-grid`, com navegação pager/cache-hit/zoom 200% ainda pendentes; demais consumidores seguem `MIGRATION_IN_PROGRESS` até validar owner, saída, acesso e cache |
| Formulários, blocos, páginas administrativas e templates Portal | módulos customizados e tema; 251 candidatos brutos de render | Form API/Render API, acesso, cache e HTMX são riscos de preservação | adaptadores SDC/UI Styles/Patterns mantendo plugin/API original | **não classificado por consumidor**; exige varredura arquivo/rota e testes direcionados |

## Próximas evidências necessárias

1. Completar o inventário por módulo/rota/configuração e homologar cada consumidor restante segundo os campos do plano vinculante.
2. Confrontar Runtime e Sync por microfase focal; preservar alterações paralelas identificadas no checkout e não incluir caminhos de terceiros nos commits.
3. Resolver Field Group `2.0.x-dev@dev` com SHA travado e avaliação documentada; até lá, não ampliar sua ativação.
4. Completar contraste e aceite visual nos dez domínios, cache-hit, zoom, perfis e rollback.
5. Executar a homologação conjunta US-14 e RN-05; a ADR permanece aberta até todos os critérios terem evidência aprovada.

## Resultado US-00

**Parcial; critério bloqueante ainda DEFERRED.** Pacotes, versões locais, extensões Sync/Runtime, 159 displays de Views, 22 entity displays com Layout Builder, 80 definições SDC e referências literais iniciais foram confrontados. Regenerar com `php scripts/inventory-ui-suite-consumers.php`. Owners e saídas das Views, validade semântica de cada referência SDC, inventário integral por cada uma das dez frentes, interfaces administrativas e todos os modelos visuais ainda não estão concluídos. Não declarar ADR-054/055 concluídas com este artefato.

O inventário funcional separado RN-00 pode ser regenerado com `php scripts/inventory-site-key-contracts.php`. O CSV agrega ocorrências por linha e categoria; menções genéricas requerem revisão semântica e os nomes propostos devem seguir o plano `ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`.


## Atualização de consumidores — Revista News View (2026-11-11)

`aculta_news:block_1` foi reclassificada `UI_SUITE_NATIVE`: UI Patterns style consome o SDC `revista-listing`; row entity teaser e `revista-teaser` permanecem nativos e reutilizados. O block placement também é `UI_SUITE_NATIVE` por delegar apresentação ao display validado. O display `default` foi classificado `FUNCTIONAL_NATIVE_EXCEPTION` por ser base herdada sem rota própria. O inventário gerado contém owners `aculta_magazine`, domínio magazine, source de dados, riscos, substituto e evidência Runtime/browser. Rollback parcial validado; zoom, cache-hit e aceite humano pendentes.

## Atualização de consumidores — Home editorial e obsolescência VVJB (2026-11-11)

`home_editorial_highlights:block_1` foi reclassificada `UI_SUITE_NATIVE`: o style UI Patterns compõe o SDC `layout-grid` em torno das três rows `entity:node`/default, mantendo o Layout Builder `layout-section` de cada entidade. HTTP 200 e nodes 14–16 foram preservados; access, query, filtros, ordenação, pager, header, row e cache foram comparados antes/depois. Firefox ESR passou 10/10 antes e 10/10 depois em cinco larguras claro/escuro, com H1/main, foco, contraste mínimo 8,80:1 e sem overflow. A Home permanece `private/no-cache`, então cache-hit está DEFERRED; zoom 200% e revisão ampla também pendem.

O estilo de View `views_vvjb` fica `LEGACY_OBSOLETE`: zero configurações de View ativas no Sync/Runtime ainda o consomem, a saída da Home agora é `layout-grid`, e os diagnósticos/testes específicos foram retirados ou atualizados. A extensão VVJB permanece instalada e habilitada; nenhuma desinstalação foi executada. Critério de retirada da extensão: confirmar zero consumidores de runtime e obter a autorização operacional exigida para `pm:uninstall`.

## Atualização de consumidores — Álbum editorial em UI Patterns (2026-11-11)

`aculta_album_fotos:block_1` foi reclassificada `UI_SUITE_NATIVE`: UI Patterns style usa `aculta420_v2:layout-grid` sobre as rows `fields` existentes; formatter PhotoSwipe, 63 imagens, ordem, pager de 150, filtro de acesso e cache foram mantidos. A importação parcial alterou somente `views.view.aculta_album_fotos`, após snapshot cujo YAML de rollback parseado coincidiu exatamente com a configuração Runtime. Firefox ESR passou 10/10 antes e 10/10 depois em 320/390/768/1024/1440 px claro/escuro, com foco, 63 imagens com alt/lazy loading, H1/main e sem overflow. PhotoSwipe abre e fecha por Escape. Zoom 200%, cache-hit, perfis e revisão assistiva seguem pendentes; o ramo VVJS da galeria do Fórum continua separado.

## Atualização — Views de galeria do Fórum e Commerce (2026-11-11)

`aculta_forum_gallery_slides:block_1` e `galeria_produto:default` agora usam UI Patterns `layout-grid`. Cada import parcial foi restrito a um YAML e precedido por snapshot Runtime; os documentos Runtime/Sync parseados coincidem. Fórum não tem galeria publicada (0 rows), portanto revisão visual com conteúdo real, access e cache ficam `DEFERRED`. A View de produto é consumida pelo hook `ProductGalleryHooks`; no produto Commerce publicado há sete mídias e a View UI Patterns retornou sete rows. Revisão visual/access/cache no navegador segue `DEFERRED`. O submódulo Gallery deixa de depender de VVJS; a extensão permanece habilitada pelo exemplo upstream `vvjs_example`, ainda classificado como candidato separado. Inventário CSV e JSON registram owner, consumidor, configuração e evidências.


## Bloqueio upstream — barra administrativa Gin (2026-11-11)

A regressão Firefox da galeria verificou que `.top-bar__actions` do Gin extrapola a viewport MAIN em 480/479/390/320 px; no host Fórum as seis larguras passaram. Owner: módulo upstream Gin; consumidor: toolbar administrativa autenticada; fonte: ações “Editar/Mais ações”. Classificação `BLOCKED_UPSTREAM`; não alterar contrib nesta migração. A grade da galeria continua dentro da viewport, sem overflow e com os 63 links PhotoSwipe. Revisão/ajuste do toolbar requer issue/upstream ou extensão proprietária isolada.


## Atualização — integração UI Patterns Views do Fórum (2026-11-14)

A configuração atual do checkout substitui os presenters Twig de tópicos e busca por UI Patterns Views/SDCs. A row `forum-topic-row` recebe `sticky` pela Source `view_field_value`; a busca usa `forum-search-page`, preservando título escapado, links contextuais e slots nativos de resultado, filtro, vazio e paginação. Config Sync e `config/install` estão alinhados. O inventário gerado marca `aculta_forum_search.page_search` como `UI_SUITE_NATIVE`. O Runtime ainda diverge nesses displays e precisa de importação/revisão de saída antes de declarar o corte homologado.

## Fórum público — row SDC nativa e source mapping (2026-11-11)

As cinco saídas (`block_recent` e quatro páginas) de `aculta_forum_public_topics` usam Fields/Views nativos e o adaptador `views-view-fields--aculta-forum-public-topics.html.twig`, que compõe o SDC reutilizável `forum-topic-row`. Runtime/Sync parseados coincidem; os cinco displays executam com 10 resultados cada. Uma renderização como anônimo no host Fórum produziu dez `<article class="forum-topic-row">` e preservou cache contexts de domain, query e node grants. Classificação `UI_SUITE_NATIVE`: o markup da row não duplica SDC. O row plugin UI Patterns não foi aplicado porque o source `view_field` instalado só mapeia slots, enquanto o SDC necessita props dinâmicas de sticky/badges; forçar o plugin hoje perderia esses dados. A composição da página (header, tabs, categorias, regras e pager) é um template ACULTA intencional composto por SDCs, não uma cópia do componente de row. Browser visual/teclado/perfis ainda pendentes.

## Transparência — cartão de documento (2026-11-11)

`aculta_documents:block_1` usa `layout-grid` e `document-card` no teaser nativo de node. O adapter envia categoria, corpo e arquivo como slots renderizados pela Field API, sem duplicar consulta ou contornar access/cache. Runtime anônimo preservou três rows e cache contexts. Classificação `UI_SUITE_NATIVE`; a lista não tem teste de browser focal e os fixtures atuais não demonstram link de arquivo, editor/admin, modo escuro, teclado, zoom 200% ou cache-hit. O SDC tem schema estrito, CSS local e story no catálogo.

## Loja — catálogo Commerce (2026-11-11)

`aculta_shop_catalog` tem duas saídas consumidoras (block/page) no domínio shop. Ambas usam `layout-grid`; o view mode `catalog` compõe o SDC `product-card` pelo adapter Twig com render arrays nativos de preço, variações e formulário de compra. Runtime anônimo manteve HTTP 200 e produto 1 antes/depois; filtros, ordenação, pager e acesso foram verificados na configuração executável. Classificação `UI_SUITE_NATIVE`. Firefox headless validou cinco larguras, dois modos, foco e contraste mínimo 12,4:1; zoom 200%, perfis, cache-hit e aceite visual humano permanecem pendentes. Dynamic Cache segue `UNCACHEABLE` por baixa cacheabilidade do Commerce.


## Wiki — listagem pública em UI Patterns (2026-11-11)

`aculta_wiki:wiki_entries` (`page_1`, `block_recent`, `block_changes`) foi classificada `UI_SUITE_NATIVE`: o style `ui_patterns` compõe o SDC reutilizável `layout-grid` e o row `entity:node`/teaser já entrega o SDC `wiki-entry` pelo adapter Twig do tema. Runtime/Sync parseados coincidem. O import parcial foi limitado à View depois de snapshot. A listagem anônima preservou seis node IDs `[72,73,42,43,44,45]`, filtros, ordem, acesso e pager; `/wiki/verbetes` segue isolada do host MAIN (404). Firefox ESR validou 215/215 em home/listagem/artigo, 320/390/767/768/1440, claro/escuro, um H1, sem overflow e reflow de grade 1→2 colunas. Capturas e hashes: `tests/browser/capturas/ui-patterns-wiki-entries/2026-11-11/manifest.json`. A página usa `private/no-cache` e Dynamic Cache `UNCACHEABLE`; cache-hit, zoom 200%, editor/admin, tecnologia assistiva e aceite humano do responsável seguem DEFERRED.


## Wiki — categorias editoriais na home (2026-11-11)

`wiki_categories:block_1` foi classificada `UI_SUITE_NATIVE` para a composição externa: UI Patterns Style usa o SDC existente `layout-grid`; as 15 rows continuam renderizadas como termos taxonômicos nativos, com os links e a ordem editorial preservados. A View mantém filtro do vocabulário, permissão `access content` e pager 30. O display `default` permanece `FUNCTIONAL_NATIVE_EXCEPTION` como base herdada sem saída própria. Import parcial apenas da View após snapshot; Runtime/Sync parseados coincidem. Firefox ESR passou 247/247 na home, listagem e artigo em 320/390/767/768/1440 e claro/escuro; 15 categorias e reflow 1→2 colunas confirmados. Capturas: `tests/browser/capturas/ui-patterns-wiki-categories/2026-11-11/manifest.json`. Zoom, cache-hit, outros perfis e aceite humano DEFERRED.
