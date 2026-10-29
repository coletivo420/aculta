# Auditoria mobile-first ACULTA420 — THEME-MF0

**Estado em 2026-10-29: inventário estático parcial, sem implementação ou aceite.**
A auditoria original foi registrada no checkout servidor `ded8a6181`. Em
2026-10-29, a recontagem foi executada na branch atualizada
`0a479dde3` em `/home/piradopirata/worktrees/opencultures-f0-adm8-e8`; a
varredura abaixo cobre os arquivos atuais do tema, mas não altera a evidência
de runtime. Nenhuma escrita persistente foi executada no Runtime.

## Escopo quantificado

Varredura recursiva de `web/themes/custom/aculta420`, excluindo `node_modules`
e `vendor`: 537 arquivos, 92 CSS/Sass, 11 JavaScript e 98 templates Twig/HTML.
A recontagem literal encontrou 53 arquivos com `@media` ou expressão de
`width`: 44 arquivos com `@media` (78 blocos), 50 com matches de largura
(146 matches) e 22 com `overflow` (39 declarações); zero `@container`. Os 146
matches incluem condições de breakpoints `min-width`/`max-width`, portanto não
são 146 declarações de dimensão fixa. A busca aceita valores `px`, `rem`, `em`
ou `vw` e é lexical, sem análise de cascata ou efeito calculado.
O inventário é estrutural; não mede overflow nem prova comportamento renderizado.

| Camada | Arquivos identificados | Observação da auditoria |
|---|---|---|
| Fundação | `css/base.css`, `css/layout.css`, `css/tokens.css`, `css/drupal-bootstrap.css` | `base.css`/`layout.css` quase sem media queries; tokens têm preferência de tema; Bootstrap injeta regras próprias. |
| Shell global | `css/components/header.css`, `navigation.css`, `institution-bar.css`, `breadcrumb.css`, `footer.css`, `institutional.css` | Afeta todos os purposes; Institution Bar e Domain Header são invariantes. |
| Componentes compartilhados | 41 folhas em `css/components/` e 45 folhas CSS de SDC | Mistura estilos sem breakpoint e media queries; os consumidores reais precisam ser correlacionados antes da migração. |
| Áreas de produto | account/portal, fórum/discussion, wiki, editorial/revista, product/cart/sales, eventos, messenger, mídia/documentos, doações e suporte | Não podem ser excluídas por ausência de rota no Studio. |
| JavaScript | 10 scripts de apresentação mais um teste JS | Auditar progressive enhancement, estado de navegação, carrosséis/slideshows, drawer Messenger, calculadora de envio, doação e notificações. |
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

O inventário reproduzível dos 92 estilos, 11 scripts e 98 templates encontra-se
em [`theme-mobile-file-inventory.tsv`](theme-mobile-file-inventory.tsv), com
contagens literais e expressões de media/container/largura/overflow por folha de
estilo. Contagens podem incluir declarações sem efeito em um purpose e não
substituem análise de cascata. A enumeração de bibliotecas anexadas
dinamicamente por Views e módulos consumidores continua pendente. As 20
manifestações custom `*.libraries.yml` foram enumeradas em
[`theme-library-inventory.tsv`](theme-library-inventory.tsv), incluindo
dependências e assets. Os 85 call sites de produção custom (80 usos de
`#attached` e cinco de `attach_library()`) estão indexados em
[`theme-library-attachment-sites.tsv`](theme-library-attachment-sites.tsv).
Testes e documentação foram excluídos da contagem. Isso não resolve expressões
dinâmicas nem relaciona cada attach à rota/purpose e consumer; declaração em
YAML não prova carregamento em runtime.

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

Recontagem lexical de 78 blocos em 44 folhas de estilo do tema. `@media`
contém também preferências/recursos de acessibilidade, não só breakpoints:

| Query literal | Blocos | Arquivos |
|---|---:|---:|
| `max-width: 575.98px` | 18 | 15 |
| `prefers-reduced-motion: reduce` | 13 | 12 |
| `max-width: 767.98px` | 12 | 8 |
| `min-width: 48rem` | 3 | 3 |
| `max-width: 768px` | 3 | 3 |
| `min-width: 992px` | 3 | 2 |
| `max-width: 1099.98px` | 3 | 2 |
| `min-width: 768px` | 3 | 3 |
| `min-width: 576px` | 2 | 2 |
| `min-width: 1100px` | 2 | 2 |

Há outras 15 expressões literais de uso único, incluindo 320–480 px, 992–1200
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
