# Testes de navegador

Esta pasta é o projeto Node dos testes de navegador do ACULTA, com Playwright e Firefox próprio (método 3). A ordem de preferência está em `docs/operations/NAVEGADOR-TESTES.md`: Playwright com Firefox, Chromium e WebKit (mesmo runner), navegador embutido e X11 com Gecko/Firefox ESR visível. Todos são permitidos; prefira o de menor custo de tokens e declare o método usado no relatório.

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
ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ npm run test:firefox
```

Hosts `*.aculta.toca.net.br` são servidor de desenvolvimento; `127.0.0.1` é local. Não misture os ambientes. O runner usa Firefox headless, locators, `expect` e espera por condição. Nunca use `sleep` fixo.

No ambiente local, o Apache usa os VirtualHosts HTTPS do ACULTA. Os hosts de teste devem resolver para `127.0.0.1` no `/etc/hosts`; o Playwright aceita o certificado local somente quando `ACULTA_BROWSER_ENV=local`. O URL mantém o hostname para preservar SNI e o VirtualHost correto.

## Frentes inventariadas

As frentes existentes são: `aculta-avatar`, `aculta-calendar-admin`, `aculta-calendar-interactive`, `aculta-calendar-piloto`, `aculta-conta-raiz`, `aculta-content-admin`, `aculta-cpf-checkout`, `aculta-document-viewer`, `aculta-editor`, `aculta-events`, `aculta-faq`, `aculta-forum`, `aculta-forum-lms`, `aculta-frete`, `aculta-gallery`, `aculta-game`, `aculta-ganjalab`, `aculta-htmx-f1`, `aculta-image-admin`, `aculta-links`, `aculta-lms`, `aculta-lms-resources`, `aculta-maps`, `aculta-mercadopago`, `aculta-messenger`, `aculta-patcher`, `aculta-portal-cep`, `aculta-qrcode`, `aculta-secrets-manager`, `aculta-statistics`, `aculta-studio`, `aculta-theme-mobile-first`, `aculta-uploader`, `aculta-visual`, `aculta-wiki`, `aculta420-v2-card-accent`, `aculta420-v2-data-table`, `aculta420-v2-empty-state`, `aculta420-v2-forum`, `aculta420-v2-navigation`, `aculta420-v2-wiki`, `eventos`, `revista-color` e `revista-editorial`.

Cada README local é a fonte dos URLs, critérios, fixtures e variáveis da frente. O runner comum cobre carregamento, visibilidade do corpo, URL final, timeout, artefatos e os códigos de saída; critérios específicos devem ser expressos em testes Playwright da própria frente.

## Códigos

`0` passou; `124` timeout; `1` erro do Playwright/asserção; `2` pré-requisito ausente. Não versionar PNG, HTML, trace, vídeo, log ou credencial.

## Autenticação de teste

No runtime `test`, os testes autenticados usam as credenciais locais documentadas em `secrets/` (ignoradas pelo Git). Antes da rodada, confirme que o ambiente é `test` e que `captcha.settings:enable_globally=0`; esse estado representa o bypass operacional de `skip CAPTCHA` exclusivo do servidor de testes. Não registre credenciais, tokens ou senhas nos artefatos e não use esse bypass em produção.

