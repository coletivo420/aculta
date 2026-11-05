# Testes de navegador

## Padrão vigente

Playwright com o Firefox distribuído pelo Playwright é o navegador único dos testes automatizados e do aceite visual. Chromium e WebKit não são navegadores de gate. Geckodriver, WebDriver e Firefox ESR do sistema não fazem parte do fluxo.

Versões fixadas no projeto: Node definido pelo ambiente, `@playwright/test` 1.56.1 e Firefox Playwright 142.0.1 (build Playwright 1495). A instalação usa apenas:

```bash
cd tests/browser
npm ci
npx playwright install firefox
```

Não use `--with-deps`: dependências do sistema exigem decisão separada.

## Execução

Cada execução declara `ACULTA_BROWSER_ENV` como `desenvolvimento` para hosts `*.aculta.toca.net.br` ou `local` para o Apache em loopback. A origem vem de `ACULTA_SITE_ORIGIN` ou as URLs, separadas por vírgula, de `ACULTA_BROWSER_URLS`. No modo local, use o hostname ACULTA apontando para `127.0.0.1`, não `http://127.0.0.1/`, para preservar SNI e o VirtualHost HTTPS; o certificado é ignorado somente nesse modo.

```bash
SUITE_OUT="${SUITE_OUT:?diretório externo}" \
ACULTA_BROWSER_ENV=desenvolvimento \
ACULTA_SITE_ORIGIN="https://aculta.toca.net.br/" \
npm run test:firefox
```

O runner espera condições com locators e `expect`; não usa `sleep` fixo. Capturas, traces, logs e relatórios ficam em `$SUITE_OUT`, fora do repositório. Credenciais somente vêm do ambiente ou de arquivo local ignorado pelo Git.

## Códigos de saída

| Código | Significado |
| --- | --- |
| 0 | URLs carregaram e asserções passaram |
| 124 | tempo limite de navegação/asserção |
| 1 | erro do Playwright ou da asserção |
| 2 | pré-requisito ausente, como origem ou URLs |

## Escopo e limites

O catálogo e as frentes estão em `tests/browser/README.md`. Cada frente mantém seu README e seus critérios; o runner comum fornece Firefox, espera, timeout, artefatos e códigos consistentes. Fluxos autenticados exigem variáveis de ambiente documentadas pela frente. Não grave credenciais nem evidências no Git.

O padrão anterior está arquivado em `docs/history/operations/NAVEGADOR-TESTES-GECKODRIVER-2026-11-05.md`.
