# Navegação SDC do ACULTA420 V2

`navigation.py` valida a integração do componente `aculta420_v2:navigation-bar`
na posição original do bloco de menu dentro do cabeçalho do domínio, usando Firefox ESR visível em
`DISPLAY=:0`. A matriz cobre os dez purposes cujos hosts são resolvidos do
`deploy.json`, nas larguras 320, 767, 768, 1024, 1099, 1100 e 1440 CSS px. A
matriz verifica o breakpoint desktop de 1100 px, menu móvel, `aria-expanded`,
foco, Escape, landmarks, logo do Domain Header carregado, marca sem duplicação
e overflow. Uma segunda sessão desativa JavaScript e confirma que os links
continuam visíveis e operáveis.

Configure `SUITE_OUT` para um diretório persistente fora do repositório.
Hosts de teste vêm da configuração de deploy; `NAVIGATION_DOMAIN_ENV` pode ser
`test` (padrão) ou `production` quando houver autorização para consultar essa
origem.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT" python3 -I tests/browser/transversal/aculta420-v2-navigation/navigation.py
```

O runner usa somente GET, não autentica e não submete formulários. Capturas e
`results.json` permanecem em `$SUITE_OUT`. Estados autenticados e leitor de tela
não fazem parte deste gate.

`admin-toolbar-layer.py` verifica, em Firefox visível, que a Institution Bar
permanece acima da sidebar administrativa Gin e que a sidebar começa abaixo da
altura medida da barra. O teste usa a folha original de Gin e uma fixture DOM
anônima; não valida uma sessão autenticada real.

`footer-layout.py` percorre os dez purposes, os modos claro/escuro e as larguras
de referência. Confere as colunas do rodapé nas fronteiras de 576 e 992 px,
overflow, alvos de menu e contraste renderizado; grava capturas do rodapé MAIN
em 320 e 1280 px sob `$SUITE_OUT`.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT" python3 -I tests/browser/transversal/aculta420-v2-navigation/footer-layout.py
```


`admin-sidebar-runtime.py` autentica uma conta administrativa de teste por ULI
e valida a Institution Bar contra a sidebar Gin real, incluindo mudança de
largura durante a expansão/recolhimento, alvos dos ícones, disclosure da conta
e ausência do aviso de bloco quebrado. UID padrão `257` pode ser substituído por
`ACULTA_ADMIN_TEST_UID`; `ACULTA_DRUPAL_ROOT` aponta para o checkout Drupal se
o script for executado de outro worktree. Capturas e JSON ficam em `$SUITE_OUT`.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT" python3 tests/browser/transversal/aculta420-v2-navigation/admin-sidebar-runtime.py
```

## Regressão visual (Playwright)

`routes.json` declara as rotas públicas desta frente. A suíte
`tests/browser/playwright.visual.spec.mjs` (config `playwright.visual.config.mjs`)
compara cada rota com referências em `tests/browser/__snapshots__/playwright.visual.spec.mjs/`:
claro e escuro, em 320, 390, 768, 1024 e 1440 CSS px, Firefox headless.

Execução (origem de desenvolvimento, `SUITE_OUT` fora do repositório):

```sh
cd tests/browser
SUITE_OUT="$SUITE_OUT" ACULTA_SITE_ORIGIN="https://aculta.toca.net.br/" \
ACULTA_BROWSER_FRONTS=aculta420-v2-navigation \
npx playwright test --config playwright.visual.config.mjs
```

Estado em 2026-11-12: referências atualizadas para o estado do servidor de
desenvolvimento naquela data, após revisão humana das candidatas. As referências
anteriores falhavam com 11 a 12% de pixels diferentes. Execução repetida contra o
mesmo servidor passou nas 10 comparações (estabilidade confirmada). Falha de
diferença de pixel só é registrada como regressão depois de revisão da imagem de
diferença em `$SUITE_OUT`.

Regra: não usar `--update-snapshots`. Para gerar novas candidatas, use
`ACULTA_VISUAL_BASELINE=1` com um `SUITE_OUT` novo e vazio; só copie para
`__snapshots__` depois de revisão humana.
