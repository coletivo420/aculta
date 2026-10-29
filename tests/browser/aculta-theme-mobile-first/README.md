# ACULTA420 — SDC responsivos do tema

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
última atividade em colunas estreitas entre 576 e 600 px.

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
1024/1280/1440 px. Não envia o formulário nem grava preferências.

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
essa variável, o browser usa o CSS que o Drupal serviu.

O teste cobre somente navegação responsiva e a presença da Institution Bar na
página autenticada `/configuracoes`. Não valida as tabelas de pedidos, todos os
submenus, teclado completo, zoom real, cache/ACL entre contas nem WCAG 2.2 AA.

## Formulário unificado de endereço

`account-address.py` valida por GET o formulário canônico `/dados/endereco`,
acessado no shell legado pela navegação Meus Dados → Endereço. Confere uma
única instância do formulário, os campos acordados, país oculto, carregamento
do behavior de `aculta_portal_cep`, agrupamento de Número/S/N/Complemento e
overflow em 320/360/390/599/600/768/1024/1280/1440 px. Repete a verificação de
layout sem JavaScript em 320/390/768/1440 px. Não submete nem salva o endereço;
valores digitados são mascarados nas capturas, que ficam fora do repositório.

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
Verifica o formulário, cartões, Institution Bar, overflow e limites da grade em
320/360/390/480/481/599/600/768/1024/1280/1440 CSS px. Confere também o
fieldset compacto até 480px, seu retorno a 24px em 481px e a largura das ações
do formulário em mobile e desktop. É somente leitura: não envia formulário nem
altera configuração. Registra a presença do Domain Header para diagnóstico; o
Runtime atual não o renderiza nesse purpose, questão registrada no Pede-A
transversal.

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

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-institution-bar
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 tests/browser/aculta-theme-mobile-first/institution-bar.py
```

Para auditar o layout servido antes da mudança, use `INSTITUTION_BAR_EXPECT=baseline`.
Para injetar o CSS do checkout e validar o candidato antes da publicação,
defina `INSTITUTION_BAR_EXPECT=candidate INSTITUTION_BAR_CSS_OVERRIDE=1`.
`INSTITUTION_BAR_EXPECT` sem override espera a geometria compacta integrada.
`INSTITUTION_BAR_DOMAIN_ENV=test` (padrão) escolhe os `test_host`; use `production`
durante a transição Alpha, após verificar DNS, TLS e qual ambiente está servido,
para ler os `production_host` da mesma tabela. O teste não presume que o host de
teste continue sendo canônico depois dessa migração. Não testa menus autenticados
completos, teclado, zoom real, tema escuro ou WCAG 2.2 AA; registra a presença do
Domain Header para o Pede-A de composição.

## Institution Bar durante scroll e Toolbar administrativa

`institution-bar-scroll.py` compara o comportamento da barra ao carregar e
rolar as homes dos dez purposes em 320/390 px como anônimo e a home MAIN como
UID administrativo em 320/390/1023/1024/1280 px. Confirma a autenticação antes
de medir toolbar/deslocamento, testa a mudança de breakpoint sem recarregar a
página, amostra a estabilização e guarda screenshots em `$SUITE_OUT`. O ULI
fica em memória; o teste não submete conteúdo nem altera configuração.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-institution-bar-scroll
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
export ACULTA_PROJECT_ROOT=/home/piradopirata/projetos/aculta
DISPLAY=:0 python3 tests/browser/aculta-theme-mobile-first/institution-bar-scroll.py
```

Para injetar a folha CSS local, defina `INSTITUTION_BAR_SCROLL_CSS_OVERRIDE=1`;
para injetar o JavaScript candidato, use `INSTITUTION_BAR_SCROLL_JS_OVERRIDE=1`.
`INSTITUTION_BAR_SCROLL_BASE_URL` restringe o teste a um host e
`INSTITUTION_BAR_DOMAIN_ENV=test|production` selecionam o host; a conta usada
é configurável com `INSTITUTION_BAR_ADMIN_UID` e deve ser autorizada para o
ambiente. `INSTITUTION_BAR_LOGIN_URI` pode apontar ao host de Conta para iniciar
a sessão antes de voltar à home MAIN. `INSTITUTION_BAR_SCROLL_SCENARIOS` aceita
`anonymous`, `admin` ou ambos (padrão). O relatório lista os elementos
`data-offset-top` sem recolher seus textos. A captura usa Firefox ESR visível.
`INSTITUTION_BAR_SCROLL_DISABLE_JS=1` testa o fallback CSS em um perfil Firefox
com JavaScript de página desativado. Esse teste focal não substitui a
matriz completa de larguras, teclado, zoom, dark mode ou WCAG 2.2 AA.


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

`auth-layout.py` compara espaçamentos computados e screenshots dos estilos de
login/registro/recuperação antes e depois da migração, em 320/360/390/575/576/
599/600/768/1024/1280/1440 px. Usa fixture, não Runtime. Requer Firefox ESR
visível, `DISPLAY=:0`, geckodriver e `websockets`; artefatos ficam em `$SUITE_OUT`.
`AUTH_BASELINE_REF` e `AUTH_WIDTHS` selecionam baseline e larguras.

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
`websockets`; screenshots ficam fora do repositório em `$SUITE_OUT`.
`EDITORIAL_CAROUSEL_BASELINE_REF` e `EDITORIAL_CAROUSEL_WIDTHS` permitem escolher
baseline e larguras.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-editorial-carousel-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/editorial-carousel-layout.py
```

## Webform Likert e modal de confirmação

`webform-layout.py` compara a exibição do texto auxiliar Likert, o modal e as
capturas de baseline/candidato em 320/360/390/575/576/599/600/650/651/768/769/
1024/1280/1440 px. Usa fixture sintética para que o caso de modal não submeta
conteúdo nem dependa de uma configuração particular. Requer Firefox ESR visível
em `DISPLAY=:0`, geckodriver e `websockets`; arquivos vão para `$SUITE_OUT`.
`WEBFORM_BASELINE_REF` e `WEBFORM_WIDTHS` selecionam baseline e larguras.

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-webform-mobile-first
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-theme-mobile-first/webform-layout.py
```
