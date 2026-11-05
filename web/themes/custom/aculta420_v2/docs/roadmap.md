# ACULTA420 V2 roadmap

## H2 — Meu Perfil privado em `/meu-perfil`

O owner `aculta_studio` expõe o editor e os formulários próprios em
`/meu-perfil` e `/meu-perfil/*`, no purpose `account`. As rotas preservam o
controle de acesso existente, declaram `no_cache` e recebem `noindex` pelo hook
privado do Studio. Os paths privados anteriores `/perfil*` não são aliases. A
rota pública permanece aguardando decisão do responsável. Unit Studio 69/738,
PHPCS e YAML PASS; no Runtime, `drush cr` e inspeção do route provider confirmam
os cinco paths, purpose e ausência dos antigos. Acesso anônimo negado. Captura
autenticada e fluxos de escrita permanecem pendentes.

## Avatar — G1 implementado; integração e validação visual pendentes

Criado o SDC `avatar`, com slot de mídia, fallback neutro nomeado e stories para
os dois estados. O componente não consulta entidade/usuário nem gera iniciais.
Schema/render Drupal passaram e Firefox ESR visível capturou os dois estados em
320, 390, 768, 959, 960 e 1440 px nos modos claro/escuro; zoom 200% em 320 CSS
px também passou sem overflow. Teclado não se aplica ao componente estático.
`account-menu` e `author-card` ainda mantêm seus slots atuais; a adoção é
separada para preservar o contrato já validado.

## Author box — G1 coberto por author-card; captura no consumer pendente

O contrato de autoria do `aculta_user` documenta a caixa de autoria como slot
`authors` de `revista-publication`; o SDC compartilhado `author-card` renderiza
identidade, avatar e biografia autorizados. O gate Drupal agora renderiza esse
SDC. Não foi criado outro componente com markup redundante. Fixture composta em
Firefox passou nos seis viewports, claro/escuro, zoom 200% em 320 CSS px e Tab
com foco visível. Conteúdo publicado e autenticado do Runtime permanece pendente.

## Tabs — G1 implementado e validado em fixture Drupal

Criado SDC de navegação por links normais, compatível com a lista de links
renderizada pelo Drupal. O item atual usa `aria-current="page"`; a lista rola
horizontalmente no mobile e não depende de JavaScript. Não usa semântica de
troca de painel (`role="tablist"`), pois Studio e suites navegam entre rotas.
Firefox ESR visível passou nas seis larguras em claro/escuro, zoom 200% em 320
CSS px sem overflow da página e foco visível por Tab. Integração com os render
arrays de navegação do Portal fica para os templates do Studio.

## Dialog — G1 implementado; consumer Runtime pendente

Criado o SDC `dialog` com `<dialog>` nativo, gatilho em forma de link para
fallback sem JavaScript, título associado por `aria-labelledby`, ação de fechar
e slots para conteúdo e ações. A variante `fullscreen` ocupa a tela no mobile e
volta à largura modal a partir de 40rem. O comportamento reutiliza
`utility-dialogs.js`, com foco cíclico por Tab/Shift+Tab; o SDC usa a descoberta
automática de `dialog.css`. Schema 55/764, design foundations 2.468, render
Drupal isolado (31 SDCs), PHP lint/PHPCS, YAML e diff passaram. Firefox ESR
visível em fixture sintética passou 32/32 checks: seis larguras (320/390/768/
959/960/1440) nos modos claro/escuro, zoom real 200% a 320 CSS px, foco cíclico,
Escape, botão/backdrop, e fallback sem JavaScript. PNGs e JSON estão fora do
repositório diretamente em `$SUITE_OUT`. A fixture não comprova integração a
rota ou consumer Drupal no Runtime.

## Form field — G1 implementado; integração a formulário real pendente

Criado o SDC `form-field` para compor o rótulo, o controle Drupal, a instrução
e a mensagem textual de erro, sem assumir a validação nem o tipo do controle.
As stories demonstram a associação label/controle e `aria-describedby` para
instrução/erro; o consumidor fornece os IDs correspondentes no markup do
controle. Schema e render isolado Drupal cobrem o estado inválido e sua mensagem.
Firefox visível em fixture sintética passou em seis larguras e dois modos, zoom
200% e foco por Tab. O consumer de formulário Drupal publicado continua pendente.

## Progress — G1 implementado; integração a dados reais pendente

Criado o SDC `progress` baseado no elemento nativo, com rótulo e valor
percentual legíveis quando determinado; `value: null` preserva o estado
indeterminado sem inventar percentual e o CSS deixa preenchimento parcial. O
gate Drupal verifica ambos os estados e o rótulo. Firefox visível passou em 12
estados nas seis larguras e dois modos, zoom 200% e sem overflow. Stories e
catálogo documentam os contratos; consumer real permanece pendente.

## Data table — G1 implementado; consumer do Studio pendente

Criado o SDC `data-table` com variante `cards` responsiva para o layout do
Studio e variante `scroll` padrão conforme ADR-040. A tabela e os render arrays
continuam no módulo consumidor; a variante de cartões exige cabeçalhos/células
com roles e `data-label`. O JS define role de região, nome e foco do wrapper
rolável somente quando há overflow, com observers limpos no detach Drupal. Stories e gates
cobrem cartões e rolagem. Firefox visível passou em seis larguras, dois modos,
sem overflow de página, zoom real 200% a 320 CSS px, Tab com foco visível e
fallback sem JavaScript. O consumer real do Studio permanece pendente.

## Empty state — G1 implementado; consumer Runtime pendente

Criado o SDC `empty-state` com slots para mensagem e ações e variante de
espaçamento compacto. Mantém o contrato neutro consumido pelo Studio: o módulo
continua decidindo o estado vazio, preparando conteúdo localizado e links
permitidos, com cache e permissões preservados. Stories, documentação e gate de
schema estrito/renderização de 35 SDCs e descoberta CSS passaram. Firefox
visível passou em 12 estados, foco por Tab e zoom real 200%. A integração em
página Studio servida com a V2 permanece pendente.

## Price — G1 aguardando autorização

O ADR-046 lista `price`, mas as referências lidas não definem props,
apresentação ou consumer semântico. A implementação é uma tarefa ligada a
Commerce/Loja, que o plano atual exclui sem autorização explícita por tarefa.
Não foi criado componente nem assumido formato, moeda, impostos ou desconto.
Retomar após autorização e definição do consumer/contrato; outras fases seguem.

## Faixa de acento — F1 DRAFT; F2 bloqueado por decisão

O SDC `card-cultural` aceita `accent: green | yellow | red` e aplica os tokens
de contraste claro/escuro. Stories mostram os três valores, sem consumer ou
inferência local. O gate renderiza o valor explícito e verifica os estilos;
Firefox passou 12 estados, foco visível e zoom real 200%. Estado DRAFT até o
Portal fornecer `domain_presentation.accent` (D1). F2
permanece aguardando confirmação da correspondência Wiki verbete/seção descrita
em `superficies.md` 8.3.1; demais tipos também dependem do contrato D1.

## Menu de conta e sites — C2 implementado; validação visual pendente

O `account-menu` combina a lista neutra `aculta_domain_sites` com o bloco de
sessão, usa links normais entre purposes e marca o atual com
`aria-current="page"`. O Portal fornece `aculta_account_name` e avatar; os
itens Drupal preservam access/cache. Stories anônima e autenticada atualizadas.
Schemas, design foundations, renderização SDC Drupal, shell, browser validators
e PHP lint passaram. As capturas e a interação autenticada ficam pendentes
porque `DISPLAY` não está disponível nesta sessão.

## Mensagens e avisos — C3 integrado; validação visual pendente

Conferido no código: gavetas `<dialog>` e rodapé fora da rolagem, contador oculto
em zero, filtros de aviso por origem/leitura entregues pelo Portal, respostas
com estado de erro textual acessível e cancelamento de requisições antigas.
Schema, render SDC, shell e foundations passaram na rodada atual. A revisão
visual autenticada, teclado e Escape estão pendentes porque `DISPLAY` não está
disponível.

## Revisão do shell e rodapé — C4 implementado; validação visual pendente

Revisadas as oito regiões declaradas: header, navigation, highlighted, help,
breadcrumb, content, sidebar_first e footer; a composição preserva conteúdo
antes da barra lateral e mantém a barra institucional/Domain Header no shell.
Corrigidos os adapters da micropágina Links (SDC dedicado, sem `<main>` aninhado)
e do rodapé social fornecido pelo Portal. Schemas, renderização SDC, shell e
design foundations passaram após as alterações. Capturas em claro/escuro,
larguras, zoom 200% e teclado permanecem DEFERRED por falta de `DISPLAY`. O
adapter de conteúdo público Links segue no inventário de templates G3.

## Layout de página — P1 implementado; validação visual pendente

Criado o SDC layout-page com variantes stack, aside e full, stories próprias e
slots title, content e aside. page.html.twig agora compõe o landmark main pelo
componente sem renomear regiões; o aside permanece depois do conteúdo no DOM e
vira coluna a partir de 60rem. Capturas de P1, landmarks, zoom e teclado
aguardam Firefox visível (DISPLAY indisponível). P2 permanece para mapear
tipo/área em preprocess_page.

## Layout de página — P2 parcial

O preprocess seleciona `aside` para wiki e tópico do fórum; `full` para home,
revista, catálogo de eventos e mapa; e `stack` por padrão. A variante é
selecionada por bundle/route name, sem hostname ou purpose. Catálogo e produto
da loja aguardam autorização explícita de tarefa. Validação visual do
mapeamento permanece pendente por falta de `DISPLAY`.

## Layout de Studio e conta — P3 implementado; validação visual pendente

ADR-048 coloca o dashboard privado em `/` no purpose `account` e Minha Conta
em `/conta`. O Portal serve a raiz pela rota protegida
`aculta_studio.dashboard`, bloqueia o path interno quando acessado diretamente
e redireciona visitantes à entrada de login. `/conta` compõe Dados cadastrais
privados e navegação contextual; rotas de Conta e Perfil mantêm `noindex` e
sem cache público. O tema mapeia dashboard e rotas de Conta para `aside`.
Subnavegação e `aria-current` existentes foram preservados. Gates de rota,
guardas, layout, noindex, lint e boundary precisam constar no registro da
entrega; Firefox autenticado, capturas, teclado e zoom permanecem pendentes.

## Evento, FAQ e LMS — P4 parcial

Evento já usa adapter e story próprias em event-page-layout. FAQ agora
apresenta cada resposta por accordion SDC com fallback nativo durante cache
SDC aquecido. A página de aula do LMS ainda precisa de adapter de apresentação
V2 e story própria; permanece pendente. Capturas e interação autenticada das
três áreas aguardam Firefox visível (DISPLAY indisponível).

## Evento, FAQ e LMS — P4 com implementação; validação visual pendente

Evento já usa adapter e story próprias em event-page-layout. FAQ apresenta cada
resposta por accordion SDC com fallback nativo durante cache SDC aquecido. Aula
LMS envolve o render array original em lms-lesson-page, com story dedicada; o
módulo mantém acesso, cache, atividade e conteúdo. Capturas e interação
autenticada das três áreas aguardam Firefox visível (DISPLAY indisponível).

## Renderização das configurações de superfície — conferida

`SurfaceSettingsHooks` é a implementação única de `preprocess_html`, aplicando
`data-theme` ao `<html>` e as classes administrativas ao `<body>`. A resposta
HTTP servida pelo Runtime é 200; a tabela de hooks lista somente essa classe. O
provider disponível não tinha valor salvo, portanto renderizou os defaults.
Gates foundation (55 pares), design foundations (2.468 checks), SDC schemas
(55 componentes/764 checks), `updatedb:status` e `config:status` passaram. B2
(exportar configuração efetivamente salva com `drush cex`) permanece pendente.

## Shell administrativo — Institution Bar e Gin

- **Implementado em `1.2.0-dev.17`:** a barra fixa mede a largura real da
  sidebar Gin e se restringe à coluna geral. A navegação administrativa mantém
  sua posição e não fica coberta. Em viewport estreito, marca e utilidades
  quebram verticalmente dentro da coluna; controles conservam alvos de toque.
- O espaçamento lateral dos extremos usa escala fluida; os ícones de utilidade
  ficam com 30 px, conforme ADR-047, e sem gap adicional entre
  alvos. O painel da conta ancora na coluna geral.
- O bloco Core Navigation `navigation_shortcuts` foi removido da configuração
  exportada porque depende do módulo `shortcut`, desativado no site, e o plugin
  legado não existe. O Runtime autenticado não mostra mais a mensagem de bloco
  quebrado.
- Firefox ESR visível autenticado com conta de teste, Gin aberto/fechado,
  claro/escuro e 11 larguras: 44/44 checks de geometria PASS. Capturas em
  `$SUITE_OUT/aculta420-v2-admin-sidebar-20261104/`. Runtime Drupal 11.5.0-beta1;
  config import aplicado após snapshot externo; `updatedb:status` sem pendências
  e `config:status` limpo. Em sidebar recolhida, o Top Bar administrativo ainda
  amplia o scrollWidth em 320/390 CSS px; isso ficou DEFERRED para revisão própria
  do toolbar. Zoom e teclado não foram executados nesta fatia.

- `1.2.0-dev.14`: refinamento de fidelidade do diálogo de entrada com a V1.
  A superfície usa `#6b9a2c`; texto escuro mantém 5,45:1. Google mantém o SVG
  oficial da V1 e recupera sombra e realce creme no hover/foco. Formulário,
  fallback e links permanecem sem alteração; Minha conta continua item do menu.
  Firefox ESR visível no Runtime, purpose `main`, rota `/`, visitante: formulário
  e Google presentes, foco inicial no campo de usuário e Escape fecha; superfície
  `rgb(107, 154, 44)`, texto `rgb(23, 21, 19)`, sombra Google presente e sem
  overflow. Viewports efetivos 500 e 1430 CSS px, claro/escuro. Tentativas de
  320/390 ficaram limitadas a 500 px pelo Firefox/X11; zoom 200%, foco do botão
  Google e outros purposes DEFERRED. Capturas/métricas em `$SUITE_OUT`.
  `validate-aculta420-v2-foundations.php` passou (55 pares), schemas passaram
  (55 componentes/764 checks) e renderização Drupal passou (19/19). A tentativa
  de schema na worktree isolada não tinha `vendor/autoload.php`; repetida com
  sucesso no checkout Runtime.

- `1.2.0-dev.13`: breadcrumb migrado da V1 para SDC e integrado ao template
  Drupal `navigation/breadcrumb`. Contrato visual preserva links editoriais,
  separador `/`, título atual e foco. Baseline: V2 não possuía override nem
  componente; referência V1 em `templates/navigation/breadcrumb.html.twig` e
  `css/components/breadcrumb.css`. Firefox ESR visível, purpose `main`, URL
  `/node/572`, anônimo: 320px claro, 390px escuro, 1440px claro/escuro. Uma
  navegação, rótulo traduzido “Trilha de navegação”, título atual preservado,
  link com contraste 5,70:1 no canvas claro e 6,79:1 na superfície escura;
  documento sem overflow. Zoom nativo 200%, viewport CSS 320px: documento 320px,
  breadcrumb 288px. Tab atingiu o link com foco visível de 3px. Capturas em
  `$SUITE_OUT/v1-v2-breadcrumb-20261104/`. Gate SDC: 19/19 componentes renderizados;
  schemas 55/764; foundations 55 pares. Cobertura focal: outras rotas e purposes
  seguem pendentes.

- `1.2.0-dev.12`: superfície do modal alinhada ao verde institucional da V1;
  Google, campos, ação amarela e hierarquia de links visualmente conferidos.
  Firefox ESR visível no purpose `main`, rota `/`, estado anônimo: 320 px
  claro, 390 px escuro e 1440 px em ambos os modos; modal 288/358/440 px,
  sem overflow e com foco inicial no campo. Zoom real de 200% com viewport de
  320 CSS px não alargou o documento; Tab avança para senha e Escape fecha o
  modal. Capturas e métricas em `$SUITE_OUT/login-modal-v2-20261104/`.
  Autenticação completa e demais purposes ficaram DEFERRED nesta verificação
  focal.

- `1.2.0-dev.11`: modal de entrada portado da identidade V1 para `<dialog>` e
  Fetch, mantendo o botão Google quando a integração Portal estiver configurada.

- `1.2.0-dev.9`: corrigida a duplicidade dos blocos Twig de avatar e menu no
  SDC de conta. Runtime autenticado renderiza a rota de materiais do curso;
  sem alterações visuais. Smoke autenticado de leituras, galerias, vídeos e
  PDF privado foi concluído por `aculta_lms_resources` dev.32.
- `1.2.0-dev.8`: menu da barra institucional integrado somente com o avatar
  fornecido por `aculta_user`; não há apelido na barra. Evidência visual e
  runtime autenticado ainda pendentes.

## Fase 1 — Tokens e fundações

**Status: subfase de tokens/fundações entregue em `1.2.0-dev.1`; a Fase 1 do ADR-046 permanece aberta para shell, purpose e UI Styles.**

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

**Status: shell em revisão; Institution Bar, Domain Header, navegação e composição de regiões estão integrados em SDC. Rodapé e restantes dos regions ainda precisam de revisão contra a V1.**

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

## Fase 3 — Purpose neutro no Portal

**Status: pendente; contrato de apresentação será alterado em commit separado no `aculta_portal`.**

## Fase 4 — UI Styles

**Status: pendente; verificar compatibilidade da versão com Drupal 11.5 e 12 antes da instalação.**

## Fase 5 — Componentes e templates

**Status: catálogo atual pendente de auditoria integral; componentes faltantes e templates de páginas seguem após shell e fundações.**

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
- Não trocar tiles OSM por CartoDB nem introduzir endpoint de pontos sem decisão
  e revisão dos contratos de privacidade, acesso, cache e purpose.

### Dívida de estilo — classes mapeadas sem CSS (pendente de decisão)

- 192 classes de `PortalUiHooks::TOKENS` não têm definição de CSS nem no V2 nem no V1. Principais grupos: `aculta-lms-course-builder__*` (11), `aculta-portal-requirements*` (7), `aculta-calendar-interactive__*` (5), `aculta-secret-value__*` (5), `aculta-social-links__*` (5), `event-card__*` (5), `aculta-map__*` (4), `badge--*` (4), `messages*` (4), `secrets-tabs__*` (4), `aculta-button--*` (3), `aculta-statistics__*` (3), `courses-home*` (3), e 2 a 1 de outros componentes LMS e Fórum.
- Estilizar essas classes seria desenho novo. Decisão do responsável: manter sem estilo até haver contrato visual por componente.
- Seis nomes do Bootstrap continuam no mapa (`bi`, `breadcrumb`, `breadcrumb-item`, `btn-primary`, `btn-outline-primary`, `btn-outline-secondary`) e não devem ser usados pelo V2. Remover do mapa muda o HTML gerado; depende de decisão.
- Estado: pendente. Sem captura visual (DEFERRED).

### HTMX — ADR-050 (F0 concluída; F1 parcial)

Core 11.5 `Htmx`, `core/drupal.htmx`, o atributo de conteúdo principal, a
renovação de `form_build_id` e o caminho CSRF foram inspecionados. Testes oficiais
Core e fixture ACULTA de popup/form dentro de `<dialog>` passaram em Firefox e
Chromium headless, incluindo token adulterado rejeitado. **F0 CONCLUÍDA; F1
iniciada e parcial**; F2 depende de F1a e F3 segue a ordem de módulos do ADR-050. Evidência em
[`docs/htmx-confirmacao.md`](../../../../../docs/htmx-confirmacao.md).

**Atualização 2026-11-05 — F1 parcial:** o Portal responde ao login popup por
HTMX, com fragmento, `HX-Redirect` após autenticação e fallback de navegação;
no tema V2, o login do shell e as gavetas de mensagens/avisos usam
`core/drupal.htmx`. A regra de engenharia está registrada: AJAX Drupal é legado
e HTMX é o padrão obrigatório para novas interações assíncronas do tema. Gates
SDC, foundations, shell contract, browser validators e render isolado dos 35
SDCs passaram nesta entrega. O Firefox usado para a fixture foi sintético e
não valida autenticação real no Runtime.

**Atualização 2026-11-05 — busca e cadastro migrados:** a busca do shell deixou
o Fetch. O gatilho abre o diálogo por `hx-get` e o campo de prévia usa `hx-get`
com debounce de 300 ms, `hx-sync="this:replace"` e a rota `/busca/previa`; o
formulário nativo e o link continuam válidos sem JavaScript. O template ganhou
o `id="aculta-search-content"`, que faltava para o alvo HTMX. O Portal responde
ao cadastro popup com fragmento e `HX-Redirect`, mantendo o caminho AJAX só no
fluxo sem HTMX. Login real com a conta de teste respondeu `HX-Redirect` e criou
sessão; o POST inválido do cadastro devolveu o fragmento com erro sem criar
conta. A suíte `tests/browser/aculta-htmx-f1/` passou 43/43 checks no Runtime
de desenvolvimento (busca, login e gaveta de avisos autenticada em 320, 390,
768, 959, 960 e 1440 px, claro e escuro, zoom 200%, prévia HTMX, teclado e
fallback sem JavaScript).

**Estado de F1:** busca, login e gavetas de mensagens/avisos usam HTMX, com
foco, `aria-live` e teclado conferidos; a gaveta de avisos autenticada foi
capturada em seis larguras e dois modos, com os dois grupos `details`. Restam
duas pendências: (1) o consumer V2 do cadastro popup, que é o checkout de
doação, reservado à F5 e aguardando autorização; (2) o fallback sem JavaScript
das gavetas — o gatilho é um `<button>` e o Portal não fornece `all_href`, então
sem JS não há link para a página completa de mensagens/avisos. Converter o
gatilho em link (ou fornecer a URL no contrato) muda o contrato de
apresentação e depende de decisão do responsável. F2 permanece dependente de
F1a; F3 segue a ordem de dependências de módulos definida na ADR-050.

## Compatibilidade e limites

O tema é desenvolvido no Drupal 11.5 instalado e declara `^11.5 || ^12`. A
execução no Drupal 12 ainda requer validação em Core 12. O Runtime de
desenvolvimento não equivale a validação Alpha ou produção.
