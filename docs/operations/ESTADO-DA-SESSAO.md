# Estado da sessão

> **Limite de registro:** no máximo 3 entradas. Registrar só ao encerrar uma fase ou o trabalho, não a cada microfase. Entradas mais antigas ficam em `docs/history/operations/ESTADO-DA-SESSAO-ARQUIVO-2026-11-05.md`, sem alteração de texto.

# HTMX F1 — busca do shell e cadastro popup — 2026-11-05

- **Objetivo:** concluir as pendências apontadas da microfase HTMX F1: migrar a busca do shell e o cadastro popup para HTMX, validar com sessão/Form API reais e executar a matriz visual.
- **Concluído:** `1efae40765` (Portal) migra `SearchPopupForm`, `RegisterPopupForm`, `SearchUtility` e `search-preview.js`. No tema, `multidomain-bar.js` abre a busca por `hx-get`, sem Fetch nem `DOMParser`; `multidomain-bar.twig` ganhou `id="aculta-search-content"`, que faltava e impedia o alvo HTMX. Nova suíte `tests/browser/aculta-htmx-f1/`.
- **Verificações:** no Runtime de desenvolvimento, login real da conta de teste respondeu `HX-Redirect: /` e criou sessão; POST inválido do cadastro devolveu o fragmento com erro e não criou conta; busca retornou `data-hx-*` com `HX-Request` e manteve o script legado sem o cabeçalho. Suíte Firefox ESR visível: 26/26 checks (busca e login em 320/390/768/959/960/1440 px, claro/escuro, prévia HTMX, foco visível, zoom real 200%, fallback sem JavaScript). `validate-browser-validators` (21), `validate-aculta420-sdc-schemas` (55/764) e shell contract (13) PASS; PHP lint e `git diff --check` PASS.
- **Gates com falha preexistente:** `validate-aculta-portal-drupal11` e `validate-project-status-index` (versões divergentes de nove módulos), `validate-portal-popup-rule` (9 ocorrências em Events/Paths) e `validate-aculta420-v2-sdc` (`src/styles/buttons.css`). Nenhum arquivo dessas falhas foi alterado nesta entrega.
- **Pendências:** filtros de avisos por origem e leitura (`studio-estrutura.md` 4.5); consumer V2 do cadastro popup (checkout de doação, F5, aguardando autorização). ADR-050 F1 permanece parcial; não declarar concluída.
- **Próximo passo:** implementar os filtros de avisos ou retomar F2 depois da autorização do consumer do cadastro.

# Resolução do CLAUDE.md (aculta, branch dev) — 2026-11-05

- **Objetivo:** reduzir o CLAUDE.md a ponteiros e mover cada regra para o arquivo dono, conforme pedido do responsável.
- **Concluído:** `753ef5f1a3` em `origin/dev`. CLAUDE.md com 1494 bytes; `AGENTS.md` novos em `aculta_portal_cep` e `aculta_pagamentos_mercadopago`; linha de independência do tema em `aculta_portal/AGENTS.md`. No rebase, a regra de CEP já estava na raiz do `AGENTS.md` do remoto, então não a dupliquei no módulo do portal. `AGENTS.md` raiz não alterado (4984 bytes).
- **Verificações:** `validate-agent-token-budget.php` PASS (CLAUDE 1494/3000, handoff 3/3); `validate-module-theme-boundary.php` PASS (4248); `validate-root-policy.php` PASS (13 checks); `validate-admin-routes-main-domain.php` PASS (169 rotas).
- **Riscos:** nenhum registrado.
- **Pendências:** nenhuma desta entrega. O repositório é `aculta`.
- **Próximo passo:** nenhum.


# Migração data-portal-ui, mapa do V2 e upload centralizado — 2026-11-05

- **Objetivo:** migrar as classes de design de `aculta_lms`, `aculta_lms_resources` e `aculta_paths` para `data-portal-ui` e mapear os tokens no tema V2; centralizar o validador de upload da resposta do LMS.
- **Concluído:** `8dfa7ddb` (migração de 22 pontos, hook `preprocess` genérico no V2, gate de tokens que varre submódulos), `7c223cf6` (`ConsumerUploadPolicy::extensionValidators()`), `091cbf81` (39 tokens mapeados no V2 sem classe, atributo `data-portal-ui` mantido no DOM, regex do gate aceita `__`) e `044b079d` (remoção de `UserUploadPolicy` e do teste Kernel dela). Todos em `origin/dev`.
- **Verificações:** `php -l` PASS nos PHP alterados. `validate-module-theme-boundary.php` PASS (4248 verificações). `validate-aculta-image.php` PASS. `validate-agent-token-budget.php` PASS. Captura visual, PHPUnit e Kernel: DEFERRED (sem Runtime e sem `vendor/`).
- **Riscos:** o hook `preprocess` do V2 não foi executado no Runtime. Sem o mapa, token não mapeado não gera classe e o elemento perde estilo; a regressão visual só se confirma com captura.
- **Pendências:** (1) portar a cobertura do teste Kernel removido (formulário de abas, diálogo, inspeção de bytes) para `ConsumerUploadPolicy`, DEFERRED; (2) decidir `config/sync/aculta_portal.user_upload.yml` e o schema, que continuam sem uso (exigem pedido explícito); (3) captura visual de aulas, eventos e Studio no V2, porque os 39 tokens dependem de seletor de atributo.
- **Próximo passo:** o responsável decidir o item (2) e autorizar a porte do teste (1); depois, captura visual no Runtime.
