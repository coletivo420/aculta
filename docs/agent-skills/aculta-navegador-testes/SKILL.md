---
name: aculta-navegador-testes
description: Regras para testes de navegador do ACULTA com Playwright e Firefox próprio, artefatos externos e scripts por frente. Use antes de criar ou rodar teste visual.
---

# Testes de navegador

1. Use Playwright com o Firefox distribuído pelo projeto. Chromium e WebKit não são navegadores de gate; geckodriver, WebDriver e Firefox ESR do sistema estão fora do fluxo.
2. Artefatos (PNG, HTML, trace, vídeo e logs) vão para `$SUITE_OUT`, fora do repositório. Nunca versione evidência com dado de conta.
3. Credenciais vêm do ambiente ou de arquivo local ignorado pelo Git. Nunca grave senha, token ou link de uso único no script.
4. Declare o ambiente: servidor de desenvolvimento (`*.aculta.toca.net.br`) ou local (`127.0.0.1`). Não confunda os dois.
5. Use locators e `expect`, com espera por condição; nunca `sleep` fixo.
6. Script novo fica em `tests/browser/<frente>/`, com README local. Preserve os URLs e critérios da frente e os códigos: 0 passou, 124 timeout, 1 erro, 2 pré-requisito ausente.
7. Regras canônicas: `docs/operations/NAVEGADOR-TESTES.md` e `tests/browser/README.md`.
