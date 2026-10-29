# Auditoria mobile-first ACULTA420 — THEME-MF0

**Estado em 2026-10-29: inventário estático parcial; MF2 tem migrações incrementais abaixo, mas o gate global MF3 não foi aprovado.**
A auditoria original foi registrada no checkout servidor `ded8a6181`. Em
2026-10-29, a recontagem foi executada na branch atualizada
`0a479dde3` em `/home/piradopirata/worktrees/opencultures-f0-adm8-e8`; a
varredura abaixo cobre os arquivos atuais do tema, mas não altera a evidência
de runtime. Nenhuma escrita persistente foi executada no Runtime.

### THEME-MF2 — busca e listagens Wiki (2026-10-29)

`wiki.css` declara tamanhos compactos do header como base; `36rem` reintroduz
as dimensões intermediárias e `48rem` as proporções desktop anteriores. Busca,
formulários de resultados e grids de categorias/verbetes começam em uma coluna
e ampliam a duas em 768px. O harness `tests/browser/aculta-wiki/visual.py`
injeta a CSS candidata local e verifica propriedades computadas/overflow nas
rotas públicas em 575/576/767/768px, além das larguras já existentes.
Stylelint e compilação Python PASS; Firefox/runtime DEFERRED por sessões
concorrentes. Hub autenticado do Studio não foi alterado nem homologado; MF3
permanece pendente.

### THEME-MF2 — controles da discussão (2026-10-29)

`discussion.css` declara como base o arranjo de 2 colunas abaixo de 360px;
`min-width: 22.5rem` amplia para 4 colunas e `min-width: 36rem` restaura as 7
trilhas, as dimensões e a posição sticky anteriores. O formulário de resposta
usa padding de `0.875rem` como base e restaura `1rem` em `36rem`. A ordem DOM,
ações, estados e ownership da barra pelo Portal não mudam.

O smoke `tests/browser/aculta-forum/progress-visual.mjs` foi ampliado para
injetar a folha candidata e conferir geometria, áreas, sticky, foco/teclado,
alvos de toque, claro/escuro e overflow em 320/360/390/500/575/576/900px.
`npm run lint:css`, `node --check`, schema SDC, Design Foundations e índice de
status PASS. A execução Firefox e a validação do formulário real em Fórum,
Wiki e Revista ficam DEFERRED por sessões concorrentes; MF3 não aprovado.

### THEME-MF2 — rodapé global (2026-10-29)

`footer.css` declara a grade em uma coluna como base móvel. A partir de
`36rem` (576 CSS px), a grade passa a duas colunas; a partir de `62rem`
(992 CSS px), restaura a composição anterior de quatro colunas com a coluna da
marca em proporção dupla. A ordem DOM, os blocos e a tipografia não mudam.
`npm run lint:css` e validações estáticas são executados. Comparação visual
Firefox e inspeção das páginas reais estão DEFERRED enquanto sessões visíveis
concorrentes ocupam o ambiente. Este resultado não aprova THEME-MF3.

### THEME-MF2 — Institution Bar abaixo de 375px (2026-10-29)

A marca da barra multidomínio agora fica oculta visualmente como estilo-base
para o viewport mais estreito e reaparece em `min-width: 23.4375rem` (375 CSS
px). O link permanece acessível; sticky, DOM e contratos de shell não mudam.
O harness existente cobre a fronteira 374/375 e todos os dez purposes, mas a
execução Firefox/runtime fica DEFERRED por sessões visíveis concorrentes. Lint
CSS e validações estáticas são executados. Esta fatia não aprova THEME-MF3.

### THEME-MF2 — Perfil público (2026-10-29)

`public-profile.css` declara o perfil empilhado e seus espaçamentos compactos
como estilo-base; `min-width: 36rem` restaura a apresentação horizontal e os
espaçamentos amplos anteriores. A mudança preserva a hierarquia e o markup do
perfil público e não altera o formulário privado da Conta. A comparação
reutilizável `tests/browser/aculta-theme-mobile-first/public-profile-layout.py`
usa fixture sintética. A primeira execução Firefox fez 21/22 checks e revelou
uma diferença de espaçamento na fixture em 320 px, corrigida no CSS; a repetição
não iniciou uma sessão pois a memória/sessões geckodriver visíveis da Revista
estavam ocupadas (DEFERRED). A inspeção da rota real servida ainda depende de
sincronização/cache rebuild. THEME-MF3 permanece pendente.

### THEME-MF2 — composição institucional (2026-10-29)

`css/components/institutional.css` agora define primeiro a pilha vertical para
CTAs do hero, as áreas institucionais e os pares de dados do bloco de
instituição. `min-width: 36rem` restaura CTAs em linha; `min-width: 48rem`
restaura duas colunas nas áreas/detalhes. O override mobile da View de projetos
foi mantido por atuar sobre o consumer do SDC `content-grid`; suas variantes
desktop permanecem sob ownership do SDC.

`tests/browser/aculta-theme-mobile-first/institutional-layout.py` usou Firefox
ESR visível para comparar o CSS anterior com o candidato em 320/360/390/575/576/
599/600/767/768/1024/1280/1440 px. As 24 verificações passaram: estilos
computados iguais ao baseline, capturas PNG idênticas e nenhum overflow do
documento. Fixture sintético verifica somente a composição CSS, não substitui
render de conteúdo institucional no Runtime. Evidências em
`/home/piradopirata/aculta-suite-out/aculta-theme-institutional-layout/`.

`npm run lint:css`, SDC schema (54 componentes/734 checks), Design Foundations
(2434 checks), browser validators (21 checks) e Foundation via Drush/Runtime
(348 checks) passaram. O Foundation leu a árvore candidata do worktree com o
container Drupal do Runtime; a folha ainda não foi servida por cache rebuild.
Inspeção de páginas reais, teclado, zoom e cobertura por purpose ficam
pendentes. MF2 segue parcial e MF3 não foi aprovado.

## Escopo quantificado

O inventário foi refeito no commit de auditoria de 2026-10-29 por
`scripts/audit-aculta420-responsive-inventory.py`. O tema tem 541 arquivos,
incluindo 92 estilos CSS/Sass, 12 scripts JavaScript (11 de produção e um de
teste) e 98 templates Twig/HTML. O TSV inclui esses 202 arquivos fonte; não
inclui a documentação. Busca lexical encontrou `@media` em 44 folhas (77
blocos), zero `@container`, expressões de dimensão em 72 folhas (599 matches)
e declarações `overflow`, `overflow-x` ou `overflow-y` em 28 folhas (54
declarações). A expressão de dimensão inclui width/height/inline-size/
block-size, variantes min/max e condições de media query. As contagens não
analisam cascata nem efeito calculado. O inventário é estrutural; não mede
overflow renderizado ou prova comportamento por tela.

| Camada | Arquivos identificados | Observação da auditoria |
|---|---|---|
| Fundação | `css/base.css`, `css/layout.css`, `css/tokens.css`, `css/drupal-bootstrap.css` | `base.css`/`layout.css` quase sem media queries; tokens têm preferência de tema; Bootstrap injeta regras próprias. |
| Shell global | `css/components/header.css`, `navigation.css`, `institution-bar.css`, `breadcrumb.css`, `footer.css`, `institutional.css` | Afeta todos os purposes; Institution Bar e Domain Header são invariantes. |
| Componentes compartilhados | 41 folhas em `css/components/` e 45 folhas CSS de SDC | Mistura estilos sem breakpoint e media queries; os consumidores reais precisam ser correlacionados antes da migração. |
| Áreas de produto | account/portal, fórum/discussion, wiki, editorial/revista, product/cart/sales, eventos, messenger, mídia/documentos, doações e suporte | Não podem ser excluídas por ausência de rota no Studio. |
| JavaScript | 11 scripts de apresentação e um teste JS | Auditar progressive enhancement, estado de navegação, carrosséis/slideshows, drawer Messenger, calculadora de envio, doação e notificações. |
| Templates/layouts | 98 Twig/HTML | A contagem inclui componentes SDC e templates do tema; precisa cruzamento com bibliotecas anexadas por módulos e Views. |

## Registro responsivo reproduzível

Contagens por arquivo, não por regra única (valores podem ter múltiplas
declarações por bloco):

| Grupo observado | Arquivos com `@media` | Risco inicial a verificar |
|---|---:|---|
| `css/components/` | 26 | Cascata carregada globalmente; breakpoints em portal-account (7), navigation (5), forum-index (5), discussion (4), wiki e portal-support (3 cada). |
| `components/**` (SDC) | 16 de 45 | 17 blocos `@media`; variantes locais devem ser testadas em consumers reais e contra a cascata global. |
| Bootstrap distribuído | 2 | Regras do tema base; não substituir infraestrutura nem alterar a cópia do Core/contrib. |
| `css/tokens.css` | 1 | Preferência light/dark não deve alterar geometria ou ocultar conteúdo. |

Arquivos com mais matches lexicais de largura (incluindo condições de media
query) incluem
`css/components/portal-account.css`, `forum-index.css`, `messenger-drawer.css`,
`navigation.css`, `discussion.css`, `institution-bar.css` e SDCs
`author-card`, `profile-identity`, `content-grid`, `context-tabs` e
`social-links-page`. A busca detecta declarações CSS literais em `px`, `rem`,
`em` e `vw`; não detecta limites impostos por HTML, tabelas, imagens, JS,
terceiros, estilos inline ou comportamento de conteúdo real.

## Matriz de cobertura por purpose

| Purpose/experiência | Ponto inicial auditado | Prioridade para MF1/MF2 | Status MF0 |
|---|---|---|---|
| MAIN público/institucional | `institutional.css`, hero, navegação, busca, conteúdo, mídia, rodapé | Reflow do shell, cabeçalhos longos, cards, mídia e teclado | CSS mapeado; rotas/estados e captura pendentes |
| ACCOUNT/Conta privada | `account.css`, `portal-account.css`, `portal-address-number.css`, formulários/modais | Form API, tabelas de pedidos, endereços, foco e zoom | CSS mapeado; cenários S-R4/S-R5 pendentes |
| Perfil público e editor privado | `public-profile.css`, `profile-*` SDCs | Separação de dados privados/públicos e formulário/upload | Componentes identificados; captura e acesso pendentes |
| Studio/Marley | `studio.css`, `context-*`, `content-*`, formulários/editorial | Dashboard, navegação interna, listagens e editor | Parcial; matriz OCS separada |
| GanjaLab/LMS | `course_card`, `content-grid`, `context-header`, `empty-state` | Cursos, progresso, certificados, sala/turma e tabela | SDCs candidatos identificados; runtime não homologado |
| WE Sativa/Wiki | `wiki.css`, `wiki-entry`, autoria/revisão | Longas tabelas, propostas, estados de revisão e moderação | CSS e SDC identificados; captura autorizada pendente |
| Fórum | `discussion.css`, `forum-index.css`, `forum-category`, `messenger-*`, `bookmark-toggle` | Tópicos, respostas, paginação e navegação sem overflow horizontal | CSS/SDCs mapeados; teste em purpose pendente |
| Revista/editorial | `revista-post.css`, `editorial-content.css`, carousel, editorial-card | Texto, imagens, metadados, carrossel e controles sem JS | CSS/JS/SDCs mapeados; render e estados pendentes |
| Loja/Commerce | `product.css`, gallery, tabs, cart, shipping, sales panel, product SDCs | Variações, tabelas, checkout, estoque/envios e ACL por loja | Camada de tema mapeada; operação/loja reais pendentes |
| Green Stash (integrado ao Studio) | UI provider/controladores do módulo Commerce e `studio.css` | Produtos, pedidos, estoque, envios, promoções, clientes, relatórios e configurações | Rotas encontradas; capacidades disponíveis precisam ser distinguidas das planejadas |
| Eventos | `event_card`, `event-page-five-regions`, `statistics.css` | Agenda, datas, participantes e layout com cinco regiões | CSS/SDCs identificados; estados/capturas pendentes |
| Messenger/alertas | drawers, compose/item/state, `alerts-dismiss.js` | Drawer acessível, lista, teclado, mensagens e fallback sem JS | Componentes/JS mapeados; acesso e cache pendentes |
| GanjaLab editorial, Galerias e demais abas Marley | Plugins de aba e seus consumers reais | Variação de conteúdo, paginação e formulários owner | Providers/plugins listados; matriz de consumers incompleta |

Esta tabela não é evidência de cobertura homologada. Cada purpose ainda exige
inventário de biblioteca anexada por controller/View/form, estados vazio/erro/
sucesso/carregando e capturas em navegador visível.

### Domínios/purposes estáveis encontrados

O contrato multidomínio e `DomainPurposeManager::DOMAIN_IDS` declaram dez
purposes. A tabela abaixo cruza os dez com superfícies do tema; não implica que
cada purpose carregue só uma categoria de CSS.

| Purpose | Superfícies que entram na regressão | Prioridade de inventário/render |
|---|---|---|
| `main` | Institucional, Fórum público/vitrine de projetos, calendário público, auth e checkout central | Home, busca, shell, header, formulário e carrinho |
| `account` | Conta, Studio inteiro, Perfil editor, Messenger, preferências e área comercial Green Stash | Institution/Domain Header, subnav, formulários, dashboard, dados privados/cache |
| `support` | Apoio/doação, formulários e confirmação | Form API, pagamento, aviso e foco/status |
| `magazine` | Revista pública e conteúdos editoriais | Texto longo, mídia, teaser, autoria, exposed filters |
| `wiki` | Wiki420, verbetes, propostas e revisão | Conteúdo longo, listas, ação de autoria vs moderação, mídia |
| `shop` | Loja pública, catálogo, detalhes, variações, carrinho de origem | Grade de produto, galeria, tabs, preços, filtros e responsividade |
| `courses` | Catálogo/páginas de curso do LMS | Conteúdo educacional, matrículas e cards/listas |
| `forum` | Índice, categorias, tópicos, comentários e anexos | Discussão extensa, controles, mídia, paginação e estado de leitura |
| `links` | Landing e micropáginas de links sociais | Botões, links longos, QR/mídia e reflow em tela estreita |
| `eventos` | Catálogo, detalhes, calendário e agenda | Datas, filtros, mapas, listas, cards e layout de cinco regiões |

Cobertura de runtime ainda pendente para todos os dez; os outros purposes e
rotas encontradas não podem ser omitidos se a associação route/library revelar
consumidores adicionais.

O inventário dos 92 estilos, 12 scripts e 98 templates encontra-se em
[`theme-mobile-file-inventory.tsv`](theme-mobile-file-inventory.tsv); pode ser
regenerado com `python3 scripts/audit-aculta420-responsive-inventory.py --write`.
Ele contém contagens lexicais de media/container/dimensão/overflow por folha de
estilo. Contagens podem incluir declarações sem efeito em um purpose e não
substituem análise de cascata. A enumeração de bibliotecas anexadas
dinamicamente por Views e módulos consumidores continua pendente. As 20
manifestações custom `*.libraries.yml` foram enumeradas em
[`theme-library-inventory.tsv`](theme-library-inventory.tsv), incluindo
dependências e assets. Os 85 call sites de produção custom (80 usos de
`#attached` e cinco de `attach_library()`) estão indexados em
[`theme-library-attachment-sites.tsv`](theme-library-attachment-sites.tsv).
O inventário inclui um arquivo JS de teste por registrar todas as fontes `.js`;
os templates Twig/HTML são incluídos sem tentar resolver condições. Isso não
resolve expressões dinâmicas nem relaciona cada attach à rota/purpose e
consumer; declaração em YAML não prova carregamento em runtime.

## Breakpoints de referência (comparação estática em `0a479dde3`)

| Origem | Breakpoints declarados | Leitura para MF1/MF2 |
|---|---|---|
| OpenCulturas Next 3.1.5 (`opcult.breakpoints.yml`) | até 479 px; 480, 768, 992, 1100 e 1600 px | Evidência da estratégia upstream; os limites servem à inspeção de composição, não como valores a copiar em bloco. |
| ACULTA420 (`aculta420.breakpoints.yml`) | base sem media query; 576, 768, 1100 e 1400 px | Contrato Breakpoint API existente, usado por imagens/galerias; preservar até auditar consumidores e dependências. |
| Drupal Bootstrap 5.0.2 (`composer.lock`) | Infraestrutura da dependência declarada | Não inferir os breakpoints efetivos do CSS carregado só pela versão do tema; inspecionar o build e os estilos anexados por purpose. |

A malha de validação aprovada (320, 360, 390, 599, 600, 768, 1024, 1280 e
1440 px) é mais detalhada que a Breakpoint API e deve continuar medindo os
pontos de transição existentes, inclusive 599/600 e 1024/1280. Esta comparação
não prova que layouts se ajustem nesses tamanhos; cada tela precisa de baseline
e captura Firefox visível antes e depois da migração.


### Maior frequência de media queries encontradas

Recontagem lexical de 77 blocos em 44 folhas de estilo do tema. `@media`
contém também preferências/recursos de acessibilidade, não só breakpoints:

| Query literal | Blocos | Arquivos |
|---|---:|---:|
| `prefers-reduced-motion: reduce` | 13 | 12 |
| `max-width: 575.98px` | 11 | 8 |
| `max-width: 767.98px` | 7 | 6 |
| `min-width: 48rem` | 7 | 6 |
| `min-width: 36rem` | 5 | 5 |
| `min-width: 768px` | 4 | 4 |
| `min-width: 576px` | 3 | 3 |
| `min-width: 992px` | 3 | 2 |
| `min-width: 48.0625rem` | 2 | 2 |
| `min-width: 1100px` | 2 | 2 |

Há outras 20 expressões literais de uso único, incluindo 320–480 px, 992–1200
px, `forced-colors`, `hover` e `prefers-color-scheme`. Sobreposições entre
Bootstrap (575.98/767.98) e limites do tema (576/768/1100), além de `max-width`
e `min-width` misturados, pedem análise de cascade por consumer; contagens não
indicam conflito visual por si só. A saída completa foi conferida diretamente
nos 92 CSS/Sass da worktree `7f073206a` em 2026-10-29.

## Regras para a migração

- Começar com 320 CSS px e estilos mobile como base; media queries devem
  acrescentar capacidade em breakpoints coerentes com Bootstrap, não apenas
  inverter mecanicamente regras atuais.
- Manter semântica e ordem DOM; usar scroll horizontal somente quando o dado for
  intrinsecamente tabular e houver alternativa acessível.
- Auditar CSS/JS próprio e bibliotecas de módulos/views que afetem a aparência;
  não editar módulos de outra frente. Registrar dependências como `Pede-A`.
- Regressão em Fórum, Wiki, Revista, Loja, Eventos, Conta, Studio, GanjaLab e
  todos os purposes adicionais encontrados é parte do gate MF3.
- Comparar claro/escuro, reduced motion, teclado/foco, zoom e larguras 320, 360,
  390, 599, 600, 768, 1024, 1280 e 1440 px; sem Firefox ESR visível, o gate
  permanece `DEFERRED`.

## THEME-MF0 — validadores estáticos executados em `dc8a294a7`

Em 2026-10-29, na worktree da branch `refactor/opencultures-codigo-visual`,
foram executados os validadores do tema após atualizar a base até este head:

- `php scripts/validate-aculta420-sdc-schemas.php`: PASS, 54 componentes, 24
  includes e 734 verificações.
- `php scripts/validate-aculta420-design-foundations.php`: PASS, 2.379
  verificações; contraste do modo escuro e foco reportado acima de 4.5:1.
- `php scripts/validate-module-theme-boundary.php`: PASS, 3.367 verificações;
  zero tokens de apresentação e classes de design nos módulos.

Esses validadores cobrem estrutura SDC, fundações/tokens e fronteira tema-módulo.
Não exercitam CSS computado em rotas, breakpoints, shell renderizado, outros
purposes, teclado, zoom ou conteúdo real; logo MF0 permanece parcial e MF3 não
está aprovado.

### Verificação do inventário de anexação de libraries

No head `5f14c5863`, conferi os 85 registros de
`theme-library-attachment-sites.tsv` contra os arquivos-fonte atuais: 85/85
caminhos/linhas ainda existem e cada linha continua contendo o call site de
anexação declarado. Isso confirma apenas que o índice está referenciando linhas
válidas; chamadas dinâmicas, condições de execução, associação à rota/purpose e
carregamento efetivo no browser permanecem sem comprovação.

## THEME-MF0 — baseline público dos dez purposes

Em 2026-10-29, `purpose-matrix.py` capturou anonimamente as raízes do
`ROUTES-SITEMAP.md` nos nove viewports (320/360/390/599/600/768/1024/1280/1440):
90/90 registros completos e zero casos com `documentScrollWidth` ou
`bodyScrollWidth` maior que o viewport. A Institution Bar apareceu em 90/90;
o seletor de Domain Header `.aculta-header` apareceu somente em MAIN e Wiki
(18/90), ausente nos demais oito purposes. Isso diverge da decisão de preservar
ambos os headers e exige esclarecimento de composition/availability pelo owner;
a Frente B não adicionará um header visual paralelo.

| Purpose | Estado capturado | Limite da evidência |
|---|---|---|
| MAIN | homepage pública renderiza; em 320 px o título “ANTIPROIBICIONISTA” quebra no meio da palavra sem overflow. | Captura somente homepage, sem validar outras páginas/estados. |
| ACCOUNT | raiz redireciona para `/entrar`; Institution Bar presente. | Não cobre Conta, Perfil ou Studio autenticados; Green Stash autorizado permanece fora. |
| SUPPORT | formulário público de valor renderiza. | Não submetido; não valida estados Form API/AJAX/erro. |
| MAGAZINE | título de página presente, corpo visual vazio e rodapé. | Conteúdo editorial não apareceu; layout de listagem/artigo não avaliado. |
| WIKI | homepage Wiki420 renderiza; ambos os headers presentes. | Não cobre autoria/revisão/moderação nem grants. |
| SHOP | vitrine pública de produtos renderiza. | Não cobre variações, carrinho/checkout, estoque ou loja autorizada. |
| COURSES | catálogo inicial renderiza sem H1 no DOM. | Não valida matrículas, turma, LMS privado nem estado sem conteúdo. |
| FORUM | índice público e cartões renderizam; Institution Bar presente. | Não cobre tópicos autenticados, anexos ou Messenger. |
| LINKS | landing social renderiza. | Apenas homepage, sem explorar links externos/QR. |
| EVENTOS | listagem pública de evento/data renderiza. | Não cobre mapa, filtros, calendário, RSVP ou editor. |

As capturas, no Firefox ESR visível, estão fora do Git em
`$SUITE_OUT/aculta-theme-mf0-purposes-20261029/`. O checkout servidor era
`75f049fd1`; os arquivos de tema/Studio/Commerce versionados eram iguais aos
da PR `b75857a5f` (diferenças apenas em documentação), mas os commits ainda
precisam ser comparados no head de uma futura rodada. Este resultado é baseline
público, não prova responsividade semântica, WCAG, zoom, keyboard, modo escuro,
reduced motion, ausência de overflow em componentes não carregados ou gate MF3.

### Pede-A — composição do Domain Header por purpose

| Owner/caminho | Contrato e pergunta técnica | Aceite esperado |
|---|---|---|
| Domain/Portal; composição de `AccountShellBuilder`, tema `templates/layout/page.html.twig` e attachments por purpose | Confirmar por que `.aculta-header` aparece somente em MAIN e Wiki nos oito propósitos públicos restantes, embora Institution Bar esteja presente em todos; confirmar se há intentionally hidden/unavailable state ou composição ausente. | Resposta/patch do owner sem duplicar shell; teste Firefox por purpose confirmando Institution Bar e Domain Header conforme decisão, incluindo ACCOUNT anônimo/autenticado e navegação canônica. |
| Frente A / `aculta_portal`: `src/Presentation/DomainPresentationBuilder.php`, `src/Hook/PortalHooks.php`, contrato `DomainPresentation` e integração de rotas/menus | Toda regra de propósito/rota, disponibilidade, links e dados do Domain Header permanece no Portal; o tema deve somente renderizar o contrato neutro. Hoje `DomainPresentationBuilder` expõe `show_site_header` apenas para Wiki e homepage MAIN e o Twig consome o boolean; confirmar e implementar no Portal a política aprovada para os dez purposes, sem decisão funcional no tema. Usar `DomainPurposeManager`/`aculta_deployer` para hosts, nunca fixar URLs de teste no contrato. | Pede-A: patch do owner Portal, com contrato de apresentação/cache contexts/tags/max-age e testes das dez rotas/purposes; Firefox deve confirmar Institution Bar e Domain Header sem duplicação em test host e durante a migração Alpha para `production_host`. A Frente B não altera rotas, serviços nem regras funcionais do Portal. |

### THEME-MF2 — Institution Bar integrada em todos os dez domains (2026-10-29)

Após `drush cr` no head `4d8993645`, o Firefox ESR visível passou 140/140
verificações integradas: 10 purposes × sete larguras (320/374/375/600/767/768/
1440px), uma Institution Bar por página e zero overflow horizontal. A lista de
hosts foi carregada de `aculta_deployer/config/deploy.json`, incluindo MAGAZINE
(`revista.aculta.toca.net.br`); `INSTITUTION_BAR_DOMAIN_ENV=production` seleciona
os hosts canônicos cadastrados para a migração Alpha. O purpose LINKS oculta
intencionalmente a área utilitária, conforme `DomainPresentation`, e foi medido
sem exigir um menu ausente.

O seletor `.aculta-header` esteve presente somente em MAIN e Wiki (14/70
combinações por purpose/largura; 2/10 propósitos), ausente em Conta, SUPPORT,
Revista, Loja, Cursos, Fórum, Links e Eventos. Isso confirma o Pede-A acima: não
é correção CSS; o owner Portal deve decidir/aplicar o contrato funcional e o
tema só o renderiza. Evidência: `/home/piradopirata/aculta-suite-out/aculta-institution-bar-integrated-all-domains-4d8993645-final/`.
O smoke não cobre rotas internas de cada área, autenticação, zoom, teclado
completo nem WCAG 2.2 AA; THEME-MF3 permanece pendente.

## THEME-MF1 — primeira camada de fundação global

No commit desta alteração, a fundação compartilhada adota gutter de 1rem,
espaçamento vertical compacto e escala H1 fluida desde 320 CSS px. A partir de
48rem, os gutters e espaços principais crescem progressivamente. O container
continua limitado a 80rem; o max-width existente de 1280px foi promovido a
token sem mudar seu valor. As regras preservam a ordem DOM e o layout Bootstrap.

Arquivos alterados: `css/tokens.css`, `css/base.css` e `css/layout.css`. Esta é
uma fundação parcial: componentes específicos ainda precisam ser migrados. A
captura visível pós-alteração da rota Studio Marley foi executada após
reconstruir os caches (`drush cr`): a suíte reportou 29 PASS, 1 SKIP e 0 FAIL,
em 320/390/768/1024/1440px,
claro e escuro. Essa rota não comprova toda a fundação global; MF1 permanece
**em andamento**, MF2/MF3 seguem pendentes e nenhum gate global é declarado.

### THEME-MF2 — primeira família migrada: `content-grid`

`patterns/content-grid` agora usa uma coluna como base, duas colunas a partir de
36rem e até três a partir de 62rem. Assim o padrão de três itens deixa de forçar
três cartões estreitos em telas médias. Consumidores verificados no código:
Marley (`MarleyController`), painel LMS (`LearnerDashboardComposer`) e Wiki
(`WikiContributorWorkspace`/`WikiContributionIntentController`). As três
stories descrevem o reflow esperado. Firefox visível capturou Marley em nove
larguras (320/360/390/599/600/768/1024/1280/1440), sem overflow; screenshots
599/600/768 confirmam visualmente duas colunas para a variante `--2`. Os
consumidores do LMS e Wiki não foram exercitados nesta rodada, e nenhuma rota
real usa `columns: 3` no código encontrado (essa variante está nas stories).
Evidência em `$SUITE_OUT/aculta-ocs-mf2-grid-20261029/`; MF2 segue parcial.

### THEME-MF2 — `content-section`

Removida a query `max-width: 575.98px` que definia `gap: 0.75rem` já garantido
pela base `clamp(0.75rem, 2vw, 1.5rem)`. O cálculo existente não reduz o espaço
abaixo de 0.75rem em nenhuma largura; a alteração remove uma regra duplicada,
mantendo o reflow visual. As cinco stories agora descrevem a base compacta com
progressão fluida. Consumers reais encontrados na Wiki: `WikiProposalSubmissionForm`,
`WikiProposalDecisionForm`, `WikiContributorWorkspace` e
`WikiProposalStatusController`. Schemas SDC e fronteira tema/módulo passaram.
O lint CSS/JS não executou na primeira rodada porque `stylelint` não estava
instalado; depois `npm ci` do lockfile, `npm run lint:css` e `npm run lint:js`
passaram no worktree. Firefox ESR visível testou a área WE SATIVA autenticada e o
workspace Wiki em 34/34 verificações, incluindo 320/360/390/640/768/1024/1280/1440
px sem overflow; PNG/JSON em `/home/piradopirata/suite-out/aculta-mf2-content-section-dd37b35d5/`.
O smoke cobre a tela real do workspace, mas não mede diretamente o gap do SDC;
zoom real, teclado nesse formulário e WCAG completa permanecem sem evidência.

### THEME-MF2 — `author-card`

O SDC já era consumido por cartões públicos de autoria do Fórum, LMS e Wiki.
Migrei a base visual para 2.5rem de avatar, alinhamento inicial e gap compacto;
a partir de 36rem, cartões inline recuperam 3rem e alinhamento central, enquanto
editorial e empilhado preservam seu alinhamento próprio. Variantes `sm`/`lg` e
as regras específicas das listagens do Fórum mantêm precedência. Firefox real
mostrou também overflow de 1px a 576/600px na data da última atividade da
listagem. Removido `white-space: nowrap` para permitir quebra natural em colunas
estreitas; o Firefox ESR confirmou 32/32 checks com consumer presente, alinhamento
flex-start a 320/360/390/575 e central a partir de 576px, e sem overflow em
320/360/390/575/576/600/768/1024/1280/1440. Capturas/JSON em
`/home/piradopirata/suite-out/aculta-theme-author-card-892f803d5/`. O script
reutilizável `tests/browser/aculta-theme-mobile-first/author-card.py` registra
consumer e breakpoint; não cobre teclado, zoom real ou WCAG completa.

### THEME-MF2 — menu responsivo do shell da Conta

O layout do shell passou a usar uma coluna, navegação em sanfona e controles
compactos como base; `min-width: 48rem` restaura a lista lateral desktop, duas
colunas e sticky. O DOM, os atributos de `account-navigation.js` e os dados dos
links não mudaram. Firefox ESR autenticado em `/configuracoes` passou 33/33
checks com a folha candidata injetada, em 320/360/390/599/600/767/768/1024/
1280/1440 px, sem overflow; a Institution Bar apareceu uma vez. O teste não
envia o formulário e não cobre tabelas, teclado completo, A/B, zoom real ou
WCAG. Após publicar e reconstruir caches, a repetição sem override também passou
33/33, confirmando a folha CSS agregada no Runtime. Evidência em
`/home/piradopirata/suite-out/aculta-account-shell-8768825cf/`.

### THEME-MF0 — purpose SUPPORT e SDC `donation-quick` (2026-10-29)

O Firefox ESR visível capturou `https://apoio.aculta.toca.net.br/`, que renderiza
o formulário de apoio e o SDC existente `donation-quick` com cinco cartões.
`tests/browser/aculta-theme-mobile-first/support-form.py` passou 36/36
verificações em 320/360/390/480/481/599/600/768/1024/1280/1440px: consumer e
Institution Bar presentes, cartões dentro da grade e nenhum overflow de
documento. A grade usou 2/3/3/4/4/5/5/6/8/10/10 tracks respectivamente. PNGs e
`results.json` estão em
`/home/piradopirata/aculta-suite-out/aculta-support-mobile-first-20261029b/`.

O markup do formulário corrente não possui `fieldset` ou input de ação; por
isso as regras correspondentes em `css/components/portal-support.css` não foram
validadas com este consumer. A captura confirmou `.aculta-header` ausente no
purpose SUPPORT e a Institution Bar presente. O Pede-A transversal de composição
do Domain Header permanece aberto; não foi criado shell visual paralelo. A
rodada é baseline para o purpose e não valida zoom, teclado, modo escuro,
reduced motion, Form API submetido, checkout nem WCAG completa.

### THEME-MF2 — navegação global do shell (2026-10-29)

As regras de colapso e alvos de toque da navegação foram movidas dos blocos
`max-width` para a base móvel; o menu horizontal e as dimensões inline são
restaurados em `min-width: 1100px`. A coluna entre header e menu mantém gap de
12px até 575px, 32px entre 576–1099px e 16px no desktop. O fallback sem JavaScript
continua com o menu visível e o botão oculto.

Baseline servido e CSS candidato passaram 28/28 verificações no Firefox ESR
visível para a home MAIN, em 13 larguras entre 320 e 1440px: sete links e ambos
os headers, mesmos valores calculados antes/depois, sem overflow, botão abre o
menu em mobile e o fallback permaneceu visível sem JavaScript em 320/768/1099/
1100/1440px. Evidência candidata em
`/home/piradopirata/aculta-suite-out/aculta-navigation-candidate-final2/`.
O teste reutilizável é `tests/browser/aculta-theme-mobile-first/navigation.py`;
ele cobre somente a home MAIN e não substitui regressão nos outros purposes.
Zoom, teclado focado completo, modo escuro, reduced motion e WCAG 2.2 AA seguem
pendentes.

### THEME-MF2 — Institution Bar global (2026-10-29)

A geometria compacta passa a ser a base mobile; `min-width: 768px` restaura as
dimensões desktop. A regra foi estendida ao `<ul class="nav flex-row">` realmente
renderizado no menu de conta, além da variante `.navbar-nav`; nenhum Twig ou JS
foi alterado. O baseline servido e o CSS candidato passaram 84/84 verificações
cada no Firefox ESR visível: seis páginas (MAIN, Conta, SUPPORT, Wiki, Loja,
Fórum), sete larguras (320/374/375/600/767/768/1440px), Institution Bar única e
sem overflow. Evidências em
`/home/piradopirata/aculta-suite-out/aculta-institution-bar-{baseline,candidate}/`.
O Domain Header não aparece em SUPPORT, Conta, Loja ou Fórum nestas rotas; essa
lacuna permanece Pede-A. Este slice não valida outros caminhos por purpose, zoom,
teclado completo, modo escuro, reduced motion ou WCAG 2.2 AA e não aprova MF3.
Teste reutilizável: `tests/browser/aculta-theme-mobile-first/institution-bar.py`.

### THEME-MF2 — deslocamento da Institution Bar com Navigation top-bar

O Firefox ESR visível reproduziu o problema autenticado: a top-bar do módulo
Navigation permanece em fluxo normal abaixo de 64rem, mas o `data-offset-top`
vazio fazia `Drupal.displace` tratar sua altura e posição documental como um
obstáculo fixo. O deslocamento observado era 185,1px em 320px, 142px em 390px
e 100px em 1023px; em 1024px e acima, a top-bar é fixa e o deslocamento correto
é sua altura de 64px. O tema agora zera o atributo no layout móvel e restaura o
cálculo nativo no desktop, inclusive quando se cruza o breakpoint sem recarga.
Sem JavaScript, o CSS mantém 4rem de separação no desktop.

Firefox ESR visível com CSS/JS candidato injetados passou 38/38 verificações
autenticadas em 320/390/1023/1024/1280px, incluindo resize e scroll. O fallback
CSS com JavaScript desativado passou 38/38. Homes anônimas dos dez purposes
passaram 99/99 verificações em 320/390px; não houve reprodução anônima nessas
homes, o que não exclui rotas internas. Evidências externas ao repositório:
`/home/piradopirata/aculta-suite-out/aculta-navigation-top-bar-candidate-c3b697e10-final2/`,
`/home/piradopirata/aculta-suite-out/aculta-navigation-top-bar-nojs-candidate-c3b697e10/`
e `/home/piradopirata/aculta-suite-out/aculta-institution-bar-scroll-all-anon-c3b697e10/`.
Os testes usaram a página servida pelo head `c3b697e10` e injetaram os arquivos
candidatos da worktree; validação integrada servida após cache rebuild é
pendente. Esta correção pontual não aprova THEME-MF3 nem os gates completos de
teclado, zoom, tema escuro, reduced motion, leitores de tela ou WCAG 2.2 AA.
Teste reutilizável: `tests/browser/aculta-theme-mobile-first/institution-bar-scroll.py`.

### THEME-MF2 — sticky e deslocamento no HEAD servido `487596bb0`

No Firefox ESR visível 153.5.1esr/geckodriver 0.37.1, o CSS servido passou
137/137 verificações, sem folhas candidatas injetadas. Anônimo: 99/99 nas dez
homes (MAIN, Conta, SUPPORT, Revista, Wiki, Loja, Cursos, Fórum, Links e
Eventos), larguras 320/390px, estabilização sem salto e Institution Bar
`position: sticky` em `top: 0` durante scroll; nenhuma Toolbar administrativa.
UID administrativo: 38/38 na MAIN em 320/390/1023/1024/1280px. Em mobile a
barra acompanha a top-bar administrativa enquanto ela ainda ocupa a viewport e
assume o topo quando a top-bar rola para fora; a mudança 1023→1024px preserva o
offset esperado (0 na top-bar em fluxo; 64px quando fixa), inclusive ao
redimensionar sem recarga. `maxTopChange` foi zero nos carregamentos medidos.

Evidência: 15 PNGs anônimos e `results.json` em
`/home/piradopirata/aculta-suite-out/institution-bar-scroll-anon-487596bb0/`;
`results.json` admin em
`/home/piradopirata/aculta-suite-out/institution-bar-scroll-admin-487596bb0/`.
Esta rodada confirma o comportamento focal servido, mas não exercita outras
rotas internas autenticadas, teclado completo, zoom real, contraste, dark mode,
reduced motion ou WCAG 2.2 AA; portanto THEME-MF3 continua pendente.


### THEME-MF2 — tabela de pedidos na Conta (2026-10-29)

A tabela da Conta passa a usar o empilhamento como regra-base e restaura a
semântica de tabela a partir de `48rem`. A restauração mantém largura, padding,
bordas e alinhamento Bootstrap medidos antes da mudança; os rótulos continuam
como `data-label` nas células. No Firefox ESR visível, a rota real `/pedidos` e
um fixture sintético sem dados pessoais passaram 22 verificações em
320/360/390/599/600/767/768/1024/1280/1440px, com zero overflow. O caso real
mostrou o estado vazio: a conta de teste só tem pedidos em rascunho, por isso a
linha real com dados e seus atributos não foi exercitada (DEFERRED). O preprocess
de tabela do Drupal converte as chaves de células de `#rows` em atributos HTML;
a hipótese anterior de que `data-label` era descartado foi refutada e não gerou
Pede-A. Evidência:
`/home/piradopirata/aculta-suite-out/account-orders-868f18e22-final2/summary.json`.
Após publicar `11eb83644` e reconstruir cache no Runtime, a folha servida sem
override repetiu 22 PASS/0 FAIL e uma DEFERRED nas mesmas larguras. `updatedb:status`
sem updates; `config:status` ainda divergente (377 linhas), sem import. Snapshot
e gates do Runtime estão em `docs/operations/ACULTA-CONTINUOUS-EXECUTION.md`.
Esta rodada integrada também não aprova THEME-MF3. Teste reutilizável:
`tests/browser/aculta-theme-mobile-first/account-orders.py`.

### THEME-MF2 — autenticação pública (2026-10-29)

`auth.css` declara padding compacto para cabeçalho e formulário como base e
restaura o painel amplo em `min-width: 36rem`, preservando os mesmos valores
computados em cada lado do breakpoint. A fixture
`tests/browser/aculta-theme-mobile-first/auth-layout.py` foi criada para
comparar candidato/baseline em 11 larguras; a execução Firefox está DEFERRED
enquanto sessões da Revista mantêm geckodriver e memória ocupados. Rotas reais
e páginas servidas após cache rebuild também permanecem pendentes.

### THEME-MF2 — carrossel editorial da home MAIN (2026-10-29)

`editorial-carousel.css` agora usa o limite compacto de 390px e itens fluidos
como base; `min-width: 48.0625rem` restaura o limite de 764px e itens de até
370px, preservando a transição original em 768px. O consumer é a View de
Destaques editoriais da home MAIN. Foi criado
`tests/browser/aculta-theme-mobile-first/editorial-carousel-layout.py` para
comparar geometria e capturas baseline/candidato em 12 larguras; Firefox e a
página servida estão DEFERRED pelas sessões concorrentes visíveis. THEME-MF3
segue pendente.

### THEME-MF2 — leitor PDF de Media (2026-10-29)

`pdf-viewer.css` agora usa gap/padding compactos, controles flexíveis e busca em
linha própria como base. `min-width: 40.0001rem` restaura a superfície ampla,
controle não expansível, busca de 14rem e o espaço de palco original; no modo
fullscreen também restaura padding e max-height. O smoke reutilizável
`tests/browser/aculta-document-viewer/visual.py` foi ampliado para 1280/768/641/
640/390/320 px e aceita `PDF_VIEWER_CSS_OVERRIDE=1` para injetar a folha
candidata. A execução Firefox e a página Runtime estão DEFERRED enquanto as
sessões visíveis da Revista ocupam o browser. O smoke não ativa fullscreen; esse
estado precisa de revisão posterior. THEME-MF3 não aprovado.

### THEME-MF2 — auxiliares Likert de Webform (2026-10-29)

A ajuda e descrição Likert recebem `display` mobile como base; acima de
768 CSS px o tema volta a ocultar ambos, preservando o conteúdo desktop. O
modal de confirmação mantém a exceção `max-width: 650px`: a largura de desktop é
calculada pelo consumidor jQuery UI/Webform a partir de opções dinâmicas
`width`/`minWidth`, portanto o tema não impõe um valor fixo. O harness
`tests/browser/aculta-theme-mobile-first/webform-layout.py` compara fixture,
geometria e capturas em 14 larguras, incluindo 650/651 e 768/769. Firefox e
rotas Webform reais estão DEFERRED enquanto sessões concorrentes ocupam o
browser. THEME-MF3 segue pendente.

### THEME-MF2 — gavetas utilitárias compartilhadas (2026-10-29)

`portal-cart.css` agora declara `--bs-offcanvas-width: 100vw` como base, cobrindo
Messenger, alertas e carrinho; `min-width: 35.0625rem` restaura o limite anterior
de 420px acima de 560 CSS px. O smoke real read-only
`tests/browser/aculta-messenger/conversation-list-readonly.mjs` aceita injeção
explícita da folha candidata (`SUITE_CSS_OVERRIDE=1`) e verifica a largura do
consumer Messenger, incluindo a fronteira 560/561 conforme o viewport efetivo.
A execução Firefox está DEFERRED por sessões concorrentes; os consumers de
Alertas e Carrinho seguem requerendo revisão visual própria. THEME-MF3 permanece
pendente.
