# Configuração do navegador para testes

## Regra vigente

Firefox ESR via geckodriver (WebDriver W3C) é o navegador de teste. Use a
sessão **visível em `DISPLAY=:0`** para revisão humana de aparência, capturas de
aceite e zoom real com atalhos do navegador. Por autorização do responsável em
2026-10-31, Firefox ESR **headless** também pode executar gates automatizados
repetitivos de geometria, reflow, DOM, foco e estados quando reduz setup/tempo;
declare-o explicitamente e não o trate como substituto da inspeção visível.
Versões verificadas em 2026-10-31: Firefox ESR 153.5.1 e geckodriver 0.37.1.

Houve travamentos históricos de abas/rede no Chromium headless; remover
`DBUS_SESSION_BUS_ADDRESS` não resolveu de forma confiável. Por autorização do
responsável em 2026-10-31, Chrome/Chromium headless podem ser reavaliados como
checagens complementares com ChromeDriver compatível. Registre a versão exata
e o resultado. Se travar ou divergir, descarte esse motor para o gate e use o
Firefox ESR; não enfraqueça o gate visual X11.

## Comando

```
scripts/check-browser-hosts.sh [URL ...]
```

Sem argumentos, usa os hosts de teste (`*.aculta.toca.net.br`) e `example.com` como controle. A saída é uma tabela (URL, código, bytes, título) e um HTML por URL em `OUT_DIR`.

| Variável | Padrão | Função |
| --- | --- | --- |
| `DISPLAY` | `:0` | Sessão X11 local |
| `TIMEOUT` | 15 | Segundos por página |
| `FIREFOX` | `/usr/bin/firefox-esr` | Binário do Firefox |
| `GECKODRIVER` | `geckodriver` | Driver WebDriver |
| `OUT_DIR` | `/home/piradopirata/aculta-suite-out/browser-check-<data>` (ou `$SUITE_OUT`) | Saída (HTML e `resumo.txt`), perfil sob `profiles/`; `/tmp` é recusado |

Códigos de saída por URL: `0` página carregou; `124` tempo esgotado; `1` erro do WebDriver. O script sai com `2` se faltar a sessão X11, o geckodriver ou o Firefox.

O script é um verificador legado somente X11 e só verifica carregamento.
Capturas de tela e medições ficam nas suítes de `tests/browser/`. Para novos
smokes/matrizes genéricos, use `tests/browser/aculta-smoke/` em vez de ampliar
este script.

## Scripts reutilizáveis

Todo script de teste visual deve ser salvo em `tests/browser/<frente>/`, com README local, e não apenas em `/tmp`. O catálogo do que existe hoje, a convenção de pastas e as regras de evidência estão em `tests/browser/README.md`; consulte-o antes de escrever um script novo.

## Hosts e larguras

- Hosts de teste: `aculta.toca.net.br`, `links.aculta.toca.net.br`, `conta.aculta.toca.net.br/entrar`, `apoio.aculta.toca.net.br`. Controle: `example.com`.
- Larguras de captura: 1280 (desktop) e 390 (celular).

## Limites conhecidos

- Não substitui medição de contraste, rolagem horizontal ou foco por teclado.
- Turnstile aparece como "Verificando…" com o aviso "Somente para teste". É esperado no ambiente de teste.
- Firefox de sessões anteriores pode continuar aberto. Verificar com `pgrep -a firefox-esr`. Fechar só os que não estão em uso, conforme `AGENTS.md`.

## Atualização

Ao trocar a versão do Firefox ou do geckodriver, rodar o script contra `example.com` e atualizar as versões nesta página e em `AGENTS.md`.

## Harness reutilizável verificado em 2026-10-31

O WebDriver compartilhado em `tests/browser/aculta-cpf-checkout/wdlib.py` aceita
`Driver(browser="firefox|chrome|chromium", headless=True|False)`. Firefox
visível continua o padrão. Para qualquer purpose, o smoke GET genérico está em
`tests/browser/aculta-smoke/smoke-page.py` e a matriz reflow/claro-escuro/axe
opcional em `tests/browser/aculta-smoke/page-matrix.py`; leia o README local e o
catálogo em `tests/browser/README.md` antes de escrever outro. Para repetir a matriz
responsiva autenticada de Culturas, use `culturas-fast-gate.sh`: verifica SHA-256
do snapshot, percorre nove larguras, claro/escuro, foco e salva JSON/screenshots
em `$SUITE_OUT`. Com axe opcional use `CULTURAS_AXE=1`; `CULTURAS_AXE_STRICT=1`
faz violações retornarem código diferente de zero. Veja o README da suíte para
limitações e variáveis.

Versões instaladas e execução real: Firefox ESR 153.5.1, geckodriver 0.37.1,
Google Chrome 156.0.8078.47, Chromium 156.0.8078.73 e ChromeDriver
156.0.8078.73 (pacote Debian `chromium-driver`, instalado nesta máquina com
autorização). geckodriver já estava instalado, então não foi reinstalado. Em
2026-10-31, a matriz de Culturas sem axe concluiu
20/20 verificações em 13,5 s (Firefox ESR headless), 20/20 em 9,7 s (Chrome
headless) e 20/20 em 11,3 s (Chromium headless). Uma execução separada de axe
no Firefox registrou 3 violações por tema de cor (contraste, landmark e nome
acessível do filtro) e 1 item incompleto por tema. O diagnóstico é real e não
aprova WCAG; contraste da informação institucional e rótulo do filtro precisam
de correção, e os avisos de landmark precisam de triagem. O orquestrador dos
três browsers (`culturas-browser-matrix.sh`) completou a mesma matriz em 34,0s,
sequencialmente, com 20/20 em cada engine.

O smoke público X11 (Firefox ESR visível em `DISPLAY=:0`) e Firefox/Chrome/
Chromium headless carregou `https://aculta.toca.net.br/` com HTTP 200, estado
`complete`, um landmark main, cinco navs e sem overflow na viewport de cada
motor. Esses artefatos estão em `$SUITE_OUT` do operador que rodou o teste; não
são versionados. A captura X11 é revisão visual de uma página pública; não
substitui viewport controlado, zoom real nem fluxo autenticado. Artefatos da
matriz: `/home/piradopirata/aculta-suite-out/culturas-browser-matrix-20261031/`;
diagnóstico axe: `/home/piradopirata/aculta-suite-out/culturas-runtime-axe-firefox-final-20261031/`;
smoke X11 e engines: pastas `smoke-aculta-{x11,firefox,chrome,chromium}-20261031`
sob `/home/piradopirata/aculta-suite-out/`.

A matriz genérica foi também testada contra a raiz pública em 320/390/1280px e
claro/escuro: Firefox ESR headless passou 6/6 em 9,1s e registrou 3 ocorrências
axe por modo; Chrome headless passou 6/6 em 3,6s. Evidências em
`/home/piradopirata/aculta-suite-out/generic-page-matrix-{firefox,chrome}-20261031/`.
Em Firefox ESR visível/X11, o runner genérico passou também 2/2 viewports
(390/1280px), com captura em
`/home/piradopirata/aculta-suite-out/generic-page-matrix-x11-20261031/`.
