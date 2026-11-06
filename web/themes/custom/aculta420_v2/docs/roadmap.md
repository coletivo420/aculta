# ACULTA420 V2 roadmap

## Estado por fase

| Fase | Status | Pendência principal |
| --- | --- | --- |
| F1 — Tokens e fundações | Entregue em `1.2.0-dev.1` | Nenhuma no tema |
| F2 — Shell | Em revisão | Aceitação visual final contra a V1 (responsável); faixa de acento aguarda o contrato D1 do Portal |
| F3 — Purpose e botões | Concluída em `1.3.0`; botões migrados | Validação no servidor de testes |
| F4 — UI Styles | Parcial; adoção por purpose pendente | Eventos: `card-cultural` na View pública validado no Runtime. Revista aguarda opção por entidade suportada pelo UI Styles |
| F5 — Componentes e templates | Parcial; adoção por purpose pendente | Studio: `/conta`, `/meu-perfil` e comparação focal de `context-tabs` validados; famílias `profile-*` não mapeadas. Eventos: View pública preserva catálogo/ACL/recorrência e usa `card-cultural`; Firefox ESR claro/escuro, 320/1440, foco e zero overflow, sem baseline anterior da listagem. Revista depende de seleção por entidade; Loja aguarda autorização; `price` fora do escopo |
| RC | Não promovida | Depende de F4 e F5 validadas no servidor, revisão visual em larguras e modos, Drupal 12 |

Detalhes por bloco: [status-componentes.md](status-componentes.md), [status-layouts-e-shell.md](status-layouts-e-shell.md) e [htmx-status.md](htmx-status.md) (ADR-050).

## Fase 1 — Tokens e fundações

**Status: tokens e fundações entregues em `1.2.0-dev.1`. Purpose neutro (`data-purpose`) e compatibilidade do `ui_styles` foram cobertos em 2026-11-05; o shell segue em revisão na Fase 2.**

- Tokens semânticos mantêm a identidade visual V1 e os modos explícitos do
  Portal. Não há detecção `prefers-color-scheme`.
- Inter e Oswald, escala de espaçamento, foco visível, movimento reduzido,
  dimensões fluidas e prevenção de overflow em conteúdo longo são fundações da
  V2. O tema continua sem Bootstrap, Vite ou dependência de Node.
- O gate `php scripts/validate-aculta420-v2-foundations.php` valida 33 pares
  texto/superfície de tema escuro acima de 4,5:1 e indicadores/bordas acima de
  3:1. O menor contraste de texto é 6,07:1.
- Auditoria antes/depois: baseline visual/código `676ff17449`; depois
  `1.2.0-dev.1`. Dez purposes públicos; URL raiz `/` conforme a matriz de
  `docs/architecture/multidomain.md` (Conta termina em `/entrar`). Viewports
  320/360/390/599/600/768/1024/1280/1440 CSS px; claro e escuro; sessão
  anônima. Reflow: 180/180 combinações sem overflow e HTTP 200 nos dez
  purposes. Firefox ESR visível: Tab e Shift+Tab 10/10, foco de 3 px; zoom
  nativo 200% e 400% 10/10, viewport efetiva 320 px; sem JavaScript 90/90
  combinações largura/purpose; `prefers-reduced-motion: reduce` 180/180
  combinações largura/modo, transição 0,01 ms. Capturas e resultados por URL
  requisitada/final em `$SUITE_OUT/aculta420-v2-foundations-full/`, fora do
  repositório.
- Limitadores: a auditoria foi anônima; estados autenticados e leitor de tela
  permanecem DEFERRED. O comando legado `validate-aculta420-foundation.php`
  falha porque exige V1 como tema público e Bootstrap5 como base; o gate
  específico `validate-aculta420-v2-foundations.php` e os gates SDC V2 passam.

## Fase 2 — Shell

**Status: shell em revisão; Institution Bar, Domain Header, navegação e composição de regiões estão integrados em SDC. Rodapé e regions foram capturados em 2026-11-05 (`tests/browser/aculta420-v2-footer/`, 6/6); a aceitação visual final contra a V1 segue com o responsável.**

- Referência visual: V1 `templates/page.html.twig` e `docs/shell.md`, somente
  leitura. Institution Bar e Domain Header foram preservados como contratos.
- Baseline antes da navegação: commit `33e4dddbf5`; Firefox ESR visível,
  anônimo, nas raízes dos dez purposes em claro/escuro e 320–1440 CSS px,
  180/180 sem overflow. Tab/Shift+Tab, zoom 200/400, sem JS e movimento
  reduzido passaram. Capturas: `$SUITE_OUT/aculta420-v2-shell-baseline/`.
- Antes: o bloco de menu principal estava dentro de `page.header`, sem o
  comportamento do SDC `navigation-bar`.
- Depois: o preprocess substitui o bloco pela instância do SDC no mesmo peso e
  posição do cabeçalho; o render array original continua como slot de menu, com
  acesso e metadados de cache preservados. O SDC não repete a marca do Domain
  Header. Menu é visível sem JavaScript; com JS, Enter abre, Escape fecha e o
  foco acompanha a interação.
- Firefox ESR visível, anônimo: 100/100 checks nos dez purposes, em
  320/767/768/1024/1099/1100/1440 CSS px; Enter, Escape, foco, landmarks e
  fallback sem JS em 320 px. A revisão focal da Conta passou 10/10 checks
  adicionais em todas as larguras, teclado e sem JavaScript.
  Evidência: `$SUITE_OUT/aculta420-v2-navigation-final3/`.
- A composição aproxima a referência visual V1: logo e menu no Domain Header,
  showcase separado abaixo dele e eventos/fórum/agenda na sidebar. A marca
  `wiki420` usa assets portados da V1. A faixa fixa permanece na mesma posição;
  Gin Core Navigation e Toolbar Core começam abaixo dela.
- A faixa institucional fica acima da sidebar administrativa Gin e do toolbar
  Core; Gin e Gin Core Navigation começam após a altura medida da faixa, e a
  bandeja Core após a faixa e sua própria barra de controles. Firefox ESR
  visível passou 15/15 checks em 320/390/768/1024/1280 px, sem overflow: barra
  0–73 px, Gin começa em 73 px, controles Core começam em 73 px e bandeja Core
  em 112 px. A fixture usa as folhas locais de Gin e Core, mas não uma sessão
  administrativa autenticada: isso permanece DEFERRED.
  Evidência: `$SUITE_OUT/aculta420-v2-admin-toolbar-layer-accept/`.
- Baseline: commit `33e4dddbf5`; screenshots atuais comparam as raízes de Main,
  Conta, Apoio, Revista, Wiki, Loja, Cursos, Fórum, Links e Eventos, URLs
  requisitadas e finais vindas do `deploy.json`, estado anônimo, claro/escuro,
  320–1440 CSS px, zoom 200/400, teclado e movimento reduzido. Resultados em
  `$SUITE_OUT/aculta420-v2-shell-after-verified/`; leitores de tela e estados
  autenticados permanecem DEFERRED. O runner aplica o CSS baseline ao DOM atual;
  essa comparação não recupera a marcação Twig histórica da baseline.
- Uma revisão focal da Conta, após conter o widget Turnstile dentro do seu
  contêiner, passou 18/18 estados de reflow em claro/escuro em 320/360/390/599/
  600/768/1024/1280/1440 px. Teclado, Shift+Tab, zoom nativo 200/400, sem JS,
  movimento reduzido e HTTP 200 passaram. O desafio mantém rolagem local quando
  excede o espaço do formulário. Evidência:
  `$SUITE_OUT/aculta420-v2-account-turnstile-visual/`.

### Faixa de acento — F1 DRAFT; F2 bloqueado por decisão

O SDC `card-cultural` aceita `accent: green | yellow | red` e aplica os tokens
de contraste claro/escuro. Stories mostram os três valores, sem consumer ou
inferência local. O gate renderiza o valor explícito e verifica os estilos;
Firefox passou 12 estados, foco visível e zoom real 200%. Estado DRAFT até o
Portal fornecer `domain_presentation.accent` (D1). F2
permanece aguardando confirmação da correspondência Wiki verbete/seção descrita
em `superficies.md` 8.3.1; demais tipos também dependem do contrato D1.

## Fase 3 — Purpose neutro no Portal e Cores por purpose

**Status: CONCLUÍDA em 2026-11-05 (versão 1.3.0); escolha de esquema ligada ao runtime neste commit.** `AppearanceHooks::preprocessHtml`
expõe `aculta_purpose` e o tema aplica `data-purpose` no `<html>` após validar
a lista permitida. O Portal mantém o estado neutro `aculta_portal.active_purposes`
via Core State API atualizado com hooks de ciclo de vida do Domain (`domain_insert`, `domain_update`, `domain_delete`).
O tema oferece a seção nativa "Cores por purpose" em `theme-settings.php`, organizando os seletores
em caixas com grid responsivo (2 colunas em desktop >= 60rem, 1 coluna em mobile), com visualização
ao vivo lado a lado dos modos claro e escuro (faixa de destaque, hex, subelementos e contraste textual).
A classe `src/Palette/PurposePaletteSchemes.php` provê a fonte única dos 10 esquemas oficiais e o gate
`scripts/validate-aculta420-palette-schemes.php` valida a coerência estrita com `tokens.css`.
O escopo estrito é respeitado: os tokens `--purpose-*` (`--purpose-accent`, `--purpose-on-accent`, `--purpose-sub-1..3`, `--purpose-surface-1..3`) isolam a variação visual na faixa de destaque, sem qualquer alteração em botões, foco, anéis, abas ou elementos institucionais.
Critérios de contraste WCAG AA aprovados em `tokens.css`. Schema e defaults configurados em `aculta420_v2.settings`.

### Botões — Migração para esquema aprovado e tokens `--button-*`

Os botões do tema V2 foram integralmente migrados para o esquema único documentado em `docs/botoes.md`:
- Consumo estrito de tokens semânticos `--button-primary-*`, `--button-secondary-*` e `--button-focus-ring` nas variantes `.button--primary`, `.button--secondary`, `.button--outline` e `.button--ghost`.
- Eliminação total do uso de `--color-brand-highlight`, `--purpose-*` e de `filter: brightness(...)` nos estados de interação.
- A invariância estática dos tokens foi verificada; a comprovação visual automatizada com Firefox ESR em 320 px e 1440 px, nos 10 purposes e dois modos, permanece pendente de runtime.
- Gate `scripts/validate-aculta420-v2-foundations.php` expandido com pares de contraste textuais (repouso, hover, active), bordas e foco (todos PASS).
- Captura de runtime real no Homelab permanece pendente (DEFERRED).

## Fase 4 — UI Styles

**Status: implementada em 2026-11-05; validação de servidor pendente.** Catálogo registrado em `aculta420_v2.ui_styles.yml` com botões (4 variantes), faixas de acento de cartão (verde, amarela e vermelha com precedência Opção A) e cores por purpose (10 esquemas oficiais da Paleta ACULTA Skunk Family). Gate `scripts/validate-aculta420-ui-styles.php` PASS (20 verificações). Teste de unidade `PurposePaletteResolutionUnitTest` PASS. Captura visual em navegador Firefox ESR visível nos dois modos, 320 px e 1440 px, sem rolagem horizontal, salva em `$SUITE_OUT`. A validação e o status de "concluída" aguardam a sincronização no servidor de testes.

## Fase 5 — Componentes e templates

**Revisão 2026-11-05:** os cinco overrides de template criados em `91549bc7` (`form/form-element`, `misc/progress-bar`, `navigation/menu-local-tasks`, `views/views-view-table` e `views/views-view`) foram removidos. Eles trocavam o markup do core por markup de SDC sem manter as classes que o CSS do tema ainda usa (`form-item`, `progress`, classes de Views), o que quebrava margens e visual. A adoção de cada SDC volta a ser feita só onde preservar a estrutura, com CSS adaptado e captura antes/depois. Os componentes continuam disponíveis.

**Status: implementação geral dos 40 componentes mantida; adoção visual por purpose permanece parcial.** UI Styles não oferece seleção por entidade para o accent da Revista. Eventos: a View pública sobre `aculta_event` usa handlers próprios para exibir/ordenar IDs e props de `EventCatalogue`, preservando ACL, recorrência, filtros e datas; Kernel Events passou 155 testes/3.616 assertions (33 depreciações). Firefox ESR mostrou dois cards em claro/escuro a 320/1440 px, foco visível e zero overflow; sem baseline anterior da listagem. Config da View exportada e sincronizada; outras diferenças são preexistentes. No Studio, `/conta` e `/meu-perfil` foram validados; a comparação focal de `context-tabs` agora passou com Firefox ESR: 77/77, 22 ignoradas, zero falhas. Baseline em `layouts-purpose-studio-before-esr-20261105/`; atual em `layouts-purpose-studio-context-tabs-paired-r2-20261106/`. Capturas pareadas das rotas estão em `$SUITE_OUT/layouts-purpose-studio-routes-20261106/studio-account-routes-{before,after}/`; `empty-state` em `$SUITE_OUT/layouts-purpose-studio-empty-state-esr-20261105/{before,after}/`. `profile-*` não mapeado. Loja aguarda autorização; `price` permanece fora do escopo.

### Consumers reais dos componentes SDC implementados

- **`tabs`**: implementado em `templates/navigation/menu-local-tasks.html.twig`, encapsulando abas primárias e secundárias do Drupal com `aculta420_v2:tabs` e mantendo títulos acessíveis.
- **`progress`**: implementado em `templates/misc/progress-bar.html.twig`, encapsulando a barra de progresso nativa com `aculta420_v2:progress`.
- **`form-field`**: implementado em `templates/form/form-element.html.twig`, encapsulando elementos de formulário Drupal com `aculta420_v2:form-field`, rótulos, controles, instruções e mensagens de erro acessíveis.
- **`data-table`**: implementado em `templates/views/views-view-table.html.twig`, integrando tabelas de Views com `aculta420_v2:data-table`, rolagem acessível e `data-label` por célula no mobile.
- **`empty-state`**: implementado em `templates/views/views-view.html.twig`, padronizando o bloco vazio de Views (`{% elseif empty %}`) com `aculta420_v2:empty-state`.
- **`author-card`**: integrado via `templates/aculta-user-author-card.html.twig` para renderização de autoria e perfil.
- **`dialog`**: integrado via `components/lms-tour/lms-tour.twig` para o percurso do estudante no LMS.
- **`price`**: aguarda autorização explícita da frente de Commerce/Loja.


### G3 — Mapa de templates por bundle/view mode (parcial)

Auditoria registrada em `docs/architecture.md`: Revista, Wiki, Fórum, FAQ,
evento, Links e aula LMS têm adapters SDC específicos; os bundles restantes
usam o template Core e seus entity view displays. Removido o override genérico
`node--teaser.html.twig`, que não respeitava o contrato de todos os bundles e
omitiria campos como o arquivo de Documento. Catálogo/produto de Loja aguardam
autorização; o mapeamento de Wiki para a faixa de acento aguarda confirmação
(F2). Captura de consumidor com a V2 ativa segue pendente.

- Há quinze componentes SDC com schema estrito e um gate Drush de descoberta,
  assets e renderização isolada.
- ACULTA Maps fornece render arrays neutros; Leaflet e Google reutilizam
  templates SDC inertes. O JavaScript de mapa usa `core/once`.
- Phosphor Icons Core 2.1.1 (MIT) está vendorizado; seus SVGs são injetados
  inline pelo SDC `icon`.
- A barra institucional usa dados neutros do Portal; conta, login, busca,
  carrinho, mensagens e alertas permanecem no shell.

### Pendências de shell e fundações

- Rodapé e regions restantes: capturados em `tests/browser/aculta420-v2-footer/`
  no Runtime de desenvolvimento (320, 390 e 1440 px, claro e escuro). Institution
  Bar e Domain Header presentes, rodapé com conteúdo e sem overflow horizontal.
  A aceitação visual final continua com o responsável; Institution Bar e
  Domain Header não foram alterados.
- Atualizar o handoff operacional após resolver a entrada `bootstrap5`
  obsoleta em `core.extension`; importação completa foi aplicada e o Runtime
  está sem diferenças de configuração. Bootstrap não foi instalado.

### Pendências antes de promover a RC

- Revisão visual Firefox em larguras móveis e amplas, nos modos claro e escuro,
  com zoom e teclado.
- Revisão assistiva das páginas reais que usam pager, mensagens e teasers.
  Auditoria estática em 2026-11-05 na home, na busca com pager e no popup de
  busca: `<main>` único, um `<h1>`, seis `<nav>` todos rotulados,
  `aria-current` no pager, `role="alert"` nas mensagens da busca, todas as
  imagens com `alt` e nenhum link sem nome acessível (os dois links do logotipo
  têm `alt="Início"`). Teclado em Firefox ESR: home 12/12 focos com contorno de
  3 px; busca 11/12, com o único foco sem contorno dentro do widget Turnstile de
  terceiro (não é markup do tema). Os `<nav>` sem `aria-label` usam
  `aria-labelledby`. Não havia pager nas buscas testadas; o template usa o SDC
  `pagination` e a validação com dados reais de listagem fica DEFERRED. Leitor
  de tela e estados autenticados permanecem pendentes.
- Não trocar tiles OSM por CartoDB nem introduzir endpoint de pontos sem decisão
  e revisão dos contratos de privacidade, acesso, cache e purpose.

### Dívida de estilo — resolvida em 2026-11-05

- As 189 classes de `PortalUiHooks::TOKENS` que não tinham CSS receberam estilo
  mínimo em `src/styles/portal-ui-map.css`, agrupado por componente e usando
  tokens semânticos. É estilo funcional, não desenho novo por componente.
- Os nomes Bootstrap saíram do mapa do V2: `bi` e a família `bi-*`,
  `breadcrumb`, `breadcrumb-item`, `btn`, `btn-link`, `form-control`,
  `btn-primary`, `btn-outline-primary`, `btn-outline-secondary` e `btn-sm`.
  O atributo `data-portal-ui` permanece no DOM e o CSS do V2 continua por
  atributo; a V1 tem mapa próprio e não foi alterada. Os seletores `.btn`,
  `.btn-link` e `.form-control` também foram removidos dos CSS portados, e o
  gate `validate-aculta420-v2-sdc.php` passou (35 componentes).
- Novo gate `scripts/validate-aculta420-mapped-classes.php`: cada classe
  mapeada precisa de CSS ou de exceção documentada em
  `docs/classes-sem-css-excecoes.md`; nomes Bootstrap proibidos no mapa fazem o
  gate falhar. PASS (453 classes, 0 exceções, 0 Bootstrap).
- Validação visual: `aculta-htmx-f1` 43/43 e `aculta420-v2-footer` 6/6 PASS
  após o CSS global. A revisão visual do desenho completo por componente
  continua como evolução, não como dívida.

## Compatibilidade e limites

O tema é desenvolvido no Drupal 11.5 instalado e declara `^11.5 || ^12`. A
execução no Drupal 12 ainda requer validação em Core 12. O Runtime de
desenvolvimento não equivale a validação Alpha ou produção.
