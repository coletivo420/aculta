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
8. Localize antes de agir: `tests/browser/INDICE.md` lista todas as frentes com o caminho completo. Não procure por nome de pasta antiga.
9. Teste novo ou movido: depois de criar, mover ou alterar uma frente, rode `python3 tests/browser/gerar-indice.py` e `php scripts/validate-browser-index.php`.
10. Teste quebrado: pode corrigir o script e o README da frente, mas sem relaxar asserções. Se a falha for regressão do produto, reporte em vez de ajustar o teste.

## Frentes de componentes e UI Suite

- Reuse primeiro `tests/browser/INDICE.md`. A frente `transversal/aculta420-v2-component-library` já tem `library-mode.py` para catálogo e modo de cor; siga o README e os parâmetros reais do script.
- Em preview de SDC/UI Patterns, confira Library e preview sob demanda sem construir formulários reais desnecessários. Quando o requisito cobrir seleção/configuração, use a rota e o formulário reais da integração habilitada.
- Confira claro e escuro, 320 CSS px e viewport desktop definido pela frente. Compare conteúdo, hierarquia, foco, contraste e overflow; não alterne `system.theme`.
- Layout Builder: escolha uma página/bloco existente e confirme a região/placement e o resultado após salvar, sem alterar conteúdo persistente em teste somente visual.
- Views: valide somente a integração afetada, distinguindo campo, display e pager quando se aplicarem. Não presuma que todo formatter foi exposto por UI Patterns.
- Ícones: confira se o seletor/pack é realmente instalado e habilitado antes de testar configuração. O tema já possui packs próprios e um SDC `icon`; não invente rota de UI Icons.
- Formulário de configuração: teste permissões, valores salvos e retorno do preview; nunca use dados reais ou conteúdo persistente sem autorização.
- Testes de preview sob demanda devem provar estado inicial, carregamento, erro/fallback e acessibilidade da região de status quando esses comportamentos forem parte do diff.
- Regressão visual sem captura ou execução do browser fica DEFERRED, com viewport e ambiente pendentes explicitados.
