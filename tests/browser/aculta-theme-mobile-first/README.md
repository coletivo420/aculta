# ACULTA420 — SDC responsivos do tema

## ACULTA STUDIO GREEN STASH — navegação e listagens

`green-stash-layout.py` valida a folha candidata das áreas comerciais integradas
ao Studio. A fixture replica os wrappers e links emitidos hoje por
`GreenStashNavigation`, `GreenStashController` e o formulário de rascunho para
Visão geral, busca, produto/remessa, detalhe e criação inicial. No Firefox ESR
visível, mede overflow, links atuais, áreas tocáveis, cartões, formulário,
foco visível e os avisos reais de estado/escopo em claro/escuro, em
320/360/390/599/600/768/1024/1280/1440px. Também aplica zoom real do Firefox
em 200%/400% nos dois temas e mede o encaixe dos formulários, links, avisos,
cartões e estado do produto. As capturas 320/390/1440 incluem Visão geral,
detalhe e formulário de criação, que salva somente rascunho. No reteste de
2026-10-31, passou 186/186 checks; corrigiu-se o `min-width:auto` do formulário
de criação que causava overflow a 320px e 400%. Evidência em
`/home/piradopirata/aculta-suite-out/green-stash-detail-create-homologation-20261031/`.
A Visão geral só informa que operações comerciais ainda não estão disponíveis
naquela tela. É fixture local
de CSS: não autentica operadores, não valida grants por loja/dados reais e não
homologa OCS-F8/F11 nem THEME-MF3.

A fixture também verifica que o menu integrado Green Stash mantém suas três
áreas em uma linha, com links de pelo menos 44px, inclusive a 320px e com zoom
do navegador, sem alargar o documento. É um teste local do consumer e não
substitui a captura da rota real.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/green-stash-layout
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/green-stash-layout.py
```

PNGs e `report.json` são gravados somente em `$SUITE_OUT`.

## Capa pública da Revista no Runtime

`magazine-cover-runtime.py` abre a raiz `https://revista.aculta.toca.net.br/`
no Firefox ESR visível, em modo anônimo e somente GET. Um proxy local restrito
ao host da Revista encaminha TLS para Apache em `127.0.0.1:443`; SNI e Host
permanecem iguais ao domínio canônico. Qualquer outro CONNECT HTTPS é negado.
Isso evita alterar `/etc/hosts` e não acessa outros domínios.

O teste mede View, chamada hero, número de publicações, imagem da primeira
capa, colunas responsivas, presença única de Institution Bar/Domain Header,
alvos de 44px, geometria de raio/sombra em todos os cartões editoriais e
overflow em 320/360/390/599/600/768/1024/1280/1440px. Também
usa Tab real do WebDriver para alcançar o título e o CTA da publicação em 320px,
verificando `:focus-visible`, contorno de pelo menos 2px e encaixe no viewport.
Mede contraste dos links em claro/escuro e aplica zoom real de 200%/400%,
verificando os alvos, o overflow e se título/CTA podem ser trazidos à área
visível. Também desativa JavaScript do site e confere conteúdo renderizado,
links nativos, colunas, alvos e shell em todas as larguras. No modo candidato,
WebDriver injeta somente a folha CSS no documento para medir o estilo sem
reativar scripts da página. Requer `xdotool` para a medição de zoom. Não
autentica, não cria conteúdo, não verifica o gate completo de acessibilidade e não substitui
comparação pareada com o OpenCulturas Next.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/magazine-cover-runtime
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/magazine-cover-runtime.py
```

`MAGAZINE_COVER_WIDTHS` seleciona larguras focais. O perfil Firefox temporário
e screenshots/`report.json` ficam em `$SUITE_OUT` e são removidos/preservados
conforme o ciclo de vida documentado pelo harness; nenhum artefato visual é
gravado no repositório.

`MAGAZINE_COVER_FALLBACK_CANDIDATE=1` testa também a capa sem imagem: substitui
temporariamente o primeiro media wrapper por markup sintético equivalente ao
render array decorativo do template candidato, verifica fundo em claro/escuro,
dimensão, proporção 16:9 abaixo de 992px e overflow nos mesmos viewports, e grava
capturas de 320/1440px. Em telas a partir de 992px a capa preenche a altura da
composição editorial, como a mídia real. Use junto de
`MAGAZINE_COVER_CSS_OVERRIDE=1`. A injeção não executa o Twig candidato nem
altera a publicação no Runtime.

Registro histórico: numa versão anterior do Runtime, a CSS servida passou 5/28
e falhou 23/28 por alvos pequenos em nove larguras, links no zoom e CTA com
19px, inclusive sem JavaScript. A folha local injetada naquela sessão passou
28/28, mas não comprovava publicação.

Reteste integrado em 2026-10-31, Runtime destacado em `8d7f1d576`, Firefox ESR
visível, sem CSS/JS candidatos, passou **28/28** sobre a página pública e seus
quatro artigos sintéticos já existentes. A CSS servida pelo Runtime e a CSS do
checkout têm SHA-256 idêntico (`7a6763239db34f26c533b2761a8342ffd9c28b79dc151ee8bc3265a304588b27`).
O reteste cobriu nove larguras, teclado, claro/escuro, zoom real de 200%/400%,
JavaScript habilitado/desabilitado, imagem carregada, grade adaptável, alvos de
44px e ausência de overflow. Contraste dos links: 12,40:1 em claro e 14,50:1
em escuro. Capturas de 320/1440px e relatório:
`/home/piradopirata/aculta-suite-out/magazine-cover-served-8d7f1d576-20261031/`.
Foi uma leitura anônima; não criou nem alterou conteúdo.

Com `MAGAZINE_COVER_FALLBACK_CANDIDATE=1` e
`MAGAZINE_COVER_CSS_OVERRIDE=1`, passou **37/37 checks**, inclusive nove
medições sintéticas da área de capa sem imagem em claro/escuro e em todas as
larguras. O harness substitui o wrapper de mídia no documento por uma `div`
decorativa; não executa o Twig nessa variante. No Runtime, `field_media_image`
é obrigatório para `revista`, e todos os quatro artigos de inspeção têm mídia,
por isso nenhum artigo publicado foi artificialmente salvo sem o campo.
Capturas e log: `/home/piradopirata/aculta-suite-out/magazine-cover-fallback-candidate-8d7f1d576-20261031/`.

Em rodada anterior, a folha candidata também passou 28/28 sobre conteúdo real.
Relatórios e capturas:
`/home/piradopirata/aculta-suite-out/revista-cover-zoom-reachability-baseline-final-20261031/`
e `/home/piradopirata/aculta-suite-out/revista-cover-nojs-candidate-20261031/`;
a rodada sem injeção está em
`/home/piradopirata/aculta-suite-out/revista-cover-nojs-baseline-20261031/`.

## Publicação editorial da Revista

`revista-article-layout.py` compara o artigo editorial antes/depois da folha
candidata em 320/360/390/575/576/600/768/1024/1280/1440 px. Mede alinhamento
do texto, padding do resumo, alinhamento de categoria/tags, ordem do DOM e
overflow. As capturas ficam em `$SUITE_OUT`. Usa fixture local em `data:`; não
acessa nem altera o Runtime e não substitui revisão do artigo real, teclado,
zoom, modo escuro ou WCAG 2.2 AA.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-revista-article
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/revista-article-layout.py
```

`REVISTA_BASELINE_REF` escolhe a folha anterior (padrão `HEAD^`), e
`REVISTA_ARTICLE_WIDTHS` aceita larguras focais. Requer Firefox ESR visível,
geckodriver e `websockets`; PNGs e `summary.json` são gravados fora do Git.

Reteste visível em 2026-10-31: 20/20 verificações passaram em Firefox ESR nas
dez larguras. A captura de 320 px foi inspecionada; título, resumo, corpo,
categoria e tags permanecem na ordem do DOM, sem alargar o documento. O teste
compara CSS sobre fixture sintética; não valida render array, campos ou conteúdo
de uma publicação real. Capturas e relatório em
`/home/piradopirata/aculta-suite-out/revista-article-visual-followup-20261031/`.

## Artigo editorial da Revista no Runtime

`revista-browser-zoom.py` verifica a raiz pública da Revista e um artigo
sintético autorizado. No Firefox ESR visível, mede título, corpo renderizado,
capa carregada, shell único e overflow do artigo em 320/360/390/599/600/768/
1024/1280/1440px; também aplica zoom real de 200% e 400%. Capturas do título e
do primeiro bloco do corpo em 320 e 1440px, e nos dois níveis de zoom, vão para
`$SUITE_OUT`. A execução de 2026-10-31 passou 15/15 checks com o conteúdo
sintético no formato editorial ativo.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/revista-article
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta \
  DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/revista-browser-zoom.py
```

`REVISTA_BASE_URL`, `REVISTA_ARTICLE_URL` e `REVISTA_ARTICLE_WIDTHS`
selecionam host, publicação e larguras. Com
`REVISTA_POST_CSS_CANDIDATE=1`, as folhas locais indicadas por
`REVISTA_CSS_ROOT` são injetadas somente na sessão do navegador para comparar
a aparência; isso não altera o Runtime nem prova que ele serve o HEAD do
worktree. O teste não submete comentários, não homologa acesso entre contas,
teclado completo, leitor de tela, reduced motion ou WCAG 2.2 AA integral, e não
é comparação pareada de fidelidade com o OpenCulturas Next.

Reteste visível em 2026-10-31 com `REVISTA_POST_CSS_CANDIDATE=1`: passou 15/15
checks na home e no artigo sintético real do Runtime, incluindo CSS candidata
injetada na sessão, capa carregada, um H1, shell único, dez larguras e zoom real
de 200%/400%, sem overflow. Conteúdo/configuração não foram alterados; Runtime
continua sem servir a folha candidata. Capturas e JSON em
`/home/piradopirata/aculta-suite-out/revista-article-runtime-branch-followup-20261031/`.

## Navigation top-bar mobile-first

`navigation-top-bar.py` compara a composição do Drupal Navigation top-bar antes
e depois da CSS candidata. Mede uma coluna e a ordem vertical em
320/360/390/575/576/599/600/767px; a partir de 768px verifica a grade original
de três regiões, alinhamentos e wrapping, além de ordem DOM e ausência de
overflow. O teste usa fixture sintética: não autentica, não acessa Runtime e
não substitui a conferência da barra administrativa nas páginas reais.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-navigation-top-bar-layout
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/navigation-top-bar.py
```

`TOP_BAR_BASELINE_REF` seleciona a CSS de baseline (padrão `HEAD^`) e
`TOP_BAR_WIDTHS` aceita larguras separadas por vírgula. O script usa Firefox ESR
visível via geckodriver; PNGs e `summary.json` são gravados em `$SUITE_OUT`.
Não cobre zoom, tema escuro, reduced motion, teclado completo ou WCAG 2.2 AA.

## Identidade SDC de Perfil mobile-first

`profile-identity-layout.py` compara a CSS baseline/candidata do SDC
`profile-identity` em 320/352/353/360/390/575/576/599/600/767/768/1024/1280/
1440px. Mede alinhamento, tamanho do avatar, direção, overflow, ordem DOM e
capturas. A composição compacta deve ocupar até 352px e o avatar de 72px volta
a partir de 353px. Usa fixture local, sem Runtime nem dados de perfil reais.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-profile-identity
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/profile-identity-layout.py
```

`PROFILE_IDENTITY_BASELINE_REF` escolhe a CSS baseline (padrão `HEAD^`) e
`PROFILE_IDENTITY_WIDTHS` aceita larguras focais. Requer Firefox ESR visível,
geckodriver e `websockets`; os PNGs e o resumo ficam em `$SUITE_OUT`. Não
substitui validação das rotas privadas/públicas, teclado, zoom, modo escuro ou
WCAG 2.2 AA.

`author-card.py` valida o SDC de autoria no consumer público da lista de tópicos
do Fórum. O script usa GETs e mudança de viewport; não autentica, submete
formulários nem altera dados. Confere o breakpoint de alinhamento em 576 CSS px,
consumer presente e overflow horizontal em larguras de 320 a 1440 px.
Na rodada de 2026-10-29, o teste também motivou a quebra natural da data de
última atividade em colunas estreitas entre 576 e 600 px. Com
`AUTHOR_CARD_CSS_OVERRIDE=1`, injeta apenas a folha local do SDC e cria links
sociais sintéticos invisíveis ao usuário para conferir seus alvos de 44×44px em
cada viewport; a rota real continua sendo medida para overflow e layout.

## Ambiente

Roda no servidor de desenvolvimento com Firefox ESR visível via `DISPLAY=:0` e
geckodriver. O padrão de URL é `https://growroom420.aculta.toca.net.br`; isso não
valida os ambientes de produção. Reutiliza `tests/browser/aculta-cpf-checkout/wdlib.py`
e requer o pacote Python `websockets`.

## Execução

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/suite-out/aculta-theme-author-card \
ACULTA_FF_PROFILE_ROOT=/home/piradopirata/suite-out/aculta-theme-author-card/profiles \
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/author-card.py
```

`FORUM_BASE_URL` pode apontar a outro host de teste com a mesma rota e consumer.
`AUTHOR_CARD_WIDTHS` aceita uma lista separada por vírgulas para uma rodada
focal, por exemplo `576,600`; sem ela, o script executa todos os viewports.
`AUTHOR_CARD_CSS_OVERRIDE=1` ativa a folha candidata e a fixture dos links
sociais descrita acima; por padrão, a página é medida sem override.
`SUITE_OUT` recebe screenshots e `summary.json`; o WebDriver remove o perfil
temporário ao fechar.

## Limitações

O smoke cobre o alinhamento do cartão autor no consumer de listagem, dimensões
responsivas básicas e overflow. Não verifica o cartão em todos os purposes,
teclado, zoom real, dark mode, leitor de tela nem WCAG 2.2 AA completa.

## Layout `page-columns`

`page-columns.py` compara a CSS committed em `PAGE_COLUMNS_BASELINE_REF` (padrão
`HEAD^`) com a folha candidata local numa fixture sem dados Drupal. Mede as
variantes de uma, duas e três colunas em 320/360/390/599/600/767/768/1024/1280/
1440 CSS px, ausência de overflow, ordem DOM e preservação da composição ampla
a partir de 768px. O layout SDC continua sem consumer de produto; este smoke não
substitui renderização no UI Patterns Library nem homologação de consumer real.
É um teste local da CSS candidata em fixture `data:`, não acessa nem valida o
Runtime.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-page-columns \
ACULTA_FF_PROFILE_ROOT=/home/piradopirata/aculta-suite-out/aculta-page-columns/profiles \
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/page-columns.py
```

Usa Firefox ESR visível com geckodriver e `tests/browser/aculta-cpf-checkout/wdlib.py`.
PNG e `summary.json` ficam em `SUITE_OUT`; o perfil temporário é removido pelo
WebDriver ao encerrar.

## Shell autenticado da Conta

`account-shell.py` usa a Conta sintética UID 5 via link único de login mantido
somente em memória. Abre `/configuracoes` por GET e confere o menu desktop,
sanfona, layout, Institution Bar e overflow em 320/360/390/599/600/767/768/
1024/1280/1440 px. Na sanfona móvel verifica a grade de duas colunas, ordem
igual ao menu desktop, largura completa para seção aberta e logout, targets de
44px ou mais e ausência de overflow. Não envia formulários nem grava
preferências.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/suite-out/aculta-account-shell \
ACULTA_FF_PROFILE_ROOT=/home/piradopirata/suite-out/aculta-account-shell/profiles \
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/account-shell.py
```

`ACCOUNT_BASE_URL` e `ACCOUNT_DRUSH_URI` devem usar o host de teste de Conta;
`ACCOUNT_TEST_UID` aceita uma conta de teste autorizada. `ACULTA_PROJECT_ROOT`
seleciona o checkout com Drush e banco do Runtime. `ACCOUNT_CSS_OVERRIDE=1`
injeta a folha candidata local no DOM para comparar antes da publicação; sem
essa variável, o browser usa o CSS que o Drupal serviu. `ACCOUNT_COLOR_MODE`
aceita `light` ou `dark` e altera somente a preferência visual da sessão.

O teste cobre somente navegação responsiva e a presença da Institution Bar na
página autenticada `/configuracoes`. Não valida as tabelas de pedidos, todos os
submenus, teclado completo, zoom real, cache/ACL entre contas nem WCAG 2.2 AA.

## Formulário unificado de endereço

`account-address.py` valida por GET o formulário canônico `/dados/endereco`,
acessado no shell legado pela navegação Meus Dados → Endereço. Confere uma
única instância do formulário, os campos acordados, país oculto, carregamento
do behavior de `aculta_portal_cep`, agrupamento de Número/S/N/Complemento e
overflow em 320/360/390/599/600/768/1024/1280/1440 px. Repete a verificação de
layout sem JavaScript em 320/390/768/1440 px. Também mede zoom real do Firefox
ESR em 200% e 400%, com JavaScript e no-JS, verificando overflow e visibilidade
do CEP. O WebDriver enquadra o campo CEP na captura sem depender do JavaScript
da página; valores de input são mascarados.
Não submete nem salva o endereço; as capturas ficam fora do repositório.
O ULI abre uma sessão autenticada temporária e grava metadados de sessão no
Runtime; antes de executar, siga a regra de snapshot em `AGENTS.md`. O comando
de zoom usa `xdotool`; PNGs e `summary.json` ficam em `$SUITE_OUT`. O smoke não
substitui teste de submissão, acessibilidade completa, ACL entre contas ou
validação do lookup ao vivo do CEP.

```sh
cd /home/piradopirata/projetos/aculta
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-account-address
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
export ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/account-address.py
```

`ACCOUNT_ADDRESS_BASE_URL`, `ACCOUNT_ADDRESS_DRUSH_URI` e `ACCOUNT_ADDRESS_UID`
selecionam o host, URI Drush e conta de teste autorizada. O padrão é o host de
Conta do servidor de desenvolvimento e UID 5. Requer Firefox ESR visível,
geckodriver, Drush local, sessão X11 e pacote Python `websockets`. O smoke não
substitui teste de submissão, acessibilidade completa, ACL entre contas ou
validação do lookup ao vivo do CEP.

## Purpose SUPPORT e `donation-quick`

`support-form.py` mede o formulário público e o SDC `donation-quick` em Firefox
ESR no servidor de desenvolvimento (`https://apoio.aculta.toca.net.br/`).
Verifica formulário, introdução, outras formas de apoio, link de contato,
cartões, Institution Bar, Domain Header, overflow e limites da grade em
320/360/390/480/481/599/600/768/1024/1280/1440 CSS px. Confirma também que o
formulário ainda não apresenta fieldsets, campos de cobrança ou botão de envio
que fariam parecer operacional um fluxo não implementado. É somente leitura:
não envia formulário nem altera configuração. No HEAD Runtime `30c6770f3`, os
dois headers renderizaram uma vez na rota SUPPORT.

Reteste visível em 2026-10-31: **37/37 checks** em 11 larguras; o formulário e
os cartões permaneceram dentro do viewport, sem overflow e sem controles de
cobrança/submit não implementados. Capturas de 320 e 1440 px revisadas em
`/home/piradopirata/aculta-suite-out/support-form-followup-20261031/`. Não cobre
teclado, zoom, modo escuro, reduced motion ou WCAG integral.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-support-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 tests/browser/aculta-theme-mobile-first/support-form.py
```

O Firefox usa perfil isolado em `$SUITE_OUT`; screenshots e `results.json` ficam
fora do repositório. Requer DNS e acesso ao servidor de desenvolvimento. Não
submete Form API, não exercita checkout/CAPTCHA, teclado/zoom/leitor de tela,
dados, pagamento, cache, permissões ou WCAG completa.

## Navegação global e fallback sem JavaScript

`navigation.py` verifica a navegação da home MAIN em Firefox ESR visível,
incluindo o breakpoint desktop em 1100px, o espaçamento aos lados de 576px,
abertura do menu Bootstrap e sua apresentação sem JavaScript. É somente leitura.
O teste também acrescenta temporariamente um rótulo longo à lista no DOM e
confere que ele quebra dentro do link em telas abaixo de 1100px, enquanto o
menu desktop mantém a apresentação em uma linha.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-navigation-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 tests/browser/aculta-theme-mobile-first/navigation.py
```

`NAVIGATION_CSS_OVERRIDE=1` injeta a folha local do worktree para comparar o
candidato antes de publicar; sem essa variável, usa a CSS servida. A suíte mede
somente a home MAIN e não substitui a regressão da navegação nos demais purposes,
nem cobre zoom, leitor de tela ou WCAG completa.

## Institution Bar global

`institution-bar.py` compara o CSS servido ou uma folha local candidata na
Institution Bar em todos os dez purposes atuais: MAIN, Conta, SUPPORT, Revista,
Wiki, Loja, Cursos, Fórum, Links e Eventos. Lê os hosts de
`aculta_deployer/config/deploy.json`, testa o consumer real `ul.nav` (além de
`.navbar-nav`), as dimensões, a presença única da barra e overflow em
320/374/375/600/767/768/1440 px. Só faz GET e salva capturas em `$SUITE_OUT`.
Também verifica que a marca institucional é visível desde 320px e que a barra
pode quebrar em linhas sem rolagem horizontal. A rota Magazine está incluída
nas capturas de 320/768/1440px porque pode não renderizar Domain Header.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-institution-bar
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 tests/browser/aculta-theme-mobile-first/institution-bar.py
```

Para auditar o layout servido antes da mudança, use `INSTITUTION_BAR_EXPECT=baseline`.
Para injetar o CSS do checkout e validar o candidato antes da publicação,
defina `INSTITUTION_BAR_EXPECT=candidate INSTITUTION_BAR_CSS_OVERRIDE=1`.
`INSTITUTION_BAR_EXPECT` sem override espera a geometria mobile-first com a
marca visível e reflow da barra.
`INSTITUTION_BAR_DOMAIN_ENV=test` (padrão) escolhe os `test_host`; use `production`
durante a transição Alpha, após verificar DNS, TLS e qual ambiente está servido,
para ler os `production_host` da mesma tabela. O teste não presume que o host de
teste continue sendo canônico depois dessa migração. Não testa menus autenticados
completos, teclado, zoom real, tema escuro ou WCAG 2.2 AA; registra a presença do
Domain Header para o Pede-A de composição.
No cenário `admin`, `INSTITUTION_BAR_DRUSH_URI` deve apontar para a URI interna
do Drush (`http://default` no servidor documentado); `INSTITUTION_BAR_LOGIN_URI`
continua sendo o host HTTPS aberto pelo Firefox.

## Rail real do catálogo de Cursos

`rail-courses.py` verifica `/` no purpose COURSES: overflow da página, consumer
real do SDC `rail`, papel e nome acessível, foco por teclado e rolagem com
ArrowRight a 320px e 400% de zoom real. A 400%, a janela começa em 1280px e
Firefox deve reflowar para viewport CSS de 320px; o teste confirma que a página
continua contida, que uma seta avança, mantém indicador de foco visível com
outline de 3px e que uma sequência de setas alcança o último cartão. Captura
320/1440, o estado após a seta em 320px e o rail antes e depois da sequência de
teclado a 400% no Firefox ESR visível. Só faz GET, foco, tecla e ajuste de zoom;
não altera dados. O
conteúdo publicado precisa conter ao menos dois cursos para testar a rolagem.
Requer `xdotool` para zoom real. URLs derivam de
`aculta_deployer/config/deploy.json`.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-courses-rail
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/rail-courses.py
```

`RAIL_DOMAIN_ENV=production` escolhe o host de produção somente após confirmar
DNS/TLS e o ambiente servido. O teste não cobre conteúdo autenticado, leitores
de tela, modo escuro ou WCAG 2.2 AA. `RAIL_CSS_OVERRIDE=1` injeta a folha local
`components/patterns/rail/rail.css` somente na sessão Firefox para validar uma
mudança do padrão sem tocar no Runtime. `RAIL_DISABLE_JS=1` desativa o JavaScript
da página no Firefox para conferir o fallback nativo do rail; WebDriver continua
apto a medir DOM e enviar teclas.

## Cartões reais do catálogo de Eventos

`events-catalogue.py` injeta o CSS candidato do SDC `event_card` no catálogo
público e mede os wrappers do `item_list`, a moldura do SDC e overflow em
320/360/390/575/576/600/768/1024/1280/1440px. Captura 320/768/1440 no Firefox
ESR visível; só faz GET e não altera conteúdo. A injeção verifica a folha local
contra o consumer real, sem rebuild do stylesheet do Runtime. Também mede se
cada link de título do cartão tem ao menos 44px de altura.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-events-layout
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/events-catalogue.py
```

`EVENTS_DOMAIN_ENV=production` seleciona o host de produção configurado no
`deploy.json`; usar somente após conferir DNS/TLS e o ambiente servido.

## Institution Bar durante scroll e top-bar administrativa móvel

`institution-bar-scroll.py` compara o comportamento da barra ao carregar e
rolar as homes dos purposes configurados em 320/390 px como anônimo e a home MAIN como
UID administrativo em 320/390/1023/1024/1280 px. Confirma a autenticação antes
de medir toolbar/deslocamento, testa a mudança de breakpoint sem recarregar a
página, amostra a estabilização e percorre o ponto de fixação sticky em passos
de 12px nos dois sentidos em mobile; guarda screenshots em `$SUITE_OUT`. O ULI
fica em memória; se o ULI redirecionar para recuperação, o teste pode usar
credenciais do administrador sintético fornecidas pelo ambiente. O teste não
submete conteúdo nem altera configuração. No cenário administrativo também
captura o shell móvel antes e depois do scroll.
Na candidata mobile, verifica que a Institution Bar fica fixa em `top: 0` no
primeiro scroll não nulo e que a top-bar de ações administrativas fica
desativada até 1023px, inclusive no topo. A partir de 1024px, a top-bar conserva
seu comportamento desktop. A sessão sem JavaScript preserva o fallback sticky
natural; a fixação no primeiro scroll depende do JavaScript de apresentação.
O harness verifica a disponibilidade visual da top-bar e o shell tanto no
carregamento quanto após scroll e redimensionamento.
Com `INSTITUTION_BAR_SCROLL_CSS_OVERRIDE=1`, o harness atualiza a folha
candidata após a transição desktop→mobile e confirma que a regra do breakpoint
está presente antes de medir; esses resultados continuam sendo de CSS candidata.
Se uma rota retornar sem Institution Bar (por exemplo, página de erro), o teste
registra a falha daquela rota e continua nos demais purposes.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-institution-bar-scroll
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
export ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta
export INSTITUTION_BAR_DRUSH_URI=http://default
DISPLAY=:0 python3 tests/browser/aculta-theme-mobile-first/institution-bar-scroll.py
```

Para injetar a folha CSS local, defina `INSTITUTION_BAR_SCROLL_CSS_OVERRIDE=1`;
para injetar o JavaScript candidato, use `INSTITUTION_BAR_SCROLL_JS_OVERRIDE=1`.
Use ambos os overrides para validar a fixação antecipada contra um Runtime que
ainda não sirva estes arquivos.
`INSTITUTION_BAR_SCROLL_STALE_DISPLACEMENT_TEST=1` adiciona uma verificação
focal a 320px que simula `--drupal-displace-offset-top: 64px` e exige que a
Institution Bar continue com `top: 0` no mobile. A simulação é removida antes
das medições normais e não altera o Runtime. Use em conjunto com
`INSTITUTION_BAR_SCROLL_CSS_OVERRIDE=1` para verificar o CSS candidato; sem o
override, a mesma verificação atua como controle negativo da regra servida.
O JSON lista em `staleDisplacementSkips` as rotas sem altura de rolagem
suficiente para chegar ao limiar sticky; isso não conta como aprovação dessa
asserção.
`INSTITUTION_BAR_SCROLL_BASE_URL` restringe o teste a um host e
`INSTITUTION_BAR_DOMAIN_ENV=test|production` selecionam o host; a conta usada
é configurável com `INSTITUTION_BAR_ADMIN_UID` e deve ser autorizada para o
ambiente. O login inicia por padrão no host de Conta definido no `deploy.json`;
`INSTITUTION_BAR_LOGIN_URI` pode sobrescrevê-lo antes de voltar à home MAIN.
`INSTITUTION_BAR_SCROLL_SCENARIOS` aceita
`anonymous`, `admin` ou ambos (padrão). O relatório lista os elementos
`data-offset-top` sem recolher seus textos. A captura usa Firefox ESR visível.
`INSTITUTION_BAR_SCROLL_DISABLE_JS=1` testa o fallback CSS em um perfil Firefox
com JavaScript de página desativado. Esse teste focal não substitui a
matriz completa de larguras, teclado, zoom, dark mode ou WCAG 2.2 AA.

Reteste da nova decisão em 2026-10-31: o candidato passou 47/47 checks de uma
conta administrativa em 320/390/1023/1024/1280px; a top-bar fica `display:none`
até 1023px, o menu completo continua no desktop e a Institution Bar fixa no
primeiro scroll. Foi necessário aumentar a especificidade para vencer os
estilos dinâmicos da Navigation top-bar. A variante sem JavaScript passou 43/43
checks com a CSS candidata e sticky natural. CSS/JS foram injetados somente na
sessão; não prova a integração servida. Evidências em
`/home/piradopirata/aculta-suite-out/admin-topbar-mobile-final-assertion-8e0091a4a-20261031/`
e `/home/piradopirata/aculta-suite-out/admin-topbar-mobile-nojs-retest-8e0091a4a-20261031/`.

Após o commit `f378d2540`, a rodada integrada sem qualquer override passou
47/47 checks no Runtime `toca` em 320/390/1023/1024/1280px. Evidência em
`/home/piradopirata/aculta-suite-out/admin-topbar-mobile-integrated-f378d2540-20261031/`.
Essa execução confirma os arquivos servidos, diferentemente dos resultados
candidatos acima.

Para a conta administrativa sintética, o ULI é tentado primeiro. Se o site
encaminhar o ULI para recuperação de conta, o harness aceita login tradicional
por credenciais fornecidas somente no ambiente e tenta novamente sem imprimir
ou salvar os valores:

```sh
set -a
. /home/aculta/secrets/aculta_visual_admin.env
set +a
export INSTITUTION_BAR_ADMIN_UID=257
export INSTITUTION_BAR_ADMIN_LOGIN_USER="$ACULTA_VISUAL_ADMIN_USER"
export INSTITUTION_BAR_ADMIN_LOGIN_PASSWORD="$ACULTA_VISUAL_ADMIN_PASSWORD"
```


## Tabela de pedidos da Conta

`account-orders.py` usa Firefox ESR visível no servidor de desenvolvimento, abre `/pedidos` com uma sessão Drush efêmera e não submete formulários. Confere a tabela realmente renderizada (inclusive o estado vazio), overflow nas larguras padrão 320/360/390/599/600/767/768/1024/1280/1440 px e uma tabela sintética com quatro células/atributos `data-label`, sem dados de pedido. Quando `ACCOUNT_ORDERS_CSS_OVERRIDE=1`, compara o CSS candidato local com o CSS servido: em desktop, largura da tabela, padding/borda da célula e alinhamento devem permanecer idênticos ao baseline; no mobile, tabela/células empilham e o cabeçalho visualmente oculto continua no DOM.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-account-orders
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
export ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta
DISPLAY=:0 python3 tests/browser/aculta-theme-mobile-first/account-orders.py
```

`ACCOUNT_ORDERS_BASE_URL`, `ACCOUNT_ORDERS_LOGIN_URI` e `ACCOUNT_ORDERS_UID` selecionam o host e a conta autorizada; `ACCOUNT_ORDERS_WIDTHS` permite rodadas focais. `ACCOUNT_ORDERS_CSS_OVERRIDE=1` injeta `css/components/portal-account.css` do checkout do script. O runtime usado nesta rodada só tinha pedidos em rascunho para a conta autorizada, portanto não havia linha real de pedido para verificar a emissão de `data-label`; o teste registra essa parte como DEFERRED. Não cobre zoom, teclado completo, claro/escuro, reduced motion ou WCAG 2.2 AA.

## Composição institucional mobile-first

`institutional-layout.py` roda em Firefox ESR visível com fixture sintético,
comparando o CSS anterior (Git `HEAD^` por padrão) ao candidato em
320/360/390/575/576/599/600/767/768/1024/1280/1440 px. Mede direction/alinhamento
de CTAs, tracks da grade institucional, a View `content-grid--2` de projetos e
pares de detalhes; guarda PNGs e `summary.json` fora do repositório. Não acessa
Runtime, não envia dados e não substitui a inspeção das páginas reais nem a
cobertura de zoom, teclado,
claro/escuro, reduced motion ou WCAG completa.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-theme-institutional-layout
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/institutional-layout.py
```

`INSTITUTIONAL_BASELINE_REF` escolhe o commit do CSS de baseline; use `HEAD` ao
rodar antes de criar o commit e mantenha o padrão `HEAD^` após a publicação.

## SDC `context-tabs`

`context-tabs-layout.py` compara a apresentação compacta de mobile ao layout
amplo em 320/352/360/368/369/390/575/576/599/600/768/1024/1280/1440px. Verifica links
estáticos e a estrutura `<ul>` produzida por `item_list`, gap em ambos os
formatos, ordem DOM, `aria-current`, alvo mínimo de 44px e ausência de overflow.
É uma fixture local com Firefox ESR visível; não acessa Runtime nem substitui a
verificação no UI Patterns Library ou nas rotas reais.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-context-tabs-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/context-tabs-layout.py
```

`CONTEXT_TABS_BASELINE_REF` escolhe o CSS de baseline (padrão `HEAD^`), e
`CONTEXT_TABS_WIDTHS` permite focar larguras específicas. Capturas e
`summary.json` ficam fora do repositório em `$SUITE_OUT`.
`INSTITUTIONAL_LAYOUT_WIDTHS` permite uma lista focal separada por vírgulas.

## Perfil público mobile-first

`public-profile-layout.py` compara geometria computada e screenshots do CSS
anterior e candidato para uma fixture sintética de Perfil público em
320/360/390/575/576/599/600/768/1024/1280/1440 px. Requer Firefox ESR visível,
`DISPLAY=:0`, geckodriver e `websockets`; evidências ficam em `$SUITE_OUT`.
Define `PUBLIC_PROFILE_BASELINE_REF` para escolher o commit baseline e
`PUBLIC_PROFILE_WIDTHS` para uma lista focal de larguras. O teste cobre somente
CSS/layout da fixture, sem Runtime, formulário privado, ACL ou gates completos
de acessibilidade.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-profile-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/public-profile-layout.py
```

## Formulários públicos de autenticação

`auth-layout.py` verifica o painel de login com a classe Drupal observada em
`/entrar` (`path-entrar`) em 320/360/390/575/576/599/600/768/1024/1280/1440 px.
Também garante que o CSS contempla as classes das rotas `/criar-conta`
(`path-criar-conta`) e `/recuperar-senha` (`path-recuperar-senha`), e registra
quando o baseline só tinha seletores `page-route-user-*` que não correspondem ao
HTML real. Usa fixture, não Runtime. Requer Firefox ESR visível, `DISPLAY=:0`,
geckodriver e `websockets`; artefatos ficam em `$SUITE_OUT`.
`AUTH_BASELINE_REF` e `AUTH_WIDTHS` selecionam baseline e larguras. Sem override,
o teste localiza o commit `fix(theme): aplica estilos nas rotas reais de
autenticação` e usa o pai como baseline, evitando que commits posteriores de
documentação tornem `HEAD^` equivalente ao candidato. A captura do Runtime
anterior à correção confirmou `path-entrar` e não aplicava os estilos
específicos de `auth.css`; a verificação do CSS corrigido no Runtime aguarda sua
publicação e rebuild de cache.

`auth-route-runtime.py` abre anonimamente uma rota real e injeta as folhas
candidatas de base e autenticação somente no DOM da sessão, sem enviar
formulários. Além de markup, paddings e scroll, mede o widget e seu contêiner.
Para validar a biblioteca e o hook realmente servidos, use
`AUTH_ROUTE_INJECT_CANDIDATE=0 AUTH_ROUTE_REQUIRE_MARKER=1`; esse modo não injeta
CSS nem marcador e exige o token emitido pelo hook. As capturas recebem o prefixo
`served-`. Sem essas variáveis o teste preserva o modo candidato histórico.
`AUTH_ROUTE_BASE_URL` define o domínio, `AUTH_ROUTE_PATH` escolhe
`/`, `/entrar`, `/criar-conta` ou `/recuperar-senha`, e `AUTH_ROUTE_WIDTHS` seleciona
as larguras. `AUTH_ROUTE_EXPECTED_PATH_CLASS` substitui o body class esperado
quando uma rota canônica, como `/` no purpose Conta, renderiza a mesma página
`/entrar`.

Para direcionar o hostname HTTPS ao Apache local sem alterar `/etc/hosts`, use
`AUTH_ROUTE_LOCAL_RUNTIME=1` e
`AUTH_ROUTE_BASE_URL=https://conta.aculta.toca.net.br`. O túnel CONNECT aceita
somente esse host local. O widget externo exige também
`AUTH_ROUTE_ALLOW_CLOUDFLARE=1`, que libera apenas
`challenges.cloudflare.com:443`; qualquer outro host é recusado. Para testar a
CSS antes de servir o novo hook do Portal, `AUTH_ADD_MARKER_CANDIDATE=1` acrescenta
temporariamente `data-portal-ui="challenge-frame"` ao wrapper renderizado; isso
simula o contrato e não valida a instalação da alteração PHP no Runtime.

Reteste integrado em 2026-10-31: `/` na Conta de desenvolvimento, HEAD
`8e0091a4a`, passou 10/10 checks nas nove larguras 320/360/390/599/600/768/
1024/1280/1440px com `AUTH_ROUTE_INJECT_CANDIDATE=0` e
`AUTH_ROUTE_REQUIRE_MARKER=1`. CSS, challenge e token vieram do Runtime. O
documento ficou em 320px no viewport de 320px e heading/form não se sobrepuseram.
Evidência em `/home/piradopirata/aculta-suite-out/auth-served-runtime-8e0091a4a-20261031/`.
Para repetir sem JavaScript, acrescente `AUTH_ROUTE_DISABLE_JS=1` e use outro
`SUITE_OUT`; em `8e0091a4a`, essa execução passou 10/10 nos mesmos viewports.

Execução candidata de 2026-10-30, Firefox ESR 153.5.1esr: login 12/12,
cadastro 10/10 e recuperação 10/10 checks passaram, com widget completo e sem
overflow de página em 320/360/390/599/600/768/1024/1280/1440px. A reprodução
anterior sem a ampliação do conteúdo em 320px mediu `documentWidth=328` e
`challengeScrollWidth=300`. Capturas/relatórios ficam em
`/home/piradopirata/aculta-suite-out/auth-captcha-frame-candidate-20261030/`,
`auth-register-captcha-candidate-20261030/` e
`auth-reset-captcha-candidate-20261030/`. Como o marcador foi simulado e o CSS
foi injetado, ainda é necessário retestar após servir a mudança Portal/Tema e
reconstruir o cache.

Revalidação focal de 2026-10-31 no Runtime local: `/entrar` anônima passou
10/10 checks nas nove larguras da matriz 320–1440px. O iframe Turnstile real
estava carregado; a 320px, media 300px dentro do slot de 300px, sem overflow de
documento. CSS candidata e marcador `challenge-frame` continuaram injetados
apenas no Firefox; hook Portal e biblioteca instalada seguem sem homologação.
Evidência em
`/home/piradopirata/aculta-suite-out/auth-route-runtime-resume-20261031/`.

Para reproduzir a raiz canônica de Conta, que renderiza o mesmo formulário de
login com `body.path-entrar`, defina `AUTH_ROUTE_PATH=/` e
`AUTH_ROUTE_EXPECTED_PATH_CLASS=path-entrar`. Em 2026-10-31, o Firefox ESR
local mediu a baseline sem candidato em 328px para viewport 320px. A folha
candidata inicialmente ainda sobrepunha o H1: o gutter vertical negativo do
`.row.g-4`/`.row.g-xl-5` não era compensado pelo título. `auth.css` agora reserva
24px até 1199px e 48px a partir de 1200px, anulando esse gutter e mantendo os
dois painéis contíguos. A raiz passou 10/10 checks com JavaScript e 10/10 sem
JavaScript nas larguras 320/360/390/599/600/768/1024/1280/1440px; o Firefox
confirmou documento de 320px e H1 sem sobreposição. CSS e marcador CAPTCHA
foram candidatos injetados somente na sessão. Evidências em
`/home/piradopirata/aculta-suite-out/account-root-overflow-candidate-20261031/`
e `/home/piradopirata/aculta-suite-out/account-root-overflow-nojs-candidate-20261031/`.
Ainda falta servir o CSS/hook Portal no Runtime e repetir sem injeção.

Execução reproduzível para as nove larguras:

```sh
export DISPLAY=:0
export AUTH_ROUTE_LOCAL_RUNTIME=1
export AUTH_ROUTE_ALLOW_CLOUDFLARE=1
export AUTH_ROUTE_BASE_URL=https://conta.aculta.toca.net.br
export AUTH_ROUTE_PATH=/
export AUTH_ROUTE_EXPECTED_PATH_CLASS=path-entrar
export AUTH_ROUTE_WIDTHS=320,360,390,599,600,768,1024,1280,1440
export AUTH_ADD_MARKER_CANDIDATE=1
export SUITE_OUT=/home/piradopirata/aculta-suite-out/account-root-overflow-candidate
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/auth-route-runtime.py
```

Como a regra da margem também é compartilhada por cadastro e recuperação, as
duas rotas e `/entrar` foram retestadas com a nova asserção geométrica: 10/10
checks por rota, nove larguras mais o acesso inicial, sem sobreposição do H1.
Evidências em
`/home/piradopirata/aculta-suite-out/auth-login-overlap-candidate-20261031/`,
`/home/piradopirata/aculta-suite-out/auth-register-overlap-candidate-20261031/`
e `/home/piradopirata/aculta-suite-out/auth-reset-overlap-candidate-20261031/`.

Repetição de 2026-10-31 após o overflow de 328px ser observado novamente na
raiz Conta: a rota `/entrar` passou 12/12 checks com a folha do worktree e token
visual simulado, em 320/360/390/575/576/599/600/768/1024/1280/1440px. A captura
de 320px foi revisada; relatório e PNGs em
`/home/piradopirata/aculta-suite-out/account-overflow-followup-20261031/`.
O Runtime mantém a baseline até o deploy da branch: o teste não exercitou o
hook PHP servido nem JavaScript desativado e, portanto, não fecha a pendência.

Para medir o layout sem JavaScript da página, defina
`AUTH_ROUTE_DISABLE_JS=1`. WebDriver continua disponível para injetar a CSS e,
com `AUTH_ADD_MARKER_CANDIDATE=1`, o token no markup já renderizado. Essa
injeção permite exercitar a regra de layout no estado sem scripts do site, mas
não simula nem valida o hook PHP. No reteste de 2026-10-31, o modo sem JavaScript
passou 12/12 checks nas mesmas onze larguras e a captura de 320px foi revisada;
`window.Drupal` estava ausente, confirmando que os scripts da página não
executaram. Relatório em
`/home/piradopirata/aculta-suite-out/account-overflow-nojs-verified-followup-20261031/`.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/auth-route-runtime
export AUTH_ROUTE_BASE_URL=https://conta.aculta.toca.net.br
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/auth-route-runtime.py
```

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-auth-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/auth-layout.py
```

## Carrossel editorial da home MAIN

`editorial-carousel-layout.py` compara geometria calculada e screenshots da
View `view-home-editorial-highlights` entre o CSS de baseline e o candidato,
em 320/360/390/575/576/599/600/768/769/1024/1280/1440 px. Usa fixture, não a
página Runtime. Requer Firefox ESR visível em `DISPLAY=:0`, geckodriver e
`websockets`; screenshots ficam fora do repositório em `$SUITE_OUT`. Mede alvos de anterior,
próximo, pausa e pontos (44×44 CSS px), marcador compacto (10 px), geometria
externa e overflow; o fixture não representa o Runtime.
`EDITORIAL_CAROUSEL_BASELINE_REF` e `EDITORIAL_CAROUSEL_WIDTHS` permitem escolher
baseline e larguras.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-editorial-carousel-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/editorial-carousel-layout.py
```

`editorial-carousel-runtime.py` abre a home MAIN pública em Firefox ESR visível,
traz a View ao viewport para disparar a hidratação lazy do VVJB, e injeta a folha
candidata apenas na sessão de navegador. Confere a View real, inicialização,
conteúdo ativo, limites físicos dos slides e ausência de rolagem horizontal em
320/360/390/575/576/599/600/768/769/1024/1280/1440px. Em 2026-10-30 passou
15/15 verificações; capturas ficam em
`/home/piradopirata/aculta-suite-out/editorial-carousel-runtime-20261030-final5/`.
A folha no candidato, no checkout servido (HEAD `4399627c4`) e em
HEAD atual da PR #299 tem o mesmo SHA-256
`843691e4d1326c5d902829a240a184350cc21ea65d935b24c2c82f8becf2598a`. Esta
validação cobre layout/estado inicial visível, não teclado, reduced motion,
claro/escuro ou WCAG 2.2 AA.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/editorial-carousel-runtime
export EDITORIAL_CAROUSEL_BASE_URL=https://aculta.toca.net.br
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/editorial-carousel-runtime.py
```

## Webform Likert e modal de confirmação

`webform-layout.py` compara os auxiliares Likert e o modal de confirmação nas
larguras 320/360/390/575/576/599/600/650/651/768/769/1024/1280/1440 px. A
fixture reproduz o `minWidth: 600` configurado pelo behavior do Webform; o teste
valida que a baseline atende o contrato de largura responsiva, que o CSS
candidato mantém a geometria e as capturas pixel a pixel em toda a grade, sem
overflow e com 600px em desktop. Não submete conteúdo nem depende de configuração
particular. O gutter é medido em pixels computados, inclusive quando definido
por `rem` ou `clamp()` no token. Requer Firefox ESR visível em `DISPLAY=:0`, geckodriver e
`websockets`; arquivos vão para `$SUITE_OUT`. `WEBFORM_BASELINE_REF` e
`WEBFORM_WIDTHS` selecionam baseline e larguras.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-webform-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/webform-layout.py
```


## Composer de resposta da gaveta Messenger

`messenger-reply-layout.py` compara a CSS baseline/candidata do composer de
resposta em fixture que reproduz a estrutura publicada pelo módulo Messenger.
Mede padding da gaveta e do formulário, margem do campo, altura da caixa, ordem
da mensagem e botão, alvo de toque e overflow em 320/360/390/575/576/599/600/
768/1024/1280/1440 px. Não autentica, envia mensagens nem acessa o Runtime.
Firefox ESR precisa estar visível em `DISPLAY=:0`; screenshots e resumo ficam em
`$SUITE_OUT`. `MESSENGER_REPLY_BASELINE_REF` e `MESSENGER_REPLY_WIDTHS` escolhem
a baseline e as larguras.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-messenger-reply-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/messenger-reply-layout.py
```


## Capa da Revista e alvos dos links editoriais

`magazine-cover-runtime.py` abre a raiz pública da Revista em Firefox ESR, somente
leitura, confirma View, capa, teasers, imagens e shell e mede os links de título e
ação de cada publicação. Os alvos precisam ter pelo menos 44px de altura; os
viewports padrão vão de 320 a 1440px. `MAGAZINE_COVER_CSS_OVERRIDE=1` injeta
`css/components/revista-listing.css` do worktree na sessão para testar uma
candidata sem alterar o Runtime. Evidência e limites estão em
`mobile-first-audit.md`.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/magazine-cover-mobile
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
export MAGAZINE_COVER_CSS_OVERRIDE=1
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/magazine-cover-runtime.py
```

O teste injeta CSS apenas no navegador e não envia formulários. Ele não valida
publicação editorial, ACL, teclado completo, leitores de tela ou conformidade
WCAG 2.2 AA.

## Zoom real da capa da Revista

`revista-browser-zoom.py` abre a home pública do purpose Magazine em Firefox ESR
visível e usa Ctrl+plus/Ctrl+0 via `xdotool` para conferir 100%, 200% e 400%.
Além da capa, segue o primeiro link público de publicação e testa a página
completa: artigo, corpo editorial não vazio, capa carregada, H1, shell, overflow
e conteúdo que ultrapasse o próprio corpo. Não segue links para outro host e
registra `DEFERRED` se não houver publicação. Salva capturas da capa e também
do H1 e do corpo do artigo em 200% e 400%, além das medições em `summary.json`,
tudo em `$SUITE_OUT`. As capturas do artigo rolam o alvo para a área visível; as
medições de overflow são coletadas antes desse scroll. O script é somente leitura:
não submete formulários nem altera conteúdo/configuração. Requer host `toca`, checkout
de desenvolvimento, `aculta_runtime`, `DISPLAY=:0`, `xdotool` e geckodriver.

```sh
cd /home/piradopirata/projetos/aculta
SUITE_OUT=/home/piradopirata/suite-out/aculta-revista-zoom-20261030 \
ACULTA_FF_PROFILE_ROOT=/home/piradopirata/suite-out/aculta-revista-zoom-20261030/profiles \
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/revista-browser-zoom.py
```

`REVISTA_BASE_URL` troca a URL dentro do purpose Magazine e
`REVISTA_ZOOM_DEVICE_WIDTH` define a largura inicial em pixels de dispositivo
(padrão 1280). O teste mede o zoom do navegador, enquanto a matriz de viewports
mede larguras CSS; um não substitui o outro. `DEFERRED` exige conferir zoom
manual no Firefox ESR. Essa rodada mede uma publicação pública real por vez;
comentários são apenas detectados no DOM, sem participação ou moderação. Ela não
substitui a matriz de larguras, inspeção editorial, teclado integral, leitores
de tela ou homologação WCAG 2.2 AA.

## Shell dos dez purposes sem JavaScript

`purpose-shell-nojs.py` desativa JavaScript no perfil Firefox e abre as dez
raízes públicas mapeadas pelo `aculta_deployer/config/deploy.json`. Confere
Institution Bar e Domain Header únicos, ausência de overflow e disponibilidade
da navegação em 320/390/599/600/768/1024/1440px. A Wiki usa busca contextual no
cabeçalho no lugar do menu primário, e o teste verifica que o campo de busca
continua visível. O teste é anônimo e somente leitura; não exercita páginas
internas, estados autenticados, teclado, zoom, leitor de tela nem WCAG completa.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/suite-out/purpose-shell-nojs
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/purpose-shell-nojs.py
```

`PURPOSE_NOJS_WIDTHS` seleciona as larguras e
`PURPOSE_NOJS_DOMAIN_ENV=production` só deve ser usado após confirmar ambiente,
DNS e TLS.

## Shell global em claro/escuro e reduced motion

`purpose-shell-color-modes.py` abre as dez raízes públicas em Firefox ESR e
alterna `data-bs-theme` somente no DOM da sessão para conferir os tokens claros
e escuros do tema. Também ativa a preferência `prefers-reduced-motion` do
Firefox, verifica shell único e overflow em 320/360/390/599/600/768/1024/1280/
1440px e salva capturas de 320 e 1440px para cada purpose/modo. Não grava
preferências ou configuração no Drupal. A verificação confirma tokens e o
estado da media query e calcula contraste dos links textuais da Institution Bar
e Domain Header sobre suas superfícies compostas; não avalia contraste de todos
os componentes, assistive technology nem funcionamento de cada animação. No
formulário público de Conta, mede também o contraste do botão Google nos estados
normal e hover. Para Conta a 320px, o JSON inclui os primeiros descendentes não
fixos cujo conteúdo ultrapassa a largura do viewport, sem coletar texto ou
valores de campos.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/suite-out/purpose-shell-color-modes
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/purpose-shell-color-modes.py
```

`PURPOSE_MODE_WIDTHS` seleciona as larguras,
`PURPOSE_MODE_PURPOSES=account` restringe uma rodada de diagnóstico a um ou mais
purposes, e
`PURPOSE_MODE_DOMAIN_ENV=production` só deve ser usado após confirmar ambiente,
DNS e TLS. `PURPOSE_MODE_AUTH_CANDIDATE=1` injeta `auth.css` do worktree e
acrescenta o marcador `data-portal-ui="challenge-frame"` ao wrapper `.captcha`
somente na página `path-entrar` do purpose Conta. Isso valida a combinação
CSS/markup candidata no Runtime servido sem instalar o hook Portal; o resto dos
purposes continua usando CSS e markup servidos.

## Zoom real nos dez purposes

`purpose-browser-zoom.py` abre as dez raízes públicas no Firefox ESR visível,
confirma a composição em 100% e usa `xdotool` para testar zoom real do navegador
em 200% e 400%. Mede a largura CSS efetiva, rolagem/overflow horizontal, shell
único e presença de `main#main-content`; salva uma captura por purpose/alvo em
`SUITE_OUT`. O perfil Firefox temporário é removido ao final. É somente leitura
e requer `hostname=toca`, Drush confirmando `aculta_runtime`, `DISPLAY=:0`,
`xdotool` e geckodriver. Não cobre páginas internas, estados autenticados,
zoom do sistema operacional, leitores de tela nem WCAG completa.

```sh
export DISPLAY=:0
export ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta
export SUITE_OUT=/home/piradopirata/aculta-suite-out/purpose-browser-zoom
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/purpose-browser-zoom.py
```

`PURPOSE_ZOOM_DEVICE_WIDTH` define a largura inicial (1280px por padrão) e
`PURPOSE_ZOOM_DOMAIN_ENV=test|production` seleciona os hosts do manifesto.
`PURPOSE_ZOOM_PURPOSES=account` restringe uma rodada de diagnóstico a um ou mais
purposes por nome. O resultado lista elementos que ultrapassem o documento para
ajudar a localizar falhas. O Firefox navega primeiro para a página e então
recebe o reset de zoom, porque GeckoDriver pode mapear a janela somente após a
primeira navegação.

Reteste visível em 2026-10-31: 30/30 checks nas dez raízes públicas em 100%,
200% e 400%, com documento sem overflow e os dois headers presentes uma vez.
Em Conta, `auth.css` candidata e marcador `challenge-frame` foram injetados;
os demais purposes usaram o Runtime servido. Não cobre páginas internas nem
prova o hook Portal instalado. Capturas e relatório em
`/home/piradopirata/aculta-suite-out/purpose-browser-zoom-resume-20261031/`.

Para comparar a correção candidata do desafio de autenticação somente na Conta,
defina `PURPOSE_ZOOM_AUTH_CSS_CANDIDATE=1`. O harness injeta `base.css` e
`components/auth.css` da worktree no DOM e simula o marcador
`data-portal-ui="challenge-frame"`; isso é uma comparação visual com CSS local,
não valida o hook Portal renderizado no servidor nem o estado sem JavaScript.

## Foco de teclado no shell público

`purpose-shell-keyboard.py` percorre por teclado as dez raízes públicas anônimas,
em Firefox ESR visível, nas larguras 320/390/768/1280 px. Testa até oito paradas
com `Tab` e `Shift+Tab` por direção, foco visível, indicador no elemento focado
(inclui o contorno `:focus-within` de cards LMS), interseção com a viewport e
overflow horizontal separado. Só faz GET e ações de teclado; não ativa links,
submete formulários ou altera conteúdo. Foco delegado ao desafio CAPTCHA externo
e fim de sequências curtas ficam como `SKIP`, nunca como PASS. Capturas e
`results.json` são gravados em `$SUITE_OUT`.

```sh
export DISPLAY=:0
export ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta
export SUITE_OUT=/home/piradopirata/aculta-suite-out/purpose-shell-keyboard
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/purpose-shell-keyboard.py
```

`PURPOSE_KEYBOARD_WIDTHS`, `PURPOSE_KEYBOARD_TABS` e
`PURPOSE_KEYBOARD_PURPOSES` selecionam larguras, quantidade máxima de paradas e
purposes (por exemplo `account,courses`). O teste verifica somente as raízes e
as primeiras paradas; não percorre menus expandidos, rotas internas, teclado de
componentes, contraste do indicador, leitor de tela ou WCAG 2.2 AA completa.
O foco interno do CAPTCHA de terceiro não é inspecionável pelo documento ACULTA;
permanece fora do resultado de foco do tema.

`PURPOSE_KEYBOARD_AUTH_CANDIDATE=1` injeta `auth.css` da árvore testada e o
marcador visual neutro apenas na sessão Firefox quando a Conta redireciona para
`/entrar`. Serve para comparar a geometria candidata e a navegação por teclado;
não instala o hook no Runtime nem altera a página servida.

O foco delegado ao Turnstile permanece `SKIP`: o diagnóstico registra o
`DIV` de `tabIndex=-1` dentro do wrapper, mas ele não corresponde a `:focus` nem
`:focus-visible`, e o wrapper não corresponde a `:focus-within`. Assim, esse
foco transferido pelo widget externo não pode ser estilizado de modo confiável
pela folha do tema. A verificação mede retângulo e estado CSS, sem inspecionar
o conteúdo cross-origin do iframe. No reteste focal de 2026-10-31, Conta passou
72 checks nas quatro larguras 320/390/768/1440px; os quatro passos transferidos
ao CAPTCHA permaneceram `SKIP` e nenhum link foi ativado. Evidência em
`/home/piradopirata/aculta-suite-out/account-captcha-focus-diagnostic-20261031/`.

`PURPOSE_KEYBOARD_DIAGNOSTICS=1` acrescenta aos registros de overflow as
larguras de `html`/`body` e os elementos cujas caixas ou áreas de rolagem
ultrapassam seus contêineres. O modo fica desligado por padrão para manter
matrizes sem falha enxutas. Reteste focal em 320px: o baseline identificou o
Turnstile de 300px dentro de uma coluna de 268px e documentou 328px; CSS e token
candidatos passaram 5/5, ampliando o formulário para 300px e mantendo o documento
em 320px. Relatórios em
`/home/piradopirata/aculta-suite-out/account-overflow-baseline-diagnostic-320-20261031/`
e `/home/piradopirata/aculta-suite-out/account-overflow-candidate-diagnostic-320-final-20261031/`.

## Título principal nas dez raízes públicas

`purpose-headings.py` confere um H1 visível dentro de `main#main-content`, o
shell único e ausência de overflow em 320/390/600/768/1024/1440px nos dez
purposes. Usa somente GET em Firefox ESR. Captura Apoio e Cursos em 320/1440px
para revisão visual. `PURPOSE_HEADING_WIDTHS` escolhe as larguras e
`PURPOSE_HEADING_DOMAIN_ENV` seleciona os hosts do manifesto (`test` por padrão).
Não valida hierarquia completa, leitores de tela nem WCAG 2.2 AA.
No Runtime em HEAD `2493d7535`, uma repetição visível com os dez purposes e as
seis larguras deste script passou 119/120: os 60 H1 estavam visíveis, os 59
shells sem a única exceção de largura estavam íntegros, e o único erro foi Conta
a 320px (`documentWidth=328`). Apoio e Cursos já exibem H1; a falha histórica
dessas páginas foi resolvida no código servido. Para o candidato de autenticação,
`auth-route-runtime.py` passou 10/10 em `/entrar`, `/criar-conta` e
`/recuperar-senha`, cada rota em 320/360/390/599/600/768/1024/1280/1440px, com
widget Turnstile real, CSS e marcador candidatos injetados somente no Firefox.
Isso não homologa o hook Portal nem elimina o overflow da CSS atualmente servida.
Relatórios atuais em
`/home/piradopirata/aculta-suite-out/purpose-headings-recheck-20261031/` e
`/home/piradopirata/aculta-suite-out/auth-turnstile-final-20261031/`.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/suite-out/purpose-headings
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/purpose-headings.py
```

## Rodapé responsivo nas dez experiências

`footer-layout.py` verifica o rodapé servido nas dez raízes públicas lidas de
`aculta_deployer/config/deploy.json`: MAIN, Conta, SUPPORT, Revista, Wiki,
Loja, Cursos, Fórum, Links e Eventos. Usa Firefox ESR visível e somente GET.
Nos nove purposes com `.aculta-footer`, mede a grade em 1 coluna abaixo de
576px, 2 colunas entre 576 e 991px e 4 colunas a partir de 992px. Links tem um
footer próprio (`.aculta-social-links-footer`) fornecido por `aculta_links`;
nesse purpose, mede os anchors reais do footer e sua área mínima de 44px. Para
todos, confere o shell único e ausência de overflow. Captura a página rolada
até o rodapé em 320 e 1440px. Larguras padrão: 320/360/390/575/576/599/600/
768/991/992/1024/1280/1440px.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/footer-layout
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
export FOOTER_CSS_OVERRIDE=1
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/footer-layout.py
```

`FOOTER_CSS_OVERRIDE=1` injeta somente a folha candidata do SDC Social Links
para testar o caso em que os links são gerados pelo LinkGenerator e chegam com
tokens semânticos no HTML. Os demais estilos continuam servidos pelo site.
`FOOTER_BASE_CSS_OVERRIDE=1` injeta também a CSS base candidata para verificar
mudanças globais de tema nos dez purposes.
`FOOTER_AUTH_CSS_OVERRIDE=1` injeta `css/components/auth.css` para validar uma
variante candidata de autenticação na rota Conta. Os overrides são opcionais e
não alteram o Runtime.
Use `FOOTER_DOMAIN_ENV=production` somente após confirmar ambiente, DNS e TLS;
`FOOTER_WIDTHS` permite selecionar larguras. Evidência da execução 2026-10-30:
`/home/piradopirata/aculta-suite-out/footer-layout-20261030-candidate4/`. O teste
mede somente o layout público do rodapé, shell e overflow; não cobre navegação
por teclado, zoom, contraste, leitores de tela, páginas internas nem aprova
THEME-MF3.

Reteste atual no Runtime/PR HEAD `2493d7535`, 320 px, sem overrides: 19/20
checks. A única falha foi `/conta` por overflow do widget; os outros nove
purposes passaram, com shell único. Evidência em
`/home/piradopirata/aculta-suite-out/footer-layout-current-2493-20261030/`.
O gate completo de rodapé ainda requer regressão após resolução do Pede-A.

Na revalidação de 2026-10-31, `purpose-shell-color-modes.py` obteve 565/576 no
baseline e 576/576 com `PURPOSE_MODE_AUTH_CANDIDATE=1`: foram detectados overflow
de 8px em `/entrar` a 320px nos dois modos e contraste de 1,14:1 do botão Google
em modo escuro; a candidata elevou o contraste mínimo medido para 11,34:1.
Relatórios em `/home/piradopirata/aculta-suite-out/purpose-shell-color-modes-baseline-followup-20261031/`
e `/home/piradopirata/aculta-suite-out/purpose-shell-color-modes-candidate-followup-20261031/`.
É evidência com CSS/markup candidato injetado, não integração instalada no Runtime
nem aprovação de THEME-MF3.
Reteste global de 2026-10-31: 663 PASS, 10 SKIP (limite de sequência/foco interno
do CAPTCHA) e um FAIL de overflow de Conta a 320px em 674 registros. A rodada
candidata focal de Conta passou 72/72, com quatro SKIPs, em 320/390/768/1280px.
Relatórios em `/home/piradopirata/aculta-suite-out/purpose-shell-keyboard-followup-20261031/`
e `/home/piradopirata/aculta-suite-out/account-keyboard-candidate-followup-20261031/`.
O segundo resultado injeta CSS/marcador no navegador e não valida integração
instalada no Runtime.
Revalidação em 2026-10-31: `purpose-headings.py` passou 119/120 checks. A única
falha foi o overflow conhecido de Conta a 320px; H1 visível, `main` e shell único
passaram em todas as combinações. Relatório:
`/home/piradopirata/aculta-suite-out/purpose-headings-followup-20261031/`.

### Revalidação Green Stash candidata — 2026-10-31

`green-stash-layout.py` passou 186/186 checks; relatório em
`/home/piradopirata/aculta-suite-out/green-stash-layout-followup-20261031/`. A
fixture mostra as três áreas disponíveis e cobre produto, detalhe, criação visual,
claro/escuro, viewports 320–1440px e zoom 200/400%. É fixture sem operação/save;
não valida Runtime, grants por loja ou a rota real.

### Agenda e calendário interativo de Eventos

`calendar-agenda.py` abre somente por GET a agenda (`/calendario`), a grade do
mês atual e a visão interativa (`/calendario/interativo`) no host de teste
derivado de `deploy.json`. No Firefox ESR visível, mede viewport e overflow,
Institution Bar, Domain Header, `main`, grade de sete colunas, fallback HTML e
presença/estado do calendário interativo. Percorre 320/360/390/599/600/768/1024/
1280/1440px, claro/escuro e sessões separadas com JavaScript ligado/desligado.
No modo escuro o harness aplica `data-bs-theme="dark"` apenas no documento de
teste e aciona o botão existente para validar o token `contrast-black` do
DHTMLX; isso não altera preferência persistente. Os arquivos de evidência
contêm somente geometrias, marcadores e contagens; nenhum texto de evento é
registrado. Não envia filtros, não modifica eventos e não constitui sozinho
aceite de THEME-MF3.

```sh
cd /home/piradopirata/worktrees/opencultures-f0-adm8-e8
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/calendar-agenda
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/calendar-agenda.py
```

`CALENDAR_WIDTHS`, `CALENDAR_MODES`, `CALENDAR_ROUTES` e `CALENDAR_BASE_URL`
permitem selecionar a matriz ou apontar para outro host de teste explicitamente.
`CALENDAR_DOMAIN_ENV` aceita somente `test`. Capturas 320/1440px e
`summary.json` ficam em `$SUITE_OUT`.
