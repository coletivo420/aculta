# Status de layouts, shell e Studio

Snapshot de status extraído do roadmap em 2026-11-05; os registros abaixo não descrevem automaticamente o checkout atual. A seção C4 sobre oito regiões e os relatos de P2/P3 são históricos. O estado atual reconciliado em 2026-11-14 está abaixo; a fila vigente fica no início de `roadmap.md`.

## Estado atual reconciliado — shell e layouts (2026-11-14)

Fontes: `aculta420_v2.info.yml`, `templates/layout/page.html.twig`,
`components/layout-page/layout-page.component.yml` e
`src/Hook/PagePresentationHooks.php` (`preprocessPage()` e
`getPageLayoutVariant()`). O tema tem um shell e cinco regiões declaradas:
`institution`, `header`, `content`, `sidebar_first` e `footer`. O template
passa título, conteúdo e barra lateral ao SDC `layout-page`; o rodapé permanece
no shell. O componente oferece `stack`, `aside` e `full`, com slot de aside
omitido quando vazio.

O hook escolhe a variante pelo nome da rota, tipo de conteúdo e front page; não
usa hostname nem `site_key`/purpose. No código atual: `wiki_entry` usa `aside`;
`aculta_forum_topic`, suas listas públicas/categorias e formulário de criação
usam `full`; `revista` usa `full`; a front page usa `full`; a rota do dashboard
Studio usa `aside`. Rotas de Conta e outras rotas mapeadas explicitamente no
`match` do hook usam `aside`; o restante cai em `stack`. Eventos, FAQ, LMS,
Commerce e toda a configuração por domínio não devem ser inferidos apenas desse
mapa: parte da composição acontece em presenters, render arrays e Config Sync.

Este snapshot confirma implementação estrutural no checkout, não homologação
visual nem o estado efetivamente ativo no Runtime. Capturas responsivas,
teclado, zoom e comparação Runtime continuam pendentes conforme a fila P0/P4
do roadmap. Os registros datados abaixo preservam resultados de suas rodadas e
não substituem este estado atual.

## H2 — Meu Perfil privado em `/meu-perfil`

O owner `aculta_studio` expõe o editor e os formulários próprios em
`/meu-perfil` e `/meu-perfil/*`, no purpose `account`. As rotas preservam o
controle de acesso existente, declaram `no_cache` e recebem `noindex` pelo hook
privado do Studio. Os paths privados anteriores `/perfil*` não são aliases. A
rota pública permanece aguardando decisão do responsável. Unit Studio 69/738,
PHPCS e YAML PASS; no Runtime, `drush cr` e inspeção do route provider confirmam
os cinco paths, purpose e ausência dos antigos. Acesso anônimo negado. Captura
autenticada e fluxos de escrita permanecem pendentes.

## Menu da barra institucional (hambúrguer) — implementado; validação em navegador pendente

O gatilho do `account-menu` é só o ícone de três linhas (rótulo "Abrir menu"
visualmente oculto, `aria-expanded` do `<details>`, alvo de 44 px, variante
ghost com tokens `--button-*`). O avatar saiu do cabeçalho.

O painel abre em tela cheia sobre a página, com X no canto superior direito.
Escape e X fecham e devolvem o foco ao gatilho; enquanto aberto, o Tab fica
preso no painel. Sem JavaScript, o `<details>` continua abrindo e fechando.
Sem logotipo no painel.

Colunas: (1) navegação principal, com o menu `system_menu_block:main` real
(mesmo render array que a barra usa); (2) conta, com avatar e nome, Minha conta
e Sair para autenticado, ou Entrar e Criar conta para anônimo; (3) lista de sites
e links legais (Busca, Política de privacidade e, só para autenticado,
Configurações de privacidade). Desktop: três colunas; 320 px: uma coluna.

Fundo do painel: `--color-surface`. Foco: `--button-focus-ring` (verde-escuro no
claro, creme no escuro). Probe `validate-aculta420-v2-foundations.php` ganhou
quatro pares do menu (texto, gatilho/fechar, hover e anel) nos dois modos.

Pendências: "Termos" e "Informações legais" não têm rota confirmada no código
nem no site publicado, então não aparecem. Validação em Firefox ESR (capturas
antes/depois, 320/1440 px, claro/escuro, anônimo/autenticado) pendente porque o
servidor local serve o checkout principal, não esta worktree.

## Mensagens e avisos — C3 integrado; validação visual pendente

Conferido no código: gavetas `<dialog>` e rodapé fora da rolagem, contador oculto
em zero, filtros de aviso por origem/leitura entregues pelo Portal, respostas
com estado de erro textual acessível e cancelamento de requisições antigas.
Schema, render SDC, shell e foundations passaram na rodada atual. A revisão
visual autenticada, teclado e Escape estão pendentes porque `DISPLAY` não está
disponível.

## Registro histórico — Revisão do shell e rodapé (snapshot 2026-11-05)

Revisadas as oito regiões declaradas: header, navigation, highlighted, help,
breadcrumb, content, sidebar_first e footer; a composição preserva conteúdo
antes da barra lateral e mantém a barra institucional/Domain Header no shell.
Corrigidos os adapters da micropágina Links (SDC dedicado, sem `<main>` aninhado)
e do rodapé social fornecido pelo Portal. Schemas, renderização SDC, shell e
design foundations passaram após as alterações. Capturas em claro/escuro,
larguras, zoom 200% e teclado permanecem DEFERRED por falta de `DISPLAY`. O
adapter de conteúdo público Links segue no inventário de templates G3.

## Registro histórico — Layout de página P1 (snapshot 2026-11-05)

Criado o SDC layout-page com variantes stack, aside e full, stories próprias e
slots title, content e aside. page.html.twig agora compõe o landmark main pelo
componente sem renomear regiões; o aside permanece depois do conteúdo no DOM e
vira coluna a partir de 60rem. Capturas de P1, landmarks, zoom e teclado
aguardam Firefox visível (DISPLAY indisponível). P2 permanece para mapear
tipo/área em preprocess_page.

## Registro histórico — Layout de página P2 (snapshot 2026-11-05; mapeamento superado)

Naquele snapshot, o preprocess selecionava `aside` para wiki e tópico do fórum.
Consulte o quadro atual acima e `PagePresentationHooks::getPageLayoutVariant()`
para o mapeamento vigente no checkout.

## Registro histórico — Layout de Studio e conta P3 (snapshot 2026-11-05; rotas podem ter mudado)

ADR-048 coloca o dashboard privado em `/` no purpose `account` e Minha Conta
em `/conta`. O Portal serve a raiz pela rota protegida
`aculta_studio.dashboard`, bloqueia o path interno quando acessado diretamente
e redireciona visitantes à entrada de login. `/conta` compõe Dados cadastrais
privados e navegação contextual; rotas de Conta e Perfil mantêm `noindex` e
sem cache público. O tema mapeia dashboard e rotas de Conta para `aside`; o
purpose `account` tem layout de container fixo pelo código (Padrão, sem caixa)
em todo o domínio, inclusive em `/conta`, que é a front page do Domain e antes
caía em `full`. O container do purpose usa o mesmo `padding-inline` da barra
institucional, então o conteúdo alinha exatamente com a marca e as ações em
todas as páginas; a leitura estreita (`stack`) permanece centralizada dentro
dele. O slot `aside` só é renderizado quando produz markup: a barra lateral
vazia não reserva coluna.
Subnavegação e `aria-current` existentes foram preservados. Gates de rota,
guardas, layout, noindex, lint e boundary precisam constar no registro da
entrega; Firefox autenticado, capturas, teclado e zoom permanecem pendentes.

## Evento, FAQ e LMS — P4 parcial

Evento já usa adapter e story próprias em event-page-layout. O view mode full
do FAQ compõe o SDC `accordion` pelo preprocess do tema; quando a definição não
está registrada, o preprocess usa o elemento Core `details`. A página de aula do LMS ainda precisa de adapter de apresentação
V2 e story própria; permanece pendente. Capturas e interação autenticada das
três áreas aguardam Firefox visível (DISPLAY indisponível).

## Evento, FAQ e LMS — P4 com implementação; validação visual pendente

Evento já usa adapter e story próprias em event-page-layout. O view mode full
do FAQ compõe o SDC `accordion` pelo preprocess do tema; quando a definição não
está registrada, o preprocess usa o elemento Core `details`. Aula
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

- `1.3.0-dev.1`: componente SDC `studio-calendar` integrado ao Studio Dashboard (`/aculta-studio-dashboard`). Suporta filtros Cursos / Eventos / Meus eventos, troca de mês e seleção de dia assíncrona por HTMX sem recarregar a página. No celular, exibe-se abaixo do cabeçalho e antes de 'Suas experiências'; no desktop, posiciona-se na coluna direita alinhado ao conteúdo. Botão '+' leva ao formulário de propostas de eventos para autenticados. Validação com Firefox ESR (DISPLAY=:0) em 320 px e 1440 px, claro e escuro, anônimo e autenticado, sem estouro horizontal (manifest versionado em `tests/browser/capturas/studio-calendario/2026-11-06/manifest.json`).

- `1.3.1`: layout de container por purpose implementado conforme o plano `docs/architecture/PLANO-LAYOUT-CONTAINER-POR-PURPOSE.md`. Adiciona a subaba "Layout de container" com suporte aos modos Boxed (padrão), Unboxed (largura total), Contained (1000 px) e Max full width (1400 px), com slider de largura geral (960-1600 px) e estreita (600-900 px). Emissão de `data-purpose-layout` e variáveis `--aculta-container-width` e `--aculta-narrow-container-width` no `<html>`. No celular (abaixo de 60 rem), a largura é forçada a 100% sem overflow. Suíte visual em Firefox ESR (:0) cobrindo 10 purposes nos modos claro e escuro, 320 e 1440 px: 300/300 checks PASS (`tests/browser/capturas/layout-container/2026-11-06/manifest.json`).


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

