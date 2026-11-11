# ADR-054 - ACULTA420 V2 SDC First e Layout Builder multidominio

Status: aprovado em 2026-11-09; corte P9 do shell executado no desenvolvimento, homologacao final residual pendente. Complemento 2026-11-10: finalizar em conjunto com a ADR-055 e a substituicao integral do visual legado por UI Suite/UI Skins.
Substitui parcialmente ADR-046 (restricao jQuery), ADR-048 (Layout Builder excluido) e o painel Layout de container.
Preserva ADR-047, ADR-050, ADR-053, seguranca, mobile-first, tokens e contratos do Portal.
Plano original: docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md.
Plano complementar vinculante: docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md.
Renomeação dos contratos internos do Portal: docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md (RN-00 a RN-05).

## Cutover UI Suite e estado do piloto — 2026-11-10

Após snapshot e autorizações explícitas para `updatedb` e `config:import`, foram migrados flood e settings de confirmação para os módulos proprietários; UI Styles CKEditor 5 foi ativado e a View de categorias do Fórum passou a usar `forum-category`. O mapa UI Skins ativo está deliberadamente limitado a `links_aculta_org: ruderalis`; demais domínios resolvem `skunk` provisório. Não há configuração visual antiga como fallback.

Validações executadas: UI Skins 134 checks, theme settings 16, V2 foundations 83 pares de contraste e Portal Drupal 12 gate 1.243 checks PASS. Browser visual, CSS calculado, contraste do piloto, cache cruzado, teclado e zoom permanecem DEFERRED porque a sessão X11 não está disponível. As ADRs permanecem abertas.

### Atualização US-06 — ComponentLayout para páginas — 2026-11-10

`core.entity_view_display.node.page.default` e `node.page.full` foram migrados de `layout_onecol` para o layout UI Patterns `ui_patterns:aculta420_v2:layout_section`, com o SDC existente `aculta420_v2:layout-section`. Os dois blocos (`body` e links da entidade), regiões, `allow_custom`, ordem e dados foram preservados. A importação parcial atingiu somente esses dois displays; rollback é restaurar seus YAML anteriores e reimportar os mesmos objetos.

Runtime confirmou os dois layouts ativos; Firefox anônimo em Links → MAIN → Links retornou 200 e tokens calculados Ruderalis → Skunk → Ruderalis, com um H1 e um `<main>` por host. Capturas Links em 1280/320 sem overflow estão no manifesto versionado `tests/browser/capturas/ui-suite-links/2026-11-10/manifest.json` (PNG externo ao Git). O cache dinâmico reporta `UNCACHEABLE`; cache-hit, acessibilidade/teclado/zoom e aceite visual amplo permanecem DEFERRED. Os outros displays Layout Builder compatíveis ainda precisam de integração; as duas exclusões documentadas não foram alteradas.

## Rodada 1 - decisoes

1. jQuery 4 liberado amplamente em modulos ACULTA, anexado via core/jquery por biblioteca, sem duplicar a distribuicao do Core.
2. Blocos basicos do Drupal permanecem como plugins nativos, configurados via UI Patterns e adaptados visualmente quando necessario.
3. Reduzir regioes e compor a apresentacao prioritariamente por SDC.
4. Shell global compartilhado; composicao interna por dominio.
5. UI Patterns Library como catalogo de SDCs e blocos basicos com previas.

## Rodada 2 - decisoes

1. Cinco regioes finais: institution, header, content, sidebar_first e footer. Preservar page_top/page_bottom como placeholders do Core.
2. Adotar Layout Builder + UI Patterns Layouts.
3. Previas de blocos com dados reais do Drupal.
4. HTMX e jQuery 4 conforme necessidade e decisao documentada. ADR-050 continua priorizando HTMX em novas requisicoes e formularios; isto nao libera genericamente Drupal AJAX.
5. Nova arquitetura implantada em paralelo, substituida apenas apos homologacao.

## Rodada 3 - decisoes

1. Cobertura de todos os conteudos e displays **tecnicamente compativeis**, por inventario e lotes de habilitacao de Layout Builder; nao forcar displays incompativeis.
2. Layouts comuns compartilhados; landing pages proprias para cada purpose.
3. sidebar_first fica como fallback nativo; layouts SDC podem ter barras internas.
4. **Somente administradores** acessam previas reais. Permissao administrativa nao dispensa verificacao de acesso ao bloco e aos dados.
5. jQuery 4 permitido tambem em SDCs do tema, quando necessario e sem carregamento global obrigatorio.
6. Piloto de rollout: Links / LinkWeed.

## Decisao complementar - fim da configuracao Layout de container

Remover a funcao administrativa 'Layout de container' do formulario de configuracoes do tema ACULTA420 V2. A largura, o estilo e a estrutura de cada pagina serao definidos pelos layouts/SDCs e pelo Layout Builder.

Durante a implementacao paralela, **nao** apagar configuracao aculta_purpose_container, schema, classe PurposeContainerLayout ou estilos de compatibilidade antes da homologacao do novo renderizador. A classe tambem possui utilidade de identificacao de rotas da UI Patterns Library; separar esses usos antes de removela. No corte homologado, descontinuar data-purpose-layout, data-purpose-style e variaveis CSS antigas. Documentar rollback.

## Adendo - Sistema de SDCs de Superfície e Conteúdo

1. Criação da família de superfícies SDC no tema `aculta420_v2`:
   - `aculta420_v2:surface`: Superfície base estrutural configurável por propriedades estritas (`tone`, `radius`, `spacing`, `elevation`, `border`) e slot `content`.
   - `aculta420_v2:surface-accent`: Superfície e moldura de destaque implementando fielmente as 4 referências visuais de blocos unitários e tricolor (`green`, `yellow`, `red`, `tricolor`) e a faixa tricolor superior fina das referências neutras institucionais (`neutral`). Geometria semântica em CSS puro, sem assets estáticos rasterizados em produção.
   - `aculta420_v2:surface-panel`: Painel institucional de superfície com bloco superior verde de destaque contínuo (modo claro e escuro) e slots para `header`, `content`, `footer` e `actions`.
2. Integração com UI Styles:
   - Registro de `surface_accent_style`, `surface_elevation_style` e `surface_radius_style` combináveis com `surface_style` em `aculta420_v2.ui_styles.yml`.
3. Integração com Layout Builder:
   - Os componentes de superfície com slots são automaticamente reconhecidos e disponibilizados como Layouts no Layout Builder através do derivador nativo `ComponentLayout` do módulo `ui_patterns_layouts`.
4. Composição com SDCs de conteúdo:
   - Tipografia, botões, formulários, abas, cartões culturais e layouts do ACULTA420 V2 são integrados por composição via slots sem duplicar HTML ou estilos específicos.

## Contratos e limites

- O Portal conhece os dez purposes e as regras de rota, dominio, acesso e cache; o tema recebe contrato neutro sem ler hostname.
- Shell fixo significa estrutura institucional compartilhada, nao eliminar o bloco institucional na regiao institution (ADR-053).
- Domain Config + aculta_domain_config atualmente aplicam overrides para block.block; isso NAO demonstra compatibilidade automatica de overrides de Layout Builder/core.entity_view_display por dominio.
- Preservar plugins e cache metadata dos blocos Core, Views e contrib; compor a apresentacao via SDC.
- Previas reais nunca podem exibir dados privados sem acesso ou em cache publico. Ausencia de contexto/permissionamento implica previa indisponivel.
- Alteracoes em pagamentos, checkout, dados privados e configuracao ativa dependem de escopo e testes proprios.
- Nenhuma nova PR/branch; trabalho em dev com commits atomicos, preservando trabalho paralelo e seguindo AGENTS.md.

## Homologacao bloqueante

Inventario de blocos e regioes sem orfaos; dez purposes, shell, claro/escuro, mobile-first e zoom; testes de Layout Builder, SDC/UI Patterns, permissao de previa, cache cruzado, acesso, import/export de configuracoes em staging, rollback. Teste nao executado = DEFERRED, nao PASS. O corte de desenvolvimento P9 ja ocorreu; NAO declarar fechamento final da ADR sem concluir criterios residuais P8/P9, integrar a migracao total de visual legado e registrar aceite.


## Adendo 2026-11-10 - Conclusao vinculada a UI Suite

- A arquitetura SDC First e o Layout Builder das 5 regioes continuam validos. Integrar a migracao UI Suite em todos os consumidores do site, nao so no shell.
- **UI Skins substitui a identidade visual por purpose**: os antigos controles de paletas, variaveis `--aculta-purpose-*` e estilos por purpose são depreciados e removidos após zero consumidores. **O serviço antigo `DomainPurposeManager` também será renomeado/refatorado para `DomainSiteManager`**, mas todas as suas funções de roteamento, acesso e URL serão preservadas no Portal (RN-00 a RN-05), sem transferência para UI Skins.
- Para os componentes visuais ainda fora da UI Suite, inventariar e marcar `LEGACY_DEPRECATED` / depreciado ate a substituicao e `LEGACY_OBSOLETE` / obsoleto depois da comprovacao de zero consumidores; evitar esconder SDCs do catalogo universal.
- Prioridade `2.0.x-dev@dev` para UI Patterns Field Group com verificacao de seguranca/Drupal 12; UI Patterns CKEditor 5 **excluido** da ADR atual. UI Styles CKEditor 5 segue no escopo.
- Gates finais ADR-054 (homologacao visual, 10 dominios, cache, Config Sync, rollback, landings e displays) compoem a US-14 de fechamento conjunto, com `PASS/FAIL/DEFERRED` explicitamente comprovados.
- Detalhamento completo em `docs/architecture/ACULTA-UI-SUITE-MIGRACAO-LEGADO-SKINS.md` e `docs/architecture/ACULTA420-V2-UI-SUITE-INTEGRACAO-VISUAL-PLANO.md`.

## Adendo 2026-11-10 - Identificadores funcionais do Portal

O responsável determinou a renomeação gradual dos identificadores internos `purpose` para **`site_key`**, além da retirada das configurações visuais baseadas em purposes. As dez chaves e Domain IDs permanecem imutáveis; `site_key` identifica a frente funcional, `domain_id` identifica a entidade Drupal Domain e `skin_id` define a aparência via UI Skins. A classe antiga e as rotas de segurança são substituídas por implementações equivalentes, não removidas sem sucessores.

A sequência normativa RN-00 a RN-05 consta de `docs/architecture/ACULTA-PORTAL-PURPOSE-PARA-SITE-KEY.md`. Gate RN-05 passa a integrar o encerramento conjunto US-14, com testes de HTTP 403/404, redirecionamentos, sessões e cache por domínio. Aliases de serviços podem ser transitórios e inventariados, mas novas rotas públicas de compatibilidade são proibidas.

## Atualização US-06 — ComponentLayout para os displays elegíveis — 2026-11-10

- As 22 configs habilitadas e elegíveis usam agora `layout-section` via UI Patterns ComponentLayout. As 20 restantes foram migradas após o piloto; componentes, UUIDs, região `content` e flags `allow_custom` foram preservados. O render mostrou que `node.wiki_entry.full` usa template SDC próprio que consome campos diretamente; sua saída usa o adapter `wiki-entry` sem o wrapper Layout Builder. As outras 21 saídas exibem o wrapper ComponentLayout.
- As exclusões `node.document.full` e `block_content.basic.default` continuam sem Layout Builder. Snapshot do Runtime precedeu o import parcial; rollback reproduzível restaurando os YAMLs do commit pai e reimportando apenas esses objetos. Nenhuma entidade ou dado foi alterado.
- Gates: UI Patterns Layouts 42 checks; Layout Builder 276 checks para 22 displays (render arrays, saída ComponentLayout ou adapter SDC Wiki, paridade Runtime/Sync e acesso); SDC 41 componentes. Capturas amplas, cache-hit, perfis autenticados e revisão visual pendentes.
- Drift global de Config Sync continua em outros objetos, incluindo Views/Forum e `system.theme`; não foi importado nesta entrega.

## Integração de Views na migração visual — 2026-11-10

A View `aculta_albuns:page_1` migrou seus níveis visuais para UI Patterns row (`revista-album-card`), style (`layout-grid`) e display (`views-display`). Os handlers de fields permanecem como sources Drupal; filtros, ordenação, pager mini, acesso `access content` e três resultados foram preservados. Runtime antes/depois respondeu HTTP 200 e permitiu anônimo. Capturas Firefox 1280/320 em claro/escuro estão no manifesto `tests/browser/capturas/ui-patterns-views/2026-11-10/manifest.json`. Isso é um piloto focal, não a homologação das dez áreas: cache-hit, pager multi-página, teclado, zoom e aceitação visual ampla seguem pendentes.

Em 2026-11-11, `aculta_forum_categories:page_categories` recebeu o SDC `views-display`, mantendo `forum-category` no row e o estilo agrupado nativo por `parent_name`. Importação parcial após snapshot alterou somente essa View. Acesso anônimo, 12 resultados e HTTP 200 foram preservados; Firefox ESR validou cinco larguras claras, duas escuras, um H1/main, foco visível e alvos ≥44 px. O manifesto registra hashes e rollback. O bloco `block_landing` permanece sem composição style/display porque seu placement está oculto na home. Cache-hit, zoom 200%, pager multi-page e homologação visual ampla seguem DEFERRED.

Também foi classificada `revista_sessoes` sem cutover redundante: os seis displays de página herdam a row nativa `entity:node` em view mode `teaser`, e `node--revista--teaser.html.twig` compõe o SDC existente `revista-teaser`, passando mídia/categoria/chamada/resumo como render arrays em slots. Classificação `UI_SUITE_NATIVE` por Sync/template. Smoke anônimo Firefox da home Revista mostrou quatro cards, um H1/main e nenhum overflow em cinco larguras; a rota `/revista` redireciona para `/`, então esses cards não foram atribuídos a `page_revista`. Acesso, pager/cache, teclado, modo escuro e zoom permanecem não homologados. Manifesto `tests/browser/capturas/revista-listing/2026-11-11/manifest.json`.
### UI Skins — cutover Runtime e piloto Links (2026-11-11)

- UI Skins 1.2.1, `aculta_portal.visual_skins` e os cinco grupos de CSS variables estão ativos no Runtime e no Config Sync. O resolvedor usa Domain ID, entrega `data-skin` com variação por domínio e não altera configuração global por requisição. A chave visual `aculta_purpose_palette` não tem alias nem cópia.
- O conflito anterior do ConfigImporter foi resolvido pela migração das dependências/configurações proprietárias; o import parcial autorizado foi concluído após snapshot. A/B/A anônimo confirmou Links=Ruderalis e os outros domínios testados=Skunk, sem reaproveitamento cruzado entre hosts. Preferência de modo do usuário permanece preservada.
- Ainda pendem contraste medido e aceitação visual dos dez domínios em claro/escuro, cinco larguras e zoom 200%, mais cache-hit em ordem inversa. Isso não fecha P8/P9 nem RN-00–05; `DomainPurposeManager` continua somente pelas responsabilidades funcionais enquanto a renomeação para `site_key` permanece aberta.

Firefox ESR anônimo passou 300/300 checks nos dez domínios nos breakpoints 320/390/768/1024/1440 e modos claro/escuro; Links resolveu `ruderalis` e os outros nove `skunk`. Confirmou `site_key`, barra institucional e H1/main únicos, links com contraste mínimo de 7,16:1 e ausência de overflow. A home pode não usar `.page__header`; Links usa o SDC standalone `social-links-page`. Manifesto e 40 capturas: `tests/browser/capturas/ui-skins-ten-domains/2026-11-11/manifest.json`. Cache-hit, zoom 200%, teclado, perfis e aceite visual humano continuam DEFERRED.



### Atualização US-12 — SDC editorial em Views da Revista (2026-11-11)

A View pública `aculta_news:block_1` agora compõe o SDC existente `revista-listing` pelo style UI Patterns Views; seus quatro render arrays de `node:revista` continuam no view mode `teaser` e adapter `revista-teaser`. Filtro published, ordem created DESC, access `access content`, cache tag/contexts e H1/main foram comparados antes/depois. Firefox ESR: 10/10 cenários por fase em cinco larguras claro/escuro, foco visível e contraste mínimo 7.16:1. O inventário e rollback estão no manifesto UI Patterns Revista News. Cache-hit, zoom 200% e aceite humano seguem DEFERRED; a ADR permanece aberta.

### Atualização US-12 — grade da Home editorial (2026-11-11)

`home_editorial_highlights:block_1` substituiu o style `views_vvjb` pelo SDC existente `layout-grid`. As três entidades editoriais 14–16, ordem, header, Layout Builder, acesso `access content`, query, filtros, pager e cache permaneceram iguais. A comparação Runtime foi isolada a `views.view.home_editorial_highlights`; Firefox ESR passou 10/10 antes e depois em 320/390/768/1024/1440 claro/escuro, com H1/main, foco visível, contraste mínimo 8.80:1 e sem overflow. A Home é `private/no-cache` e dynamic cache `UNCACHEABLE`; variação por cache-hit, zoom 200% e aceite ampliado permanecem DEFERRED. `views_vvjb` não tem View consumidora no Runtime/Sync e é `LEGACY_OBSOLETE`; a extensão continua habilitada até autorização operacional de desinstalação. Capturas, hashes e rollback: `tests/browser/capturas/ui-patterns-home-editorial/2026-11-11/manifest.json`.

### Atualização US-12 — galeria editorial em UI Patterns (2026-11-11)

`aculta_album_fotos:block_1` migrou de `views_vvjs` para o style UI Patterns com o SDC existente `layout-grid` e slot `view_rows`. A View preserva 63 linhas, PhotoSwipe, ordenação, pager de 150 itens, filtro de acesso por mídia e cache; a apresentação VVJS permanece somente na galeria independente do Fórum e em outros consumidores da extensão. Snapshot e rollback da configuração foram comparados antes do import parcial. HTTP 200 e 63 fotos antes/depois; Firefox ESR passou 10/10 antes e 11/11 depois em 320/390/768/1024/1440 claro/escuro, incluindo foco, texto alternativo, H1/main, sem overflow e teclado para abrir/fechar PhotoSwipe. Cache-hit, zoom 200%, perfis editor/administrador, revisão visual humana e homologação ampla permanecem DEFERRED; as ADRs seguem abertas. Manifesto: `tests/browser/capturas/ui-patterns-gallery/2026-11-11/manifest.json`.

### Atualização US-12 — transparência/documentos em UI Patterns (2026-11-11)

`aculta_documents:block_1` usa `layout-grid` e o novo SDC `document-card` no template de teaser de node, preservando Views, Field API e render arrays de categoria, corpo e arquivo. Import parcial autorizado e isolado após snapshot; Sync e Runtime parseados coincidem. Render anônimo manteve três rows, um H1/main e cache contexts de domínio, permissões, node grants e rota. Os fixtures têm descrição de arquivo vazia, então link de anexo não foi comprovado visualmente. Browser dedicado, perfis editor/admin, tema escuro, zoom 200%, cache-hit e revisão humana permanecem DEFERRED; ADR-054/055 seguem abertas.

### Atualização US-12 — catálogo público Commerce (2026-11-11)

`aculta_shop_catalog` aplica `layout-grid` nos displays públicos block/page e o view mode `catalog` compõe o novo SDC `product-card` por adapter Twig, passando os campos e formulário Commerce nativos no slot. Filtros de publicado/tipo, ordenação ASC, acesso `access content` e pager full de 12 foram preservados. Import parcial isolado após snapshot; Sync/Runtime parseados coincidem; HTTP 200 e produto 1 antes/depois. Firefox ESR headless passou 10/10 em 320/390/768/1024/1440 claro/escuro, sem overflow, foco por teclado e contraste do título de 12,4:1. O cache permanece `private/no-cache` e Dynamic Cache `UNCACHEABLE`. Zoom 200%, perfis editor/admin, cache-hit e aceite visual humano seguem DEFERRED; ADR-054 permanece aberta.


## Atualização — galerias do Fórum em UI Patterns (2026-11-11)

`aculta_forum_gallery_slides:block_1` e `galeria_produto:default` foram migradas de VVJS para o SDC existente `layout-grid`, cada uma por importação parcial isolada após snapshot do Runtime. Configuração Sync e Runtime parseada coincide; a View executável usa `ui_patterns` e não retorna resultados porque não há galeria Fórum publicada. PhotoSwipe, rows Fields, query, argumento contextual, pager, filtro de acesso a Media e cacheability do módulo permanecem. Os controles de slideshow/miniaturas do Fórum e a dependência de VVJS foram removidos do `aculta_gallery`; o hook Commerce segue construindo a View sem mudança no contrato do render array. Aceite visual/access/cache do Fórum está `DEFERRED` até existir fixture publicada; aceite visual e revisão de acesso/cache da galeria de produto também estão `DEFERRED`. A ADR permanece aberta; `galeria_produto` e `vvjs_example` seguem no inventário como consumidores sem decisão de cutover nesta microfase.


## Atualização — View pública de tópicos do Fórum (2026-11-11)

A View `aculta_forum_public_topics` já compõe suas rows com o SDC existente `forum-topic-row` por um adaptador Twig do tema, sem duplicar o markup visual. Runtime/Sync coincidem; os cinco displays ativos retornam 10 resultados cada. Render anônimo no host Fórum produziu dez rows e manteve cache contexts de domain/query/node grants. O UI Patterns Views row plugin permanece pendente porque o source `view_field` instalado só fornece slots, enquanto o componente exige props de estado do tópico; sua adoção sem ampliar o mapeamento perderia pinned/badges. Isto é classificado `UI_SUITE_NATIVE` e requer revisão de browser/perfis antes do aceite, não uma migração visual de markup.


## Atualização US-12 — Wiki pública em UI Patterns (2026-11-11)

`wiki_entries` usa `layout-grid` no style UI Patterns para page e blocos da Wiki. O teaser `entity:node` existente continua compondo `wiki-entry`; consulta, filtros published/type/Domain, acesso, ordenação, pager 12 e os seis resultados permaneceram. Importação parcial isolada após snapshot; Runtime e Sync parseados coincidem. Firefox ESR passou 215/215 para home, listagem e artigo em 320/390/767/768/1440 e claro/escuro, sem overflow e com grade 1 coluna abaixo de 768px, 2 colunas acima. Main continua sem acesso à listagem Wiki (404). Zoom 200%, cache-hit, perfis editor/admin, tecnologia assistiva e aceite visual do responsável ficam DEFERRED; a ADR permanece aberta.


## Atualização US-12 — categorias da Wiki na home (2026-11-11)

`wiki_categories:block_1` agora usa UI Patterns com o SDC `layout-grid`; as 15 rows taxonômicas continuam nativas. Filtro do vocabulário, links, acesso, ordem weight ASC e pager 30 foram preservados. Import parcial após snapshot e Runtime/Sync parseados iguais. Firefox ESR passou 247/247 nos três fluxos Wiki, cinco larguras e claro/escuro, confirmando a grade das categorias com uma coluna abaixo de 768px e duas a partir de 768px. Comparação visual antes/depois não identificou mudança perceptível nas amostras 320 claro e 1440 escuro. Zoom, cache-hit, perfis e aceite do responsável ficam DEFERRED; ADR aberta.

## Atualização US-14 — homologação residual de UI Skins (2026-11-11)

Firefox ESR visível nos dez domínios, em 320/768/1440, fechou 70/70 verificações: navegação só por teclado alcança elementos com `:focus-visible`, outline/box-shadow computados e dentro da viewport (30); zoom de página a 200%, emulado por `layout.css.devPixelsPerPx=2`, mantém um único institution/main/H1 e zero overflow horizontal (30); e os headers de cache foram observados em três requisições anônimas por domínio (10). As homes respondem `private/no-cache` e dynamic cache `UNCACHEABLE`, então MISS/UNCACHEABLE é o resultado contratual, não HIT. Evidência: `tests/browser/capturas/ui-skins-final-acceptance/2026-11-11/manifest.json`. Restam DEFERRED o aceite visual humano e os perfis editor/admin; RN-05 segue em execução, portanto a ADR permanece aberta.

Nota de bloqueio (2026-11-11): a sondagem de rotas públicas (`/galleries`, `/transparencia`, homes dos dez domínios) retorna consistentemente `Cache-Control: private, no-cache` e `X-Drupal-Dynamic-Cache: UNCACHEABLE`; não existe cache-hit a medir até que a política de cache público mude. O login programático por `drush uli` responde 404 e o formulário `/entrar` existe apenas no host de conta; a verificação autenticada deve usar o runner Playwright `tests/browser/auth-login.mjs`, não um runner próprio.

Fechamento parcial de 2026-11-11 — teste autenticado: o login de `teste_administrativo` pelo `/entrar` autentica (o `users.login` é atualizado e a sessão é gravada com uid 266), mas a requisição seguinte com o cookie emitido responde anônima; a causa não foi isolada e o aceite de perfis editor/admin fica bloqueado no ambiente de desenvolvimento. O teste revelou e permitiu corrigir um H1 duplicado no dashboard do Studio: `studio-menu` emitia `<h1>` além do page title; o topbar passou a `<p class="aculta-studio-topbar__title">` e o dashboard ficou com um único H1. Evidência: `auth-shell.json` no diretório de saída do runner (fora do Git).

## Atualização US-12 — listagem pública de galerias (2026-11-11)

A View `media_galleries:page_1` (`/galleries`, MAIN) migrou para row SDC `revista-album-card`, mapeando o formatter nativo da miniatura e o título, com style UI Patterns `layout-grid` sobre `view_rows`. Import parcial isolado em `views.view.media_galleries` após snapshot; Runtime/Sync coincidem e preservam pager full, consulta, filtros e acesso. Antes/depois a View entregou três resultados; depois são três cards, três títulos e duas imagens. Firefox ESR confirmou HTTP 200, um H1/main e grade sem overflow em 320/390/768/1024/1440, claro/escuro. Não há volume suficiente para exercitar navegação de pager. Cache-hit, zoom 200%, teclado, perfis e revisão visual humana seguem DEFERRED. Evidência: `tests/browser/capturas/ui-patterns-media-galleries/2026-11-11/manifest.json`.

## Atualização RN-04/RN-05 — renomeação publicada (2026-11-11)

O contrato funcional `purpose` foi substituído por `site_key` em classes, serviços, métodos, rotas, opções de rota, State, sessão e plugin de condição de bloco. `DomainPurposeManager`, `PurposeFrontPathProcessor`, `ContentPurposeResolver` e `ActivePurposesState` não existem mais; `DomainSiteManager`, `SiteFrontPathProcessor`, `ContentSiteKeyResolver` e `ActiveSiteKeysState` os substituem, sem aliases internos. A condição de bloco passou a `aculta_domain_site`/`site_key`, com 23 configs migradas no Sync e no install.

Gates: PHPStan 0; suíte unit 3287 PASS; Portal 1234 PASS; boundary 7888 PASS; browser index 151; admin routes e aliases PASS; Firefox ESR 70/70 nos dez domínios após `drush cr`. O import parcial autorizado das 22 configs de bloco foi executado após snapshot; o site voltou a responder 200 e o smoke repetiu 70/70 nos dez domínios. O Kernel focal de mapeamento de domínios passou com SQLite isolado; a suíte Kernel completa fica DEFERRED por dependências de ambiente. Ficam DEFERRED o cache-hit (`private/uncacheable`), a sessão autenticada (DT-S01) e o aceite visual humano. RN-05 não está conclusivo, portanto a ADR permanece aberta. Também foi configurado o `views-display` do `aculta_forum_categories:block_landing` (row já migrada); o placement segue oculto na home, portanto a evidência visual permanece DEFERRED.
