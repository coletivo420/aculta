# Auditoria mobile-first ACULTA420 — THEME-MF0

**Estado em HEAD `1a4de33b6` (2026-10-30): inventário lexical regenerado; THEME-MF0 continua parcial, MF2 tem migrações incrementais e THEME-MF3 não está aprovado.**
A auditoria inicial foi registrada no checkout servidor `ded8a6181`. Esta
recontagem foi executada na branch `refactor/opencultures-codigo-visual` em
`/home/piradopirata/worktrees/opencultures-f0-adm8-e8`; ela cobre fontes do
tema, não altera a evidência de páginas servidas. Na rodada inicial não houve
escrita no Runtime. O incidente posterior, incluindo o snapshot e a correção
direcionada de `message.settings`, está registrado em
`docs/operations/ACULTA-CONTINUOUS-EXECUTION.md`.

### THEME-MF0 — recontagem lexical em HEAD `1a4de33b6` (2026-10-30)

O gerador oficial foi executado com `--write` e repetido sem escrita; a segunda
saída corresponde byte a byte a `theme-mobile-file-inventory.tsv`. São 206
fontes rastreadas: 93 CSS/Sass, 12 JavaScript e 101 Twig/HTML. O índice aponta
84 blocos `@media` em 46 folhas, zero `@container`, 636 correspondências
lexicais de dimensões em 75 folhas e 53 declarações de overflow em 28 folhas.
As três novas media queries em `css/tokens.css` usam `min-width` e limitam os
tokens de fidelidade a `.studio` e `.aculta-profile-editor`; não alteram a
composição global. Esses números são lexicais, incluem possíveis comentários e
não medem cascata, uso efetivo ou falhas de layout. A enumeração completa de
consumidores por rota/purpose e o gate THEME-MF3 continuam pendentes.

### Atualização THEME-MF0/OCS-F0 — shell multidomínio em todas as raízes (2026-10-30)

A evidência posterior substitui os registros abaixo que diziam que o Domain
Header aparecia somente em MAIN/Wiki ou que aguardava deploy para validação.
No Runtime servido pelo docroot em `44946347d`, Firefox ESR visível capturou
as dez raízes públicas/anônimas em nove viewports (90/90). A Institution Bar e
o Domain Header apareceram **exatamente uma vez em cada captura (90/90 cada)**;
nenhuma captura teve overflow horizontal medido ou título/texto indicador de
HTTP 500. O antigo Pede-A para habilitar o header nos demais purposes fica
resolvido no ambiente de desenvolvimento; não foi testado hostname de produção
ou Alpha. A suíte mede somente raízes e viewport CSS, sem rotas internas,
sessões autenticadas, zoom, teclado, WCAG nem estados claro/escuro. Artefatos:
`/home/piradopirata/aculta-suite-out/domain-header-purpose-matrix-20261030/`.
Isso é evidência parcial de MF0/MF2 e não aprova THEME-MF3 ou OCS-F11.

### THEME-MF2 — Domain Header global para dez purposes (2026-10-30)

O Portal agora marca `show_site_header` como verdadeiro para todos os dez
purposes com Domain configurado; o mesmo template do tema apresenta o cabeçalho
antes da página de Links e das demais páginas. Os títulos e URLs seguem o
contrato `DomainPresentation` e `DomainPurposeManager`; não há hostname nem
decisão funcional no tema. Teste Kernel candidato: dez purposes, incluindo
Magazine, Eventos e Links, com home URL e cache tag do Domain configurado.
Unit/Kernel passou 2/74; PHPCS Drupal/DrupalPractice e PHP lint passaram. Firefox contra o
checkout servido e checagem de não duplicação nas dez raízes/páginas internas
estão DEFERRED até a branch/HEAD da PR ser servida. Isso não aprova THEME-MF3.

### THEME-MF2 — compatibilidade do dropdown na biblioteca SDC (2026-10-30)

A rota administrativa `/painel-administrativo/aparencia/ui/components` lançou
`TypeError` ao renderizar `bootstrap:dropdown`: o template do tema-base chamava
`block(dropdown_menu)` e entregava o render array do slot como nome de bloco.
ACULTA420 agora substitui o componente por `aculta420:dropdown` via `replaces`,
com o mesmo contrato de props/slots e uma condição que trata o slot como valor;
story inclui menu via slot. Não foi alterado código Bootstrap contrib.

Validação candidata: gate SDC 55 componentes/756 checks PASS; compatibilidade
com o schema upstream conferida por `SchemaCompatibilityChecker` PASS; Twig
isolado renderiza o slot PASS; Design Foundations 2452 PASS. O componente ainda
não foi servido pelo checkout Runtime; a rota administrativa precisa de reteste
após publicação/rebuild. O Foundation Drupal executado pelo checkout servido
não descobriu `aculta420:dropdown`, pois o Runtime ainda usa o código antigo;
esse resultado é `DEFERRED` para integração candidata, não aprovação nem falha
do schema estático. Não declara OCS-F3/F11 nem THEME-MF3.

### Estado integrado dos gates (2026-10-30)

Na auditoria inicial, Firefox ESR ficou `DEFERRED` porque `DISPLAY` estava
indisponível. Retestes focais de Fórum, Wiki e shell foram registrados nas
seções abaixo, mas não homologam o tema inteiro. No HEAD `81b275163`, a
fronteira tema/módulo passou em 3427 verificações, sem tokens de apresentação
nem classes de design no módulo. Os validadores de slugs (393 rotas), aliases
(35 arquivos), mapa módulo/tema (209 checks), Portal Drupal 11+ (1188 checks),
Foundation (349 checks) e shell contract (11 checks) também passaram. Lint
CSS/JS, SDC (54 componentes/745 checks) e Design Foundations (2445 checks)
passaram; Unit focal: 36 testes/389 assertions; Kernel Wiki focal: 1/69.

O Foundation e o shell contract foram executados por Drush no checkout servidor
`d56343a6d`, usando os scripts e arquivos do worktree `81b275163`; são gates
read-only e não significam que esse HEAD foi publicado ou servido. A fronteira
foi corrigida movendo classes visuais de render arrays de Eventos para tokens
`data-portal-ui` mapeados no tema, preservando `use-ajax`. O gate Drupal 11 foi
ajustado para contar o hook legítimo de endereço do Portal e continuar
reprovando hook de gateway Mercado Pago no Portal. Esta evidência não substitui
Firefox visível, a matriz global, zoom, WCAG ou a validação de páginas servidas;
THEME-MF3 permanece pendente.

### THEME-MF2 — marca da Institution Bar na base móvel (2026-10-30)

A captura de `/noticias` em 320px mostrou a marca da Institution Bar ausente:
`institution-bar.css` a escondia abaixo de 375px assumindo que o Domain Header
sempre fornecia marca equivalente. Essa suposição falha na Magazine e em outras
rotas sem `.aculta-header`. A base agora mantém a marca institucional de 56×19px
visível e permite que a barra/utilitários quebrem em linhas; a partir de 768px
mantém as dimensões de 78×26px. DOM, links, sticky e ownership Portal não mudam.

Firefox ESR visível testou a CSS candidata nos dez purposes e em
320/374/375/600/767/768/1440px: 147/147 verificações passaram, marca visível em
todas as larguras, Institution Bar única e sem overflow horizontal. 320/768/
1440 foram capturados para MAIN, SUPPORT e MAGAZINE; MAIN e SUPPORT foram
inspecionados visualmente; Magazine/320 também foi inspecionada e confirma a
marca sem Domain Header. Evidência em
`/home/piradopirata/aculta-suite-out/institution-bar-brand-mobile-20261030-final/`.
É teste de folha candidata injetada sobre o Runtime, não rebuild do stylesheet
servido nem homologação THEME-MF3. A ausência do Domain Header em vários
purposes foi uma observação anterior; a presença nas dez raízes está resolvida
e confirmada no reteste 90/90 do HEAD `9ad2b86b9` registrado abaixo.

O harness de sticky/scroll foi repetido com a CSS candidata em Firefox ESR nos
dez purposes, anônimo, 320/390px: 101/101 checks passaram, `maxTopChange=0`,
barra fixa no topo após scroll e zero overflow. O cenário administrativo foi
tentado com ULI, mas a autenticação não se confirmou (`recuperar-acesso/1`, sem
toolbar); geometria admin fica `DEFERRED`, sem reprovação visual. A snapshot de
`aculta_runtime` anterior ao teste ficou fora do Git em
`/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-institution-bar-admin-20261030.sql`
(SHA-256 `e6f03b9256a37cca57a83bee3de68251c3071784eca273c230b406a3b5b76dec`).
Evidência anônima em
`/home/piradopirata/aculta-suite-out/institution-bar-brand-scroll-mobile-20261030/`.

### THEME-MF2 — SDC rail no catálogo real de Cursos (2026-10-30)

O `views-view-unformatted--courses-catalog.html.twig` consome `aculta420:rail`
com cursos reais publicados. Firefox ESR verificou 320/390/768/1440px: 11/11
checks passaram, incluindo região nomeada/focável, três itens, trilha rolável
em 320px, ArrowRight aumentando `scrollLeft` e ausência de overflow da página.
1440px mostra os três cards sem rolagem horizontal; as capturas 320/1440 e
320 após a seta foram inspecionadas em
`/home/piradopirata/aculta-suite-out/aculta-courses-rail-20261030-final/`.
É navegação horizontal interna deliberada, sem JavaScript; não substitui teste
de leitor de tela, zoom ou THEME-MF3. Harness reutilizável:
`tests/browser/aculta-theme-mobile-first/rail-courses.py`.

### THEME-MF2 — rótulo de data do cartão Eventos (2026-10-30)

A captura real de `/eventos` em 320px mostrou “Próxima data:” duplicado. O
controller entrega uma linha localizada com prefixo e data; o adaptador visual
do tema encaminhava esse texto completo para a prop `proxima`, embora o contrato
do SDC seja data simples e o Twig já forneça o rótulo. `PortalUiHooks` agora
extrai o valor `dd/mm/aaaa HH:mm` no adaptador do tema, sem alterar o controller,
ACL, cache, timezone ou regras do módulo. PHP lint e Unit do hook passaram
(15 testes/97 assertions); Runtime visual da correção fica `DEFERRED` até servir
este commit e reconstruir o cache. A evidência baseline está em
`/home/piradopirata/aculta-suite-out/aculta-purposes-mobile-baseline-20261030-3c1691fc5/eventos-320.png`.

A moldura dupla do cartão também foi corrigida na camada visual: o `li`
`list-group-item` do `item_list` é agora transparente e sem border/padding, e o
SDC mantém sua única borda. O Firefox injetou a folha candidata no consumer real
em dez larguras (320–1440px): 20/20 checks passaram, três cards presentes e sem
overflow. As capturas 320/1440 foram inspecionadas e confirmam a moldura única;
Runtime CSS continua dependente de servir a folha do commit. Harness e capturas
em `/home/piradopirata/aculta-suite-out/aculta-events-layout-20261030/`.

### THEME-MF2 — layout interno do Studio (2026-10-29)

`studio.css` passa a empilhar sidebar e conteúdo como base; `min-width: 48rem`
restaura a composição lateral e a largura de 14rem anterior. Shell global,
markup, links, AJAX e ACL não mudam. O smoke visual do Studio aceita injeção da
folha candidata via `STUDIO_CSS_OVERRIDE` e mede direção, dimensões e overflow
em 320/390/767/768/1024/1440px. Stylelint e sintaxe JS PASS; execução
Firefox/Runtime DEFERRED por sessões concorrentes. Não homologa OCS-F11 nem
THEME-MF3.

### THEME-MF3 — foco de teclado nas raízes públicas (2026-10-30)

`purpose-shell-keyboard.py` percorreu as dez raízes de purpose no Firefox ESR
visível, anônimo, em 320/390/768/1280 px. O relatório contém 663 checks PASS,
uma falha e dez SKIP: 624 paradas de foco do tema exibiram indicador visível
(incluindo o outline do container `:focus-within` nos títulos dos cards LMS), e
39/40 telas não tiveram overflow horizontal. A única falha foi Conta/320 px,
`documentWidth=328`. Quatro SKIPs são foco delegado ao Turnstile externo e seis
são fins de sequências de foco curtas; não foram contados como PASS.

Capturas de primeiro foco em Conta e Cursos a 320 px foram inspecionadas;
relatório e capturas estão em
`/home/piradopirata/aculta-suite-out/purpose-shell-keyboard-accepted-20261030/`.
Nenhum link foi ativado nem formulário submetido. O teste cobre apenas raízes e
até oito paradas em cada direção; não testa menus expandidos, páginas internas,
contraste de foco ou leitor de tela. A validação interna do widget de terceiro
permanece externa ao documento ACULTA. O overflow da Conta, o CAPTCHA, WCAG 2.2
AA e os demais gates transversais mantêm THEME-MF3 pendente.

### THEME-MF2 — busca e linhas de tópicos do Fórum (2026-10-29)

`forum-index.css` usa uma coluna como base na linha de tópico e no formulário
exposto de busca. `min-width: 36rem` restaura duas colunas para os metadados e
busca; `min-width: 62rem` amplia a busca para três trilhas. Ordem DOM e conteúdo
não mudam.

`forum-index-visual.mjs` injeta a CSS candidata e mede uma linha sintética
somente no DOM mais categorias reais em 320/360/390/575/576/600/767/768/1024/
1280/1440px. `search-visual.mjs` injeta a mesma folha e mede a busca real nas
fronteiras 575/576 e 991/992, além das larguras de regressão. Stylelint e
sintaxe JS PASS. O Firefox ESR público da busca passou 38/38 verificações em
320/360/390/575/576/600/767/768/991/992/1024/1280px; light/dark em 390/1024,
contraste do botão acima de 12:1 e CSS candidata sem overflow. A suíte detectou
que o media query de 576px sobrescrevia a grade de 3 colunas de 992px por ordem
na cascata; os blocos foram reordenados e o reteste passou. Capturas e
`results.json`: `/home/piradopirata/aculta-suite-out/theme-mf2-forum-search-final-20261030/`.
A linha sintética do índice ainda não substitui a inspeção de tópico real; Wiki,
Fórum e demais purposes não estão homologados globalmente e MF3 permanece
pendente.

O Firefox ESR visível também passou **62/62 verificações** na View pública de
categorias com 12 cartões reais, em 320/360/390/575/576/600/767/768/1024/1280/
1440px: reflow, ausência de overflow, link de salto/teclado, e capturas claro/
escuro. A linha de tópico e a ação da listagem foram fixtures somente no DOM;
não se enviou conteúdo. Capturas e `results.json`:
`/home/piradopirata/aculta-suite-out/theme-mf2-forum-index-20261030/`. O tema
servido no Runtime não foi reconstruído; a barra multidomínio nessa captura
reflete os assets servidos e não integra a CSS candidata do índice. A validação
completa de tópico real e shell permanece pendente.

As ações do cabeçalho da View de tópicos também foram migradas da media query
com limite superior para aprimoramento progressivo: dimensões compactas a
partir de `36rem` e dimensões originais restauradas em `62rem`. Os mesmos
valores permanecem em 320–575, 576–991 e 992px ou mais. O harness agora mede o
consumer por fixture DOM nas onze larguras; Firefox/runtime continuam DEFERRED
nesta sessão, sem alterar o status global MF3.

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

O consumer público de verbete também foi migrado: `wiki-entry.css` usa uma
coluna como base e posiciona o aside antes do texto; `min-width: 48rem` restaura
a composição de duas colunas. `tests/browser/aculta-wiki/visual.py` injeta as
duas folhas candidatas e mede colunas/posição do aside na fronteira 767/768px.
O espaçamento inferior das abas também usa `1rem` como base; em `48rem` restaura
`1.25rem`, com o mesmo smoke medindo o valor calculado no verbete.
Lint CSS e sintaxe Python PASS; Firefox/Runtime DEFERRED por `DISPLAY` ausente e
sessões geckodriver concorrentes. MF3 segue pendente.

#### Reteste THEME-MF2 — Firefox ESR público (2026-10-30)

Com `WIKI_CSS_OVERRIDE=1`, Firefox ESR visível mediu as quatro rotas públicas
(home, listagem, busca e verbete canônico) em 320/360/390/575/576/600/640/767/
768/1024/1280/1440px: **302/302 verificações PASS**, sem overflow, erro visível
ou regressão nos breakpoints de 576 e 768px. No verbete, o aside fica antes do
texto abaixo de 768px e volta à grade de duas colunas em 768px. Capturas de
320px e 1440px foram inspecionadas. Evidência: `/home/piradopirata/aculta-suite-out/theme-mf2-wiki-visual-20261030-r2/`.

Com JavaScript desativado, home/listagem/busca/verbete passaram 16/16 checks,
incluindo um H1, resultados e links renderizados no servidor. A fixture do
behavior de prévia foi alinhada ao contrato atual do Portal e passou 14/14
checks de role, foco, região viva e links por teclado em 320/1280px.
Evidências e logs: `/home/piradopirata/aculta-suite-out/theme-mf2-wiki-no-js-final-20261030/`,
`/home/piradopirata/aculta-suite-out/theme-mf2-wiki-keyboard-candidate-final-20261030/`
e `/home/piradopirata/aculta-suite-out/theme-mf2-wiki-keyboard-live-final-20261030/`.

A integração de teclado na página pública **não foi aprovada**: o HTML atualmente
servido pelo Runtime ainda traz a classe antiga da busca e não inclui
`data-portal-ui="search-preview-input"`, embora o builder desta branch use esse
atributo para anexar o behavior. A rodada pública marcou 36/40: os dois checks
de prévia falharam em cada largura (1280 e 320px), enquanto foco visível e
alcance do campo passaram; o harness agora registra o estado e segue sem abortar.
Os checks de integração continuam DEFERRED até alinhar e repetir o Runtime; não
alterei o módulo Portal nesta microfase visual. O smoke sem JavaScript e a
fixture sintética não substituem essa integração nem os gates de acessibilidade
completos. THEME-MF3 permanece pendente.

### THEME-MF2 — layout SDC page-columns

Todas as variantes do layout empilham como base; as variantes duas/três colunas
são restauradas a partir de `48rem`. `tests/browser/aculta-theme-mobile-first/
page-columns.py` compara baseline/candidata em dez larguras e preserva DOM e
ordem. O SDC ainda não tem consumer de produto. Lint CSS, schema SDC e sintaxe
Python passam; Firefox visível DEFERRED por `DISPLAY` ausente e sessões
concorrentes. MF3 segue pendente.

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
status PASS. A validação Firefox e formulário real em Fórum, Wiki e Revista
ficaram então DEFERRED por sessões concorrentes; MF3 não aprovado.

#### Reteste THEME-MF2 — Firefox ESR visível (2026-10-30)

No host de teste público do Fórum, o harness adicionou somente seis mensagens
sintéticas ao DOM, sem autenticação, submissão ou gravação. A primeira execução
reproduziu overflow a 576px ao ativar as sete trilhas sticky: os rótulos e gaps
excediam o espaço interno. `discussion.css` agora permite encolher a barra,
usa gaps e padding compacto entre 576–767px, preserva controles com altura de
44px e restaura o padding amplo em 768px. O ajuste não muda DOM, lógica ou
contrato do Portal.

Firefox ESR visível com `DISCUSSION_CSS_OVERRIDE=1`: 35/35 verificações PASS em
320/360/390/500/575/576/900px; sem overflow incremental, com áreas e sticky
esperados, interação por Enter/setas/botões, anúncio de estado, ação Não lidas,
contraste claro/escuro e alvo de toque de 44px nas larguras móveis medidas.
Evidências privadas: `/home/piradopirata/aculta-suite-out/theme-mf2-discussion-progress-check3-20261030/`.
Esta evidência cobre o consumer de Fórum com DOM sintético; Wiki, Revista,
assistive technology e MF3 global continuam pendentes.

### THEME-MF2 — rodapé global (2026-10-29)

`footer.css` declara a grade em uma coluna como base móvel. A partir de
`36rem` (576 CSS px), a grade passa a duas colunas; a partir de `62rem`
(992 CSS px), restaura a composição anterior de quatro colunas com a coluna da
marca em proporção dupla. A ordem DOM, os blocos e a tipografia não mudam.
`npm run lint:css` e validações estáticas são executados. Em 2026-10-30, o
Firefox ESR visível verificou os dez purposes públicos em 13 larguras de 320 a
1440px, incluindo os limites de 576 e 992px. Os nove rodapés comuns renderizados
pelo servidor tiveram 1/2/4 colunas, alvos de navegação de pelo menos 44px e
nenhum overflow; Institution Bar e Domain Header apareceram uma vez. No LINKS,
o Presenter do Portal envia links via LinkGenerator, que não executa o hook
`preprocess_link`; por isso seus tokens permaneciam no HTML sem classes. A
folha candidata do SDC aplica esses tokens semânticos diretamente aos estilos
do canal/ícone. Com `FOOTER_CSS_OVERRIDE=1`, os links e ícones do footer passaram
na medição de altura/alvo e não houve overflow. Execução 260/260; evidência em
`/home/piradopirata/aculta-suite-out/footer-layout-20261030-candidate4/`.
É uma validação de layout público com CSS candidato apenas no LINKS; não cobre
páginas internas, teclado, zoom, contraste/WCAG ou THEME-MF3.

### THEME-MF2 — Institution Bar abaixo de 375px (histórico; revertido em 2026-10-30)

Naquele estado, a marca ficava oculta visualmente abaixo de 375px e reaparecia
em `min-width: 23.4375rem`. A regra foi revertida porque Magazine e outras rotas
podem não apresentar Domain Header. O registro atualizado está na seção
“marca da Institution Bar na base móvel” acima; mantenha esta evidência somente
como histórico, não como contrato vigente.

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

O inventário foi regenerado em 2026-10-30 por
`scripts/audit-aculta420-responsive-inventory.py`. O TSV contém 206 arquivos-
fonte: 93 folhas CSS/Sass, 12 scripts JavaScript (11 de produção e um de teste)
e 101 templates Twig/HTML; não inclui documentação ou demais ativos. A busca
lexical atual encontra `@media` em 46 folhas (81 blocos), zero `@container`,
expressões de dimensão em 74 folhas (633 matches) e declarações `overflow`,
`overflow-x` ou `overflow-y` em 28 folhas (53 declarações). A expressão de
dimensão inclui width/height/inline-size/block-size e variantes min/max; os
valores em media queries também aparecem porque o gerador percorre o texto CSS
sem construir uma árvore sintática. As contagens não analisam cascata nem efeito
calculado. O inventário é estrutural; não mede overflow renderizado ou prova
comportamento por tela.

| Camada | Arquivos identificados | Observação da auditoria |
|---|---|---|
| Fundação | `css/base.css`, `css/layout.css`, `css/tokens.css`, `css/drupal-bootstrap.css` | `base.css`/`layout.css` quase sem media queries; tokens têm preferência de tema; Bootstrap injeta regras próprias. |
| Shell global | `css/components/header.css`, `navigation.css`, `institution-bar.css`, `breadcrumb.css`, `footer.css`, `institutional.css` | Afeta todos os purposes; Institution Bar e Domain Header são invariantes. |
| Componentes compartilhados | 42 folhas em `css/components/` e 45 folhas CSS de SDC | Mistura estilos sem breakpoint e media queries; os consumidores reais precisam ser correlacionados antes da migração. |
| Áreas de produto | account/portal, fórum/discussion, wiki, editorial/revista, product/cart/sales, eventos, messenger, mídia/documentos, doações e suporte | Não podem ser excluídas por ausência de rota no Studio. |
| JavaScript | 11 scripts de apresentação e um teste JS | Auditar progressive enhancement, estado de navegação, carrosséis/slideshows, drawer Messenger, calculadora de envio, doação e notificações. |
| Templates/layouts | 100 Twig/HTML | A contagem inclui componentes SDC e templates do tema; precisa cruzamento com bibliotecas anexadas por módulos e Views. |

## Registro responsivo reproduzível

Contagens por arquivo, não por regra única (valores podem ter múltiplas
declarações por bloco):

| Grupo observado | Arquivos com `@media` | Risco inicial a verificar |
|---|---:|---|
| `css/components/` | 27 | 58 blocos. Cascata carregada globalmente; breakpoints em portal-account (6), forum-index (5), institutional (4), wiki, discussion, institution-bar, navigation e portal-support (3 cada). |
| `components/**` (SDC) | 16 de 45 | 19 blocos `@media`; variantes locais devem ser testadas em consumers reais e contra a cascata global. |
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
| Eventos | `event_card`, `event-page-five-regions`, `statistics.css` | Agenda, datas, participantes e layout com cinco regiões | Catálogo e página de detalhe agora consomem os SDCs; captura real, estados e acessibilidade pendentes |
| Messenger/alertas | drawers, compose/item/state, `alerts-dismiss.js` | Drawer acessível, lista, teclado, mensagens e fallback sem JS | Componentes/JS mapeados; acesso e cache pendentes |
| GanjaLab editorial, Galerias e demais abas Marley | Plugins de aba e seus consumers reais | Variação de conteúdo, paginação e formulários owner | Providers/plugins listados; matriz de consumers incompleta |

Esta tabela não é evidência de cobertura homologada. Cada purpose ainda exige
inventário de biblioteca anexada por controller/View/form, estados vazio/erro/
sucesso/carregando e capturas em navegador visível.

### THEME-MF0 — anexação literal de bibliotecas e consumers (2026-10-30)

O scanner `scripts/audit-aculta420-library-consumers.php` correlaciona as
definições das bibliotecas customizadas com referências literais em arquivos de
código, templates e SDCs fora de diretórios de teste/documentação. A saída
versionada [`theme-library-consumer-inventory.tsv`](theme-library-consumer-inventory.tsv)
registra 42 libraries customizadas, 54 referências literais em 2.474 arquivos
de fonte e seus CSS/JS declarados, dependências, arquivo e linha do consumer.
É possível regenerá-la com `php scripts/audit-aculta420-library-consumers.php
--write`; a execução padrão escreve TSV em stdout para comparação reproduzível.

O rastreio confirmou, por exemplo, que `aculta420/global` é declarada como
biblioteca default do tema (41 assets CSS e 5 JS); `revista-listing` é anexada
ao teaser de `revista`; `editorial-content` aparece no template de artigo e no
SDC de citação; `editorial-carousel` é anexada pelo template de View; e
`aculta_studio/navigation` é anexada pelo controller Marley. Isso identifica
owners e pontos de ligação relevantes para revisar escopo global e telas da
Revista/Studio, mas **não prova que cada caminho condicional foi renderizado**,
nem associa automaticamente cada consumer a uma rota/purpose. Referências
construídas dinamicamente, dependências transitivas Core/contrib e anexações
resolvidas apenas em Runtime exigem inspeção Drupal/HTTP. A matriz por purpose,
View/Form API, estados e validação visual continuam parciais; MF0/MF3 não são
aprovados por este scanner.

### THEME-MF2 — rótulos longos da navegação global

Os links da navegação usam quebra de texto como base até 1099px e preservam
uma linha no menu desktop a partir de 1100px. Isso evita que nomes extensos de
áreas ou traduções empurrem o viewport horizontalmente. `navigation.py` injeta
um rótulo longo temporário no consumer real da home MAIN e verifica a quebra,
além dos estados Bootstrap com e sem JavaScript e dos viewports existentes.
Stylelint e sintaxe Python foram executados; Firefox visível/Runtime ficam
DEFERRED enquanto `DISPLAY=:0` não estiver disponível e houver sessões
geckodriver concorrentes. O teste não homologa a navegação nos demais purposes.

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

O inventário dos 93 estilos, 12 scripts e 101 templates encontra-se em
[`theme-mobile-file-inventory.tsv`](theme-mobile-file-inventory.tsv); pode ser
regenerado com `python3 scripts/audit-aculta420-responsive-inventory.py --write`.
Ele contém contagens lexicais de media/container/dimensão/overflow por folha de
estilo. Contagens podem incluir declarações sem efeito em um purpose e não
substituem análise de cascata. A enumeração de bibliotecas anexadas
dinamicamente por Views e módulos consumidores continua pendente. As 20
manifestações custom `*.libraries.yml` foram enumeradas em
[`theme-library-inventory.tsv`](theme-library-inventory.tsv), incluindo
dependências e assets. Os 91 pontos lexicais de anexação em código de produção
(85 linhas com `#attached` e seis chamadas Twig `attach_library()`) estão
indexados em
[`theme-library-attachment-sites.tsv`](theme-library-attachment-sites.tsv).
O inventário inclui um arquivo JS de teste por registrar todas as fontes `.js`;
os templates Twig/HTML são incluídos sem tentar resolver condições. Isso não
resolve expressões dinâmicas nem relaciona cada attach à rota/purpose e
consumer; declaração em YAML não prova carregamento em runtime.

### Ativos de apresentação fora de `*.libraries.yml` — THEME-MF0

O cruzamento recursivo encontrou 149 fontes CSS/JS de tema e módulos
customizados, sem `vendor/` ou `node_modules/` (105 no tema e 44 nos módulos).
Os 20 manifests declaram 96 caminhos de asset locais; 94 pertencem a esse
conjunto de fontes, dois são os arquivos Leaflet em `assets/vendor/` excluídos
da varredura. Há também um path de biblioteca gerenciada externa
(`/libraries/tabulator/dist/tabulator.min.css`) e nenhum caminho relativo
declarado ausente.

Dos 55 arquivos sem referência direta nesses manifests: 46 vivem em diretórios
SDC (45 CSS e um JS, ainda dependentes de consumer real); sete pertencem a
módulos, um é `css/ckeditor5.css`, incluído por `ckeditor5_stylesheets`, e um é
o teste JS do contador do Messenger. Os sete arquivos de módulo são
`pdf-viewer-wrapper.css`, `docx-frame.js` e `sheet-frame.js` (iframes do
`aculta_document_viewer`); `h5p-integration.js`,
`h5p-admin-integration.js` e `h5peditor-application.js` (bibliotecas registradas
dinamicamente por `aculta_H5PLibraryHooks`); e
`h5p_drupal_custom.css`, para o qual a árvore custom não contém referência
literal e a associação de runtime segue `DEFERRED`. Assets encontrados não são
por si só prova de execução. A cobertura de rota/purpose e a inspeção visual
desses consumers ainda são necessárias; módulos proprietários não foram
alterados nesta auditoria.

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

Snapshot histórico da recontagem lexical de 82 blocos em 46 folhas de estilo
do tema, registrado antes da migração mobile-first do modal Webform. `@media`
contém também preferências/recursos de acessibilidade, não só breakpoints:

| Query literal | Blocos | Arquivos |
|---|---:|---:|
| `min-width: 36rem` | 16 | 13 |
| `min-width: 48rem` | 15 | 12 |
| `prefers-reduced-motion: reduce` | 13 | 12 |
| `min-width: 768px` | 5 | 5 |
| `min-width: 62rem` | 4 | 4 |
| `min-width: 48.0625rem` | 3 | 3 |
| `min-width: 576px` | 3 | 3 |
| `min-width: 62rem` | 3 | 3 |
| `min-width: 992px` | 3 | 2 |
| `min-width: 1100px` | 2 | 2 |
| `min-width: 30rem` | 2 | 1 |

Há outras 17 expressões literais de uso único, incluindo limites estreitos,
`forced-colors`, `hover` e `prefers-color-scheme`. As contagens mostram onde há
mais regras, mas não provam conflito visual; a cascata precisa ser lida por
consumer. A saída completa daquela fotografia está no TSV gerado por
`scripts/audit-aculta420-responsive-inventory.py`.

### Recontagem THEME-MF0 em 2026-10-30

No head `e1634a6eb`, o scanner atual encontrou 81 blocos `@media` em 46 folhas,
632 correspondências lexicais de dimensões em 74 folhas, zero blocos
`@container` e 54 declarações de overflow em 28 folhas. `css/components/`
passou a conter 58 blocos `@media` em 27 folhas. A queda de um bloco e uma
correspondência acompanha a conversão do modal Webform para largura fluida na
regra-base. O TSV `theme-mobile-file-inventory.tsv` foi regenerado na mesma
revisão. Esta é uma contagem estática; não altera o status parcial de MF0 nem
aprova MF3.

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

**Atualização em 2026-10-30, após rebase sobre `44ba133a1`:** reconciliei o
índice com os consumers de Eventos adicionados na branch. O parser YAML conferiu
todos os 20 manifests custom e todos os IDs de library: 20/20 arquivos, zero
IDs ausentes ou obsoletos. A varredura lexical dos consumers de produção
enumerou 91 pontos em 70 arquivos (85 linhas `#attached`, seis
`attach_library()` em Twig); caminhos, linhas e tipos coincidem com o TSV, sem
ausências ou entradas obsoletas. Foram atualizados offsets e incluídos os
formulários de data/local de Eventos. O scanner exclui testes e não interpreta
condições, chamadas indiretas, anexos providos por Views/Core/contrib ou a
rota/purpose efetivamente renderizada. Portanto, isto fecha a consistência
estática destes dois índices, não a matriz de consumers dinâmicos nem a
homologação visual por purpose.

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
| MAIN | homepage pública renderiza; na captura visível de 2026-10-30 a 320 px, o H1 quebra entre palavras e “ANTIPROIBICIONISTA” permanece inteiro, sem overflow. | A captura atual cobre somente a homepage e não substitui as demais páginas/estados; a observação antiga de quebra no meio da palavra não se reproduziu. |
| ACCOUNT | raiz redireciona para `/entrar`; Institution Bar presente. | Não cobre Conta, Perfil ou Studio autenticados; Green Stash autorizado permanece fora. |
| SUPPORT | formulário público de valor renderiza. | Não submetido; não valida estados Form API/AJAX/erro. |
| MAGAZINE | baseline anterior mostrava título e corpo vazio; a repetição 2026-10-30 encontrou document title, sem H1 nem conteúdo no corpo e somente o rodapé. | Página vazia em todos os nove viewports; consumidor/estado editorial precisam de verificação do owner. |
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

### Repetição pública nos dez purposes — 2026-10-30

`tests/browser/aculta-studio/purpose-matrix.py` foi executado no Firefox ESR
visível contra o docroot no HEAD remoto `3c1691fc5`, em 320/360/390/599/600/
768/1024/1280/1440px. Foram 90/90 capturas, zero deferred e zero registros com
overflow horizontal medido. A Institution Bar apareceu 90/90; `.aculta-header`
apareceu 18/90, somente nas nove capturas MAIN e nas nove WIKI. Nos oito outros
purposes não apareceu em nenhuma raiz capturada. Revista respondeu em 9/9
larguras com `page-view-revista-sessoes`, mas sem H1/corpo editorial visível;
somente o rodapé apareceu no screenshot. Esta grade é anônima e cobre apenas a
raiz de cada purpose; não prova layouts internos, autenticação, teclado, zoom,
claro/escuro ou gates MF3. PNG/JSON estão em
`/home/piradopirata/aculta-suite-out/aculta-purposes-mobile-baseline-20261030-3c1691fc5/`.

O resultado de shell acima pertence ao snapshot antigo `3c1691fc5`; foi
substituído pelo reteste do checkout `9ad2b86b9` registrado na seção
“Composição do Domain Header por purpose — Pede-A resolvido”. O diagnóstico
editorial da Revista permanece válido para essa captura.

O docroot foi testado no HEAD remoto, que precede nove commits locais desta
worktree. Logo, esta captura atualiza o baseline da PR remota e não valida as
alterações locais não publicadas. O teste do SDC author-card acima é exceção
focal: seus dois arquivos CSS foram comparados por blob e coincidem com a
worktree.

### Rota interna da Revista — `/noticias` (2026-10-30)

O sitemap público aponta `/noticias` como rota da Revista. A execução adicional
de `purpose-matrix.py` com `OCS_EXTRA_ROUTES_ONLY=1` capturou essa URL em nove
viewports: 9/9 completos, zero overflow. A tela é uma página pública comum com
H1 “Notícias” e texto sobre as iniciativas de comunicação da Associação; a
View `aculta-news` não renderizou rows de publicações. Isso demonstra uma tela
editorial legível, mas não substitui um consumer de entidade `revista`. As
capturas 320/1440 foram inspecionadas em
`/home/piradopirata/aculta-suite-out/aculta-revista-noticias-20261030/`.

Reteste visual real da capa no Runtime, Firefox ESR visível em HEAD `2493d7535`
(2026-10-30): `magazine-cover-runtime.py` passou 10/10 verificações na raiz
canônica em modo anônimo. A View `revista_sessoes` renderizou hero “Revista
420”, quatro teasers e as quatro imagens de capa; Institution Bar e Domain
Header apareceram uma vez cada em todos os viewports. 320/360/390/599px
renderizaram uma coluna; 600/768px, duas; 1024/1280/1440px, três. A primeira
publicação assumiu a composição de destaque em grid a partir de 992px. Não houve
overflow horizontal. Capturas 320px e 1440px foram inspecionadas visualmente.
Firefox 153.5.1esr / geckodriver 0.37.1. Evidências em
`/home/piradopirata/aculta-suite-out/magazine-cover-runtime-20261030/`.

Esse reteste substitui o baseline antigo sem conteúdo em
`aculta-purposes-mobile-baseline-20261030-3c1691fc5`; ele cobria outro HEAD e
não representa a capa atual. A página estática `/noticias` permanece um estado
distinto. O reteste não cobre zoom, teclado, claro/escuro, reduced motion,
comparação pareada com dashboard OpenCulturas nem WCAG; não aprova THEME-MF3.

### Composição do Domain Header por purpose — Pede-A resolvido

O Pede-A histórico foi aberto por capturas de heads anteriores, que
mostravam o Domain Header apenas em MAIN e Wiki. Está resolvido no contrato
Portal vigente: `DomainPresentationBuilder` fornece `show_site_header` em todos
os purposes reconhecidos, o Twig apenas o apresenta, e
`DomainPresentationBuilderKernelTest` valida os dez purposes, URLs de domínio e
cache tags. A Revista permanece na matriz pelo purpose `magazine`.

No reteste em Firefox ESR visível, contra o checkout servido no HEAD
`9ad2b86b9`, a matriz pública `purpose-matrix.py` completou 90/90 capturas
(10 purposes × 9 larguras: 320/360/390/599/600/768/1024/1280/1440px): cada
captura apresentou exatamente uma Institution Bar e um Domain Header; zero
overflow horizontal e zero páginas com erro. O teste Kernel do contrato passou
com 2 testes e 74 assertions (uma depreciação upstream de EntityType). Os PNGs,
JSON e logs estão em
`/home/piradopirata/suite-out/domain-header-purpose-9ad2b86b9/`.

Repetição pós-migration no HEAD `4eb116482`: 90/90 capturas novamente passaram,
com os dois headers exatamente uma vez, zero overflow e zero erro 500,
incluindo `eventos` em 320–1440px. Evidência em
`/home/piradopirata/suite-out/domain-header-purpose-4eb116482-post-updb/`.

Esta evidência encerra somente o handoff sobre a presença dos dois headers nas
raízes anônimas. Não cobre páginas internas, conteúdo autorizado, zoom,
teclado, WCAG 2.2 AA nem THEME-MF3. As diferenças visuais registradas em
snapshots anteriores são evidência de heads antigos, não estado vigente nem
Pede-A aberto.

### Revista — dados da capa verificados; captura visual pendente

A auditoria read-only confirmou View ativa, quatro publicações com imagens e
template descoberto; o teste Firefox acima verificou o consumer visual real em
nove viewports. O antigo Pede-A sobre ausência de publicação/consumer está
resolvido e nenhuma alteração é solicitada ao owner do Magazine. Preservar a
página estática `/noticias` como estado distinto. Não criar conteúdo de teste
adicional; os limites de zoom, acessibilidade e comparação upstream continuam
registrados como pendentes.

O registro anterior do `ReportQueueController` foi um handoff já resolvido: o
controller emite `forum-report-queue` e `forum-report-pager`, e o tema mapeia
essas classes. ACL, pager, conteúdo e cache continuam no owner; Unit do
controller (3 testes/13 assertions), `PortalUiHooks` (12/83) e fronteira
módulo/tema (3418 checks) passaram naquela rodada.

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
Revista, Loja, Cursos, Fórum, Links e Eventos no snapshot antigo do checkout
`4d8993645`. A composição foi posteriormente corrigida no Portal e validada no
retorno vigente descrito acima. Evidência histórica:
`/home/piradopirata/aculta-suite-out/aculta-institution-bar-integrated-all-domains-4d8993645-final/`.
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

**Revalidação Firefox ESR visível (2026-10-30):** a rota pública real
`/discussoes/novas` renderizou cinco cartões e passou 35/35 verificações em
320/360/390/575/576/599/600/768/1024/1280/1440px. Não houve overflow do
documento; o alinhamento mudou de `flex-start`/avatar 32px em 575px para
`center`/avatar 48px em 576px, conforme o breakpoint do SDC. Os hashes dos
arquivos `author-card.css` e `forum-index.css` no docroot `3c1691fc5` coincidem
com os do HEAD auditado, então esses dois estilos da captura correspondem ao
código atual da worktree. Evidências em
`/home/piradopirata/aculta-suite-out/author-card-mobile-first-20261030/`.
É um cenário anônimo de um consumer; não cobre ACL autenticada, teclado, zoom
real nem WCAG completa.

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
Reteste em 2026-10-30 no host de desenvolvimento, com UID sintético 5 e a folha
candidata atual injetada: 33/33 novamente em 320–1440px, sem overflow e com
13 seções equivalentes entre menu desktop e sanfona. As capturas 320/768/1440px
foram inspecionadas. Não houve submissão nem gravação de preferências. Evidência:
`/home/piradopirata/aculta-suite-out/account-shell-20261030-final/`.

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
purpose SUPPORT e a Institution Bar presente naquele snapshot. O Pede-A de
composição foi encerrado pelo reteste vigente registrado acima; não foi criado
shell visual paralelo. A
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
O Domain Header não aparecia em SUPPORT, Conta, Loja ou Fórum neste snapshot
histórico; a presença nas dez raízes foi posteriormente confirmada no reteste
90/90 registrado acima. Este slice não valida outros caminhos por purpose, zoom,
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

### THEME-MF2 — repetição da Institution Bar em todas as homes (2026-10-30)

Com Firefox ESR visível, a CSS candidata foi injetada nas dez homes como
anônimo em 320/390px. Nas oito homes que responderam normalmente, os testes
confirmaram Institution Bar única, `position: sticky`, topo estabilizado sem
salto (`maxTopChange=0`), permanência no topo durante scroll e nenhuma rolagem
horizontal. Resultado: 87/89 checks passaram. As duas falhas são a home Wiki,
que retornou HTTP 500 e não entregou Institution Bar em nenhuma largura; a
resposta foi confirmada por GET separado. Evidências em
`/home/piradopirata/aculta-suite-out/institution-bar-scroll-anonymous-65da0f287-final/`.

O cenário anônimo não usa Navigation top-bar administrativa. A tentativa de
cenário autenticado não confirmou a sessão de UID 1 e ficou inconclusiva;
nenhuma conclusão de regressão autenticada é registrada. A CSS candidata do tema não foi servida nesta rodada; o cache foi reconstruído para validar o reparo do hook. O harness foi ajustado para registrar
shell ausente e continuar nos outros purposes, sem erro de cálculo com posição
nula. THEME-MF3 permanece pendente.

O watchdog do Runtime confirmou a causa do HTTP 500: dois providers do tema
registravam `preprocess_item_list`, gerando `LogicException` no `ThemeManager`
em toda renderização de lista. A adaptação dos cards de Evento foi consolidada
no único `PortalUiHooks::preprocess`; foram adicionados testes de conversão e
unicidade de hook. Os testes Unit locais passam. Após aplicar a correção no
checkout de desenvolvimento e reconstruir o cache, GET anônimo à home Wiki
respondeu HTTP 200; commit/publicação na branch continuam pendentes.

#### Reteste servido após o avanço da PR para `29961db75` (2026-10-30)

O harness reutilizável foi executado novamente sem injeção de CSS, contra as
dez homes do ambiente de desenvolvimento, como anônimo em 320/390px. Firefox
ESR passou 101/101 verificações: barra única e `position: sticky`, topo
estável no carregamento (`maxTopChange=0`), topo mantido durante scroll e sem
overflow horizontal. Evidência em
`/home/piradopirata/aculta-suite-out/aculta-institution-bar-scroll-recheck-20261030/`.
Este reteste não reproduziu o deslocamento anônimo. Não incluiu sessão
administrativa/toolbar, nem routes internas autenticadas; portanto não encerra
essa ocorrência nem aprova THEME-MF3. Não foi feita escrita persistente no
Runtime.


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

### THEME-MF2 — autenticação pública (2026-10-30)

A inspeção Firefox ESR visível de `/entrar` encontrou que o `<body>` real usa a
classe `path-entrar`, enquanto `auth.css` selecionava classes inexistentes
`page-route-user-*`; por isso os estilos próprios do painel não eram aplicados
na rota real. As rotas canônicas vêm de `DomainRouteSubscriber`: `/entrar`,
`/criar-conta` e `/recuperar-senha`. Os seletores foram corrigidos para as
classes `path-*` correspondentes, preservando os seletores de formulário por
contexto.

A fixture Firefox ESR visível passou 28/28 verificações em 11 larguras
(320/360/390/575/576/599/600/768/1024/1280/1440 px), confirmando espaçamentos
compactos até 575 px, painel amplo a partir de 576 px, seletor para as três
classes reais e ausência de overflow. Evidência:
`/home/piradopirata/aculta-suite-out/auth-selector-fix-20261030-rerun/summary.json`.
A validação do markup real anônimo em `https://conta.aculta.toca.net.br/entrar`
passou 12/12 checks no Firefox ESR, com a folha candidata injetada somente no
DOM da sessão, em 11 larguras (320/360/390/575/576/599/600/768/1024/1280/1440
px). Classes reais, H1, formulário e paddings passaram; em 320 px o scrollWidth
foi 328 por causa do offcanvas fechado, enquanto `overflow-x: clip` e `scrollX=0`
confirmaram ausência de rolagem horizontal. Evidência:
`/home/piradopirata/aculta-suite-out/auth-route-runtime-20261030-final/summary.json`.
Como CSS foi injetado no navegador, a revalidação pós-publicação e rebuild de
cache permanece DEFERRED; essa branch ainda não está servida.

### THEME-MF2 — carrossel editorial da home MAIN (2026-10-30)

`editorial-carousel.css` usa limite compacto de 390px e itens fluidos como base;
`min-width: 48.0625rem` restaura wrapper de até 764px e slides de até 370px.
O harness sintético existente passou 24/24 checks em 12 larguras. No Firefox ESR
visível, a home MAIN real passou 15/15 checks em 12 larguras: a View contém três
slides, o VVJB hidrata após entrar no viewport, há um slide ativo com conteúdo,
os demais ficam ocultos, e as larguras renderizadas não geram rolagem horizontal.
A captura foi posicionada sobre o card editorial real. O CSS do worktree, do
checkout servido em HEAD `4399627c4` e do HEAD atual da PR #299 tem o mesmo SHA-256
`843691e4d1326c5d902829a240a184350cc21ea65d935b24c2c82f8becf2598a`; a injeção
no harness garante a folha revisada, mas não corresponde a uma mudança nova de
CSS. Evidências em
`/home/piradopirata/aculta-suite-out/editorial-carousel-runtime-20261030-final5/`
e `editorial-carousel-20261030-final5/`. Teclado, reduced motion, claro/escuro e WCAG
2.2 AA não foram exercitados. THEME-MF3 segue pendente.

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
768 CSS px o tema volta a ocultar ambos, preservando o conteúdo desktop. O
behavior do modal Webform define `minWidth: 600`; a regra anterior só alterava
`width` abaixo de 650px e deixava a largura mínima fixa, causando overflow em
viewports menores que 616px. O tema agora reduz essa largura mínima conforme o
gutter disponível, mantendo os 600px em desktop e preservando a largura
configurada pelo consumidor. O harness
`tests/browser/aculta-theme-mobile-first/webform-layout.py` reproduz o mínimo do
jQuery UI, compara baseline/candidato e captura 14 larguras, incluindo
650/651 e 768/769. Sintaxe Python e lint CSS PASS; Firefox e rotas Webform reais
estão DEFERRED enquanto `DISPLAY` estiver vazio e sessões concorrentes ocuparem
o browser. THEME-MF3 segue pendente.

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

### THEME-MF2 — painel de Estatísticas (2026-10-29)

`statistics.css` declara os filtros empilhados e de largura total como base;
`min-width: 36rem` restaura os controles em linha e a largura mínima de 12rem
do seletor. O markup e os dados do painel não mudam. O teste reutilizável
`tests/browser/aculta-statistics/visual.py` ganhou uma fixture sintética para
medir os estados mobile em 320px e ampliado em 768px; a rota autenticada mede
overflow em 320/390/768/1280px. Firefox/Runtime estão DEFERRED nesta sessão
(`DISPLAY` indisponível e geckodrivers concorrentes); a fixture não equivale à
homologação do painel real e THEME-MF3 permanece pendente.

### THEME-MF2 — publicação da Revista (2026-10-29)

`revista-post.css` usa figuras full-width/static como base e restaura em
`min-width: 36rem` as larguras pequenas/padrão e os modelos larga/destaque
centralizados. A regra móvel redundante para o subtítulo foi removida. A
alteração foi commitada em `e40c39265`. O lint CSS foi executado posteriormente
na raiz correta do tema e PASS. A comparação Firefox da fixture está registrada
abaixo; a revisão visual do artigo real segue pendente porque a homepage da
Revista não expôs conteúdo editorial nesta rodada.

### THEME-MF2 — leitura e metadados do artigo da Revista (2026-10-29)

O corpo do artigo e o slot `revista-publication.body` começam alinhados ao
início em telas estreitas; a justificação editorial volta em `36rem` (576px).
Resumo e metadados começam compactos; a composição anterior é restaurada
progressivamente a partir do mesmo limite. A ordem DOM, os campos, os SDCs e
os contratos do módulo permanecem iguais. O smoke reutilizável
`tests/browser/aculta-theme-mobile-first/revista-article-layout.py` compara
baseline/candidato em 320–1440px e mede alinhamento, padding, ordem e overflow.
Lint CSS, schema SDC, Design Foundations, sintaxe Python e `git diff --check`
PASS na execução original. O Firefox daquela rodada ficou DEFERRED por DISPLAY
ausente e sessões concorrentes; a revalidação posterior está abaixo. Nenhuma
fixture homologa artigo real ou THEME-MF3.

**Revalidação Firefox ESR visível (2026-10-30):**
`revista-article-layout.py` comparou o CSS de `origin/refactor/opencultures-codigo-visual`
com o CSS atual da worktree em 320/360/390/575/576/599/600/768/1024/1280/1440px.
22/22 checks passaram: estilo esperado mobile até 575px, editorial amplo desde
576px, ordem de conteúdo estável e `scrollWidth` igual ao viewport. Os blobs da
folha comparada são idênticos; o teste confirma os contratos calculados e não
prova alteração visual nem substitui consumer editorial real. Capturas em
`/home/piradopirata/aculta-suite-out/revista-article-current-20261030/`;
320/1440 foram inspecionadas. Teclado, zoom real, tema claro/escuro e WCAG AA
continuam sem cobertura nesta fixture.

### THEME-MF2 — SDC editorial-card

`editorial-card.css` agora começa com `min-height: 0` e padding de `1.25rem`.
Em `min-width: 48.0625rem` (769 CSS px com raiz de 16px), amplia para a altura
mínima de 390px e padding de `1.5rem`. Isso mantém os valores anteriores em
ambos os lados da fronteira 768/769px e preserva DOM e slots. Lint CSS e os
gates estáticos SDC passaram; Firefox/inspeção do consumer
`node--editorial-highlight` estão DEFERRED sem `DISPLAY` e com 13 sessões
geckodriver ativas. THEME-MF3 permanece pendente.

### THEME-MF2 — galeria pública de álbuns

`album-slideshow.css` agora declara o palco em uma coluna, miniaturas recolhidas
e botão de alternância como base. `min-width: 30rem` (480px) restaura duas
colunas, miniaturas visíveis e oculta o botão. `tests/browser/aculta-gallery/
visual.py` mede 320/390/479/480/600/1280px nos fixtures públicos dos domains
principal e Fórum, incluindo uso do toggle e retorno ao estado fechado. Lint CSS
e sintaxe Python passam; Firefox/Runtime DEFERRED por `DISPLAY` ausente e
sessões concorrentes. THEME-MF3 segue pendente.
Com JavaScript desativado, a CSS mantém as miniaturas visíveis; o botão de
recolhimento só existe após o behavior inserir o controle acessível.

### THEME-MF2 — leitor DOCX e planilhas

`document-viewer.css` usa padding de `0.75rem` e iframe DOCX de `50vh` como
base; em `min-width: 36rem` retoma padding de `1rem` e altura mínima de `60vh`.
`office-options.py` verifica a fronteira 575/576px em ACULTA420 e Olivero, além
do overflow. Lint CSS e sintaxe Python passam; Firefox visual DEFERRED por
`DISPLAY` ausente e sessões concorrentes. THEME-MF3 segue pendente.

### THEME-MF2 — Navigation top-bar mobile-first (2026-10-29)

`drupal-bootstrap.css` agora empilha título, contexto e ações como base móvel;
em `min-width: 48rem` (768 CSS px) restaura a grade upstream de três regiões,
alinhamentos e ações sem quebra. Mantém especificidade necessária para a folha
administrativa do Drupal Navigation. Nenhum markup, shell, comportamento da
Institution Bar ou código de módulo foi alterado.

`tests/browser/aculta-theme-mobile-first/navigation-top-bar.py` compara
baseline/candidato em 320/360/390/575/576/599/600/767/768/1024/1280/1440px,
verificando uma coluna, ordem vertical/DOM, ausência de overflow e paridade da
grade larga. Lint CSS e sintaxe Python são gates estáticos desta microfase;
Firefox visível fica DEFERRED porque `DISPLAY` não está disponível e há sessões
geckodriver concorrentes. A fixture não substitui validação autenticada da
Navigation top-bar servida no Runtime. THEME-MF3 continua pendente.

### THEME-MF2 — identidade SDC de Perfil mobile-first (2026-10-29)

`profile-identity.css` agora usa alinhamento inicial e avatar de `3.5rem` como
base. Em `min-width: 22.0625rem` (353 CSS px), restaura o avatar de `4.5rem` e
alinhamento central, preservando o breakpoint de composição horizontal em
`48rem`. O consumer Studio e seus props/slots não mudam.

`profile-identity-layout.py` compara baseline/candidato em 14 larguras, mede o
avatar, alinhamento, overflow, ordem DOM e mantém captura de cada estado. Lint
CSS, sintaxe Python, Design Foundations e índice de status PASS; Firefox visível
DEFERRED porque `DISPLAY` não está disponível e sessões geckodriver concorrentes
estão ativas. A fixture não valida conteúdo ou ACL de perfil real. THEME-MF3
continua pendente.

### THEME-MF2 — menu de opções do Messenger mobile-first (2026-10-29)

`messenger-drawer.css` agora oculta o rótulo “Opções” e usa o controle compacto
como base. A partir de `min-width: 36rem` (576 CSS px), mostra o rótulo e
restaura dimensões e espaçamento do botão desktop. A barra de retorno, ações,
contratos de formulário e comportamento da gaveta não mudam.

`tests/browser/aculta-messenger/suite.mjs` verifica o rótulo e o botão largo em
desktop, depois as dimensões compactas, alinhamento e overflow a 390px. Lint CSS
e sintaxe JS PASS; Firefox E2E DEFERRED porque a suíte prepara e altera dados de
teste do Runtime, `DISPLAY` está ausente e sessões concorrentes estão ativas.
THEME-MF3 continua pendente.

### THEME-MF2 — formulário público de apoio (2026-10-29)

`portal-support.css` agora começa com fieldset de 16px de padding, margem de
16px e ações de largura total com quebra de texto; em 481px (`30.0625rem`),
restaura padding/margem de 24px e largura natural das ações. Ordem DOM, campos,
semântica e Form API não mudam. `support-form.py` mede padding, largura das
ações, grade de doações e overflow em 320–1440px. Lint CSS e sintaxe Python
foram executados; Firefox/Runtime ficam DEFERRED sem `DISPLAY=:0`. Esta fatia
não homologa SUPPORT nem THEME-MF3.

### THEME-MF2 — View de projetos institucionais

O override mobile de `.aculta-project-list .view-content` foi migrado de
`max-width: 767.98px` para uma grade de uma coluna como base; `min-width: 48rem`
restaura duas colunas e o gap horizontal do SDC `content-grid`, e `62rem`
restaura o row-gap de desktop. `institutional-layout.py` agora injeta o CSS do
SDC e compara também a View de projetos nas fronteiras 576/767/768/992px,
mantendo os testes existentes de CTA, áreas, detalhes, screenshots e overflow.
Lint CSS e sintaxe Python passaram. Firefox/Runtime DEFERRED sem `DISPLAY=:0`;
nenhum gate global MF3 é declarado.

### THEME-MF2 — SDC Context Tabs

O SDC reaproveitado usa gaps compactos como base em links estáticos e listas
`item_list`; `min-width: 23.0625rem` (369px) recupera os gaps amplos. A story
`context-tabs.provider-list.story.yml` documenta o markup de lista, e
`context-tabs-layout.py` mede ambas as estruturas, a fronteira 368/369px,
ordem/acessibilidade e overflow em catorze larguras, incluindo os viewports
globais 320/360/390/599/600/768/1024/1280/1440px. Lint CSS, validação estática
do catálogo e sintaxe Python passaram; Firefox e UI Patterns Runtime ficam
DEFERRED nesta sessão sem `DISPLAY=:0`. MF3 permanece pendente.


### THEME-MF2 — composer de resposta da gaveta Messenger (2026-10-29)

`messenger-drawer.css` usa padding compacto para a gaveta e o formulário,
margem curta entre mensagem e ação e textarea de 5.5rem como base móvel. Em
`min-width: 36rem` (576 CSS px), restaura os espaçamentos anteriores e a altura
natural da caixa. Markup, ordem DOM, Form API e envio continuam no módulo. O
harness `tests/browser/aculta-theme-mobile-first/messenger-reply-layout.py`
compara baseline/candidata nas larguras globais e na fronteira 575/576px. Lint
CSS, sintaxe do harness, Design Foundations (2.442 verificações), índice do
projeto e `git diff --check` passaram. Firefox visível fica DEFERRED sem
`DISPLAY=:0`; o fixture não cobre envio, ACL nem a gaveta real no Runtime.
THEME-MF3 continua pendente.


### THEME-MF2 — repetição após reparo da Wiki (2026-10-30)

No HEAD 3c1691fc5, Firefox ESR visível passou novamente 28/28 checks da
navegação global em 320/360/390/575/576/599/600/768/1024/1099/1100/1280/1440px,
com CSS candidata, cinco larguras sem JavaScript e rótulo longo temporário no
consumer. Evidências em
`/home/piradopirata/aculta-suite-out/aculta-navigation-mobile-first-3c1691fc5/`.

A Institution Bar passou 101/101 checks anônimos nas dez homes, em 320/390px,
com CSS candidata: aparece uma vez, fica sticky no topo sem salto, sem toolbar
e sem overflow durante scroll. A Wiki passou depois do reparo e cache rebuild.
Evidências em
`/home/piradopirata/aculta-suite-out/institution-bar-scroll-anonymous-3c1691fc5/`.
Para investigar o relato intermitente de deslocamento anônimo, MAIN foi
repetido três vezes com CSS e JS candidatos em 320/390px: 36/36 checks
passaram. A barra permaneceu no fluxo inicial e colada em `top: 0` após scroll
80/240/480px, sem toolbar, salto ou overflow. Evidências privadas:
`/home/piradopirata/aculta-suite-out/institution-bar-anonymous-repeat-{1,2,3}/`.
O problema não foi reproduzido nesta repetição focal; isto não exclui falhas
raras nem substitui o teste de interação autenticada com a toolbar.
Um ensaio anterior, no checkout `4eb116482`, confirmou a sessão de teste e
passou 142/142 checks autenticados; uma tentativa posterior de repetir ULI no
HEAD `3c1691fc5` não estabeleceu sessão. Portanto, a autenticação não foi
retestada junto com estas três rodadas. Isto não cobre toda a matriz THEME-MF3.


### THEME-MF2 — largura do modal Webform sem breakpoint inverso (2026-10-30)

`webform-confirmation-modal` agora declara na base a largura fluida limitada
por 600px e pelo gutter de cada lado. Removi a regra `max-width: 650px`; a
largura mínima responsiva mantém o mesmo contrato. Firefox ESR visível comparou
baseline e candidata em 14 viewports (320 a 1440px): 62/62 checks passaram,
com métricas e PNGs idênticos em todos os tamanhos, sem overflow. O harness
mede o gutter computado em pixels, corrigindo uma conversão que antes falhava
com tokens `rem`. Evidências em
`/home/piradopirata/aculta-suite-out/webform-mobile-first-7453cf6eb-rerun/`.
Não foi exercitada uma submissão Webform real; THEME-MF3 permanece pendente.


## Guardrails para agentes — 2026-10-30

Criada a norma transversal
[`ACULTA420-MOBILE-FIRST-GUARDRAILS.md`](../../../../docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md). O `AGENTS.md` raiz e o `CLAUDE.md` agora
exigem sua consulta por todo agente que altere UI do tema; o AGENTS local do
tema aponta para a mesma norma. Ela formaliza base 320 CSS px, aprimoramento
progressivo, escopo de todos os purposes, preservação dos dois headers,
ownership, evidência Firefox e linguagem `PASS`/`DEFERRED`. A norma não altera
o status de THEME-MF0–MF3, que segue parcial até os gates transversais reais.

### OCS-F2 — abas internas do Studio (2026-10-30)

A base de `.studio__tabs.nav` e `.studio__subtabs.nav` permanece horizontal com
quebra de linha, espaçamento compacto e sem largura fixa; estilos de tela larga
não mudam a ordem DOM nem os destinos. A distinção visual entre áreas primárias
e abas secundárias foi adaptada dos estilos `templates/navigation/tabs.scss` do
OpenCulturas 3.1.5 e está confinada ao CSS Studio. O CSS de lista neutraliza as
molduras herdadas de `.list-group-item`, confirmadas por estilo computado no
Firefox. Marley e a navegação contextual de Conta passaram 57/57 checks
executáveis com CSS candidata reinjetada após navegação: foco visível após Tab,
teclado, AJAX, histórico, contraste, claro/escuro e geometria invariável em
Marley; os 9 links, estado atual e ausência de overflow na navegação de Conta em
320/390/768/1280px. Marley cobriu 320/390/767/768/1024/1280/1440px. Edição de
publicação ficou SKIP por falta de fixture própria. Resultados:
`/home/piradopirata/aculta-suite-out/ocs-f2-marley-account-border-final-20261030/`.
A comparação com dashboard OpenCulturas e a cobertura das outras experiências
Studio permanecem DEFERRED. OCS-F2 continua parcial e esta evidência não aprova
THEME-MF3.

#### Reteste candidato de layout Marley (2026-10-30)

Firefox ESR visível testou `studio.css` do worktree (`800c37cda`) injetado na
rota real `/studio/marley` do Runtime servido em `29961db75`. A suíte registrou
50 PASS, 0 FAIL e 1 SKIP (edição sem publicação própria publicada). Mediu
320/390/767/768/1024/1280/1440px; confirmou reflow de coluna até 767px, duas
colunas a partir de 768px, ausência de overflow, foco por Tab, Enter, AJAX,
histórico, `aria-current`, contraste claro/escuro e geometria preservada entre
modos. Inspecionei as capturas desktop e móvel. Evidência privada em
`/home/piradopirata/aculta-suite-out/aculta-studio-candidate-800c37cda/`.
O Runtime não recebeu a CSS candidata e o teste cobre apenas Marley; a matriz
restante de Studio, Conta/Perfil e a comparação com dashboard OpenCulturas
continuam pendentes. Não aprova OCS-F11 nem THEME-MF3.

### Gates de rotas e fronteira tema/módulo — 2026-10-30

O `aculta_events` deixou de emitir classes de tema em render arrays; o tema
restaura as classes pelo contrato `data-portal-ui`, com regressão Unit que
confirma também a preservação de AJAX. `validate-module-theme-boundary.php`
passa 3427 checks. A allowlist Foundation inclui o override
`aculta-event-page.html.twig`; o gate do Portal conta o hook de endereço
legítimo e mantém a proibição específica para hooks de pagamento. Os paths
oficiais passaram a `/studio/green-stash` e `/studio/we-sativa`, com os nomes
ACULTA STUDIO correspondentes; IDs internos e ownership Commerce/Wiki foram
preservados. Unit focal: 36/389; Kernel Wiki focal: 1/69; PHPCS Drupal e
DrupalPractice sem erros/avisos. Os commits `c9e02d281` e `81b275163` estão
somente na branch local. Runtime visual, Firefox neste HEAD e THEME-MF3 seguem
DEFERRED.

### THEME-MF0 — reconciliação do inventário em HEAD `e5a799816`

A comparação recursiva dos CSS, Twig e JavaScript do tema encontrou um template
Twig SDC sem linha no TSV: `components/patterns/dropdown/dropdown.twig`. A linha
foi adicionada. Rechecagem: 206 caminhos únicos no inventário, 201 arquivos
visuais verificados nas árvores CSS/components/templates/JS, zero caminhos
faltantes e zero entradas obsoletas. Esta conciliação fecha apenas a cobertura
do inventário estático consultado; não homologa toda a auditoria MF0 nem MF3.

### THEME-MF2 — hooks de apresentação dos formulários de Eventos (2026-10-30)

Os formulários de datas e locais passaram a emitir tokens `data-portal-ui`; o
tema `PortalUiHooks` mapeia os tokens para as mesmas classes visuais e conserva
`use-ajax` como contrato de comportamento do Drupal. O gate módulo/tema agora
passa com zero classes de design no módulo (3.442 verificações), e o mapa passa
210 checks. `PortalUiHooksTest` passou (17 testes/110 assertions); os Kernel
`EventFormDatesKernelTest` e `EventLocationModalAndAdminKernelTest` passaram
conjuntamente (9 testes/141 assertions, 24 depreciações de Core/contrib). Isso
valida o contrato de apresentação e os formulários cobertos, mas não substitui
capturas visuais pós-atualização nem aprova THEME-MF3.

### THEME-MF0 — recenseamento lexical global da árvore do tema (2026-10-30)

O gerador `scripts/audit-aculta420-responsive-inventory.py` foi executado em
modo de leitura e sua saída foi comparada byte a byte ao TSV versionado: PASS.
O arquivo cobre 206 fontes encontradas recursivamente no tema: 93 CSS/SCSS/Sass,
12 JavaScript e 101 Twig/HTML. A contagem lexical registrou 81 ocorrências de
`@media`, nenhuma de `@container`, 633 declarações dimensionais e 53 de
`overflow` nos estilos. Essas contagens servem como índice para revisão, não
como análise semântica: podem incluir comentários e valores não equivalem a
breakpoints, larguras fixas ou defeitos distintos. O gerador não examina
libraries YAML, código PHP consumidor, CSS de módulos, proveniência de regras,
cascata efetiva ou renderização por purpose. O TSV agora inclui a regra de
48rem do CSS de discussão e declarações dimensionais adicionais nos CSS de
Studio, discussão e Institution Bar. A matriz de telas/purposes e THEME-MF3
continuam parciais; esta reconciliação não é aprovação mobile-first global.

### OCS-F1 — folha candidata no consumer Marley (2026-10-30)

Firefox ESR visível, Runtime de desenvolvimento e CSS candidata do worktree
injetada somente nos wrappers Studio: 63/63 verificações executáveis passaram,
com uma edição `SKIP` sem publicação própria na conta sintética. Foram medidos
zero overflow em 320/360/390/599/600/767/768/1024/1280/1440 px; flexão vertical
abaixo de 768 px e lateral a partir de 768 px; Montserrat computada e carregada;
gutter global antes/depois idêntico; contraste calculado mínimo de 6,49:1 claro
e 11,51:1 escuro; geometria de dez elementos igual entre modos; navegação AJAX
e Enter em link real; sem erros JavaScript. As capturas Marley/1280 e /390
foram inspecionadas em
`/home/piradopirata/aculta-suite-out/ocs-f1-candidate-20261030-after-500-fix/`.

O harness injeta somente os tokens Fidelity escopados e `studio.css`, com as
fontes WOFF2 inline, sobre markup servido no checkout `096ecbfae`; portanto a
execução não prova que o CSS da branch esteja publicado no Runtime nem valida
outras telas/purposes. A preferência color mode da conta de teste foi restaurada
para `light`. Não cobre zoom 200/400, no-JS, reduced motion, leitores de tela,
matriz A/B ou comparação equivalente com o dashboard autenticado upstream. Não
declara THEME-MF3, OCS-F1 integral ou OCS-F7.

Antes da sessão autenticada, o Runtime foi identificado no host `toca`, checkout
`/home/piradopirata/projetos/aculta` em `096ecbfae`, URI `http://default`, banco
`aculta_runtime`, 620 tabelas. O snapshot íntegro anterior à sessão está fora do
Git em `/home/piradopirata/aculta-suite-out/runtime-snapshots/aculta_runtime-before-ocs-f1-browser-20261030.sql`
(104.586.620 bytes, SHA-256
`b904551cc2181cb75acc9299df422f0aee9e8f8c5328aceac2ca2a013df04f10`). `updatedb:status`
mostrou o update `aculta_events 10010`; nenhum update ou config import foi
executado. `drush cr` expôs temporariamente uma duplicação `preprocess_page` no
estado compartilhado do Portal e o login respondeu 500; após a duplicação deixar
de existir no checkout e novo rebuild, `/` redirecionou a `/entrar` com HTTP 200
e formulário de login presente. Nenhum código Portal foi alterado por esta
frente. A preferência da conta sintética foi restaurada para `light`.

### OCS-F1 / OCS-F8 — Green Stash sob a fundação visual Studio (2026-10-30)

O token neutro `green-stash-entry` agora mapeia para `studio` e
`aculta-green-stash`: a área comercial herda a tipografia e os tokens Fidelity
Studio mantendo seus estilos específicos. O container principal que contém
`.studio` ou `.aculta-profile-editor` recebe margens, padding e largura máxima
baseados no layout do opCult, sem selecionar os containers da Institution Bar
ou do Domain Header. Breakpoints de Fidelity foram alinhados à fonte
OpenCulturas 3.1.5 verificada: 48em, 70em, 85em e 120em. A alteração é somente
no tema; markup funcional, ACL, rotas e navegação do Commerce não mudam.

Fonte verificada: tarball `openculturas-3.1.5.tar.gz`, SHA-256
`2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`, extraído
fora do repositório. Stylelint passou; Design Foundations passou 2419 checks;
fixtures do analisador passaram 159 resultados esperados; PHP lint e PHPCS
passaram; Unit focal passou 18 testes/113 assertions. Firefox visual fica
`DEFERRED`: `DISPLAY` não está disponível para esta sessão e há um processo
geckodriver ativo no host. O Runtime também está com divergência de configuração,
então não houve rebuild de cache nem alegação de captura candidata publicada.
Isto não aprova OCS-F8/F11 nem THEME-MF3.

### OCS-F3 / F4 / F8 e THEME-MF2 — cartões Fidelity e links de experiência (2026-10-30)

O SDC existente `patterns/card` ganhou a variante `studio_fidelity`, usada pelo
adaptador de apresentação nos links reais do Hub e do ponto de entrada
`account-experiences`. O root `account-entry` também recebe a classe `.studio`,
mantendo Conta privada dentro do contexto visual próprio do Studio. O adaptador
preserva `#access`, cache contexts/tags/max-age, attachments e o render array do
link no slot `content`. Não muda registry, destino, provider ou permissão.

A apresentação da lista pública de próximos eventos e do dashboard de
organização do Studio Culturas recebeu seletores de tema próprios, com uma
coluna fluida desde 320px, wrapping, alvo mínimo de 44px e foco visível; não
altera dados, Form API, ACL ou cache dos owners Eventos. Nenhum CSS funcional
foi movido ao módulo.

O cartão reexprime a geometria de `templates/node/teasers.scss` e
`sass/abstracts/_teasers.scss` do OpenCulturas 3.1.5: margem vertical de metade
do row-gap, padding 1rem/2rem, borda transparente de 1px, raio 1.5rem e sombra
centrada. Firefox ESR visível usou uma fixture local do markup equivalente e
as CSS candidatas com as WOFF2 Montserrat do tema. Passou 45/45 checks em
320/360/390/599/600/768/1024/1280/1440: viewport exato, zero overflow, grade de
uma coluna abaixo de 768 e duas a partir de 768, dois cartões no contexto
`.studio` e geometria esperada. A captura inicial encontrou overflow do H1 em
320/360; `studio.css` agora limita o título com `clamp(2rem, 7vw, 3rem)`, e a
repetição passou. PNGs e `summary.json` estão em
`/home/piradopirata/suite-out/aculta-studio-card-fidelity/`; o harness
reutilizável está em `tests/browser/aculta-studio/`.

Também passaram Stylelint, PHP lint/PHPCS, SDC schema (55 componentes/756
checks), Design Foundations (2421 checks e 159 fixtures), documentação de
contratos Portal/tema (464 tokens/371 usados/148 atributos), fronteira
módulo/tema (3453 checks) e Unit `PortalUiHooks` (20 testes/121 assertions).
A fixture não acessa o Drupal nem homologa ACL, HTML produzido pelo preprocess,
cache real, dark mode, zoom ou todos os estados; render do Runtime é
`DEFERRED` enquanto a configuração do Runtime divergir e não houver rebuild.
OCS-F3/F8 e THEME-MF2 permanecem parciais; isto não aprova THEME-MF3 ou OCS-F11.

### THEME-MF2 — sticky com Navigation top-bar após migration de Events (2026-10-30)

No Firefox ESR visível, `institution-bar-scroll.py` passou 142/142 checks no
Runtime após o checkout `4eb116482` e as migrations de schema. O cenário
anônimo cobriu as dez homes em 320/390px: a Institution Bar inicia no topo,
estabiliza sem salto (`maxTopChange=0`), permanece no topo durante scroll e não
causa overflow. No cenário administrativo autenticado via conta de teste, em
320/390/1023/1024/1280px, a Navigation top-bar fica em fluxo no mobile e a
Institution Bar acompanha sua altura enquanto visível; após scroll, assume
`top: 0`. A 1024/1280px a top-bar fica fixa em 64px e o Domain displacement e a
Institution Bar respeitam esse offset, inclusive ao redimensionar. O percurso
administrativo confirmou a sessão e não submeteu conteúdo.

Capturas e `results.json` estão em
`/home/piradopirata/suite-out/institution-bar-scroll-4eb116482/`. Esta
verificação fecha o defeito focal de salto/posição no shell nas rotas e viewports
exercitados; não cobre todas as páginas internas, zoom real, leitor de tela,
WCAG completa ou THEME-MF3.


### THEME-MF2 — capa responsiva da Revista e fixture editorial (2026-10-30)

A rota `/` do purpose `magazine` renderiza a View `revista_sessoes`. Após
inspecionar a versão servida, a listagem tinha cartões sem imagem, subtítulo ou
resumo por divergência focal de configuração no Runtime. O responsável autorizou
a criação de conteúdo sintético para inspeção humana. Foram criados quatro nós
públicos publicados, marcados `[TESTE VISUAL 20261030]`, com capas geométricas
originais sem ativos do OpenCulturas. A apresentação foi corrigida usando o
template da View, o SDC existente `patterns/hero` para o título da página e
`patterns/card` nos teasers existentes. O CSS é mobile-first: uma coluna na
base, duas a partir de 600px e três a partir de 992px; a primeira publicação
usa capa em destaque horizontal a partir de 992px. O DOM, a View, os dados, a
ACL e a paginação permanecem no Drupal/módulo proprietário.

Firefox ESR visível no Runtime capturou `/` e `/node/599` em 320, 360, 390,
599, 600, 768, 1024, 1280 e 1440px: 18/18 capturas; zero overflow medido,
Institution Bar e Domain Header presentes uma vez e H1 `Revista 420` na raiz.
As capturas de 390, 600 e 1440px foram inspecionadas manualmente. Evidências:
`/home/piradopirata/suite-out/aculta-revista-capa-visual-20261030/runtime-after-layout/`.
`npm run lint:css`, parser Twig e `git diff --check` passaram. Snapshot de banco
pré-fixture: `/home/piradopirata/aculta-backups/aculta-runtime-revista-visual-20261030/aculta_runtime-before-revista-visual.sql`,
SHA-256 `89d3f4441e0ab5d5bfdec3100b0ea01b98aa31de4cd2b5b473fdd5b85c130aba`.

Ajuste foi focal no display `core.entity_view_display.node.revista.teaser`
para habilitar os campos de capa, subtítulo e resumo que já constam em
`config/sync`; não foi importado o diff global. O `config:status` global
continua com 224 diferenças.

### THEME-MF2 — rail do catálogo de Cursos em viewport móvel (2026-10-30)

A inspeção da matriz de purposes mostrou parte do segundo cartão visível na
captura de 320px. A verificação do DOM confirmou que isso é o rail horizontal
intencional do SDC `patterns/rail`: os três cartões são mantidos, a região tem
`role=region`, nome acessível `Cursos disponíveis` e `tabindex=0`, e o indicador
de rolagem nativo aparece. Não é overflow do documento nem perda de conteúdo.
O teste de navegador reutilizável `tests/browser/aculta-theme-mobile-first/rail-courses.py`
confirmou que `ArrowRight` desloca o rail de `scrollLeft=12` para `266` a 320px.

Firefox ESR visível, Runtime no HEAD `f22c8f08d`, passou 11/11 checks a
320/390/768/1440px: página sem overflow, região rotulada/focável, trilha
rolável e navegação por teclado. Capturas e resultados estão em
`/home/piradopirata/suite-out/course-rail-validation-f22-20261030/`. O teste é
anônimo e somente leitura; não cobre leitores de tela, zoom, autenticação,
contraste WCAG 2.2 AA ou outros consumers do rail. Esta evidência focal não
fecha THEME-MF3.

### THEME-MF2 — shell sem JavaScript nas dez raízes públicas (2026-10-30)

O teste reutilizável `tests/browser/aculta-theme-mobile-first/purpose-shell-nojs.py`
usa os domínios de teste do manifesto `aculta_deployer` e Firefox ESR visível
com JavaScript desativado. Em MAIN, Conta, SUPPORT, Revista, Wiki, Loja, Cursos,
Fórum, Links e Eventos, passou 210/210 verificações em 320/390/599/600/768/
1024/1440px: Institution Bar e Domain Header únicos, sem overflow horizontal,
menu primário expandido e disponível sem JS. Na Wiki, que substitui o menu
primário pela busca contextual no cabeçalho, o campo de busca também ficou
visível. Evidências e `results.json`:
`/home/piradopirata/suite-out/purpose-shell-nojs-f22-20261030-rerun/`.

O teste cobre apenas as raízes públicas anônimas; não verifica páginas internas,
estados autenticados, teclado, zoom, leitores de tela, contraste ou WCAG 2.2 AA.
É evidência transversal adicional, não aprovação de THEME-MF3.

### THEME-MF2 — shell global em claro/escuro e reduced motion (2026-10-30)

Firefox ESR visível percorreu as dez raízes públicas nos modos `light` e `dark`,
com `prefers-reduced-motion` ativo, em nove larguras (320/360/390/599/600/768/
1024/1280/1440px). Passaram 360/360 verificações de tokens de superfície/texto,
estado de reduced motion, Institution Bar e Domain Header únicos e ausência de
overflow horizontal. Foram inspecionadas capturas do Fórum em 320px nos dois
modos. Evidência completa (JSON e 40 capturas):
`/home/piradopirata/suite-out/purpose-shell-modes-f22-20261030/`.

O script seleciona o modo pelo atributo DOM apenas na sessão do navegador; não
altera configuração persistente. Mede tokens e a preferência de movimento, não
contraste WCAG, cada estado de componente ou animações em todas as telas. É um
gate focal do shell e não aprova THEME-MF3.

### THEME-MF2 — título principal em Apoio e Cursos

A matriz pública no Runtime (Firefox ESR, 320px) registrou oito purposes com
H1 e duas exceções: Apoio (`Apoie a Associação`) e Cursos (`Cursos`) tinham título
de página no `<title>`, mas nenhum H1 em `main#main-content`. O bloco core
`page_title_block` já é extraído pelo tema para `aculta_page_title`; a condição
da home o suprimia. O template agora o exibe também nos purposes `support` e
`courses`, preservando os títulos geridos pelo Drupal e sem alterar módulos,
rotas, dados ou contrato do shell. O teste reutilizável é
`tests/browser/aculta-theme-mobile-first/purpose-headings.py`.

Validação estática do candidato: parser Twig do pacote instalado PASS,
`py_compile` do teste PASS e `git diff --check` PASS. O teste real de headings e
reflow baseline foi executado no Runtime HEAD `a1e1a4a71`: 108/120 checks; as
12 falhas foram exatamente ausência de H1 em SUPPORT e COURSES nas seis
larguras, sem falhas de shell ou overflow. Este resultado documenta o defeito
antes do candidato, não é aprovação do candidato. O teste pós-correção está
**DEFERRED**: o Runtime não contém este template; a branch remota da PR não está
publicada como `refs/heads` para levar o candidato ao checkout, e não editei o
docroot manualmente.

Gates estáticos no candidato local: SDC schema PASS (55 componentes, 759 checks),
Design Foundations PASS (2424), Browser Validators PASS (21), CSS lint PASS,
análise estática do shell PASS, parser Twig PASS, PHP lint/PHPCS PASS e
`PageTitleVisibilityTest` PASS (11 testes/26 assertions). O validador global de
fronteira tema/módulo segue vermelho (1/3482): classes `messages`,
`messages--warning`, `button--primary` e `button` em
`aculta_events/src/Form/EventOrganizationForm.php` e
`EventOrganizationMembersForm.php`. Há mudança paralela não commitada no módulo
`aculta_events` no docroot; preservei-a sem editar esse módulo. Este gate impede
publicação até resolução pelo owner do módulo. Nenhum resultado aprova THEME-MF3.

Reteste focal posterior no Firefox ESR visível: claro e escuro + reduced motion
capturados em 9/9 viewports cada, sem overflow e com os dois headers únicos. O
script confirmou `prefers-color-scheme`/`prefers-reduced-motion`; isto não mede
contraste WCAG nem prova o comportamento de toda animação do site. A suíte de
teclado observou 120 passos Tab/Shift+Tab em 320/390/600/1024/1440px: 115 alvos
focáveis, todos com `:focus-visible` e outline calculado, mais cinco retornos
esperados ao body. O zoom real do Firefox, em largura inicial de dispositivo de
1280px, alcançou 200% (640 CSS px) e 400% (320 CSS px); os dois estados tiveram
zero overflow horizontal, H1 e quatro capas presentes, e Institution Bar e
Domain Header únicos. Evidências estão em `/home/piradopirata/suite-out/` nos
subdiretórios `aculta-revista-modes-light-20261030`,
`aculta-revista-modes-dark-reduced-20261030`,
`aculta-revista-keyboard-20261030` e `aculta-revista-zoom-final-20261030`.
Esses checks fecham zoom e parte de teclado/modos somente para a home Magazine,
não cobrem no-JS, leitor de tela, contraste WCAG 2.2 AA, todos os estados ou a
matriz de todos os purposes. THEME-MF3 permanece pendente.

### OCS-F3 — contexto de WE SATIVA e fallback Bootstrap (2026-10-30)

Firefox ESR visível, conta sintética autorizada, rota real
`https://wiki420.aculta.toca.net.br/wesativa`: uma captura baseline identificou
que o `item_list` dos quatro estados herdava `flex-direction: column` e bordas
Bootstrap. A folha candidata do SDC `context-tabs` foi injetada apenas na sessão
Firefox (sem escrita no Runtime) e passou os checks estruturais em
320/360/390/599/600/768/1024/1280/1440px: quatro itens, eixo `row`, wrapping,
bordas neutralizadas, links de 48px e largura do documento igual ao viewport.
Screenshot 390px inspecionado: três estados na primeira linha e o quarto na
segunda, sem corte ou rolagem horizontal. Evidências em
`/home/piradopirata/aculta-suite-out/ocs-context-tabs-final-links-20261030/`.
Este reteste não valida ACL entre contas, operações de revisão, zoom, WCAG 2.2
AA nem os gates THEME-MF3/OCS-F11.

Reuso cruzado do mesmo SDC confirmado no Studio Marley: o `<ul>` conserva
classes `nav-tabs` e `list-group`, mas, com a folha candidata, os quatro links
medem 44px em 320 e 390px, sem bordas ou overflow. Conta e Perfil autenticados
não renderizaram `context-tabs` nesses caminhos, portanto não foram tratados
como consumidores. Evidência em
`/home/piradopirata/aculta-suite-out/ocs-context-tabs-marley-retake-20261030/`.

### Reteste visual MAIN e autenticação (2026-10-30)

Firefox ESR visível voltou a validar a rota anônima `/entrar` com CSS candidato:
12/12 checks (markup real, formulário, estado anônimo e paddings corretos em
320–1440px; sem scroll horizontal, com `overflow-x: clip` onde a gaveta fechada
amplia a scrollWidth). Nenhum formulário foi enviado. O carrossel editorial da
home MAIN passou 15/15 em 320/360/390/575/576/599/600/768/769/1024/1280/1440px:
View real com três slides, hidratação VVJB após entrada no viewport, um item
ativo e conteúdo visível, limites esperados de largura e sem overflow. As
capturas 320/1440 foram inspecionadas manualmente. Evidências em
`/home/piradopirata/aculta-suite-out/auth-route-runtime-resume-20261030/` e
`/home/piradopirata/aculta-suite-out/editorial-carousel-runtime-resume-20261030/`.
O CSS de autenticação foi injetado só no navegador; a captura não prova o
stylesheet instalado no Runtime. A execução do carrossel também não valida
teclado, reduced motion, alto contraste ou WCAG 2.2 AA. THEME-MF3 permanece
pendente.

### Leitor de documentos — DOCX e planilhas (2026-10-30)

O Firefox ESR visível executou `office-options.py` sobre fixtures locais em
memória nos temas ACULTA420 e Olivero: 55 checks, zero falhas. Nos dois temas,
verificou opções e rótulos, propagação ao leitor DOCX/planilha, conteúdo de
cabeçalho/rodapé/notas e quebra de página, modo Tabela somente leitura,
paginação de 25 linhas e navegação por teclado. ACULTA420 manteve iframe com
mínimo 450px e padding 12px até 575px, 540px e 16px a partir de 576px; em
320/360/390/575/576/600/768/1024/1280/1440px não houve overflow. Capturas e
`summary.json`: `/home/piradopirata/aculta-suite-out/aculta-document-viewer-office-resume-20261030/`.
O harness é local e não modifica o Runtime; não testa PDF embutido real,
fullscreen, leitores de tela ou WCAG 2.2 AA. THEME-MF3 continua pendente.

### Reteste Firefox ESR de componentes com gates anteriormente adiados (2026-10-30)

Com `DISPLAY=:0` disponível, executei oito harnesses fora do repositório. Todos
passaram, sem falhas:

| Harness | Resultado | Escopo da evidência |
|---|---:|---|
| `navigation-top-bar.py` | 40/40 | Fixture Drupal Navigation; uma coluna até 767px e três regiões a partir de 768px, ordem preservada, sem overflow. |
| `profile-identity-layout.py` | 56/56 | Fixture do SDC; avatares/alinhamento nos breakpoints, ordem DOM e sem overflow em 14 larguras. |
| `institutional-layout.py` | 24/24 | Fixture de áreas, projetos e detalhes; CSS candidato idêntico à baseline testada nos viewports e sem overflow. |
| `context-tabs-layout.py` | 70/70 | Fixture com links planos e `item_list`, gaps, estado atual, alvos e overflow em 14 larguras. |
| `revista-article-layout.py` | 20/20 | Fixture de artigo; tipografia, resumo, categoria, ordem DOM e overflow em 10 larguras. |
| `webform-layout.py` | 62/62 | Fixtures Likert/modal; minWidth responsivo, breakpoint de 600px, screenshots/medidas preservados e sem overflow. |
| `messenger-reply-layout.py` | 66/66 | Fixture de composer; densidade mobile/desktop, textarea, alvo de 44px, ordem e overflow em 11 larguras. |
| `author-card.py` | 32/32 | Consumer público real do Fórum, cinco cartões; alinhamento/avatar no breakpoint de 576px e sem overflow em 10 larguras. |

Artefatos, na mesma ordem: `/home/piradopirata/aculta-suite-out/` nos diretórios
`navigation-top-bar-resume-20261030`, `profile-identity-resume-20261030`,
`institutional-layout-resume-20261030`, `context-tabs-layout-resume-20261030`,
`revista-article-layout-resume-20261030`, `webform-layout-resume-20261030`,
`messenger-reply-layout-resume-20261030` e `author-card-resume-20261030`.
Somente `author-card.py` consultou uma rota pública real; os demais usam fixtures
e não validam integração Drupal/ACL nem todos os estados. A bateria não cobre
zoom 200/400%, leitor de tela, contraste WCAG 2.2 AA, autenticação em todos os
purposes ou toda a matriz cross-product. THEME-MF3 continua pendente.

### Shell global repetido no HEAD atual da PR (2026-10-30)

O docroot de desenvolvimento (`toca`, `/home/piradopirata/projetos/aculta`,
`http://default`, `aculta_runtime`) e `refs/pull/299/head` foram observados em
`30c6770f3` antes desta rodada e confirmados no mesmo SHA ao final. No Firefox
ESR visível, as dez raízes públicas passaram 360/360 checks em claro/escuro e
reduced-motion (320/360/390/599/600/768/1024/1280/1440px): tokens computados,
shell único e sem overflow. A alternância de tema ocorreu somente no DOM local
da sessão Firefox. Separadamente, sem JavaScript, as dez raízes passaram
210/210 checks em 320/390/599/600/768/1024/1440px: Institution Bar e Domain
Header únicos, navegação (ou busca Wiki) visível e sem overflow. Evidências em
`/home/piradopirata/aculta-suite-out/purpose-modes-head-30c-20261030/` e
`/home/piradopirata/aculta-suite-out/purpose-nojs-head-30c-20261030/`.
Essas matrizes auditam o que estava servido no HEAD registrado; não comprovam
cada interação/página interna, zoom, teclado completo, leitores de tela,
contraste WCAG 2.2 AA nem a distribuição dos estilos ainda locais da branch.
THEME-MF3 segue pendente.

#### Reteste posterior — raiz Conta a 400% (2026-10-30)

Reexecutei a matriz inteira depois da captura histórica: Firefox ESR visível,
Runtime em HEAD `2493d7535`, dez raízes públicas, zoom real a partir de 1280px.
O resultado atualizado é **29/30**, não 30/30: baseline 100% e zoom 200%/400%
passaram nas dez raízes, exceto Conta a 400%, quando a viewport equivale a 320
CSS px e `documentScrollWidth/bodyScrollWidth=328`; o shell e o H1 continuam
presentes. As outras nove raízes passaram os dois níveis de zoom sem overflow.
Capturas e relatório: `/home/piradopirata/aculta-suite-out/purpose-browser-zoom-full-20261030/`;
a captura de Conta/400% foi inspecionada.

Na comparação focal seguinte, `purpose-browser-zoom.py` injetou no DOM da sessão
Firefox `base.css` e `components/auth.css` da branch, além de simular no wrapper
CAPTCHA o contrato `data-portal-ui="challenge-frame"`. Conta passou 3/3
checks, inclusive 400%/320 CSS px sem overflow; evidência em
`/home/piradopirata/aculta-suite-out/account-zoom-candidate-20261030/` e captura
400% inspecionada. Isso confirma a candidata de apresentação, mas a falha no
Runtime permanece até o hook e a folha servidos serem integrados e revalidados;
no-JS, páginas internas e acessibilidade seguem pendentes. O harness agora
aceita `PURPOSE_ZOOM_PURPOSES` e registra candidatos a overflow para diagnóstico.

### Zoom real de 200%/400% nas dez raízes públicas (2026-10-30)

O novo harness reutilizável `purpose-browser-zoom.py` usou Firefox ESR visível e
atalhos reais do navegador, viewport inicial de dispositivo 1280px e perfil
temporário; verificação somente leitura em `toca/aculta_runtime`. As dez raízes
passaram 30/30 checks: 100% inicial, 200% (640px CSS) e 400% (320px CSS), shell
único, `main#main-content` e zero overflow horizontal. Capturas mostram o
reflow em cada purpose; resumo em
`/home/piradopirata/aculta-suite-out/purpose-browser-zoom-20261030/`.
**A inspeção de headings na mesma captura registrou zero H1 em SUPPORT e
COURSES no Runtime**, enquanto as outras oito raízes tinham um. O template
candidato local inclui a preservação do `page_title_block` nesses purposes e
seu teste Unit já existe, mas a correção ainda não está no Runtime, então esse
zoom não prova o reflow do conteúdo com título corrigido. O gate de headings
pós-candidato segue DEFERRED. Esta matriz não valida páginas internas, teclado,
leitores de tela ou WCAG 2.2 AA e não aprova THEME-MF3.

### Retificação de contrato visual do formulário SUPPORT (2026-10-30)

O teste anterior presumia que a rota pública renderizava `<fieldset>` e ações de
pagamento; a primeira execução confirmou que esses elementos não existem no
formulário atual, portanto suas 11 falhas de padding eram um falso negativo do
harness. `SupportForm::buildForm()` atualmente entrega introdução, estado de
serviço e bloco de outras formas de apoio, sem campos de cobrança, controles visíveis ou submit. O
harness agora mede esses consumers reais, a grade `donation-quick` e confirma que
nenhum controle de pagamento planejado aparece como funcional. Firefox ESR
visível passou 37/37 checks em 320/360/390/480/481/599/600/768/1024/1280/1440px,
sem overflow; `formWidth` ficou contido no viewport. Institution Bar e Domain
Header apareceram uma vez no SUPPORT do Runtime HEAD `30c6770f3`. Evidências em
`/home/piradopirata/aculta-suite-out/support-form-final-20261030/`.
Não houve submissão; não cobre checkout, pagamento, ACL, teclado, zoom ou WCAG.

### Reteste de autenticação e `page-columns` (2026-10-30)

`auth-layout.py` passou 28/28 com baseline resolvido pelo commit anterior à
correção dos seletores reais, em 11 larguras: paddings candidatos, ausência de
overflow e classes `path-entrar`, `path-criar-conta` e
`path-recuperar-senha`. `auth-route-runtime.py` também passou 12/12 na rota
anônima real `/entrar` (CSS candidata injetada, nenhum envio de form). O SDC
`page-columns` passou 70/70 na fixture em dez larguras para variantes de uma,
duas e três colunas, ordem DOM e overflow; não possui consumer de produto
homologado. Evidências em `/home/piradopirata/aculta-suite-out/`:
`auth-layout-default-baseline-20261030`, `auth-route-runtime-resume-20261030` e
`page-columns-resume-20261030`. Esses resultados não validam formulários reais de
cadastro/recuperação, autorização, consumo Drupal do SDC ou THEME-MF3.

### Conta autenticada e endereço unificado — reteste 2026-10-30

Com a conta sintética UID 5 e Firefox ESR visível, `account-shell.py` passou
33/33 checks em 320/360/390/599/600/767/768/1024/1280/1440px, com CSS candidato
injetado apenas na sessão: as 13 seções aparecem iguais no menu desktop e no
acordeão mobile, a coluna/layout mudam no breakpoint esperado e não há overflow.
A Institution Bar permaneceu única. A sessão GET-only não salvou preferências.
Evidência: `/home/piradopirata/aculta-suite-out/account-shell-runtime-retest-20261030/`.

`account-address.py` foi executado contra o Runtime confirmado no HEAD
`4307e6fe9`, com UID sintético 5: 29/29 checks em JavaScript e sem JavaScript.
`/dados/endereco` apresentou uma instância do formulário canônico, todos os
campos esperados, país oculto e behavior de `aculta_portal_cep`; `/dados` e a
aba Endereço estavam ativos no shell legado. A geometria do agrupamento existente
coube no conteúdo e não houve overflow em 320/360/390/599/600/768/1024/1280/1440px;
o fallback sem JS passou em 320/390/768/1440px. Nada foi submetido/salvo; as
capturas mascaram valores e ficam fora do Git em
`/home/piradopirata/aculta-suite-out/account-address-head-4307-20261030/`.
Isto não cobre lookup CEP ao vivo, ACL entre contas, teclado completo, zoom ou
WCAG 2.2 AA.

Reteste independente da Conta em 2026-10-30, com Runtime no HEAD `2493d7535`:
`account-shell.py` passou 33/33 checks em 320/360/390/599/600/767/768/1024/
1280/1440px com a folha candidata injetada no Firefox, sem submit ou gravação
de preferências. As 13 seções dos menus desktop e mobile coincidiram, não houve
overflow e a Institution Bar apareceu uma vez. A captura de 320px foi inspecionada.
Snapshot pré-sessão: 624 tabelas, 58,555,853 bytes, SHA-256
`ba691089b5237404f4e47b62f746c0d79f0413fe1a67c743aa32f47ff8a6c647`, em
`/home/piradopirata/aculta-suite-out/runtime-snapshots/runtime-before-account-visual-20261030.sql`.
Capturas e resumo: `/home/piradopirata/aculta-suite-out/account-shell-f9-candidate-20261030/`.
Isto valida somente a navegação responsiva autenticada, não os formulários de
Conta nem S-R4/S-R5, ACL entre titulares, zoom ou WCAG completa.

### THEME-MF2 — contrato visual neutro para widgets de verificação (2026-10-30)

Firefox ESR visível reproduziu o overflow real em `/entrar` a 320 px: o conteúdo
disponível era 268 px, o widget flexível mede 300 px e a página chegava a 328 px;
a 360 px, o espaço disponível era 308 px. A folha não tenta redimensionar,
cortar nem identificar o provedor.

Implementação local: `CaptchaPresentationHooks` no Portal, executado depois do
hook contrib `captcha`, acrescenta o token neutro
`data-portal-ui="challenge-frame"` ao elemento de apresentação, inclusive em
formulários aninhados e sem duplicar tokens existentes. O tema usa esse contrato
para tornar `.aculta-content` um query container apenas quando existe o elemento
e amplia o formulário em 2rem somente quando a largura disponível é menor que
300 px. A ordem DOM, o widget `flexible`, seu token, sua validação e a política
de exceções não mudam. Não há referência a CAPTCHA/Turnstile em CSS.

PHP lint passou; Unit do hook passou 2 testes/4 assertions. Em Firefox ESR
visível, o CSS local foi injetado e o marcador foi simulado apenas no DOM, sem
submeter formulário: `/entrar` passou 12/12, `/criar-conta` 10/10 e
`/recuperar-senha` 10/10 verificações nas larguras 320/360/390/599/600/768/1024/
1280/1440px, sem overflow e mantendo o widget completo. As capturas em 320px
foram inspecionadas. Evidências em
`/home/piradopirata/aculta-suite-out/auth-captcha-frame-candidate-20261030/`,
`auth-register-captcha-candidate-20261030/` e
`auth-reset-captcha-candidate-20261030/`.

O Runtime servido em `2493d7535` ainda não tem o novo hook do Portal: a suíte
validou a CSS candidata com marcador simulado, não sua instalação real. Após
servir o código e reconstruir cache, repetir as três rotas com o marcador do
Portal efetivo, conferir estados com JavaScript/no-JS e formularios/`captcha_point`
ativos. Não homologa THEME-MF3. A investigação inicial do rodapé em dez purposes
(19/20) permanece evidência do mesmo overflow no CSS antigo; reteste após servir
o contrato novo ainda pendente.

Reteste posterior no checkout servido `2493d7535`, confirmado por Drush em
`aculta_runtime` e correspondente ao HEAD da PR antes e depois da rodada:
`purpose-headings.py` cobriu os dez purposes nos nove viewports exigidos
(320/360/390/599/600/768/1024/1280/1440). Todos os 90 H1 estavam visíveis em
`main#main-content`; os 90 pares Institution Bar/Domain Header estavam
presentes uma vez. O total foi 179/180 checks: a única falha de shell/overflow
segue Conta a 320 px (`documentWidth=328`), na rota de entrada com desafio de
300 px. Evidência:
`/home/piradopirata/aculta-suite-out/purpose-headings-pr-2493-full-20261030/results.json`.
O achado antigo de H1 ausente em Apoio/Cursos foi resolvido no código servido;
este teste valida headings e shell, não estados internos nem aprova THEME-MF3.

### THEME-MF2 — rechecagem do formulário de entrada estreito (2026-10-30)

Reexecutei `auth-route-runtime.py` em Firefox ESR visível contra o Apache local
do Runtime, em 320/360 px, com folhas candidatas injetadas apenas na sessão. O
markup anônimo real de `/entrar` foi confirmado; depois de acrescentar ao DOM o
marcador `data-portal-ui="challenge-frame"` (simulação explícita do hook PHP),
as duas larguras passaram junto com a checagem de rota: **3/3**. A 320 px, o
widget mede 300 px, o documento fica em 320 px e `scrollX=0`; a 360 px o widget
se ajusta a 308 px e o documento fica em 360 px. Capturas foram gravadas em
`/home/piradopirata/aculta-suite-out/auth-overflow-recheck-20261030/`; a captura
de 320 px foi inspecionada.

O resultado confirma a CSS candidata com o contrato simulado; não valida o hook
do Portal no Runtime nem a CSS servida sem override. Portanto a falha anterior
de 320 px continua aberta na instalação corrente até que a branch seja servida,
o cache reconstruído e a mesma rota seja retestada com o marcador produzido
server-side, inclusive sem JavaScript. Na repetição atual, o hook passou no
PHPUnit (**2 testes/4 assertions**) e a comparação Firefox candidatada repetiu
**3/3** em 320/360 px. Evidência mais recente:
`/home/piradopirata/aculta-suite-out/auth-overflow-repeat-20261030/`. Como o CSS
e o marcador foram injetados no DOM, esse resultado confirma a candidata, não a
instalação servida. Não aprova THEME-MF3.

### Registro histórico — gates de fronteira e catálogo em Eventos/Índice

A auditoria anterior encontrou classes de design diretamente nos formulários de
organização de Eventos e tokens de apresentação ausentes do mapa/documentação.
Esse bloqueio foi corrigido nesta branch no commit `9ea2e45f1`: os módulos usam
`data-portal-ui`, `PortalUiHooks` traduz os tokens (inclusive em inputs), e a
apresentação de `RelatedContentBlock` pertence ao tema. O Pede-A histórico para
essa família está resolvido; evidência atual abaixo. A resolução não aprova o
gate global THEME-MF3.

### THEME-MF2 — regressão do carrossel editorial no HEAD local `3a245f1ff` (2026-10-30)

Reexecutei `editorial-carousel-layout.py` em Firefox ESR visível depois dos commits
locais desta rodada. Baseline (`HEAD^`) e candidata renderizaram capturas idênticas
em 320/360/390/575/576/599/600/768/769/1024/1280/1440 px; 24/24 verificações
passaram, sem overflow. Evidência em
`/home/piradopirata/aculta-suite-out/editorial-carousel-retest-20261030/`.
Como este HEAD não alterou a CSS do carrossel, a repetição é regressão do estado
atual, não nova prova de paridade editorial nem aprovação THEME-MF3.

### THEME-MF2 — organização de Eventos e bloco de conteúdo relacionado (2026-10-30)

Os consumidores reais de organizações de Eventos e `RelatedContentBlock` foram
migrados para contratos visuais `data-portal-ui` renderizados pelo tema. Removi
a biblioteca CSS inativa de `aculta_index` e centralizei a apresentação no tema;
a tabela do modal agora rola em seu contêiner, e os botões da busca têm alvo
mínimo de 44 px. Em `EventOrganizationPageController`, os links passaram a ser
render arrays tipados em vez de HTML de lista montado manualmente. Não foram
alterados permissões, serviços, rotas ou operações funcionais.

Gates locais executados: fronteira módulo/tema PASS (3.488 checks); catálogo de
tokens PASS (515 mapeados, 420 usados, 149 atributos); fundamentos PASS (2.429
checks); schemas SDC PASS (55 componentes, 759 checks); Stylelint PASS; PHP
lint e PHPCS PASS; Unit PASS (83 testes/420 assertions); Kernel PASS (15
testes/399 assertions, 34 depreciações de Core/contrib) no banco dedicado
`aculta_simpletest`. Runtime não foi alterado.

Firefox ESR visível, com o Bootstrap local efetivamente usado pelo tema, passou
74/74 verificações da fixture em 320/360/390/599/600/768/1024/1280/1440 px,
claro/escuro: zero overflow horizontal, grids progressivos, rolagem isolada da
tabela, botão de 44 px, superfície em ambos os modos e foco de teclado visível.
Capturas de 320 e 1440 px foram inspecionadas. Evidência:
`/home/piradopirata/aculta-suite-out/aculta-events-organization-layout-r6/`.
A fixture cobre CSS e markup representativo, não markup renderizado/ACL/AJAX de
Drupal. THEME-MF3 permanece pendente até os gates globais e de integração real.

### Revista — capa editorial servida e referência OpenCulturas verificada (2026-10-30)

A raiz pública `https://revista.aculta.toca.net.br/` foi aberta no Firefox ESR
visível, anônima e somente GET, contra o docroot de desenvolvimento em
`2493d7535`. O Runtime já contém quatro publicações sintéticas autorizadas para
inspeção humana (NIDs 599–602); esta rodada não criou nem alterou conteúdo. A
View e sua chamada hero, os quatro teasers, as imagens carregadas, Institution
Bar e Domain Header passaram **10/10 verificações** em 320/360/390/599/600/768/
1024/1280/1440px, sem overflow. Capturas de 320 e 1440px foram inspecionadas em
`/home/piradopirata/aculta-suite-out/magazine-cover-runtime-current-20261030/`.
O shell e o estado servido foram lidos; as alterações locais de Eventos no
docroot permaneceram intactas.

O tarball oficial OpenCulturas 3.1.5 foi conferido novamente: SHA-256
`2cc0a34293e3cd7ccff633d07d61844afc1cec752d506a955c49a319fcbbb406`. Para os
teasers, `openculturas/profile/themes/opcult/sass/abstracts/_teasers.scss`
documenta uma família configurável de superfície, raio, padding e sombra; o
Twig `templates/node/node--teaser-unified.html.twig` delega ao teaser unificado.
Isso é referência de composição, não autorização para copiar folhas, imagens,
SVGs ou template. A capa ACULTA atual reutiliza os SDCs `patterns/hero` e
`patterns/card`, apresenta publicação destaque e lista editorial real, empilha
imagem/conteúdo no móvel e amplia para grade progressiva em telas maiores. A
captura valida o consumer servido; sem uma homepage equivalente autenticada do
OpenCulturas e sem baseline pareado de conteúdo, não se declara paridade visual.

Antes da inspeção foi produzido snapshot íntegro do `aculta_runtime`
(624 tabelas), armazenado fora do Git com modo restrito; SHA-256:
`665c41fcdce303d6bc4d1171ea3421c8e42de53d5261c80e5c23d5a955c1fdae`.
Não houve escrita de conteúdo ou configuração no Runtime durante a inspeção da
capa. A autenticação administrativa descrita abaixo criou metadados transitórios
de sessão/login no Runtime.

### THEME-MF2 — barra multidomínio com sessão administrativa (2026-10-30)

O Firefox ESR visível autenticou a conta administrativa sintética pelo host de
Conta e verificou a home MAIN servida no Runtime, sem CSS/JS de override. O
harness confirmou uma única Institution Bar, o offset da barra superior, a
estabilização no carregamento e a posição após scroll de 80/240/480 px. Foram
aprovadas 38/38 verificações em 320/390/1023/1024/1280 px, incluindo mudança de
viewport 320→1024→320. Em 320/390 px a barra acompanha a barra administrativa
enquanto ela está visível e ocupa o topo depois que ela sai da viewport; em
1024/1280 px mantém offset fixo de 64 px. Não houve overflow horizontal nem
mudança tardia de posição. Capturas móveis inicial e após scroll foram
inspecionadas em
`/home/piradopirata/aculta-suite-out/institution-bar-admin-default-login-20261030/`.

O ULI foi iniciado no host de Conta configurado em `deploy.json`; o primeiro
UID administrativo disponível nesse ambiente redireciona para recuperação, por
isso o teste permite credencial sintética fornecida pelo ambiente como fallback.
Credenciais não foram gravadas em artefatos nem impressas. A autenticação criou
metadados transitórios de sessão/login no Runtime; não houve alteração de
conteúdo ou configuração. Este teste fecha o cenário de scroll administrativo,
mas não cobre as demais rotas, zoom, teclado completo ou WCAG 2.2 AA e não aprova
THEME-MF3.

### OCS-F8 / THEME-MF2 — navegação e listagens do Green Stash (2026-10-30)

`studio.css` apresenta a navegação contextual existente de nove áreas, busca
GET, cartões de produto e remessa sob `.studio.aculta-green-stash`. A base
mobile usa colunas fluidas, links de 48px, foco de 3px e controles de formulário
de 44px; modo escuro usa tokens Fidelity existentes. Não foram alterados render
arrays, dados, Form API, ACL, Commerce ou shell. `npm run lint:css` passou.
Firefox ESR visível passou 74/74 verificações na fixture que replica a
estrutura renderizada do controller, em 320/360/390/599/600/768/1024/1280/1440px
e claro/escuro; sem overflow, com links/estados, cartões, formulário e foco
observados. Capturas revisadas em
`$SUITE_OUT/green-stash-layout-20261030/`. Evidência da CSS candidata, não de
rota real, operador ou grants por loja. OCS-F8, OCS-F11 e THEME-MF3 continuam
pendentes.

### OCS-F2 / THEME-MF2 — reteste real de navegação e alvos (2026-10-30)

`studio.css` aplica alvos de 44×44 CSS px também aos links contextuais de Conta
sob `.studio__subtabs`, mesmo quando o render não fornece a classe interna
`.studio__link`. A ordem DOM, destinos, estado `aria-current`, wrap e shell
permanecem. No Runtime identificado em `toca`, Firefox ESR visível com a folha
candidata injetada sobre o markup servido passou 79/79 verificações na conta
editorial comum (um `SKIP` por não haver publicação própria para testar edição)
e 80/80 com a conta sintética `aculta_visual_admin`, proprietária dos quatro
artigos fictícios autorizados. A lista real mostrou quatro linhas com links
canônicos `/marley/revista/{id}/editar`. Foram medidos `/marley`, as cinco abas
editoriais da seção Publicações, criação da Revista, Fórum, Galerias e navegação
contextual de Conta; todos os alvos examinados tiveram ao menos 44px. Não houve
overflow em 320/360/390/599/600/767/768/1024/1280/1440px no Marley, nem em
Conta a 320/390/768/1280px. Contraste textual medido: 6,59:1 claro e 11,51:1
escuro; geometria igual nos dois modos. Capturas estão em
`/home/piradopirata/aculta-suite-out/studio-visual-candidate-20261030e/`,
`/home/piradopirata/aculta-suite-out/studio-editorial-rows-retest-20261030/` e
`/home/piradopirata/aculta-suite-out/studio-editorial-mobile-20261030/`;
desktop e mobile foram revistos.

O teste injeta a CSS do worktree no Firefox e não demonstra que ela já esteja
implantada no Runtime. Também não cobre ausência de JS, zoom 200/400%, todos os
consumidores, navegação completa por teclado, reduced motion ou auditoria WCAG
2.2 AA. A captura da tabela a 390px mantém `table-responsive` e scroll dentro
do componente, sem overflow do documento. OCS-F2/F5 seguem parciais; THEME-MF3
e OCS-F11 continuam pendentes. A fixture sintética anterior (243/243 em 27
larguras) continua como evidência complementar.
