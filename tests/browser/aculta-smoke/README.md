# Harness genérico de navegador para o ACULTA

Esta suíte atende o **projeto inteiro**, não apenas `aculta420` ou Studio.
Passe URLs dos módulos, produtos, domínios/purposes e consumers que deseja
inspecionar; os scripts não presumem um host ou shell específico. Rode também
os testes da área quando precisar provar contratos de aplicação.

O script `tests/browser/aculta-smoke/smoke-page.py` serve como primeiro check reutilizável para páginas
públicas: carrega URLs por GET, coleta título/estado/documentWidth, landmarks,
tempo e captura screenshots. Não autentica, não submete formulários e não prova
que conteúdo/API ou controles de acesso estão corretos.

Para matriz mobile-first de **qualquer purpose**, use
`tests/browser/aculta-smoke/page-matrix.py`: mede URLs em larguras configuráveis,
temas claro/escuro, overflow e landmarks; axe-core é opcional. Pode começar por
uma sessão autenticada fornecendo `BROWSER_SESSION_START_URL` pelo ambiente; o
valor (incluindo query/ULI) não é escrito nos relatórios. Prefira runners
específicos de domínio quando seja necessário verificar autorização, conteúdo
privado ou submissões.

## Execução

No checkout da PR ou worktree que contém o script, crie `$SUITE_OUT` fora do
repositório. Firefox X11 para inspeção visual:

```sh
export SUITE_OUT=/home/piradopirata/aculta-suite-out/smoke-home
export DISPLAY=:0
python3 tests/browser/aculta-smoke/smoke-page.py https://aculta.toca.net.br/ https://revista.aculta.toca.net.br/
```

Firefox ESR headless para smoke repetitivo:

```sh
export SUITE_OUT=/home/piradopirata/aculta-suite-out/smoke-home-headless
export BROWSER=firefox HEADLESS=1
python3 tests/browser/aculta-smoke/smoke-page.py https://aculta.toca.net.br/
```

Chrome/Chromium headless suplementar:

```sh
export SUITE_OUT=/home/piradopirata/aculta-suite-out/smoke-home-chrome
export BROWSER=chrome HEADLESS=1
python3 tests/browser/aculta-smoke/smoke-page.py https://aculta.toca.net.br/
# Chromium usa o mesmo ChromeDriver:
export SUITE_OUT=/home/piradopirata/aculta-suite-out/smoke-home-chromium
export BROWSER=chromium HEADLESS=1
python3 tests/browser/aculta-smoke/smoke-page.py https://aculta.toca.net.br/
```

Matriz responsiva genérica em Firefox headless, incluindo uma auditoria axe
diagnóstica (resultados `incomplete` e violações devem ser revisados):

```sh
npm ci --prefix web/themes/custom/aculta420
export SUITE_OUT=/home/piradopirata/aculta-suite-out/portal-home-matrix
export BROWSER=firefox HEADLESS=1 ACCESSIBILITY_SCAN=1
export MATRIX_WIDTHS=320,360,390,599,600,768,1024,1280,1440
export MATRIX_MODES=light,dark
python3 tests/browser/aculta-smoke/page-matrix.py https://aculta.toca.net.br/
```

Para usar a matriz no browser visível, selecione `BROWSER=firefox`, remova
`HEADLESS=1` e confirme `DISPLAY=:0`; screenshots ficam no mesmo diretório.
Use `ACCESSIBILITY_STRICT=1` para retornar código diferente de zero quando axe
encontrar violações.

Variáveis de binário/driver (`FIREFOX`, `GECKODRIVER`, `CHROME`, `CHROMIUM`,
`CHROMEDRIVER`) podem sobrescrever os caminhos padrões do `wdlib.py`. Cada
sessão usa perfil descartável dentro de `$SUITE_OUT/profiles`; o driver o remove
ao fechar. `$SUITE_OUT` e perfis são modo 0700; screenshots e `results.json`
ficam com permissões privadas no diretório de saída.

Antes de atingir Runtime, confira host, checkout, URI e banco segundo
`AGENTS.md`; nunca use esse smoke para substituir um teste autenticado com
permissões nem para submeter dados. Para reflow por várias larguras, teclado,
zoom, axe-core ou fixtures, crie/reutilize um runner específico na pasta da
frente e descreva em README local. Todo novo script deve declarar sua cobertura,
ambiente, comandos, variáveis, artefatos, permissões e limitações no catálogo
`tests/browser/README.md` e README da pasta correspondente.
