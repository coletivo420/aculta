# Changelog — ACULTA420 V2

## Não versionado — auditoria de templates G3

- Removido `node--teaser.html.twig`, override genérico que apresentava bundles
  distintos como cartão cultural e podia omitir campos definidos no display,
  como arquivo institucional. Bundles sem adapter específico retornam ao
  template Core; adapters próprios e render arrays/access/cache permanecem.
- `docs/architecture.md` registra o mapa por bundle/view mode. Visual no Runtime
  com V2 ativa permanece pendente; Loja espera autorização específica.

## Não versionado — faixa de acento do card (DRAFT)

- `card-cultural` aceita `accent` neutro (`green`, `yellow`, `red`) e aplica os
  tokens contrastados por modo. Stories DRAFT mostram as cores sem mapear
  domínios ou tipos; o Portal ainda não fornece `domain_presentation.accent`.
- Gate Drupal verifica prop, classe e token; foundations verifica os seis
  contrastes contra a superfície de cartão (mínimo 3:1). Nenhum consumer de
  conteúdo foi alterado. F2 aguarda D1 e a confirmação da correspondência de Wiki.

## Não versionado — componente Empty state

- Criado o SDC `empty-state`, compatível com o contrato neutro já consumido pelo
  Studio: mensagem e ações vêm dos render arrays do módulo. A variante compacta
  controla somente o espaçamento; o componente não determina acesso nem vazio.
- Stories default/compact, catálogo, especificação do Studio e gate Drupal de
  render/assets incluídos. A integração em página Studio servida com a V2 ainda
  não foi exercitada no Runtime. Drupal validou schema estrito e render de 35
  SDCs; Firefox visível passou em 12 estados, foco por Tab e zoom real 200%.

## Não versionado — componente Data table

- Criado o SDC `data-table` com tabela semântica, variante de cartões móveis e
  rolagem horizontal explícita conforme ADR-040. O comportamento JS transforma
  o wrapper em região nomeada/focável somente durante overflow, acompanhando
  resize e mudanças renderizadas pelo Drupal.
- Stories cobrem as variantes. O gate Drupal V2 validou schema, assets e render
  de 34 SDCs; browser validators também passaram. O validador estático
  `validate-aculta420-sdc-schemas.php` cobre a V1, não a V2. Firefox visível
  passou em 12 estados (seis larguras e dois modos), zoom 200% a 320
  CSS px e fallback sem JavaScript passaram; consumer Drupal de Studio permanece
  pendente.

## Não versionado — componente Progress

- Criado o SDC experimental `progress` com elemento nativo `<progress>`, rótulo
  acessível e stories para estado determinado e indeterminado. O percentual só
  aparece quando o valor é conhecido.
- Gate SDC cobre os dois estados e a associação do rótulo. Firefox visível
  passou em 12 estados nas seis larguras e dois modos, com zoom 200%; a captura
  corrigiu o estilo indeterminado que inicialmente parecia completo. Integração
  a dados reais e consumer publicado permanecem pendentes.

## Não versionado — componente Form field

- Criado o SDC experimental `form-field` com slots de rótulo, controle,
  instrução e erro. O controle e a validação continuam sob responsabilidade
  do Drupal/Portal; duas stories demonstram associações acessíveis.
- Gate SDC estendido para renderizar e conferir controle, descrições e erro.
  Firefox passou 12/12 estados em fixture sintética nas seis larguras, claro e
  escuro, zoom 200% e foco por Tab. Integração em formulário consumidor segue
  pendente.

## Não versionado — componente Dialog

- Criado o SDC `dialog` com `<dialog>` nativo, variante fullscreen no mobile,
  fallback por navegação sem JavaScript e conteúdo/ações fornecidos pelo módulo
  proprietário. O CSS é anexado pelo SDC; a abertura reutiliza o comportamento
  compartilhado `utility-dialogs`, que mantém o foco cíclico por Tab/Shift+Tab.
  Story, documentação e gate de render incluídos.
- Schema (55/764), foundations (2.468) e render Drupal isolado (31 SDCs) PASS;
  Firefox visível passou 32/32 checks em fixture sintética, incluindo seis
  larguras, claro/escuro, zoom real 200%, teclado, fechamento e fallback sem JS.
  Integração a consumer publicado/autenticado do Runtime permanece pendente.

## Não versionado — componente Avatar

- Criado SDC `avatar` com tamanhos responsivos, slot de mídia de responsabilidade
  do Portal e fallback `user-circle` acessível. O componente nunca deriva letras
  ou iniciais do nome. Stories de mídia sintética e fallback documentam os dois
  estados; integração com os consumers existentes fica para uma entrega própria.

## Não versionado — auditoria do Author box

- Mapeada a função prevista de Author box ao slot `authors` de
  `revista-publication` e ao SDC `author-card`, conforme o contrato Portal
  `AUTHOR-CARDS.md`. O gate de render Drupal cobre o cartão com identidade
  autorizada, avatar e bio; captura no fluxo da Revista ainda pendente.

## Não versionado — componente Tabs

- Criado SDC de navegação entre páginas, com slot para a lista de links do
  Drupal, alvos de 44 px, scroll horizontal e estado ativo `aria-current`.
  Não adiciona JavaScript nem troca de painéis. Gates SDC e capturas responsivas
  passaram; integração aos render arrays das suítes segue para G3.

## Não versionado — dashboard e Minha Conta na rota definida pela ADR-048

- O preprocess mapeia a rota `aculta_studio.dashboard` para `aside`, inclusive
  quando ela é servida na raiz do purpose `account`.
- O Portal separa o dashboard em `/` de Minha Conta em `/conta`; o path interno
  do dashboard é bloqueado diretamente e a tela de Conta reutiliza a composição
  privada existente. Validação visual autenticada permanece pendente.

## Não versionado — accordion para FAQ e layout LMS

- Criado o SDC accordion com story e interação HTML nativa (details), foco
  visível e sem JavaScript. O template do FAQ usa o SDC quando disponível e
  mantém fallback sem cache persistente atualizado.
- Criado o SDC lms-lesson-page e sua story. Na rota de aula o tema o usa como
  contêiner do render array original, sem mover acesso, cache, atividades ou
  conteúdo do módulo LMS.

## Não versionado — acordeão nativo para FAQ

- Criado o SDC accordion com story e interação HTML nativa (details), foco
  visível e sem JavaScript. O template do FAQ usa o SDC quando disponível e
  mantém fallback sem cache persistente atualizado.

## Não versionado — compatibilidade com cache de descoberta aquecido

- O template mantém a página renderizável com `<main>` de compatibilidade quando
  o cache Drupal ainda não descobriu o SDC layout-page. O preprocess aceita a
  antiga lista compilada de argumentos e usa `stack` até o container conhecer
  os serviços de rota. A variante nova depende do rebuild normal autorizado.
- A disponibilidade de layout-page, accordion e lms-lesson-page é consultada no
  plugin manager; o arquivo Twig sozinho não indica que o plugin está registrado.

## Não versionado — mapeamento P2 de layout

- `PagePresentationHooks` seleciona `aside` para wiki/fórum e `full` para
  homepage, revista e rotas públicas de catálogo/mapa de eventos, com fallback
  stack. Sem regra por hostname/purpose. Loja permanece fora, aguardando
  autorização explícita de tarefa; confirmação visual também pendente.

## Não versionado — layout-page P1

- Criado SDC layout-page com variantes stack, aside e full, três stories
  e slots semânticos. Refatorado page.html.twig mantendo as oito regiões e
  removendo o grid lateral antigo. O template seleciona stack até o mapeamento
  P2; capturas, landmarks, zoom e teclado seguem pendentes por ausência de
  DISPLAY.

## Não versionado — revisão estrutural do shell (C4)

- `page.html.twig` adapta a micropágina de Links ao SDC próprio e apresenta o
  `social_links_page_footer` do Portal na variação social do `site-footer`. O
  SDC mantém dados, links e identidade como render arrays fornecidos pelo módulo.
  Render SDC e gates estáticos passaram; a revisão visual fica pendente porque
  `DISPLAY` não está disponível.

## Não versionado — paridade de mensagens e avisos (C3)

- Documentada a implementação já existente no shell V2 e no Portal: filtros de
  origem/leitura, contador zero oculto, estado de erro com texto, rodapé fixo e
  cancelamento de respostas antigas. A revisão visual autenticada e teclado
  ficam pendentes por ausência de `DISPLAY` nesta sessão.

## Não versionado — completa menu de conta e lista de sites

- O `account-menu` apresenta `aculta_domain_sites` do Portal com links normais
  e `aria-current="page"` no site atual. O bloco de sessão autenticada mostra
  o avatar e `aculta_account_name`; visitantes recebem o link Entrar dentro do
  disclosure. Os itens de conta originais mantêm access e cache do Drupal.
- Alvo do controle da barra alinhado em 35 px e ícone em 30 px, conforme
  instrução do responsável. Stories anônima/autenticada e documentação SDC
  atualizadas. Schemas (55 componentes/764 checks), design foundations (2.468),
  render Drupal SDC (23 componentes), shell (13 checks), browser validators
  (21 checks), PHP lint e `git diff --check` passaram. Capturas e interação
  autenticada ficam pendentes: `DISPLAY` não está disponível nesta sessão.

## Não versionado — unifica preprocess_html; render conferido

- O Core permite uma implementação `preprocess_html` por tema. Reunidos em
  `SurfaceSettingsHooks` o `data-theme` e as classes das opções de superfície;
  `ColorModeHooks` foi removido. A implementação usa `ContainerInjectionInterface`
  para resolver dependências do provider no mapa de hooks existente.
- PHP lint, gates de foundations (55 pares), design foundations (2.468 checks)
  e schemas (55 componentes/764 checks) passaram. O HTTP do Runtime retornou
  200; o HTML servido traz `data-theme="light"` e as cinco classes de superfície
  esperadas no `<body>`. A tabela de hooks ativa contém somente
  `SurfaceSettingsHooks::preprocessHtml`. `ThemeSettingsProvider` está disponível;
  como o provider não tem valor persistido para `aculta_radius_button`, o hook
  aplicou os defaults documentados. `updatedb:status` sem atualizações e
  `config:status` sem diferenças. Nenhum `cex` foi feito.

## 1.2.0-dev.17 — corrige a Institution Bar com Gin e ícone administrativo (2026-11-04)

- A Institution Bar mede a coluna lateral Gin e ocupa apenas o espaço geral,
  sem deslocar nem cobrir a navegação administrativa. O conteúdo mantém margens
  fluidas e reorganiza as utilidades quando a coluna restante fica estreita.
- Os ícones de busca, carrinho, mensagens e avisos passam a 32 px, alinhados ao
  tamanho do avatar; o espaço entre controles foi reduzido.
- Removido de `navigation.block_layout` o plugin legado `navigation_shortcuts`,
  indisponível quando o módulo Core `shortcut` está desativado. O bloco quebrado
  deixou de aparecer no menu Gin.
- Firefox ESR visível autenticado com a conta administrativa de teste: a matriz
  cobre sidebar Gin expandida/recolhida, Institution Bar, mensagem de bloco
  quebrado, alinhamento, painel de conta e ícones em 11 larguras entre 320 e
  1440 CSS px, nos modos claro/escuro: 44/44 checks de geometria PASS. Capturas em
  `$SUITE_OUT/aculta420-v2-admin-sidebar-20261104/`.
- O scrollWidth da página excede a viewport quando a sidebar Gin está recolhida
  em telas estreitas (385 px para viewport 320; 420 px para viewport 390). A
  inspeção aponta ações do Top Bar administrativo fora da Institution Bar; a
  rolagem da página como um todo fica DEFERRED para uma correção própria do
  toolbar, sem ampliar este ajuste do shell.

## 1.2.0-dev.16 — conecta categorias da landing do Fórum ao SDC (2026-11-04)

- A View `aculta_forum_categories:block_landing` agora usa `forum-category`,
  com wrapper próprio e seletores alinhados ao ID do bloco real do Runtime.
  Antes, a landing imprimia campos sem o cartão SDC; depois, os cartões e
  descrições passaram a usar a mesma apresentação da página de categorias.
- Firefox ESR visível, purpose `forum`, visitante, landing, categorias e tópico
  real, claro/escuro, 320/360/390/599/600/768/1024/1280/1440 CSS px: 76/76
  checks; Tab/Shift+Tab e zoom real 200%/400% com viewport CSS de 320 px sem
  overflow. Capturas em `$SUITE_OUT/aculta420-v2-forum-landing-20261104/`.
- Drush descobriu, validou e renderizou 25/25 SDCs. Runtime pré-sync:
  `updatedb:status` sem pendências e `config:status` sem diferenças; `drush cr`
  passou. O conteúdo foi somente lido.

## 1.2.0-dev.15 — migra apresentações do Fórum (2026-11-04)

- Portados o teaser e a apresentação completa de tópico para o SDC `forum-topic`,
  o cartão `forum-category` e o template de comentários via `discussion-comment`,
  além dos estilos da View pública, filtros de busca, vazios, controles de
  discussão e formulário de comentário. Render arrays de autoria, favoritos,
  contagens e atividade seguem sob controle do Drupal/Portal.
- Adapters de tópico, categoria e Views preservam os render arrays Drupal.
- Firefox ESR visível, Runtime Drupal 11.5.0-beta1, purpose `forum`, sessão
  anônima: landing, categorias e tópico em claro/escuro, 320/360/390/599/600/
  768/1024/1280/1440 CSS px; 58/58 verificações, sem overflow e com um H1.
  Tab/Shift+Tab passaram. Zoom real do Firefox em 200% e 400%, viewport efetiva
  de 320 CSS px, sem rolagem horizontal. Evidências em
  `$SUITE_OUT/aculta420-v2-forum-final-20261104/`.
- Gate Drush descobriu e renderizou 25/25 SDCs, incluindo três componentes do
  Fórum. Foundations: 51 pares de contraste PASS. O template de comentário
  renderiza no gate isolado, mas as páginas públicas consultadas não continham
  comentários do bundle `aculta_discussion_post`; esse estado autenticado segue
  DEFERRED. O Fórum continua anônimo; nenhuma mutação foi feita.

## Em validação — Wiki420

- Portados para SDC os templates de teaser e artigo completo de Wiki, com
  abas de ação, resumo, corpo, imagem, categorias, referências, discussão e
  links de operação renderizados pelo Drupal.
- A família preserva a composição V1 de artigos e cartões com tokens
  semânticos e CSS mobile-first. O artigo é dono do H1; home e listagens usam
  a grade de cartões adaptada ao wrapper real `aculta-wiki-list`.
- Drush descobriu e renderizou 22/22 SDCs. Firefox ESR visível no Runtime,
  home/listagem/verbete, claro e escuro, 320/390/767/768/1440 CSS px: 189/189
  checks; Tab/Shift+Tab e ausência de overflow passaram. Zoom físico e estados
  autenticados seguem pendentes. `/wiki/busca` responde 404 no Runtime atual;
  a rota não foi recriada pelo tema. O CSS de busca/categorias herdado da V1
  ainda aguarda um consumer real no Runtime.

## Em validação — migração visual da Revista

- Adicionados os SDCs `author-card`, `revista-listing`, `revista-teaser` e
  `revista-publication`, com adapters para a View `revista_sessoes`, os view
  modes `teaser`/`full` e o hook visual de autoria do `aculta_user`.
- A apresentação cobre a grade de 1/2/3 colunas, destaque da primeira
  publicação, metadados/compartilhamento, corpo editorial, imagens com modelos
  pequena/padrão/larga/destaque e alinhamento, autoria e discussão. Render arrays
  Drupal são mantidos como slots.
- A listagem passa a fornecer o H1 editorial “Revista 420”; o título genérico do
  Drupal fica oculto somente quando o SDC `revista-listing` está presente,
  removendo o H1 duplicado que a revisão Firefox detectou.
- PHP lint do gate, parse de todos os schemas/stories e `git diff --check`
  passaram. Validação de renderização Drupal e evidência Firefox Runtime ficam
  pendentes; a versão será marcada após esses gates.

## 1.2.0-dev.14 — aproxima o diálogo de acesso da V1

- A superfície recupera o verde exato da V1 (`#6b9a2c`), com texto escuro
  para manter contraste de 5,45:1. O botão Google recupera a sombra discreta e
  o fundo creme em hover e foco por teclado; usa o mesmo SVG de marca da V1.
- A composição, o formulário AJAX, os links e o item Minha conta no painel
  autenticado permanecem no contrato atual. No Runtime, Firefox ESR visível
  confirmou o formulário e Google em claro/escuro, Escape e ausência de overflow
  nos viewports efetivos de 500 e 1430 CSS px. 320/390 px e zoom ficaram
  DEFERRED porque o Firefox/X11 não reduziu a janela abaixo de 500 CSS px.

## 1.2.0-dev.13 — porta o breadcrumb editorial da V1

- A trilha de navegação agora é um SDC e substitui o template nativo com
  separadores, cor editorial, estado atual e foco visível da V1.
- O template mantém a responsabilidade Drupal de montar os itens e o Portal
  continua fornecendo o título atual. O vermelho editorial foi aprofundado para
  atingir contraste AA: 5,70:1 no canvas claro; no escuro, 6,79:1 na superfície.

## 1.2.0-dev.12 — reconcilia painel de login com a identidade V1

- O diálogo usa o verde institucional legado pela paleta semântica V2 e texto
  escuro para manter contraste AA em claro e escuro.
- A caixa Google, campos claros, ação amarela e links creme preservam a
  composição da V1. O gate de fundações mede esses pares em ambos os modos.
- Firefox ESR visível confirmou o modal e Google em 320, 390 e 1440 CSS px,
  além de zoom real de 200% com viewport de 320 CSS px. Tab avança do usuário
  para senha e Escape fecha o diálogo.

## 1.2.0-dev.11 — aplica o estilo ao controle HTML do Drupal

- O botão de envio do formulário Core é um input com `data-drupal-selector`,
  não um elemento `button`; o seletor do modal agora pinta o controle amarelo.
- O foco do formulário usa um token dedicado com contraste sobre as superfícies
  claras fixas dos campos e do provedor.

## 1.2.0-dev.9 — corrige slots Twig do menu de conta (2026-11-04)

- O template `account-menu` agora consome `avatar` e `menu_items` como slots
  SDC, sem redefinir blocos que colidiam com o `embed` de `multidomain-bar`.
  Fallback neutro de avatar e links recebidos foram preservados.
- Gate SDC e smoke funcional autenticado da rota de materiais passaram; turma
  e mídia temporárias foram removidas. Sem alteração visual/CSS.

## 1.2.0-dev.8 — fallback de entrada sem duplicar links

- O bloco de conta Core vazio/inacessível para visitante deixa de ocupar o slot.
  A Institution Bar então renderiza o link de entrada do Portal; autenticados
  continuam usando o render array protegido do menu Drupal.

## 1.2.0-dev.7 — mantém a entrada para visitantes

- Quando o bloco de conta do Drupal não fornece links para visitante, a barra
  usa o link `/entrar/janela` como fallback. O handler existente aprimora-o
  para o diálogo AJAX; sem JavaScript continua sendo um link navegável.

## 1.2.0-dev.6 — corrige o slot comum do menu

- O SDC `account-menu` declara `menu_items` uma única vez para visitante e
  usuário autenticado. Gate de renderização detectou a duplicidade Twig.

## 1.2.0-dev.5 — declara slots aninhados do menu (2026-11-04)

- Declara os slots de avatar e itens encaminhados pelo `multidomain-bar` ao
  SDC `account-menu`, permitindo a compilação da árvore Twig aninhada.
- `drush cr` revelou o problema de descoberta que o gate estático não cobria;
  a validação de renderização será repetida após a correção.

## 1.2.0-dev.4 — botão de conta somente com avatar (2026-11-04)

- A Institution Bar não recebe nem mostra apelido. O botão autenticado contém
  somente o avatar e “Minha conta” visualmente oculto como nome acessível.
- O resumo mantém o alvo de toque de 44 px. O Portal fornece apenas o avatar;
  o painel continua contendo os links Minha conta e Sair.
- A entrada `1.2.0-dev.3` foi substituída após a correção final do escopo.



## 1.2.0-dev.3 — shell institucional e integração visual (2026-11-04)

- A composição do Domain Header agora reúne marca e menu principal no SDC
  `navigation-bar`, preservando os render arrays e o peso do menu. Título,
  breadcrumb e ajuda são renderizados em suas regiões semânticas; a Institution
  Bar continua sendo a faixa fixa superior.
- A vitrine de portais foi isolada no SDC `purpose-showcase`, abaixo do header.
  Logo horizontal e variantes responsivas do Wiki420 vieram dos assets da V1;
  o tema escolhe o asset pela chave neutra fornecida por `domain_presentation`.
- Três blocos de eventos, fórum e agenda foram movidos de `header` para
  `sidebar_first`, recuperando a composição de duas colunas da referência V1.
  Uma entrada obsoleta de `bootstrap5` foi removida do `core.extension` de
  sincronização; nenhuma dependência ou instalação Bootstrap foi adicionada.
- Gin Core Navigation fixa a sidebar em `top: 0` com especificidade baseada em
  ID. O override do shell mede a altura real da Institution Bar e inicia a
  sidebar abaixo dela. Turnstile conserva o desafio dentro do formulário sem
  ampliar a largura da página em viewports estreitos.
- Firefox ESR visível: navegação 100/100 checks nos dez purposes, em
  320/767/768/1024/1099/1100/1440 px, com teclado e fallback sem JavaScript.
  Camadas Gin, Gin Core Navigation e Toolbar Core: 15/15 checks em
  320/390/768/1024/1280 px. Conta: 18/18 estados de reflow em claro/escuro nas
  larguras 320–1440; foco, Shift+Tab, zoom nativo 200/400, sem JavaScript e
  movimento reduzido passaram. HTTP 200; artefatos ficam em `$SUITE_OUT`.
- Runtime Drupal 11.5: nenhum update de banco, configuração ativa igual a
  `config/sync` e `drush cr` concluído. Shell e fundações continuam em revisão;
  toolbar autenticado, leitor de tela e propósitos/rotas fora da matriz focal
  permanecem DEFERRED.

## 1.2.0-dev.2 — navegação SDC no shell (2026-11-04)

- O bloco Drupal `system_menu_block:main` permanece na mesma posição da região
  `header`; o preprocess o envolve no render element nativo do SDC
  `navigation-bar`, preservando o render array original como slot e mantendo
  acesso, peso e cacheabilidade.
- O menu continua visível sem JavaScript; JavaScript aprimora o drawer mobile,
  com foco ao abrir, fechamento por Escape e retorno de foco. A marca permanece
  opcional no componente para evitar duplicação do Domain Header.
- Os landmarks do SDC e do menu Core têm nomes distintos (`Primary navigation`
  e `Main navigation`); links e título continuam sob o sistema de menus Drupal.
- A Institution Bar permanece no topo acima das camadas administrativas. A
  sidebar Gin começa após a altura medida da barra; a bandeja vertical Core
  começa após a barra e os controles do toolbar.
- Firefox ESR visível: baseline `33e4dddbf5`; a matriz funcional cobre dez
  purposes, 320/767/768/1440 px, Enter/Escape/foco e fallback sem JS. A matriz
  visual passou nas dez raízes de purpose em claro/escuro e 320–1440 px, sem
  overflow; zoom 200/400, foco de teclado, sem JS e movimento reduzido passaram.
  O runner injeta o CSS baseline no DOM atual, portanto não representa o
  histórico Twig anterior. O teste de camadas usa as folhas Gin/Core e fixtures
  DOM anônimas (10/10 checks em 320/390/768/1024/1280 px): a barra ocupa
  0–73 px, Gin começa em 73 px, controles Core em 73 px e bandeja Core em
  112 px. Sessão administrativa real e leitor de tela ficam DEFERRED.

## 1.2.0-dev.1 — tokens e fundações com identidade da V1 (2026-11-04)

- Consolidados tipografia Inter/Oswald, escala de espaçamento herdada, foco
  visível tokenizado, movimento reduzido e regras de reflow mobile-first.
- A biblioteca Drupal carrega as mesmas famílias tipográficas externas usadas
  pela V1; nenhum Node, Vite ou Bootstrap foi adicionado ao tema.
- O novo gate `validate-aculta420-v2-foundations.php` verifica tokens e pares
  de contraste do modo escuro. Os 33 pares de texto passam 4,5:1; os 4 pares
  de foco e 4 pares de borda passam 3:1. Piores resultados: texto muted em
  superfície elevada 6,78:1; link editorial em superfície elevada 6,07:1;
  borda em superfície elevada 3,21:1.
- Firefox ESR visível: baseline `676ff17449` comparado ao estado `1.2.0-dev.1`
  nos dez purposes documentados, nas raízes públicas (Conta redireciona à tela
  de entrada). Em 320/360/390/599/600/768/1024/1280/1440 CSS px, os modos
  claro/escuro passaram 180/180 estados sem overflow; todos responderam HTTP
  200. Tab e Shift+Tab: 10/10 focos visíveis. Zoom nativo a 200% e 400%, com
  viewport efetiva de 320 px: 10/10 em cada nível sem overflow. Sem JavaScript:
  90/90 larguras/purposes sem overflow. Movimento reduzido: 180/180
  combinações de largura/modo, transição calculada em 0,01 ms. Sessões anônimas;
  logins autenticados e leitores de tela não foram cobertos. Evidências e URLs
  ficam em `$SUITE_OUT/aculta420-v2-foundations-full/`; purposes e hosts vêm da
  matriz em `docs/architecture/multidomain.md`.
- O gate legado `scripts/validate-aculta420-foundation.php` continua exigindo a
  V1 como tema público e Bootstrap5 como base. No Runtime ativo em V2 ele falha
  com `ACULTA420 is the default public theme.`; o gate específico V2 passou.
- A declaração Drupal `^11.5 || ^12` permanece sem alegação de suporte
  validado no Core 12, conforme ADR-045.

## 1.1.0-dev.5 — menu de conta e login modal da barra (2026-11-04)

- O menu Drupal de conta voltou à barra institucional fixa. Os links nativos
  “Minha conta” e “Sair” permanecem sob controle de acesso do sistema de menu;
  o Portal fornece a URL de logout com token de sessão.
- Para visitantes, “Entrar” continua apontando ao popup do Portal. V2 carrega
  o formulário e envia o login por Fetch ao endpoint `drupal_ajax`, aplicando
  respostas Drupal de substituição/redirect dentro de `<dialog>` nativo. A
  busca usa modal nativo e preserva a prévia do Portal. Os dois gatilhos da
  barra não dependem de `core/drupal.dialog.ajax` nem de jQuery. Uma View
  existente ainda anexa `core/drupal.ajax` (e sua dependência jQuery) à home;
  essa biblioteca não é introduzida pela barra.
- A barra lê o layout de purpose do Portal, realça a conta no purpose account
  e respeita a supressão da faixa utilitária no purpose links.
- Validação SDC 15/15, PHP lint/PHPCS, sintaxe JavaScript e `drush cr` passaram.
  Smoke HTTP confirmou home, endpoint de login e endpoint de busca em 200.
  O envio de credenciais não foi testado. `updatedb:status` e `config:status`
  estão limpos.

## 1.1.0-dev.4 — ícones vetoriais e integração CDN/cache (2026-11-04)

- Adicionada a biblioteca Phosphor Icons Core 2.1.1 (regular e duotone, MIT)
  e o SDC `icon`, com nomes validados e SVG inline via Twig `source()` para
  herdar `currentColor` sem dependência de CORS ou sprite externo.
- Badge, button e card-cultural aceitam ícones SDC; os metadados de data e
  local usam ícones Phosphor.
- Busca, carrinho, mensagens, avisos, fechar diálogos, navegação e mensagens de
  status também usam o SDC `icon`; removidos os SVGs de interface desenhados
  diretamente nos templates.
- Documentada a estratégia de assets Drupal/CDN: URLs relativas ao Drupal,
  bibliotecas agregáveis e cache-tag invalidation pelo ambiente. Vite continua
  fora da fundação conforme a decisão anterior de usar Drupal libraries.
- Leaflet próprio recebe ícones inline renderizados pelo tema em templates
  inertes e os monta em `L.divIcon`; IDs e classes de categoria são validados.
- Validação: 15/15 SDCs discover, validate and render; icon output includes
  inline SVG, and a template audit found no inline interface SVGs outside the
  icon SDC. Runtime cache rebuild and Eventos `/mapa` HTTP smoke succeeded.
  Unit Maps: 4 tests / 26 assertions PASS. Visual/assistive review and
  environment CDN deployment remain follow-up.

## 1.1.0-dev.3 — 2026-11-04

- Added the global institutional bar SDC fixed at the legacy top position: the
  ACULTA logo links to the main domain, followed by icon-only search, cart,
  messages, and alerts. Optional portal links render in a separate secondary
  row; the four utilities have no visible text labels.
  Domain resolution, utility URLs/counts, and cache dependencies remain owned
  by ACULTA Portal.
- Migrated the ACULTA420 logo variants and favicon from V1. Added an
  `apple-touch-icon.png` derived from the V1 favicon and a web app manifest
  using square brand icons. Color mode remains controlled by the existing
  explicit selector.
- Foundation schema: 55 SDCs / 764 checks PASS; Design Foundations: 2468
  checks PASS; V2 SDC discovery/schema/render 14/14 PASS. Runtime cache rebuild
  and HTTP 200 confirmed. Visual review remains pending.

## 1.1.0-dev.2 — 2026-11-04

- Integrated the `map-popup` SDC with ACULTA Maps theme hooks. The map module
  supplies escaped, provider-neutral render data and the theme selects the SDC.
- Leaflet and Google InfoWindows clone the same inert server-rendered template;
  popup HTML is not transferred through `drupalSettings`.
- Added the optional `route_url` popup prop. Validation and visual review remain
  pending.

## 1.1.0-dev.1 — 2026-11-04

- Added ten schema-enforced Single Directory Components for buttons, badges,
  cultural cards, audio, hero banners, navigation, pagination, filter chips,
  status messages, and map popups.
- Added Drupal template adapters for pager, status messages, and node teasers.
- Added explicit light/dark token sets controlled by the existing ACULTA Portal
  appearance preference. System color-scheme detection is disabled; legacy
  `auto` resolves to light in V2.
- Added a progressive mobile navigation drawer using Vanilla JavaScript,
  `Drupal.behaviors`, and `core/once`.
- Added a Drush SDC validation gate and architecture documentation.
- Validation: Core strict SDC schemas and isolated renders, all Twig parses,
  node teaser render, YAML, JavaScript syntax, PHP lint/PHPCS, CSS parse and
  mobile-first media audit, explicit mode mapping, and development Runtime
  smoke. Full consumer visual and assistive technology review remains pending.

State: Dev. No RC, production deployment, or release tag is declared.
