# Arquitetura do tema ACULTA420 V2

> **Atualização normativa (2026-11-09):** ADR-054 e `docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md` substituem as restrições anteriores ao jQuery e ao Layout Builder e descontinuam o painel administrativo de container. Este documento também descreve o código legado durante a migração paralela. O painel já foi retirado; a leitura dos valores antigos ainda funciona temporariamente.

Este documento descreve como o tema está montado hoje (`1.2.0-dev.2` mais o menu de conta não versionado). Para decisões, veja os ADRs em `docs/decisions/`; para status, veja [roadmap.md](roadmap.md).

## 1. Camadas

Dependência de cima para baixo. Uma camada não importa de baixo sem passar pelo contrato.

```text
Tokens primitivos (paleta)        → só dentro de src/styles/tokens.css
Tokens semânticos                 → usados pelos componentes
Fundações                         → tipografia, espaçamento, foco, movimento, reflow
Componentes SDC                   → apresentação, props com schema estrito
Shell                             → barra institucional, navegação, regiões da página
Templates de página e conteúdo → adapters por tipo/view mode; fallback mantém o display Drupal
```

Hoje estão entregues as três primeiras camadas, os componentes do shell e a navegação SDC. Há adapters específicos para Revista, Wiki, Fórum, FAQ, evento, Links e aula LMS; outros bundles mantêm o template Core para renderizar os campos definidos pelo display Drupal.

## 2. Fronteira com o Portal

O tema não conhece hostnames, domínios, purposes ou regras de acesso. Ele recebe variáveis de página preparadas pelo `aculta_portal`.

| Variável | Origem | Uso no tema |
| --- | --- | --- |
| `domain_presentation.layout.institution_bar_account` | Portal | marca a barra como barra de conta |
| `domain_presentation.layout.show_utility_bar` | Portal | exibe ou oculta as utilidades da barra |
| `domain_presentation.regions.showcase` | Portal | links da vitrine de portais (`multidomain_network_links`) |
| `domain_presentation.identity.purpose` | Portal | chave neutra do purpose atual |
| `aculta_purpose` | Portal (`AppearanceHooks::preprocessHtml`) | chave neutra aplicada como `data-purpose` no `<html>` (com base em `domain_presentation.identity.purpose`); sem hostname nem regra de domínio |
| `domain_presentation.accent` | Portal; **pendente (D1)** | valor neutro `green`, `yellow` ou `red` para o SDC `card-cultural`; ausente no contrato atual, então consumers não devem enviar/mapeamento permanece DRAFT |
| `aculta_domain_sites` | Portal (`DomainPresentationBuilder`) | links dos sites publicados, com purpose, nome, URL e sinalização do atual |
| `aculta_account_name` | Portal (`aculta_user`) | apelido aparado da pessoa autenticada; `NULL` sem apelido |
| `aculta_account_menu` | Portal (render array de `system_menu_block:account`) | slot de itens do menu de conta; o PHP do tema o encaixa no SDC |
| `aculta_account_authenticated` | tema (`PagePresentationHooks`, via `current_user`) | escolhe o estado visitante ou autenticado do menu |
| `aculta_account_avatar` | Portal (`aculta_user`, AvatarResolver) | slot do avatar do menu de conta; ausente → ícone neutro |
| `aculta_cart` | Portal (Commerce) | gatilho e painel do carrinho |
| `aculta_messenger` | Portal (`aculta_messenger`) | gatilho e painel de mensagens |
| `aculta_alerts` | Portal | gatilho e painel de avisos |
| `aculta_search_utility` | Portal (`aculta_portal/search-preview`) | gatilho de busca |
| `aculta_brand_home` | Portal | destino do logotipo |
| `aculta_color_mode` | Portal | `light` ou `dark` (demais valores viram `light`) |
| `aculta_user_author_card` | `aculta_user` | render array cacheável de identidade pública; o template do tema o encaminha ao SDC `author-card` |

Regras:

- O Portal não instancia componentes do tema. O tema não resolve regra de domínio.
- Links que atravessam purpose chegam como URL absoluta, gerada pelo `DomainPurposeManager` no Portal.
- A chave neutra de purpose (Fase 3) existe como `aculta_purpose` (derivada de `domain_presentation.identity.purpose`) e o tema aplica `data-purpose` no `<html>` após validar a lista permitida. O ajuste de tokens de destaque por purpose aplica a Paleta ACULTA Skunk Family oficial em `tokens.css` através dos tokens `--purpose-*` (`--purpose-accent`, `--purpose-on-accent`, `--purpose-sub-1..3`, `--purpose-surface-1..3`), mantendo botões, foco, anéis e elementos institucionais perfeitamente isolados e consistentes; o tema não lê hostname, domínio nem regra de acesso.

### Placeholders e Pedidos ao Portal (Fórum Growroom420)

Conforme a seção 5 e 6 do plano visual Growroom420 (`GROWROOM420-VISUAL-PLANO.md`), o tema não consulta regras de domínio, banco de dados ou entidade. Foram estabelecidos contratos e os seguintes dados permanecem como pedidos pendentes ao Portal:
- Papel do autor e variante de faixa (`stripe: brand | highlight | member`).
- Contagem de posts do autor e total de visualizações por tópico.
- Prévia do último post do tópico.
- Lista de moderadores e participantes da comunidade.
- Estado "seguindo" e "salvo" por usuário para os tópicos.

Enquanto esses dados não existirem nos contratos de variáveis ou views fornecidos pelo Drupal/Portal, o tema renderiza:
- Valores textuais e contadores ausentes como "—" acompanhados de `<span class="visually-hidden">Dado indisponível</span>`.
- Ações como "Seguir", "Salvar" e lista de moderadores com atributo `disabled` e `aria-disabled="true"` com pílula ou rótulo "Em breve".

### Componentes e Telas da Entrega 2 (Growroom420)

Na entrega 2 foram incorporados:
- `forum-sidebar-panels`: painéis laterais em coluna com faixas alternadas vermelha/amarela (`stats`, `featured`, `moderators`, `rules`, `related`). Moderadores e campos não entregues pelo backend operam com placeholder neutro e desabilitados com "Em breve".
- `forum-composer-guidelines`: diretrizes de publicação em duas colunas no desktop e uma coluna no mobile, faixa amarela no topo e detalhes nativos recolhíveis.
- Formulário `/criar-topico` adaptado com faixa vermelha no topo, botões de ação e diretrizes integradas via template `node-add-form--aculta-forum-topic.html.twig`.
- Nova rota e template da View `/categorias/{termo}` (`page_category_term` em `views.view.aculta_forum_public_topics.yml`), integrando `forum-category-header`, `forum-tabs`, lista de tópicos e `forum-sidebar-panels`.

- O CSS do widget CAPTCHA/Turnstile pertence ao `aculta_portal` (biblioteca `captcha-presentation`, anexada por `CaptchaPresentationHooks`). O tema não cita CAPTCHA; a regra de fronteira é verificada pelo gate de fundação.

## 3. Modo de cor

- `SurfaceSettingsHooks::preprocessHtml()` define `data-theme` no `<html>` a partir de `aculta_color_mode`. Valor diferente de `dark` vira `light`.
- `src/styles/tokens.css` define `:root` (claro) e `:root[data-theme="dark"]`.
- Não há detecção automática do sistema. Um valor legado `auto` resolve para claro até o contrato mudar.
- Componentes não conhecem o modo. Eles usam tokens.

`src/Hook/SurfaceSettingsHooks.php` é a implementação única de `preprocess_html`
do tema: aplica `data-theme` no `<html>` e classes neutras no `<body>` derivadas
das configurações de raio, sobrescrita de acento e visibilidade da faixa. No
hook, o Core fornece o atributo do `<body>` como `attributes`; os valores vêm de
`ThemeSettingsProvider`, com fallback aos defaults e validação dos enums. O hook
implementa `ContainerInjectionInterface` para resolver dependências mesmo quando
o mapa de hooks armazenado antecede uma compilação do container. Não consulta
domínio nem purpose. Instruções operacionais estão em
[`configuracao-do-tema.md`](configuracao-do-tema.md).

## 4. Tokens e fundações

`src/styles/tokens.css` contém:

- durações de movimento `--motion-instant`, `--motion-fast`, `--motion-base` e `--motion-slow`, usadas em transições e animações; opacidades de cor são feitas com `color-mix()` sobre tokens de cor;
- cores semânticas de texto, superfície, marca, destaque, borda, estado e foco;
- a base visual herdada da V1 (verde e vermelho editorial, mídia, sombra elevada), com valores para o modo escuro;
- escalas de tipografia (Inter e Oswald), espaçamento, raios e sombras;
- `--tap-target-min: 44px`;
- aliases de compatibilidade (`--color-primary`, `--border-radius-*`, `--shadow-*` antigos), mantidos apenas enquanto houver consumidores.

`base.css` e `layout.css` definem reset, foco visível, movimento reduzido, prevenção de overflow em conteúdo longo e a estrutura da página. `wiki.css` adapta os wrappers de listagem e busca da Wiki420. A biblioteca `global-styling` carrega essas folhas e as duas fontes externas do Google Fonts.

A biblioteca `statistics` carrega `src/styles/statistics.css` e depende de `global-styling`. É anexada por `PagePresentationHooks::preprocessPage` somente nas rotas `aculta_statistics.dashboard` e `aculta_statistics.area`. Os seletores de layout que estavam em `portal-ui-map.css` foram movidos para cá; a home não baixa mais essas regras (verificado por requisição real).

A biblioteca `studio` carrega `src/styles/studio.css` e `src/styles/studio-identity.css` e depende de `global-styling`. É anexada somente nas rotas `aculta_studio.*` (Conta, Marley, Ganjalab, Salvos e perfil). As regras do Studio que estavam em `layout.css` para a biblioteca de componentes foram movidas para `component-library-mode.css`, que só é carregado nas rotas da UI Patterns Library. A home não baixa mais o CSS do Studio (verificado por Playwright, anônimo e autenticado).

As demais folhas de aplicação também são contextuais: `portal-account` (purpose `account`, pelo contrato neutro), `public-profile` (`aculta_user.perfil_publico`), `event-organization` e `events` (`aculta_events.*`), `wiki` (purpose `wiki`) e `forum` (rotas do Fórum e nós `aculta_forum_topic`/`aculta_lms_topic`). `public-profile.css` foi dividido: `.portal-article-author*` ficou no global `article-authors.css` porque a caixa de autoria aparece em artigos de qualquer purpose. O `global-styling` caiu de 268.036 para 107.609 bytes (−60%). Permanecem globais apenas fundações, utilitários compartilhados, busca, botões de formulário, gaveta de mensagens e os mapas `portal-ui-*`.

A biblioteca `shell-v2` carrega `src/styles/shell-v2.css` e depende de `global-styling`. É anexada somente por `PagePresentationHooks::preprocessPage` quando `aculta_shell_variant` é `v2`; o shell clássico não a carrega. Ela define as cinco áreas finais do ADR-054 e a área contextual usada como ponte para as regiões antigas.

`portal-account.css` foi portado do V1 em 2026-11-05 (cobre a Minha Conta: navegação, bio, redes, cursos e foto). As variáveis `--aculta-*` e `--bs-*` foram trocadas por tokens semânticos do V2 (ex.: `--aculta-text-primary` → `--color-text-primary`); as regras `.form-control` e `.table-responsive` do Bootstrap não foram portadas. Estado: pendente de captura visual (320 e 1440 px, claro e escuro); DEFERRED. `messenger-drawer.css` foi portado do V1 pelo mesmo critério (gavetas e conversas do Mensagens). Regras só do Bootstrap (`.btn`, `.bi`) saem dos seletores; regras do Mensagens são mantidas. Mesmo estado: DEFERRED. `public-profile.css` foi portado do V1 com o mesmo critério (perfil público): `--aculta-border-*` vira `--color-border`; `--aculta-interactive-*` vira `--color-primary`; `--aculta-surface-*` vira `--color-canvas`. Estado: DEFERRED (sem captura visual). `utilities.css` (classes `aculta-u-*`, `aculta-main-canvas`, `aculta-panel`/`aculta-card`) foi portado do V1 com o mesmo critério. `studio.css` foi portado do V1 (2110 linhas): os aliases `--aculta-studio-fidelity-*` continuam nos mesmos seletores de escopo do V1, mas agora apontam para tokens semânticos do V2 (ex.: `primary` → `--color-brand-primary`, `input-focus` → `--color-focus-ring`). Os valores de escala (fontes, espaçamento, raios) foram mantidos. Seletores só do Bootstrap foram removidos (2, ambos `.btn` sob atributo `data-portal-ui`). `--color-shadow-tint` foi adicionado a `tokens.css` para a sombra do Studio. Estado: DEFERRED (sem captura visual). `event-organization.css` (organização de eventos, 270 linhas) foi portado do V1 com o mesmo critério. `--aculta-border-subtle` virou `--color-border`, sem tom mais claro no V2, e `--aculta-status-warning-*` virou `--color-status-warning-surface` e `--color-warning`. Nenhum seletor do Bootstrap foi encontrado. Estado: DEFERRED (sem captura visual). Em 2026-11-05 houve uma correção de mapeamento: `--aculta-interactive-bg` e `--aculta-interactive-border` viram `--color-brand-highlight`, e `--aculta-interactive-text` vira `--color-on-highlight`, como no V1 (amarelo e verde-escuro); o mapeamento anterior, que usava a cor primária e a superfície, estava errado. Por isso `portal-account`, `messenger-drawer`, `public-profile` e `studio` foram regenerados a partir do V1 com o script de port. Seletores do Bootstrap (`.btn`, `.bi`, `.form-control`, `.close`) foram removidos; seletores com `:not()` foram mantidos. Nos portes de `buttons` e `search`, as propriedades locais `--aculta-btn-*` e `--aculta-button-*` substituíram as `--bs-btn-*`. Portados nesta rodada, com o mesmo critério: `search.css`, `statistics.css`, `studio-identity.css` (camada ativada por `html.studio-identity`) e `buttons.css`. Estado de todos: DEFERRED (sem captura visual). `portal-ui-extras.css` reúne só as regras do V1 que atendem as 36 classes mapeadas em `PortalUiHooks::TOKENS` que não tinham CSS no V2 (login, diálogos, consentimento, Revista, conta, suporte, notificações do Mensagens, painel de vendas e endereço). Origem de cada bloco indicada por comentário. Em 2026-11-05 a dívida foi resolvida: os nomes Bootstrap saíram do mapa do V2 (`bi` e `bi-*`, `breadcrumb`, `breadcrumb-item`, `btn`, `btn-link`, `form-control`, `btn-primary`, `btn-outline-primary`, `btn-outline-secondary`, `btn-sm`) e as 189 classes restantes receberam estilo mínimo em `portal-ui-map.css`, agrupado por componente e com tokens semânticos. Os seletores `.btn`, `.btn-link` e `.form-control` também foram removidos dos CSS portados. O gate `validate-aculta420-mapped-classes.php` exige CSS ou exceção documentada em `docs/classes-sem-css-excecoes.md` e recusa nomes Bootstrap no mapa; PASS (453 classes, 0 exceções). O desenho visual completo por componente continua como evolução, não como dívida. `content.css` (conteúdo e cartões, 221 linhas do V1) foi portado com o mesmo critério. As propriedades `--bs-card-*` do V1 não foram portadas, porque o V2 não usa Bootstrap. `--aculta-space-5` virou `1.25rem` literal, sem token equivalente no V2. `--aculta-motion-fast` virou `180ms` e `--aculta-ease-standard` virou `ease`, como no V1. Estado: DEFERRED (sem captura visual). Os demais grupos de classes sem CSS no V2 continuam pendentes.

## 5. JavaScript

O JavaScript legado é predominantemente vanilla com `Drupal.behaviors` e `core/once`. ADR-054 autoriza jQuery 4 via `core/jquery` sob demanda em módulos e SDCs; HTMX permanece padrão para novas requisições conforme ADR-050.
HTMX por `core/drupal.htmx` é o padrão obrigatório das interações assíncronas;
Drupal AJAX (`core/drupal.ajax`, `#ajax`, `use-ajax`) é legado e não deve ser
adicionado ao tema. Migrações preservam links e formulários nativos como
fallback e documentam qualquer compatibilidade antiga ainda necessária.

| Arquivo | Papel |
| --- | --- |
| `components/multidomain-bar/multidomain-bar.js` | sincroniza a altura da barra com o espaço da página e com a barra administrativa; abre os popups de login e de busca via HTMX integrados ao Portal, em `<dialog>` nativo |
| `components/navigation-bar/navigation-bar.js` | drawer de navegação mobile: abre, fecha com Escape e devolve o foco |
| `components/data-table/data-table.js` | marca como região nomeada/focável somente a tabela com overflow; observa resize e atualizações Drupal e limpa observers no detach |
| `js/utility-dialogs.js` | diálogos utilitários com `data-aculta-utility-drawer`: abre por `showModal()`, contém Tab/Shift+Tab, fecha por botão, Escape ou clique no fundo e atualiza `aria-expanded` dos gatilhos |
| `js/messenger-drawers.js` | configura attrs HTMX nos links e formulários dos fragmentos de mensagens/avisos, trata loading/error, confirmação, chips de destinatários e rolagem de conversa; `hx-sync="this:replace"` cancela respostas antigas |
| `js/counters.js` | atualiza contadores após `htmx:afterSettle`, com zero oculto |
| `js/alerts-dismiss.js` | dispensa avisos e atualiza o contador |
| `js/studio-htmx.js` | em `htmx:afterSettle`, move o foco para `[data-aculta-focus]` ou `h1`, atualiza `document.title` por `data-aculta-title` e anuncia a troca em região `aria-live` quando o alvo ainda não tem uma; em swaps `outerHTML`, resolve o novo alvo pelo id antes de focar |

Os adapters de tema `templates/content/node--revista--teaser.html.twig` e
`node--revista--full.html.twig` passam os campos renderizados do nó para os
slots de `revista-teaser` e `revista-publication`. A View
`templates/views/views-view--revista-sessoes--page-revista.html.twig` encaminha
suas linhas já renderizadas para `revista-listing`. A folha dos SDCs preserva a
grade editorial, capa 16:9, destaque da primeira publicação em telas amplas e
coluna de leitura do artigo. O adapter
`templates/aculta-user-author-card.html.twig` converte a API visual
`aculta_user_author_card` para `author-card` sem ler entidades, perfil ou foto.

Os adapters `templates/content/node--wiki-entry--teaser.html.twig` e
`node--wiki-entry--full.html.twig` encaminham campos e ações renderizados pelo
Drupal ao SDC `wiki-entry`; o componente aplica a composição visual da V1 para
cartões e verbetes. `src/styles/wiki.css` trata apenas as listagens, busca,
categorias e mudanças recentes fornecidas pelo owner da Wiki. O Domain Header
continua sob o contrato do shell.

Templates específicos seguem a tabela de tipos e view modes:

| Bundle/rota | Template/adaptador | Apresentação | Fallback/limite |
| --- | --- | --- | --- |
| `revista` teaser/full | `node--revista--*.html.twig` | SDCs `revista-teaser`/`revista-publication`; P2 `full` | render arrays do node |
| `wiki_entry` teaser/full | `node--wiki-entry--*.html.twig` | SDC `wiki-entry`; P2 `aside` | campos e access Drupal |
| `aculta_forum_topic` teaser/full | `node--aculta-forum-topic.html.twig` | SDC `forum-topic`; P2 `aside` | campos, flags e discussão do Portal |
| `aculta_faq` full | `node--aculta-faq--full.html.twig` | SDC `accordion`; P2 `stack` | `<details>` nativo se o SDC não estiver descoberto |
| evento | `aculta-event-page.html.twig` | SDC `event-page-layout`; rota P2 `full` | render arrays do módulo `aculta_events` |
| aula LMS | adapter da rota `aculta_lms.lesson_view` | SDC `lms-lesson-page`; P2 `aside` | render array do LMS |
| Links | `aculta-links-page.html.twig` | SDC `social-links-page` | hook do submódulo Links |
| bundles restantes (`page`, `document`, `aculta_notice`, `aculta_adm`, `aculta_lms_topic`, `activity` legado) | nenhum override genérico | template/display Core; P2 `stack` por padrão | fields/access/cache do entity view display |

O override genérico `node--teaser.html.twig` foi removido porque aplicava um
cartão cultural a bundles sem esse contrato e podia ocultar campos como o
arquivo de um `document`. Loja (catálogo/produto) permanece aguardando autorização
por tarefa; os módulos Layout Builder e UI Patterns Layouts estão habilitados, mas os displays só passam a usar Layout Builder mediante migração e testes explícitos conforme ADR-054.

A biblioteca `studio-htmx` depende de `core/drupal.htmx`, `core/drupal` e `core/once`. A biblioteca `utility-drawers` depende de `studio-htmx` e carrega os diálogos, gavetas, contadores e dispensa de avisos. Os diálogos nativos usam `data-aculta-utility-drawer` como contrato de comportamento; estilos específicos de cada SDC ficam isolados para que um modal não herde dimensões de gaveta. As gavetas declaram `data-hx-get`, alvo e `data-hx-sync="this:replace"`; os fragmentos mantêm `href` e POST nativos como fallback sem JavaScript. O login e a busca do shell usam os endpoints HTMX do Portal: o gatilho é um link normal, o JS acrescenta `data-hx-get`, alvo, `select` e `hx-sync` antes de abrir o `<dialog>`. A prévia da busca usa `data-hx-get` no campo, com debounce de 300 ms e `hx-sync="this:replace"` no lugar do `AbortController`. Busca e login não usam `core/drupal.dialog.ajax`; a V2 remove essa dependência do utilitário de busca (`PagePresentationHooks::removeSearchDialogDependency`). A matriz visual e o fallback sem JavaScript da busca e do login estão em `tests/browser/aculta-htmx-f1/`.

Com JavaScript desligado, os links dos gatilhos continuam válidos: o login e a busca abrem as páginas próprias do Portal. Exceção registrada em 2026-11-05: as gavetas de mensagens/avisos são abertas por `<button>` e exigem JavaScript; o Portal não fornece `all_href` para um link nativo de página completa, e o responsável decidiu manter essa exceção documentada nesta fase.

## 6. Shell

- `templates/layout/page.html.twig` é o shell clássico e permanece o padrão. `templates/layout/page--shell-v2.html.twig` é o shell paralelo da ADR-054, ativado por `aculta_shell_variant = v2` (Aparência → configurações do tema → Shell da página). A sugestão `page__shell_v2` é adicionada por `PagePresentationHooks::themeSuggestionsPageAlter`; o padrão continua `classic` para rollback imediato.
- O shell v2 compõe as cinco áreas finais (`institution`, `header`, `content`, `sidebar_first`, `footer`). Até o corte, renderiza `navigation` junto do header e `highlighted`, `help` e `breadcrumb` numa área contextual acima do conteúdo, para que nenhum bloco configurado fique órfão. Não altera o `info.yml` nem exige importação de configuração nessa fase.
- Quando o shell v2 está ativo, um `page_title_block` que um override de Domain Config tenha colocado em `navigation` ou `highlighted` é movido para o slot de título do `layout-page`, mantendo um único H1 dentro do landmark principal (`PagePresentationHooks::composeShellTitle`).
- Os layouts de composição são SDCs com `group: Layouts`: `layout-page` (variantes stack/aside/full), `layout-section`, `layout-two-columns`, `layout-grid`, `layout-narrow` e `layout-full`. O `ui_patterns_layouts` deriva um plugin de layout para cada SDC com slots e usa o `group` como categoria; por isso os seis aparecem no seletor do Layout Builder sem registro manual. Os demais SDCs com slots também são derivados pelo módulo contrib, mas ficam nas categorias editoriais do próprio componente.
- O planejamento de habilitação do Layout Builder por display é somente leitura (`scripts/audit-aculta420-layout-builder.php`): classifica os 95 displays em compatível, adaptador e incompatível.
- Lote inicial ativado no desenvolvimento com backup e autorização do responsável: **22 dos 24 displays compatíveis** com `allow_custom: false`, somando nós `full` e `default`, oito displays de `block_content` e `taxonomy_term.editorial_author.default`. Cada ativação foi comparada antes/depois em sete páginas; a revalidação por multiconjunto de tokens eliminou falsos positivos de ordenação da Wiki. Excluídos apenas `node.document.full` e `block_content.basic.default`, que perdem conteúdo de forma reproduzível. Gate: `validate-aculta420-layout-builder-batch.php` (170 checks).
- `templates/layout/page.html.twig` monta a página e inclui a barra institucional (`multidomain-bar`) antes do `page.header`.
- A região `footer` é apresentada pelo SDC `site-footer`; quando o Portal fornece `social_links_page_footer`, usa a variação `social-links` (C4).
- `templates/aculta-links-page.html.twig` adapta o hook de apresentação do submódulo Links ao SDC `social-links-page`, sem resolver dados ou regras de domínio.
- `templates/aculta-links-landing.html.twig` sobrescreve a landing de LinkWeed e a compõe com `layout-section`; os links, a descrição e o acesso continuam no módulo. Verificado nos shells clássico e v2 (1 H1 e 1 `<main>`).
- A barra institucional é fixa. A altura medida dela vira `--aculta-institution-bar-height`, e um espaçador (`.aculta-institution-bar-placeholder`) mantém o conteúdo abaixo dela.
- O bloco `system_menu_block:main` é envolvido pelo SDC `navigation-bar` no mesmo peso e posição do cabeçalho. O render array original fica como slot, com access e cache preservados.
- O menu de conta (`account-menu`) fica dentro da barra. Visitante vê "Entrar"; autenticado vê o disclosure com avatar e as ações de conta (ver [components.md](components.md)).
- A barra institucional convive com a barra administrativa Gin e com o toolbar Core: a faixa fica acima das duas camadas, e cada uma começa após a altura medida da faixa.
- O catálogo de prévias reais de blocos fica no módulo do Portal `aculta_ui_library`, não no tema: ele lê os blocos do `aculta420_v2` pela configuração ativa, exige `administer blocks`, verifica o acesso do plugin e usa `max-age 0`. A UI Patterns Library continua sendo o catálogo de SDCs e stories.
- UI Patterns Library (`overview`, `provider` e `single`) é uma exceção administrativa: `ComponentLibraryRoutes::isComponentLibraryRoute()` identifica as rotas e `PagePresentationHooks` usa a variante `full`, sem container legado. A classe `aculta-component-library-page` estiliza cada `.ui_patterns_component` como bloco de largura ampla, sem impor grid às stories; a barra institucional mantém a marca e oculta a faixa de utilidades só nessas rotas. `UiPatternsStoryHooks` instancia stories PHP declaradas pelos componentes e passa props/slots resolvidas ao `component_story`, mantendo a barra e o menu do Studio ligados aos providers reais.

## 7. Hooks e registro

- `#[Hook]` em classes com injeção de dependência. Não há `\Drupal::` em `src/`.
- `PagePresentationHooks` (`preprocess_page`) mapeia o contrato do Portal para variáveis do tema, move o menu de conta para a barra, envolve o menu principal com `navigation-bar` e remove a dependência de diálogo AJAX do utilitário de busca. Expõe `aculta_shell_variant` (`classic` ou `v2`) e, no shell v2, `composeShellTitle` move o título da rota para o conteúdo.
- `aculta420_v2_theme_suggestions_page_alter` no arquivo `aculta420_v2.theme` adiciona a sugestão `page__shell_v2` somente quando a configuração do tema seleciona o shell paralelo. A configuração é lida de `aculta420_v2.settings`, não de hostname. A sugestão fica no arquivo do tema porque é apresentação; `src/Hook` não registra hooks de sugestão de template.
- `PagePresentationHooks::preprocessPortalComponent()` mapeia os contratos `empty-state` e `context-tabs` do Portal para os SDCs `empty-state` e `tabs` do tema, preservando props e slots e mantendo o fallback para nomes fora do mapa ou contrato incompleto. `ComponentFallbackHooks` do Portal executa antes do preprocess do tema; para esses dois nomes o tema substitui deliberadamente o render array genérico. O mapeamento focal passou em PHPUnit e renderização Drupal. Firefox ESR comparou o `empty-state` real em `/group/131`, antes/depois, claro/escuro e 320/1440 px; após a mudança o DOM contém `data-component-id="aculta420_v2:empty-state"`, a mensagem correta e nenhuma rolagem horizontal; o foco visível mede 3 px. A suíte visual ampla do Studio segue parcial (34/48 checks OK, 22 skipped, 14 falhas, incluindo composição e o check legado de modo escuro); detalhes em `docs/layouts-por-purpose.md`.
- O mesmo hook define `aculta_layout_variant` por bundle do nó ou nome de rota: wiki/fórum `aside`, revista `full`, home e catálogo/mapa de eventos `full`; demais rotas `stack`. Não consulta hostname nem purpose, e entrega ao SDC somente a variante. Catálogo e produto de loja aguardam autorização explícita de tarefa.
- Dashboard do Studio na raiz `account` e rotas de Minha Conta (`/conta/*`), Perfil, WE Sativa, Ganjalab, aula LMS e Culturas escolhem `aside`; a raiz usa `aculta_studio.dashboard` e `/conta` apresenta Dados cadastrais privados. O Portal bloqueia acesso direto ao path interno do dashboard e aplica a guarda de autenticação.
- `SurfaceSettingsHooks` (`preprocess_html`) aplica o modo de cor e as classes de superfície em uma única implementação.
- Evento: o adapter `aculta-event-page.html.twig` consome render arrays do módulo no SDC `event-page-layout`, cuja story é `event-page-layout.default.story.yml`. FAQ: `node--aculta-faq--full.html.twig` apresenta cada resposta por `accordion`; o conteúdo continua vindo do render array do Portal.
- `PortalUiHooks` (`preprocess`, genérico): converte `data-portal-ui` em classes do V2 em todo `*attributes` do theme hook. Em 2026-11-08 os tokens de botão já migrados para o SDC `aculta420_v2:button` saíram do mapa (`aculta-btn-*`, `aculta-button-*`, `surface-list-edit-action`, `lms-answer-review-button`, `wiki-proposal-contribute`, `event-organization-admin-action` e variantes `event-location-*` sem uso). Continuam mapeados os tokens usados por envios de formulário e por controles com comportamento próprio que ainda passam pelo mapa (`social-links-settings-button`, `friendship-action-button`, `notice-read-state-action`, `conversation-action`, `event-*button*`, `event-date-cancel`, `event-date-delete`) e os tokens exigidos pelo `ui_styles` (`button-primary`, `button-secondary`, `button-outline`, `button-ghost`). Tokens fora desse mapa ainda não têm classe no V2 (ver o gate `validate-module-theme-boundary`). Sem número de versão até a validação visual no Runtime; status: pendente, captura visual DEFERRED.
- `MailEnvelopeHooks` (`preprocess_aculta_mailer_wrap`) monta o envelope HTML dos e-mails do `aculta_mailer`: cores claras e escuras lidas de `src/styles/tokens.css` a cada montagem e logo do asset do próprio V2. Não lê configuração da V1.
- `src/Hook/*` é a única camada PHP do tema. Lógica de negócio não pertence aqui.

## 8. Dependências e compatibilidade

- Drupal `^11.5 || ^12`. O desenvolvimento roda no 11.5 do servidor de desenvolvimento. Compatibilidade com 12 é meta, sem validação ainda (ADR-045).
- Ícones Phosphor (MIT, Core 2.1.1) estão vendorizados em `assets/icons/phosphor/` e são injetados inline pelo componente `icon`.
- Não há UI Styles nem CTools no tema hoje. UI Styles entra na Fase 4; CTools fica no Portal, conforme ADR-044.


## Fórum Growroom420 — placeholders pendentes (seção 5 do plano)

- Visualizações por tópico (coluna da lista).
- Prévia do último post, miniatura do tópico e moderadores.
- Painéis de destaque e de tópicos relacionados.
- Estado "Seguir" e "Salvar" por usuário (botões desabilitados com "Em breve").
- Contrato com o Portal: papel do autor, variante de faixa e contagem de posts.
