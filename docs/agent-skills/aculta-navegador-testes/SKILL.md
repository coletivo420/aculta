---
name: aculta-navegador-testes
description: Regras para testes de navegador do ACULTA: Firefox ESR visível, artefatos fora do repositório e scripts por frente. Use antes de criar ou rodar teste visual.
---

# Testes de navegador

1. Use Firefox ESR visível em `DISPLAY=:0`, via geckodriver. Não use Chromium nem headless.
2. Artefatos (PNG, HTML, logs) vão para `$SUITE_OUT`, fora do repositório. Nunca commite evidência com dado de conta.
3. Credenciais vêm de `secrets/*.env` ou do ambiente. Nunca grave senha no script.
4. Declare o ambiente: servidor de desenvolvimento (`*.aculta.toca.net.br`) ou local (`127.0.0.1`). Não confunda os dois.
5. Script novo fica em `tests/browser/<frente>/`, com `README.md` local.
6. Regras completas e histórico: `docs/portal/TESTES-NAVEGADOR.md` e `tests/browser/README.md`. Leia só a seção necessária.
