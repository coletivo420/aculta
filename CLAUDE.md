# Claude Code — ACULTA

Só o específico do Claude. Regras gerais: `AGENTS.md`. Ambientes: `docs/operations/AGENT-OPERATIONS.md`.

## Antes de começar

1. Leia `AGENTS.md`. Git: push direto em `origin/dev` a cada entrega; PR só com pedido explícito. A instrução de "PR por fase" do claude.ai está desatualizada; não a siga.
2. Regras do escopo: `docs/operations/AGENT-POLICY-INDEX.md`. Não leia `docs/history/`.
3. Confirme status no código e no git.
4. Decisões do responsável: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` e `docs/decisions/`. Decisão vale sobre plano; registre o conflito.

## Ponteiros

- **Navegador:** `docs/operations/NAVEGADOR-TESTES.md`. Ordem: Playwright com Firefox, Chromium e WebKit, navegador embutido, X11 com Gecko/Firefox ESR. Todos permitidos; prefira o de menor custo de tokens e declare o método usado.
- **Endereço e CEP:** `web/modules/custom/aculta_portal/modules/aculta_portal_cep/AGENTS.md`.
- **Mercado Pago:** `web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/AGENTS.md`.
- **Credenciais:** `docs/operations/SECRETS.md`.
- **Rotas administrativas:** gate `php scripts/validate-admin-routes-main-domain.php`.
- **Tema do Portal:** `web/modules/custom/aculta_portal/AGENTS.md`.
- **ACULTA420 visual:** `docs/architecture/ACULTA420-MOBILE-FIRST-GUARDRAILS.md` e `web/themes/custom/aculta420_v2/AGENTS.md`.

## Pesquisa na web

Liberada. Confirme antes de publicar ou enviar conteúdo para fora.

## Dúvida

Pare e pergunte ao responsável. Faça antes o que for independente.

