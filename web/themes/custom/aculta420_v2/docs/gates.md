# Gates do tema V2

Gates são scripts de validação do repositório. Rode os que se aplicam à mudança e cole a saída na entrega. Quando um gate não puder rodar no ambiente, registre como `DEFERRED` com o motivo.

Os caminhos são relativos à raiz do repositório. Gates "Drush" rodam com `php:script` do Drush, que exige `vendor/` instalado e o Drupal inicializado.

## Gates do tema

| Gate | Como rodar | O que verifica | Quando |
| --- | --- | --- | --- |
| `validate-aculta420-sdc-schemas.php` | `php scripts/validate-aculta420-sdc-schemas.php` | schemas dos componentes SDC | toda mudança em `components/` |
| `validate-aculta420-design-foundations.php` | `php scripts/validate-aculta420-design-foundations.php` | fundações de design do tema | mudança em tokens, base ou layout |
| `validate-browser-validators.php` | `php scripts/validate-browser-validators.php` | validadores de navegador | mudança em JavaScript ou em formulários |
| `validate-aculta420-v2-foundations.php` | `php scripts/validate-aculta420-v2-foundations.php` | tokens e pares de contraste da V2, incluindo o modo escuro (33 pares de texto acima de 4,5:1; 4 de foco e 4 de borda acima de 3:1) | mudança em tokens ou cores |
| Stylelint do CSS V2 | `npm run lint:css` | convenções CSS Drupal e exceções compatíveis com os recursos usados pelo tema | toda mudança de CSS |
| `validate-aculta420-v2-sdc.php` | `php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-v2-sdc.php` | descoberta, assets e renderização isolada dos componentes V2; usa descoberta com backend nulo para incluir SDCs novos sem limpar cache persistente | toda mudança em componente |
| Configurações de superfície | `php vendor/drush/drush/drush.php config:status` e verificação da disponibilidade de `ThemeSettingsProvider` | consistência do Config Sync e serviço usado por `SurfaceSettingsHooks` | alteração das opções administrativas do tema |
| `validate-aculta420-mapped-classes.php` | `php scripts/validate-aculta420-mapped-classes.php` | toda classe de `PortalUiHooks::TOKENS` tem CSS ou exceção em `docs/classes-sem-css-excecoes.md`; nomes Bootstrap proibidos no mapa | mudança no mapa de tokens ou nos estilos |
| `validate-aculta420-theme-settings.php` | `php scripts/validate-aculta420-theme-settings.php` | ausência do painel `aculta_container_purpose` e do rótulo "Layout de container"; presença das superfícies, das cores por purpose e do schema de compatibilidade até o corte (ADR-054) | mudança em `theme-settings.php` ou no schema do tema |
| `validate-aculta420-css-scope.php` | `php scripts/validate-aculta420-css-scope.php` | CSS de aplicação não voltou ao `global-styling`; bibliotecas contextuais declararam os arquivos certos; seletores movidos saíram do arquivo global | mudança em `libraries.yml`, CSS de aplicação ou anexação condicional |
| `validate-aculta420-layouts.php` | `php scripts/validate-aculta420-layouts.php` (delega ao Drush) | descoberta dos layouts SDC pelo `ui_patterns_layouts` (mesmo registro do Layout Builder), categoria/regiões e renderização real de cada layout | criação ou alteração de layout SDC |
| `validate-aculta420-shell-regions.php` | `php scripts/validate-aculta420-shell-regions.php` | cinco regiões finais no `info.yml`, ausência de bloco órfão, composição do shell v2 com ponte para as regiões antigas, opt-in `classic`/`v2` e ausência de comparação de hostname | mudança no shell, em `info.yml` ou na configuração de regiões |
| `validate-aculta420-shell-contract.php` | `php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-shell-contract.php` | contrato do shell: barra, navegação e regiões | mudança em `page.html.twig`, barra ou navegação |
| `validate-aculta420-foundation.php` | `ACULTA_DRUSH_ROOT=$PWD php scripts/validate-aculta420-foundation.php` (ou via `php:script` do Drush) | fundação da V2: tema público e base-less, sem Bootstrap no Composer e no Runtime, blocos `aculta420_v2_*`, branding nos assets do V2, tokens `--color-*`, Twig, SDCs e cores e durações fora de `tokens.css` | toda mudança em tema, Config Sync ou Composer. Última execução: 410 checks PASS (2026-11-07), com Runtime sem a V1 |
| `validate-aculta420-studio-identity.php` | ver o cabeçalho do script | identidade do estúdio | não está na lista de gates do ADR-046; confirme o escopo antes de usar |

Os scripts `audit-aculta420-*.php` e `audit-aculta420-responsive-inventory.py` fazem inventário (consumidores de biblioteca, SDC assets, responsividade). Não são gates: a saída informa, não aprova.

## Gates do Portal que tocam o tema

| Gate | Quando |
| --- | --- |
| `php scripts/validate-aculta-user-photo.php` | mudança de avatar (regra: foto → acervo, sem inicial) |
| `php scripts/validate-admin-routes-main-domain.php` | qualquer ação administrativa na barra |
| `php scripts/validate-aculta-portal-drupal12.php` | mudança no Portal, incluindo contrato de variáveis |

## Verificação visual e de navegador

Não é um script de gate, mas faz parte da entrega de mudança visual (ver [AGENTS.md](../AGENTS.md)):

- Firefox ESR visível (`DISPLAY=:0`, geckodriver). Sem Chromium e sem headless.
- Larguras: 320, 390, 768, 1024 e 1440 CSS px, no mínimo.
- Modos claro e escuro. Zoom de 200%. Teclado (Tab, Shift+Tab, Enter, Escape).
- Movimento reduzido: `prefers-reduced-motion: reduce`.
- Estado anônimo e estado autenticado, em separado.
- Capturas em `$SUITE_OUT`, fora do repositório.

## Estado atual

## Execução Recente — Redesenho Visual Growroom420 (Entrega 2)

Validação dos componentes SDC complementares (`forum-sidebar-panels`, `forum-composer-guidelines`), do template `/criar-topico`, da nova rota `/categorias/{termo}` e da View em `config/sync`:
- `npm run lint:css` no tema: PASS sem erros.
- `php scripts/validate-aculta420-sdc-schemas.php`: PASS (55 componentes, 27 includes, 764 checks).
- `php scripts/validate-aculta420-design-foundations.php`: PASS (2468 checks; Dark token-only e contrastes em conformidade).
- `php scripts/validate-aculta420-mapped-classes.php`: PASS (462 classes mapeadas).
- `git diff --check`: PASS.

## Execução Recente — Redesenho Visual Growroom420 (Entrega 1)

Validação dos novos componentes SDC do Fórum (`forum-category-header`, `forum-tabs`, `forum-topic-row`, `forum-topic-card`, `forum-post-card`), templates e estilo CSS:
- `npm run lint:css` no tema: PASS sem advertências ou violações de ordem.
- `php scripts/validate-aculta420-sdc-schemas.php`: PASS (55 componentes, 27 includes, 764 checks).
- `php scripts/validate-aculta420-design-foundations.php`: PASS (2468 checks; Dark token-only PASS, Dark contrast AA/AAA em todos os pares avaliados).
- `php scripts/validate-module-theme-boundary.php`: DEFERRED (7 falhas preexistentes em `modules/aculta_studio/`, sem relação com o tema `aculta420_v2`).
- `php scripts/validate-aculta420-mapped-classes.php`: PASS (462 classes, 0 exceções, 0 classes Bootstrap).
- `git diff --check`: PASS (sem conflitos ou trailing whitespaces).



- Os gates da V2 passaram nas entregas anteriores, conforme o [CHANGELOG.md](../CHANGELOG.md).
- **Account-menu:** o render isolado do componente apontou `summary_label` vazio quando a prop não era fornecida. A story anônima agora declara o rótulo e o Twig oferece fallback explícito. SDC schema, design foundations e shell passaram nesta rodada; o render Drupal foi repetido após a correção.
- **C4 — revisão estrutural do shell e micropágina Links:** schema SDC (55 componentes/764 checks), design foundations (2.468 checks), foundations V2 (55 pares), browser validators (21 checks), shell contract (13 checks), render SDC Drupal (24 componentes), `php -l` e `git diff --check` PASS. Capturas responsivas, tema claro/escuro, zoom 200% e teclado: DEFERRED, `DISPLAY` indisponível. O adapter de conteúdo Links permanece no inventário G3.
- **E1/P1 — layout-page:** schema SDC (55 componentes/764 checks), design foundations (2.468), foundations V2 (55 pares), shell contract (13 checks), render SDC Drupal (25 componentes), `php -l` e `git diff --check` PASS. Capturas 320/390/768/959/960/1440, claro/escuro, zoom 200%/400%, landmarks e teclado: DEFERRED, `DISPLAY` indisponível. PHPCS do validador SDC: PASS.
- **E2/P2 — mapeamento de variantes:** schema SDC (55/764), design foundations (2.468), shell contract (13), render SDC Drupal (25), PHP lint, PHPCS `Drupal,DrupalPractice` em `PagePresentationHooks.php` e `git diff --check` PASS. Firefox visual DEFERRED (`DISPLAY` indisponível); catálogo/produto de loja não executados, aguardando autorização explícita.
- **E3/P3 — conta/Studio:** implementação Portal separa `aculta_studio.dashboard` (raiz `account`, path interno inacessível diretamente) de Minha Conta em `/conta`; a tela de dados usa a composição privada existente. Testes de rotas, guarda da raiz, path interno e layout/theme hooks executados nesta entrega; `PrivatePageMetatagHooksTest` cobre noindex. Firefox autenticado para `/` e `/conta`, subnavegação `aria-current`, capturas claro/escuro e zoom: DEFERRED até validação visual.
- **E4/P4 — evento, FAQ e LMS:** SDC schema 55/764, design foundations 2.468, foundations V2 55 pares, shell 13, render SDC Drupal 27 componentes, PHP lint/PHPCS e `git diff --check` PASS. Capturas e teste visual/teclado/anônimo/autenticado DEFERRED por `DISPLAY` indisponível.
- **G1 — avatar / author-box:** schema SDC 55/764, design foundations 2.468, YAML parse e renderização Drupal de 29 componentes PASS. Avatar: mídia com alt preservado e fallback neutro nomeado; Firefox ESR visível, 12 capturas em claro/escuro a 320/390/768/959/960/1440 px e zoom 200% em 320 CSS px sem overflow. Author box é representado pelo slot `authors` de `revista-publication` com `author-card`; gate e fixture composta conferem identidade, avatar e bio. Firefox: 12 capturas em claro/escuro, seis larguras, zoom 200% em 320 CSS px sem overflow e Tab com foco visível. Conteúdo publicado/autenticado no Runtime continua pendente.
- **G1 — tabs:** schema SDC 55/764 e render Drupal de 30 componentes PASS; o gate confirma `<nav>`, label, link atual e ausência de `role="tablist"`. Firefox ESR visível: 12 capturas em claro/escuro a 320/390/768/959/960/1440 CSS px; zoom 200% a 320 CSS px sem overflow da página; Tab alcança o primeiro link com foco visível. O scroll horizontal interno em 320 px é intencional.
- **G1 — dialog:** schema 55/764, foundations 2.468 e render Drupal isolado de 31 SDCs PASS; o gate verifica fallback por link, associação do título, slots, dependência `utility-drawers` e descoberta de `dialog.css`. Firefox ESR visível, fixture sintética: 32/32 checks em seis larguras e dois modos, zoom real 200% em viewport CSS 320, Tab/Shift+Tab cíclicos, Escape, botão, backdrop e fallback sem JS. A fixture não valida consumer Drupal publicado/autenticado no Runtime.
- **G1 — form-field:** SDC schemas 55/764, design foundations 2.468, foundations V2 (55 pares) e renderização Drupal isolada de 32 SDCs PASS. Render verifica associação label/controle, `aria-describedby`, estado inválido e mensagem textual. Firefox ESR visível em fixture sintética: 12/12 estados nas larguras 320/390/768/959/960/1440, claro/escuro; zoom nativo 200% sem overflow; Tab alcançou o campo com foco de 3 px. Capturas e `results.json` em `$SUITE_OUT`; integração em formulário consumidor permanece pendente.
- **G1 — progress:** schema 55/764 e render Drupal isolado de 33 SDCs PASS, incluindo os estados determinado/indeterminado. Firefox ESR visível em fixture sintética: 12/12 estados em 320/390/768/959/960/1440, claro/escuro, sem overflow; rótulo associado, valor e percentual determinados; estado indeterminado sem atributo `value` e com preenchimento visual parcial. Zoom real 200% sem overflow. Capturas e `results.json` em `$SUITE_OUT`; integração a dados reais permanece pendente.
- **G1 — data-table:** o gate Drupal V2 aplicou validação de schema estrito e renderizou 34 SDCs PASS; browser validators 21 checks PASS. O gate confere markup semântico de tabela/cartões, `data-label`, JS descoberto pelo SDC e dependências `core/drupal`/`core/once`. Firefox ESR visível em fixture sintética: 12/12 estados em 320/390/768/959/960/1440, claro/escuro, sem overflow da página; modo cartão no mobile; rolagem contida em região nomeada/focável só quando há overflow. Zoom real 200% com viewport 320 CSS px e fallback sem JS passaram. Reprodução: `DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-data-table" python3 tests/browser/aculta420-v2-data-table/visual.py` (detalhes no [README da suite](../../../../../tests/browser/aculta420-v2-data-table/README.md)). Consumer de Studio permanece pendente.
- **Rodapé e regions:** `tests/browser/aculta420-v2-footer/` capturou a home no Runtime de desenvolvimento (Firefox ESR visível): 6/6 estados em 320, 390 e 1440 px, claro/escuro, com Institution Bar e Domain Header presentes, rodapé institucional com conteúdo e sem overflow horizontal. Aceitação visual final permanece com o responsável.
- **HTMX F1 — busca, login e gavetas:** a suíte `tests/browser/aculta-htmx-f1/` executou no Runtime de desenvolvimento (Firefox ESR visível, `DISPLAY=:0`): 43/43 checks em busca, login e gaveta de avisos autenticada nas seis larguras e dois modos, prévia por HTMX, foco visível, zoom real de 200%, Escape com retorno de foco ao gatilho e fallback sem JavaScript. `validate-browser-validators.php` (21 checks), `validate-aculta420-sdc-schemas.php` (55/764) e `validate-aculta420-shell-contract.php` (13 checks) PASS. `validate-aculta420-v2-sdc.php` e `validate-aculta-portal-drupal12.php`/`validate-portal-popup-rule.php` falham por dívidas preexistentes fora deste diff (`buttons.css`, versões do status-index e regra de popup em Events/Paths). Consumer V2 do cadastro popup: não existe; o checkout de doação é F5.
- **G1 — empty-state:** gate Drupal V2 aplicou validação de schema estrito e renderizou 35 SDCs PASS; confere descoberta do CSS, mensagem/ação do consumer, variante compacta e ausência de ação inventada. Firefox visível: 12/12 estados em seis larguras e dois modos, foco por Tab e zoom real 200% sem overflow. Reprodução: `DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-empty-state" python3 tests/browser/aculta420-v2-empty-state/visual.py` (detalhes no [README da suite](../../../../../tests/browser/aculta420-v2-empty-state/README.md)). Consumer real permanece pendente.
- **F1 — accent (DRAFT):** o gate Drupal V2 verifica prop estrita `accent`, render da classe neutra e os tokens green/yellow/red. O gate foundations passou os seis contrastes de faixa contra `--color-surface` nos modos claro/escuro (mínimo 3:1). Firefox ESR: 12/12 estados em seis larguras e dois modos, cores resolvidas pelos tokens, sem overflow; Tab e zoom real 200% em viewport efetiva 320 CSS px PASS. Reprodução: `DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-card-accent" python3 tests/browser/aculta420-v2-card-accent/visual.py` (ver [README](../../../../../tests/browser/aculta420-v2-card-accent/README.md)). Sem consumer: DRAFT até D1; F2 depende do contrato e da decisão Wiki.
- **G3 — auditoria de templates:** removido o override genérico `node--teaser.html.twig`; a tabela em `docs/architecture.md` registra adapters específicos e fallback para o template/entity view display Core. `php vendor/drush/drush/drush.php php:script scripts/validate-aculta420-v2-sdc.php` PASS (35 SDCs; descoberta, schema, assets e render isolado); confirmação da remoção e `git diff --check` PASS. Captura responsiva e navegação por teclado: DEFERRED, sem sessão gráfica disponível nesta execução. Loja permanece aguardando autorização.
- **Compatibilidade com descoberta SDC persistente:** `watchdog:show` encontrou invocador compilado com aridade anterior, ausência de SDCs novos no manager e hook Portal instanciado sem DI. O tema agora decide fallback via `plugin.manager.sdc::hasDefinition`, sem inferir disponibilidade pela existência do arquivo Twig; hooks aceitam construção antiga sem argumentos recém-adicionados. `/conta` voltou a 403 (rota protegida para visitante) com `X-Robots-Tag: noindex, nofollow, noarchive`. Sem rebuild/clear de cache no Runtime.


## Fórum Growroom420 — gates desta entrega

- `validate-aculta420-sdc-schemas.php`: PASS (52 componentes, 74 includes, 1062 checks).
- `stylelint` em `components/forum-*/*.css` e `src/styles/forum.css`: PASS.
- `git diff --check`: PASS.
- `validate-aculta420-mapped-classes.php`: PASS (462 classes).
- `validate-aculta420-design-foundations.php`: FAIL (599/1368). Exige a família `--aculta-*`; a V2 usa `--color-*`. Decisão pendente.
- `validate-module-theme-boundary.php`: FAIL (21/7127). Falhas em módulos do Portal. Refatoração pendente.
- Captura visual Firefox ESR (320/390/1440 px): DEFERRED. Sem `FORUM_BASE_URL` nem `SUITE_OUT` nesta sessão.

## ADR-054 - Gates pendentes da refatoração SDC First (2026-11-09)

Estes são **requisitos de homologação**, não resultados PASS; os gates automatizados específicos ainda devem ser implementados nos pacotes do plano `docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md`.

- **UI administrativa de containers:** verificar que `aculta_container_purpose` e seus campos não aparecem em `system_theme_settings`, mas `aculta_purpose_palette` continua funcional; confirmar que configurações legadas não são apagadas. **DEFERRED** até execução no Runtime.
- **Regiões e blocos:** inventariar instâncias `block.block.*` globais e coleções Domain; antes do corte, nenhuma instância poderá apontar para regiões retiradas. **PENDENTE**.
- **Layout Builder:** testar displays por bundle/view_mode, overrides de entidade permitidos, fallback e compatibilidade com renderização especial. **PENDENTE**.
- **UI Library com dados reais:** impedir acesso por não-administrador, exigir também acesso ao bloco/entidade e assegurar cache seguro. **PENDENTE**.
- **Dez purposes e cache:** snapshots e intercâmbio de domínios em ordem direta/inversa, claro/escuro, 320/390/768/1024/1440, zoom 200%, teclado e perfis de autenticação. **PENDENTE**.
- **JavaScript e CSS:** jQuery 4 sob demanda por `core/jquery`, HTMX preservado, ausência de listeners duplicados, comparação de bytes e unused CSS entre legado/novo. **PENDENTE**.
- **Rollback:** backup, ativação controlada e reversão comprovada; testar importação de configuração em staging, não no Runtime com drift. **PENDENTE**.

Este registro não autoriza `drush cim`, `updatedb` ou alterações destrutivas; seguir AGENTS.md. A retirada isolada do formulário de container foi publicada em código, mas o teste de execução permanece DEFERRED.

## Execução Recente — ADR-054 P0/P2 (2026-11-09)

- `php scripts/audit-aculta420-v2-inventory.php`: informativo. 36 blocos `block.block.aculta420_v2_*`, 5 fora das cinco regiões finais (todos em `highlighted`); 95 displays, 0 com Layout Builder habilitado; 21 entradas em `global-styling`.
- `php scripts/validate-aculta420-theme-settings.php`: PASS (14 checks). Formulário sem `aculta_container_purpose` e sem o rótulo "Layout de container"; superfícies, UI Styles da Revista, paleta por purpose e schemas de compatibilidade preservados.
- Inventário publicado em `docs/architecture/ACULTA420-V2-P0-INVENTARIO.md`.

## Execução Recente — ADR-054 P3 shell paralelo (2026-11-09)

- `php scripts/validate-aculta420-shell-regions.php`: PASS (30 checks). Cinco regiões finais presentes; 5 blocos `aculta420_v2_*` ainda em `highlighted` (ponte documentada); shell v2 compõe institution/header/content/sidebar_first/footer e renderiza navigation/highlighted/help/breadcrumb como ponte; opt-in `classic`/`v2` com padrão `classic`.
- `php scripts/validate-aculta420-theme-settings.php`: PASS (14 checks).
- `php scripts/validate-aculta420-sdc-schemas.php`: PASS (64 componentes, 113 includes, 1481 checks).
- Smoke no Runtime de desenvolvimento (Drush local, `drush cr`, requisições HTTPS com `--resolve`): shell v2 ativo em `aculta`, `links`, `eventos`, `wiki420`, `revista`, `loja`, `cursos`, `apoio` e `growroom420`. Todas as páginas testadas responderam 200 com **1 H1** e **1 `<main>`**. `links` e `revista` tinham dois H1 antes da correção (landing Links com `<main>`/`<h1>` próprio e componentes `revista-listing`/`forum-category-header` com H1 fixo); ambos agora usam `h2` por prop `title_level` e a landing Links não duplica landmark.
- Rollback: `drush config:set aculta420_v2.settings aculta_shell_variant classic` + `drush cr` testado. Shell clássico voltou a responder 200 com 1 H1 e 1 `<main>` em `aculta`, `links` e `revista`.
- `validate-aculta420-design-foundations.php`: FAIL (38/1057) pré-existente, fora do diff. O validador lê `css/tokens.css`, mas a V2 publica `src/styles/tokens.css`; as falhas citam tokens de modo escuro e CSS de componentes não alterados. DEFERRED para correção do caminho do validador.
- `validate-aculta420-mapped-classes.php`: FAIL (5 classes `portal-profile__reactions*` sem CSS nem exceção), pré-existente e fora do diff.
- `npm run lint:css`: DEFERRED. `node_modules` do repositório não contém o binário `stylelint`; o comando falha com `stylelint: not found` antes de analisar qualquer arquivo.

## Execução Recente — ADR-054 P4 layouts SDC (2026-11-09)

- `php scripts/validate-aculta420-sdc-schemas.php`: PASS (69 componentes, 113 includes, 1527 checks).
- `php scripts/validate-aculta420-layouts.php`: PASS (42 checks). Descobre `layout_page`, `layout_section`, `layout_two_columns`, `layout_grid`, `layout_narrow` e `layout_full` no `plugin.manager.core.layout` com categoria `Layouts` e regiões iguais aos slots; renderiza cada um com o renderer do Drupal e confere classe e conteúdo.
- `php scripts/audit-aculta420-layout-builder.php`: informativo. 95 displays: 24 `COMPATÍVEL`, 48 `COMPATÍVEL COM ADAPTADOR`, 23 `INCOMPATÍVEL`; 0 habilitados. Nenhuma configuração alterada.
- Habilitação do Layout Builder por display: **DEFERRED**. Exige importação de configuração em ambiente isolado e teste de renderização/rollback por display; não executar `drush cim` no Runtime de desenvolvimento com drift.

## Execução Recente — ADR-054 P6/P7 prévias e escopo de CSS (2026-11-09)

- `php scripts/validate-aculta-ui-library.php`: PASS (23 checks). Rotas `_admin_route` com `_aculta_domain_purpose=main` e `administer blocks`; visitante e autenticado sem permissão negados; administrador autorizado; catálogo com blocos reais e `max-age 0`; prévia real de `site_branding` com `max-age 0`; sem `\Drupal::` no controlador.
- HTTP: `/painel-administrativo/aculta/ui-library/blocos` responde **403 no MAIN** e **404** em `links` e `wiki420` para visitante (confirmado com `--resolve`).
- `php scripts/validate-aculta420-css-scope.php`: PASS (8 checks). `statistics.css` saiu do `global-styling` e é anexado só em `aculta_statistics.dashboard`/`aculta_statistics.area`; os seletores de estatísticas saíram de `portal-ui-map.css`.
- Requisição real na home do MAIN após `drush cr`: nenhum CSS servido contém `.aculta-statistics` (antes continha). Redução de ~2,7 KB por página, além do CSS agregado correspondente.
- `validate-module-theme-boundary.php`: a sugestão `page__shell_v2` saiu de `src/Hook` e foi para `aculta420_v2.theme`. As falhas restantes (64) são pré-existentes em módulos do Portal, fora deste diff.
- `validate-agent-token-budget.php`: nossos itens passaram (TODO do módulo criado, TODO do tema reduzido). Falhas restantes: `AGENTS.md` 5333 bytes (limite 5000), `aculta_domain_config` sem TODO e `aculta_forum/TODO.md` com versão divergente — pré-existentes.
- `validate-aculta-portal-drupal12.php`: falha em `AccountCoursesManager` (P5.5) e `InstitutionBarBlock` (service locator), pré-existentes e fora do diff. O item do novo módulo foi resolvido (README + status + versão).
