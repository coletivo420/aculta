---
name: aculta-navegador-testes
description: Regras para testes de navegador do ACULTA, com a ordem de preferência (embutido, WebDriver, Playwright, X11 Gecko), artefatos externos e scripts por frente. Use antes de criar ou rodar teste visual.
---

# Testes de navegador

1. Ordem de preferência: navegador embutido, WebDriver (Geckodriver), Playwright (Firefox distribuído pelo projeto) e X11 com Gecko/Firefox ESR visível. Todos são permitidos; prefira o que gera menos tokens. Declare o método usado. Chromium e WebKit ficam fora da ordem, como complemento declarado.
2. Artefatos (PNG, HTML, trace, vídeo e logs) vão para `$SUITE_OUT`, fora do repositório. Nunca versione evidência com dado de conta.
3. Credenciais vêm do ambiente ou de arquivo local ignorado pelo Git. Nunca grave senha, token ou link de uso único no script.
4. Declare o ambiente: servidor de desenvolvimento (`*.aculta.toca.net.br`) ou local (`127.0.0.1`). Não confunda os dois.
5. Use locators e `expect`, com espera por condição; nunca `sleep` fixo.
6. Script novo fica em `tests/browser/<frente>/`, com README local. Preserve os URLs e critérios da frente e os códigos: 0 passou, 124 timeout, 1 erro, 2 pré-requisito ausente.
7. Regras canônicas: `docs/operations/NAVEGADOR-TESTES.md` e `tests/browser/README.md`.
