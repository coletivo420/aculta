---
name: aculta-regressao-visual
description: Regressão visual determinística do ACULTA com Playwright (screenshots em 320 a 1440 px, claro e escuro). Use ao alterar CSS, SDC, UI Styles, UI Skins, UI Icons ou layout visual.
---

# Regressão visual no ACULTA

Use junto com `aculta-navegador-testes`, `css`, `sdc`, `ui-styles`, `ui-patterns` e `aculta-acessibilidade`.

## Estado real (leia antes de afirmar cobertura)

- **Cobertura parcial:** existem referencias versionadas da navegacao, mas nao uma suite ampla para todas as frentes. Conferir arquivo e baseline da rota afetada; ausencia de referencia nesta frente e DEFERRED, nao PASS.
- Uma frente sem (a) baseline aprovado, (b) browser/fontes fixados e (c) execucao autorizada permanece **DEFERRED**. Outras frentes com evidencias podem ter resultado proprio.
- Não diga "regressão visual PASS" sem essas três condições.

## Regras de determinismo

1. Browser e versão fixos pelo `@playwright/test` 1.56.1 em `tests/browser/package.json`. Motor de referência: Firefox (padrão do runner).
2. Viewports fixos: 320×800, 390×844, 768×1024, 1024×768, 1440×900. `deviceScaleFactor: 1`.
3. `reducedMotion: 'reduce'`, `animations: 'disabled'` e `caret: 'hide'` em todo screenshot.
4. Fontes: usar apenas as fontes locais do tema. Aguardar `document.fonts.ready` antes do snapshot.
5. Dados voláteis (datas, contadores, avatar, id de sessão, valores de saldo) são mascarados com `mask` do Playwright ou removidos de fixture. Não baixe dados reais para gerar snapshot.
6. Sem atualização automática: `--update-snapshots` é proibido em execução de CI e em falha. Snapshot novo exige revisão humana antes de ser versionado.
7. Nenhuma imagem pode conter credencial, dado pessoal, conteúdo privado de usuário ou sessão. Revise antes de commitar.

## Onde guardar

- Código da suíte: `tests/browser/playwright.visual.spec.mjs`, com config próprio `tests/browser/playwright.visual.config.mjs`. Fica fora do `testMatch` do config base, então a execução padrão não gera nem compara screenshot.
- Referências aprovadas: `tests/browser/__snapshots__/playwright.visual.spec.mjs/` (escolha do responsável). Versionadas somente após revisão: capturas de rotas públicas, sem sessão. Hoje há 10 referências da frente `aculta420-v2-navigation` (claro e escuro, 5 viewports), que passam em execução contra o dev.
- Gerar candidatas: `ACULTA_VISUAL_BASELINE=1 SUITE_OUT=<fora-do-repo>`. Copiar para `__snapshots__` só após revisão. Nunca `--update-snapshots`.
- Saídas temporárias (diff, trace, resultado bruto): sempre em `SUITE_OUT`, fora do repositório.

## Cobertura alvo (a implementar por frente, reaproveitando `routes.json`)

Tema claro e escuro; UI Skins; UI Styles; componentes SDC; UI Patterns Library; ACULTA UI Library; UI Icons Picker; modais; formulários; Layout Builder; Views; componentes do Fórum; componentes da Conta; Studio; barra multidomínio.

Para cada tela, o teste deve afirmar além do pixel: ausência de overflow horizontal em 320 px, alinhamento de bloco, hierarquia (título antes de corpo), e que o elemento esperado está visível.

## Comandos

- Listagem: `cd tests/browser && npm run test:list` (confirme a configuracao e a frente antes da execucao).
- Execução: `ACULTA_SITE_ORIGIN=<origem> SUITE_OUT=<fora-do-repo> npx playwright test --config playwright.config.mjs playwright.visual.spec.mjs`.
- Nunca use `--update-snapshots` para "fazer passar".

## Gate

- Quando alterar CSS, SDC, UI Styles, UI Skins ou UI Icons, o gate pede regressão visual nos viewports afetados. Sem execução, o resultado é DEFERRED, com motivo.
- Falha antiga (já presente antes da alteração) é registrada separadamente de falha nova.

## Relação com outras skills

- `aculta-acessibilidade` cuida de contraste e reflow funcionais; regressão visual cuida de aparência e alinhamento.
- `css` define tokens e modos; `sdc` define o contrato do componente que será fotografado.

## Contas de teste (uso)

- Capturas autenticadas usam o helper `tests/browser/auth-login.mjs`, com as mesmas regras de `aculta-acessibilidade` (credenciais em `secrets/`, nunca impressas, DEFERRED se a conta faltar).
- Capturas autenticadas exigem máscara de dados pessoais do usuário logado (nome, e-mail, avatar) e nunca devem ser versionadas sem revisão.
