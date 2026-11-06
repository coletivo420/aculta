# Testes de navegador

Esta pasta é o projeto Node dos testes de navegador do ACULTA, com Playwright. Os comandos abaixo são de shell e valem para qualquer agente ou pessoa (Claude, Codex, AGY ou outro). A ordem de preferência está em `docs/operations/NAVEGADOR-TESTES.md`: Playwright com Firefox, Chromium e WebKit (mesmo runner), navegador embutido e X11 com Gecko/Firefox ESR visível. Todos são permitidos; prefira o de menor custo de tokens e declare o método usado no relatório.

## Instalação

Pré-requisito: Node 22 e npm. Versões fixadas no `package-lock.json`: `@playwright/test` 1.56.1.

```bash
cd tests/browser
npm ci
npx playwright install firefox chromium webkit
```

- Use `npx playwright install` sem `--with-deps`. Dependências do sistema exigem decisão e instalação com administrador.
- Se o WebKit reclamar de biblioteca do sistema, rode `npx playwright install-deps --dry-run webkit`. O comando imprime a lista de pacotes `apt` (não executa nada). Peça a um administrador para instalar, ou instale apenas se estiver autorizado.
- Para instalar só um motor, troque a lista: `npx playwright install firefox`, `npx playwright install chromium` ou `npx playwright install webkit`.

## Scripts por motor

Os três scripts usam o mesmo runner (`run-playwright.mjs`) e o motor vem de `ACULTA_BROWSER_ENGINE`:

| Script | Motor | Equivale a |
| --- | --- | --- |
| `npm run test:firefox` | Firefox Playwright (padrão) | `ACULTA_BROWSER_ENGINE=firefox` |
| `npm run test:chromium` | Chromium Playwright | `ACULTA_BROWSER_ENGINE=chromium` |
| `npm run test:webkit` | WebKit Playwright | `ACULTA_BROWSER_ENGINE=webkit` |

Se `ACULTA_BROWSER_ENGINE` não for definido, o padrão é `firefox`. Valores fora de `firefox`, `chromium` e `webkit` encerram com código `2`. `test:front` continua apontando para o runner com o motor padrão.

## Execução e ambientes

Declare sempre o ambiente e os artefatos. `SUITE_OUT` é um diretório externo ao repositório, onde ficam relatórios, traces, capturas e logs:

```bash
SUITE_OUT=/caminho/externo ACULTA_BROWSER_ENV=desenvolvimento \
ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ npm run test:firefox

SUITE_OUT=/caminho/externo ACULTA_BROWSER_ENV=local \
ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ npm run test:chromium
```

- Hosts `*.aculta.toca.net.br` são servidor de desenvolvimento; `127.0.0.1` é local. Não misture os ambientes.
- `ACULTA_BROWSER_ENV` define o ambiente declarado e, em `local`, ignora erro de certificado. Se não for definido, o runner assume `desenvolvimento`.
- A origem vem de `ACULTA_SITE_ORIGIN` ou de `ACULTA_BROWSER_URLS` (lista separada por vírgula). Pelo menos uma das duas é obrigatória (código `2` se faltarem).
- O runner usa headless, locators, `expect` e espera por condição. Nunca use `sleep` fixo.

No ambiente local, o Apache usa os VirtualHosts HTTPS do ACULTA. Os hosts de teste devem resolver para `127.0.0.1` no `/etc/hosts`. O URL mantém o hostname para preservar SNI e o VirtualHost correto.

## Teste mínimo de sanidade

Para verificar um motor sem fixture de conta, use uma página estática local, fora do repositório. Não use `about:blank`: a página vazia tem `body` sem tamanho e o `expect` de visibilidade falha. Não use `data:` (a vírgula da URL quebra o separador de `ACULTA_BROWSER_URLS`).

```bash
mkdir -p "$SUITE_OUT"
printf '<!doctype html><html><body><p>ACULTA smoke</p></body></html>\n' > "$SUITE_OUT/smoke.html"
SUITE_OUT="$SUITE_OUT" ACULTA_BROWSER_URLS="file://$SUITE_OUT/smoke.html" \
ACULTA_BROWSER_FRONTS=aculta-avatar npm run test:chromium
```

Troque `test:chromium` por `test:firefox` ou `test:webkit`. Código de saída `0` e um teste aprovado indicam o motor pronto.

## Método X11 com Firefox ESR visível (método 5)

Para aceite visual humano ou quando os métodos Playwright não reproduzem o caso. Não há script no repositório: o comando é executado diretamente, com um display X ativo. Em uma máquina com sessão gráfica, use o display existente (normalmente `:0`) e não suba outro servidor X.

```bash
mkdir -p "$SUITE_OUT/ff-profile"
DISPLAY=:0 firefox-esr --no-remote --profile "$SUITE_OUT/ff-profile" --new-instance "file://$SUITE_OUT/smoke.html"
```

- `DISPLAY` aponta para o display X visível; `SUITE_OUT` guarda o perfil temporário, que não deve ser o perfil pessoal do usuário.
- Sem sessão gráfica, suba um servidor virtual apenas se estiver autorizado: `Xvfb :99 -screen 0 1440x900x24 &`, registre o PID (`echo $!`) e encerre-o ao final com `kill <PID>`. Para isso, use `DISPLAY=:99`.

## Frentes inventariadas

As frentes existentes são: `aculta-avatar`, `aculta-calendar-admin`, `aculta-calendar-interactive`, `aculta-calendar-piloto`, `aculta-conta-raiz`, `aculta-content-admin`, `aculta-cpf-checkout`, `aculta-document-viewer`, `aculta-editor`, `aculta-events`, `aculta-faq`, `aculta-forum`, `aculta-forum-lms`, `aculta-frete`, `aculta-gallery`, `aculta-game`, `aculta-ganjalab`, `aculta-htmx-f1`, `aculta-image-admin`, `aculta-links`, `aculta-lms`, `aculta-lms-resources`, `aculta-maps`, `aculta-mercadopago`, `aculta-messenger`, `aculta-patcher`, `aculta-portal-cep`, `aculta-qrcode`, `aculta-secrets-manager`, `aculta-statistics`, `aculta-studio`, `aculta-theme-mobile-first`, `aculta-uploader`, `aculta-visual`, `aculta-wiki`, `aculta420-v2-card-accent`, `aculta420-v2-data-table`, `aculta420-v2-empty-state`, `aculta420-v2-forum`, `aculta420-v2-navigation`, `aculta420-v2-wiki`, `eventos`, `revista-color` e `revista-editorial`.

Cada README local é a fonte dos URLs, critérios, fixtures e variáveis da frente. O runner comum cobre carregamento, visibilidade do corpo, URL final, timeout, artefatos e os códigos de saída; critérios específicos devem ser expressos em testes Playwright da própria frente.

## Códigos

`0` passou; `124` timeout; `1` erro do Playwright/asserção; `2` pré-requisito ausente ou motor inválido. Não versionar PNG, HTML, trace, vídeo, log ou credencial.

## Autenticação de teste

No runtime `test`, os testes autenticados usam as credenciais locais documentadas em `secrets/` (ignoradas pelo Git). Antes da rodada, confirme que o ambiente é `test` e que `captcha.settings:enable_globally=0`; esse estado representa o bypass operacional de `skip CAPTCHA` exclusivo do servidor de testes. Não registre credenciais, tokens ou senhas nos artefatos e não use esse bypass em produção.
