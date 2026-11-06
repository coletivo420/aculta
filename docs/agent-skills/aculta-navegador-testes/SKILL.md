---
name: aculta-navegador-testes
description: Testes de navegador do ACULTA: ordem de motores, artefatos em SUITE_OUT e reuso dos scripts salvos em tests/browser/<purpose>/<frente>/. Use antes de criar ou rodar teste de navegador.
---

# Testes de navegador

1. Ordem de preferência: Playwright com Firefox, Chromium e WebKit (mesmo runner), navegador embutido e X11 com Gecko/Firefox ESR visível. Todos são permitidos; prefira o que gera menos tokens. Declare o método usado.
2. Saídas temporárias (trace, vídeo, logs) vão para `$SUITE_OUT`. Capturas de evidência vão para `tests/browser/capturas/<frente>/<AAAA-MM-DD>/` com `manifest.json`; PNG fica fora do Git. Nunca versione dado de conta.
3. Credenciais vêm do ambiente ou de arquivo local ignorado pelo Git. Nunca grave senha, token ou link de uso único no script.
4. Declare o ambiente: servidor de desenvolvimento (`*.aculta.toca.net.br`) ou local (`127.0.0.1`). Não confunda os dois.
5. Use locators e `expect`, com espera por condição; nunca `sleep` fixo.
6. Reuse antes de criar: se `tests/browser/<purpose>/<frente>/` já tem script, rode-o seguindo o README local e não escreva outro. Script novo (só se a frente não tiver) fica nessa pasta, com README, para o próximo agente reutilizar. Preserve os URLs e critérios da frente e os códigos: 0 passou, 124 timeout, 1 erro, 2 pré-requisito ausente.
7. Regras canônicas: `docs/operations/NAVEGADOR-TESTES.md` e `tests/browser/README.md`.
