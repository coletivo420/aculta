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

Estão listadas abaixo as pastas reais, com o caminho completo. A convenção é `tests/browser/<purpose>/<frente>/` (purpose: `account`, `courses`, `eventos`, `forum`, `gallery`, `links`, `magazine`, `shop`, `studio`, `support`, `transversal`, `wiki`). Frentes antigas na raiz (`aculta-avatar`, `aculta-game`, etc.) continuam válidas. Antes de criar um script, procure a frente nesta tabela.

**Manutenção do índice.** `tests/browser/INDICE.md`, a tabela abaixo e os `README.md` das pastas agrupadoras são gerados por `python3 tests/browser/gerar-indice.py`. Depois de criar, mover ou remover uma frente, rode o script. `php scripts/validate-browser-index.php` é o gate: confere o `--check` e se algum documento cita caminho inexistente. Regras de criação, alteração e correção de testes: `docs/operations/NAVEGADOR-TESTES.md`, seção "Manutenção".

<!-- INDICE:INICIO -->

Índice completo, com contagem de scripts e `routes.json`: [`INDICE.md`](./INDICE.md).

| Pasta | Frente |
|---|---|
| `tests/browser/account/aculta-conta-raiz/` | Raiz da Conta (Domain ACCOUNT): verificação anônima no navegador |
| `tests/browser/aculta-avatar/` | Teste visual — avatar padrão |
| `tests/browser/aculta-game/` | Smoke visual do ACULTA Game |
| `tests/browser/aculta-maps/` | ACULTA Maps — visual smoke |
| `tests/browser/aculta-messenger/` | Suíte de navegador do ACULTA Messenger |
| `tests/browser/aculta-patcher/` | Revisão visual do ACULTA Patcher |
| `tests/browser/aculta-portal-cep/` | Testes do Portal CEP |
| `tests/browser/aculta-qrcode/` | Validação visual do ACULTA QR Code |
| `tests/browser/aculta-secrets-manager/` | Painel de credenciais (aculta_secrets_manager) |
| `tests/browser/aculta-statistics/` | Revisão visual do painel de estatísticas |
| `tests/browser/courses/aculta-forum-lms/` | ACULTA Forum LMS — diálogo sob conteúdo H5P |
| `tests/browser/courses/aculta-lms/` | Revisão visual do formulário de resposta com arquivo (LMS) |
| `tests/browser/courses/aculta-lms-resources/` | Revisão visual do campo de vídeos do curso |
| `tests/browser/eventos/` | Navegador — eventos (agenda e incorporação) |
| `tests/browser/eventos/aculta-calendar-admin/` | Revisão visual de ADM8 — hosts de incorporação do calendário |
| `tests/browser/eventos/aculta-calendar-interactive/` | Calendário interativo — revisão visual servida |
| `tests/browser/eventos/aculta-calendar-piloto/` | Piloto isolado — DHTMLX Scheduler (edição GPL) |
| `tests/browser/eventos/aculta-events/` | Firefox checks for ACULTA Studio Culturas organization layouts |
| `tests/browser/forum/aculta-forum/` | Suíte visual X11 do ACULTA Forum |
| `tests/browser/forum/aculta420-v2-forum/` | Firefox ESR visual smoke — Fórum V2 |
| `tests/browser/gallery/aculta-gallery/` | Galeria ACULTA |
| `tests/browser/links/aculta-links/` | Micropágina pública: layout mobile no navegador |
| `tests/browser/magazine/aculta-gallery-ui-patterns/` | Galeria editorial — UI Patterns Views |
| `tests/browser/magazine/aculta-news-ui-patterns/` | UI Patterns Views — listagem da Revista |
| `tests/browser/magazine/revista-color/` | Revista — rótulos de categoria |
| `tests/browser/magazine/revista-editorial/` | Revista — fluxo editorial (ARQ-08) |
| `tests/browser/shop/aculta-cpf-checkout/` | Navegador — CPF/checkout compartilhado Loja/LMS |
| `tests/browser/shop/aculta-frete/` | Navegador — simulador de frete na página do produto |
| `tests/browser/shop/aculta-mercadopago/` | Mercado Pago — login sandbox do comprador |
| `tests/browser/shop/aculta-shop-catalog-ui-patterns/` | Catálogo da Loja — UI Patterns Views |
| `tests/browser/studio/` | Testes do ACULTA Studio, por subcategoria |
| `tests/browser/studio/culturas/` | Studio · Culturas |
| `tests/browser/studio/ganjalab/` | Experiência educacional ACULTA Studio Ganjalab |
| `tests/browser/studio/geral/` | Revisão visual e de navegação do ACULTA Studio |
| `tests/browser/studio/green-stash/` | Studio · Green Stash |
| `tests/browser/studio/marley/` | Studio · Marley |
| `tests/browser/studio/we-sativa/` | Studio · WE SATIVA |
| `tests/browser/support/aculta-faq/` | Revisão visual do ACULTA FAQ |
| `tests/browser/transversal/aculta-botoes-a11y/` | Leitor de tela (Orca) — botões em estados autenticados |
| `tests/browser/transversal/aculta-content-admin/` | Revisão visual do Painel de conteúdo |
| `tests/browser/transversal/aculta-document-viewer/` | Revisão visual do ACULTA Document Viewer |
| `tests/browser/transversal/aculta-editor/` | ACULTA Editor — acesso anônimo, editor e administrador |
| `tests/browser/transversal/aculta-home-editorial-ui-patterns/` | Home editorial — UI Patterns Views |
| `tests/browser/transversal/aculta-htmx-f1/` | ACULTA420 V2 — HTMX F1 (busca e login) |
| `tests/browser/transversal/aculta-image-admin/` | Revisão visual das opções administrativas do ACULTA Image |
| `tests/browser/transversal/aculta-theme-mobile-first/` | ACULTA420 — SDC responsivos do tema |
| `tests/browser/transversal/aculta-uploader/` | Smoke do aculta_file_uploader (navegador) |
| `tests/browser/transversal/aculta-visual/` | Revisão visual da Frente B |
| `tests/browser/transversal/aculta420-v2-account-menu/` | ACULTA420 V2 — account menu |
| `tests/browser/transversal/aculta420-v2-card-accent/` | ACULTA420 V2 — faixa de acento do cartão (DRAFT) |
| `tests/browser/transversal/aculta420-v2-component-library/` | ACULTA420 V2 — biblioteca de componentes |
| `tests/browser/transversal/aculta420-v2-data-table/` | ACULTA420 V2 — Data table |
| `tests/browser/transversal/aculta420-v2-empty-state/` | ACULTA420 V2 — Empty state |
| `tests/browser/transversal/aculta420-v2-footer/` | ACULTA420 V2 — rodapé e regions do shell |
| `tests/browser/transversal/aculta420-v2-navigation/` | Navegação SDC do ACULTA420 V2 |
| `tests/browser/transversal/ui-styles-block/` | UI Styles Block — bloco de Documentos institucionais |
| `tests/browser/wiki/aculta-wiki/` | ACULTA Wiki — visual smoke test |
| `tests/browser/wiki/aculta420-v2-wiki/` | ACULTA420 V2 — visual Wiki |
<!-- INDICE:FIM -->

Cada README local é a fonte dos URLs, critérios, fixtures e variáveis da frente. O runner comum cobre carregamento, visibilidade do corpo, URL final, timeout, artefatos e os códigos de saída; critérios específicos devem ser expressos em testes Playwright da própria frente.

**Cobertura real por frente.** O `playwright.spec.mjs` faz só smoke: abre a origem (`ACULTA_SITE_ORIGIN` ou `ACULTA_BROWSER_URLS`) em cada frente e confirma carregamento. Ele não percorre as rotas da frente. A cobertura funcional fica no script da própria frente (`visual.py`, `*.mjs` ou similar) descrito no README local, que define suas rotas, login e ambiente. Uma frente que precise de rotas específicas roda o script dela, não o runner comum.

**Reuso.** Antes de escrever teste novo, procure `tests/browser/<purpose>/<frente>/` e rode o script que já existe, seguindo o README local. Crie script novo só se a frente não tiver um, salvando-o na mesma pasta com README, para o próximo agente reutilizar. Regras em `docs/operations/NAVEGADOR-TESTES.md`, seção "Reuso de scripts por frente". Não há URL por frente no runner, de propósito: as frentes têm ambientes e pré-requisitos diferentes, e um mapa único ficaria falso.

## Códigos

`0` passou; `124` timeout; `1` erro do Playwright/asserção; `2` pré-requisito ausente ou motor inválido. Não versionar PNG, HTML, trace, vídeo, log ou credencial.

## Cobertura por frente (routes.json)

Cada frente com rotas públicas declaradas ganha um `routes.json` na sua pasta (`tests/browser/<purpose>/<frente>/routes.json`, ou `tests/browser/<purpose>/routes.json` quando a frente é a própria pasta do purpose). O `playwright.routes.spec.mjs` descobre todos e testa só `public_routes`: status abaixo de 400 e `body` visível.

```json
{
  "front": "eventos",
  "purpose": "eventos",
  "origin_env": "EVENTOS_BASE",
  "source": "README da frente, seção de rotas",
  "public_routes": [{ "path": "/" }, { "path": "/mapa" }],
  "notes": "rotas dinâmicas e de login ficam fora"
}
```

- `origin_env` aponta para a variável com o host da frente. Sem ela, o teste é pulado com motivo.
- Rotas que exigem login, dados ou data específica não entram em `public_routes`; ficam em `notes` e no script da frente.
- Para rodar só uma frente: `ACULTA_BROWSER_FRONTS=<frente>` com a variável de host definida.

### Login de teste por frente (campo `auth`)

Frentes que precisam de sessão declaram `"auth": { "account": "teste_comum" }` (ou `teste_administrativo`). O `auth-login.mjs` lê as credenciais de `secrets/` na raiz do checkout principal, em tempo de execução, e entra pelo `/entrar` do host de conta (`CONTA_HOST`). Nenhum valor é impresso. O runner gera um teste de login por frente; `auth_routes` lista as rotas autenticadas que a frente testa depois do login.

Frentes sem decisão de login (rota pública ou privada ainda não confirmada) são classificadas pelo agente que roda o teste: ele verifica por categoria quais páginas são públicas e quais exigem sessão, e cria testes mistos (visualização pública e privada) quando a frente mistura as duas.

## Autenticação de teste

No runtime `test`, os testes autenticados usam as credenciais locais documentadas em `secrets/` (ignoradas pelo Git). Antes da rodada, confirme que o ambiente é `test` e que `captcha.settings:enable_globally=0`; esse estado representa o bypass operacional de `skip CAPTCHA` exclusivo do servidor de testes. Não registre credenciais, tokens ou senhas nos artefatos e não use esse bypass em produção.

## Capturas

As capturas ficam dentro do projeto, em `tests/browser/capturas/<frente>/<AAAA-MM-DD>/`, com caminho relativo. Os PNG e JPG ficam fora do Git pelo `.gitignore` deste diretório. Cada pasta de data tem um `manifest.json` versionado com método, motor, viewport, modo de cor, URL relativa, resultado das asserções e hash do commit testado. Comparações usam `before/` e `after/`. A regra completa está em `docs/operations/NAVEGADOR-TESTES.md`.
