---
name: aculta-acessibilidade
description: Revisa acessibilidade WCAG 2.2 A/AA de telas, SDCs, ícones e HTMX com axe-core no Playwright e checagens manuais. Use ao alterar interface, formulário, modal ou atualização HTMX.
---

# Acessibilidade no ACULTA

Use junto com `aculta-navegador-testes` (motores, `SUITE_OUT`, scripts por frente), `sdc`, `css`, `ui-patterns`, `ui-styles`, `ui-icons` e `ui-suite-integrations`. Para interações HTMX, use também `htmx`.

## O que a automação cobre e o que não cobre

- **Coberto:** axe-core (WCAG 2.0/2.1/2.2 A e AA) sobre as rotas públicas de cada `routes.json`; reflow em 320 CSS px sem rolagem horizontal; execução com `prefers-reduced-motion: reduce`.
- **Não coberto, exige pessoa:** navegação completa por teclado, leitor de tela (NVDA, Orca), contraste sobre imagem ou gradiente, comportamento de modal e mensagem de erro.
- Um teste verde **não** comprova acessibilidade. Declare isso no relatório.

## Suíte e comandos

- Arquivo: `tests/browser/playwright.a11y.spec.mjs`, descoberto pelo `playwright.config.mjs` (`testMatch` inclui `.a11y`). Os runners `playwright.spec.mjs` e `playwright.routes.spec.mjs` continuam intactos.
- Dependência fixa: `@axe-core/playwright` 4.14.0.
- Execução: `cd tests/browser && ACULTA_SITE_ORIGIN=<origem> SUITE_OUT=<fora-do-repo> npm run test:a11y`.
- Sem a variável da frente, os testes ficam **skipped**. Skipped não é PASS: registre DEFERRED.

## Critérios de bloqueio

- Falha em impacto `serious` ou `critical` bloqueia. `minor` e `moderate` viram dívida registrada.
- `incomplete` do axe (ex.: contraste sobre imagem) não é violação; vai para auditoria manual.
- Não altere o tema para o teste passar sem evidência de bug real. Confirme regra, seletor e cor computada; depois corrija o componente ou o token, com teste de regressão.

## Checklist por tela

Itens detalhados em [references/checklist-por-tela.md](references/checklist-por-tela.md): formulários e erros, modais, HTMX, ícones decorativos e informativos, foco visível, alvos de toque, cor e tema, zoom e reflow, movimento, idioma e título.

## Registro

- Resultado por rota e regra vai para `docs/operations/testing/`, não para o AGENTS.md.
- Relatórios brutos (JSON, trace, screenshot) ficam em `SUITE_OUT`. Não versione imagem com dado pessoal.
- Contraste confirmado vira dívida com seletor, cores de texto e fundo, razão medida e token responsável.

## Relação com outras skills

Tokens e contraste: `css`. Markup de SDC: `sdc`. Classes editoriais: `ui-styles`. Ícones: `ui-icons`. Fragmentos e DOM: `htmx`. Motores: `aculta-navegador-testes`.

## Contas de teste (uso)

- As credenciais ficam em `secrets/` na raiz do checkout principal, ignorada pelo Git. O helper `tests/browser/auth-login.mjs` as lê em tempo de execução. Nunca abra, copie ou imprima esses arquivos; cite apenas o nome da conta.
- Login: a janela de login do portal é `/entrar/janela` (`/entrar` responde 404). O envio é AJAX e a URL não muda. O helper confirma só que o envio não mostrou erro; o efeito da sessão é verificado pelo teste (ex.: `/user` não termina em login).
- Contas declaradas pelo helper: `teste_comum` e `teste_administrativo`, cada uma com um `.env` próprio. Se o `.env` não existir, o login falha com erro de conta desconhecida ou de credencial ausente: registre DEFERRED, não PASS.
- Para testes autenticados de acessibilidade, use `login(page, host, 'teste_comum')` e, antes, confirme que o ambiente é `test` e que `captcha.settings:enable_globally=0` (ver `tests/browser/README.md`, seção "Login de teste por frente").
- Conta administrativa só para verificar acesso negado e permissões; não use para alterar conteúdo.
