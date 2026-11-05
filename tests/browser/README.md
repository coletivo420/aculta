# Testes de navegador — Playwright

Esta pasta é o projeto Node dos testes de navegador do ACULTA. O padrão único é Playwright com Firefox próprio; não use geckodriver, WebDriver, Firefox ESR do sistema, Chromium ou WebKit como gate.

## Projeto

```bash
cd tests/browser
npm ci
npx playwright install firefox
```

`package.json` e `package-lock.json` fixam `@playwright/test` em 1.56.1. Artefatos devem ficar em `$SUITE_OUT`, fora do repositório.

## Execução e ambientes

Declare sempre o ambiente:

```bash
SUITE_OUT=/caminho/externo ACULTA_BROWSER_ENV=desenvolvimento \
ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ npm run test:firefox

SUITE_OUT=/caminho/externo ACULTA_BROWSER_ENV=local \
ACULTA_SITE_ORIGIN=http://127.0.0.1/ npm run test:firefox
```

Hosts `*.aculta.toca.net.br` são servidor de desenvolvimento; `127.0.0.1` é local. Não misture os ambientes. O runner usa Firefox headless, locators, `expect` e espera por condição. Nunca use `sleep` fixo.

## Frentes inventariadas

As frentes existentes são: `aculta-avatar`, `aculta-calendar-admin`, `aculta-calendar-interactive`, `aculta-calendar-piloto`, `aculta-conta-raiz`, `aculta-content-admin`, `aculta-cpf-checkout`, `aculta-document-viewer`, `aculta-editor`, `aculta-events`, `aculta-faq`, `aculta-forum`, `aculta-forum-lms`, `aculta-frete`, `aculta-gallery`, `aculta-game`, `aculta-ganjalab`, `aculta-htmx-f1`, `aculta-image-admin`, `aculta-links`, `aculta-lms`, `aculta-lms-resources`, `aculta-maps`, `aculta-mercadopago`, `aculta-messenger`, `aculta-patcher`, `aculta-portal-cep`, `aculta-qrcode`, `aculta-secrets-manager`, `aculta-statistics`, `aculta-studio`, `aculta-theme-mobile-first`, `aculta-uploader`, `aculta-visual`, `aculta-wiki`, `aculta420-v2-card-accent`, `aculta420-v2-data-table`, `aculta420-v2-empty-state`, `aculta420-v2-forum`, `aculta420-v2-navigation`, `aculta420-v2-wiki`, `eventos`, `revista-color` e `revista-editorial`.

Cada README local é a fonte dos URLs, critérios, fixtures e variáveis da frente. O runner comum cobre carregamento, visibilidade do corpo, URL final, timeout, artefatos e os códigos de saída; critérios específicos devem ser expressos em testes Playwright da própria frente.

## Códigos

`0` passou; `124` timeout; `1` erro do Playwright/asserção; `2` pré-requisito ausente. Não versionar PNG, HTML, trace, vídeo, log ou credencial.
