# ACULTA420 — SDC responsivos do tema

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
320/360/390/480/481/599/600/768/1024/1280/1440 CSS px. É somente leitura: não
envia formulário nem altera configuração. Registra a presença do Domain Header
para diagnóstico; o Runtime atual não o renderiza nesse purpose, questão
registrada no Pede-A transversal.

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
