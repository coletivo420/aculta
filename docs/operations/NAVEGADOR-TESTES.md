# Testes de navegador

## Ordem de preferência

Todos os métodos abaixo são permitidos. Use o primeiro que atender ao teste, nesta ordem, priorizando o que gera menos tokens:

1. **Playwright com Firefox:** Firefox distribuído pelo Playwright, em `tests/browser`. É o padrão para suítes repetíveis, com artefatos em `$SUITE_OUT`.
2. **Playwright com Chromium:** mesmo runner e mesmo script, com o motor Chromium. Para comparar motores.
3. **Playwright com WebKit:** mesmo runner e mesmo script, com o motor WebKit. Para cobertura de motor.
4. **Navegador embutido (built-in browser):** ferramentas `mcp__Claude_Browser__*` ou `mcp__remote-devices__Claude_Browser__*`, quando disponíveis. Para verificação pontual de uma página, porque cada passo custa tokens.
5. **X11 com Gecko/Firefox ESR visível:** para aceite visual humano ou quando os métodos acima não reproduzem o caso. É o mais caro em tokens, porque depende de captura e leitura de imagem.

A ordem vale por custo: scripts com asserções de texto custam quase nada por execução; operar o navegador passo a passo ou olhar imagens custa mais.

Regras de economia:

- Confira por texto (título, status, atributo, contagem) sempre que possível. Capture imagem só para aparência.
- Não repita o mesmo teste em mais de um método sem motivo. Se os resultados divergirem, registre qual prevaleceu e por quê.
- No relatório, declare o método usado em cada teste.

Versões fixadas no projeto: `@playwright/test` 1.56.1 (no `package-lock.json`). A instalação dos três motores Playwright é:

```bash
cd tests/browser
npm ci
npx playwright install firefox chromium webkit
```

Não use `--with-deps`: dependências do sistema exigem decisão separada. Se o WebKit reclamar de biblioteca, `npx playwright install-deps --dry-run webkit` lista os pacotes `apt` sem instalar nada.

Os scripts são `npm run test:firefox`, `npm run test:chromium` e `npm run test:webkit`. Todos chamam o mesmo runner; o motor vem de `ACULTA_BROWSER_ENGINE` (padrão `firefox`). Detalhes em `tests/browser/README.md`.

Para o método 5 (X11 com Firefox ESR), não há script: `DISPLAY=:0 firefox-esr --no-remote --profile "$SUITE_OUT/ff-profile" --new-instance "file://$SUITE_OUT/pagina.html"`, com `SUITE_OUT` externo ao repositório e um display X já ativo.

## Execução

Cada execução declara `ACULTA_BROWSER_ENV` como `desenvolvimento` para hosts `*.aculta.toca.net.br` ou `local` para o Apache em loopback. A origem vem de `ACULTA_SITE_ORIGIN` ou as URLs, separadas por vírgula, de `ACULTA_BROWSER_URLS`. No modo local, use o hostname ACULTA apontando para `127.0.0.1`, não `http://127.0.0.1/`, para preservar SNI e o VirtualHost HTTPS; o certificado é ignorado somente nesse modo.

```bash
SUITE_OUT="${SUITE_OUT:?diretório externo}" \
ACULTA_BROWSER_ENV=desenvolvimento \
ACULTA_SITE_ORIGIN="https://aculta.toca.net.br/" \
npm run test:firefox
```

Troque `test:firefox` por `test:chromium` ou `test:webkit` para outro motor, com o mesmo ambiente e os mesmos artefatos. Para o teste mínimo de sanidade, use página estática local em `file://` dentro de `$SUITE_OUT`, não `about:blank` nem `data:`, conforme `tests/browser/README.md`.

O runner espera condições com locators e `expect`; não usa `sleep` fixo. Capturas, traces, logs e relatórios ficam em `$SUITE_OUT`, fora do repositório. Credenciais somente vêm do ambiente ou de arquivo local ignorado pelo Git.

## Códigos de saída

| Código | Significado |
| --- | --- |
| 0 | URLs carregaram e asserções passaram |
| 124 | tempo limite de navegação/asserção |
| 1 | erro do Playwright ou da asserção |
| 2 | pré-requisito ausente, como origem ou URLs |

## Reuso de scripts por frente

Antes de escrever qualquer teste de navegador, verifique se a frente já tem script salvo em `tests/browser/<frente>/`, por exemplo com `rg --files tests/browser/<frente>`. Se houver:

- rode o script existente com as variáveis descritas no README da frente;
- não escreva um script paralelo para o mesmo fluxo.

Se a frente não tiver script, crie-o em `tests/browser/<frente>/` com um README local, para que o próximo agente reutilize. Alterações em script existente entram no mesmo commit da entrega. Nunca grave segredo, link de login de uso único ou caminho absoluto de máquina no script ou no README.

O runner comum (`playwright.spec.mjs`) cobre só smoke da origem; a cobertura funcional está nos scripts das frentes.

## Escopo e limites

O catálogo e as frentes estão em `tests/browser/README.md`. Cada frente mantém seu README e seus critérios; o runner comum fornece Firefox, espera, timeout, artefatos e códigos consistentes. Fluxos autenticados exigem variáveis de ambiente documentadas pela frente. Não grave credenciais nem evidências no Git.

O padrão anterior está arquivado em `docs/history/operations/NAVEGADOR-TESTES-GECKODRIVER-2026-11-05.md`. A ordem acima substitui a exclusão de Geckodriver e WebDriver, em decisão do responsável em 2026-11-06.
