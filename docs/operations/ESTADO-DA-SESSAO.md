# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# Skills de handoff, padrões Drupal 12, segurança e Playwright (decisão) — 2026-11-05

- **Objetivo:** melhorar o handoff, adicionar skills de padrão Drupal e de segurança estática, adicionar a skill de alinhamento e renomear o padrão normativo para Drupal 12.
- **Concluído:** `2249d7a5` (handoff com passo de retomada, `aculta-drupal-padroes`, `aculta-seguranca-estatica`, limite de 9 skills), `a55dbe1d` (`aculta-grill-me`, `/graphify-out/` no `.gitignore`, limite de 10 skills), `576760cc` (renomeia `DRUPAL-11-STANDARDS.md` para `DRUPAL-12-STANDARDS.md`). Gate do Drupal renomeado para `validate-aculta-portal-drupal12.php` nesta entrega (commit a seguir).
- **Decisões:** Trail of Bits não copiada (licença CC-BY-SA; instalação do plugin fica com o responsável). Skill Drupal do registro não copiada (licença não declarada; conteúdo de Drupal 10 com DDEV); escrita versão própria. Navegador de teste: **Playwright como padrão único** (opção B), que aposenta o geckodriver e o Firefox ESR; o aceite visual passa a ser captura do Playwright. Esta decisão ainda não está em `NAVEGADOR-TESTES.md`.
- **Verificações:** `validate-agent-token-budget.php` PASS (skills 10). `validate-aculta-portal-drupal12.php` DEFERRED: falta `vendor/`. Gate de caminhos PASS. Graphify: grafo gerado sem LLM (127.738 nós, 202.904 arestas), consulta funcionando; semgrep instalado, varredura de linha de base não feita.
- **Riscos:** o padrão de navegador em `NAVEGADOR-TESTES.md` e na skill `aculta-navegador-testes` ainda diz Firefox ESR até a migração. Commit de V1 acidental foi revertido antes do push.
- **Pendências:** migração para Playwright (prompt já entregue ao agente local); varredura do Semgrep; decidir o plugin da Trail of Bits; rodar o gate do Drupal 12 onde houver `vendor/`.
- **Próximo passo:** o agente local migra os testes para Playwright e atualiza `NAVEGADOR-TESTES.md` e a skill, e depois roda a varredura do Semgrep sobre os módulos alterados.

# HTMX F1 — busca do shell e cadastro popup — 2026-11-05

- **Objetivo:** concluir as pendências apontadas da microfase HTMX F1: migrar a busca do shell e o cadastro popup para HTMX, validar com sessão/Form API reais e executar a matriz visual.
- **Concluído:** `1efae40765` (Portal) migra `SearchPopupForm`, `RegisterPopupForm`, `SearchUtility` e `search-preview.js`; `31a061ef75` corrige a pendência documental dos filtros. No tema, `multidomain-bar.js` abre a busca por `hx-get`, sem Fetch nem `DOMParser`; `multidomain-bar.twig` ganhou `id="aculta-search-content"`, que faltava e impedia o alvo HTMX. Nova suíte `tests/browser/aculta-htmx-f1/`. Validação real encontrou e este commit corrige `data-hx-post=""` no login e no cadastro, que enviava o POST à página atual em vez da rota do popup; agora a URL é explícita.
- **Verificações:** no Runtime de desenvolvimento, login real da conta de teste autenticou e o `HX-Redirect` navegou; POST inválido do cadastro devolveu o fragmento com erro e não criou conta; busca retornou `data-hx-*` com `HX-Request` e manteve o script legado sem o cabeçalho. Suíte Firefox ESR visível: 43/43 checks (busca, login e gaveta de avisos autenticada em 320/390/768/959/960/1440 px, claro/escuro, prévia HTMX, foco visível, zoom real 200%, Escape com retorno de foco e fallback sem JavaScript). `validate-browser-validators` (21), `validate-aculta420-sdc-schemas` (55/764) e shell contract (13) PASS; PHP lint e `git diff --check` PASS.
- **Gates com falha preexistente:** `validate-aculta-portal-drupal11` e `validate-project-status-index` (versões divergentes de nove módulos), `validate-portal-popup-rule` (9 ocorrências em Events/Paths) e `validate-aculta420-v2-sdc` (`src/styles/buttons.css`). Nenhum arquivo dessas falhas foi alterado nesta entrega.
- **Pendências:** consumer V2 do cadastro popup (checkout de doação, F5, aguardando autorização). O critério de navegador de F1 (busca, login, gavetas, foco, `aria-live` e teclado) está coberto; o consumer do cadastro é F5.
- **Próximo passo:** retomar F2 ou aguardar a autorização do consumer do cadastro.

# Resolução do CLAUDE.md (aculta, branch dev) — 2026-11-05

- **Objetivo:** reduzir o CLAUDE.md a ponteiros e mover cada regra para o arquivo dono, conforme pedido do responsável.
- **Concluído:** `753ef5f1a3` em `origin/dev`. CLAUDE.md com 1494 bytes; `AGENTS.md` novos em `aculta_portal_cep` e `aculta_pagamentos_mercadopago`; linha de independência do tema em `aculta_portal/AGENTS.md`. No rebase, a regra de CEP já estava na raiz do `AGENTS.md` do remoto, então não a dupliquei no módulo do portal. `AGENTS.md` raiz não alterado (4984 bytes).
- **Verificações:** `validate-agent-token-budget.php` PASS (CLAUDE 1494/3000, handoff 3/3); `validate-module-theme-boundary.php` PASS (4248); `validate-root-policy.php` PASS (13 checks); `validate-admin-routes-main-domain.php` PASS (169 rotas).
- **Riscos:** nenhum registrado.
- **Pendências:** nenhuma desta entrega. O repositório é `aculta`.
- **Próximo passo:** nenhum.
