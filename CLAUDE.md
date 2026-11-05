# Claude Code — ACULTA

Só o específico do Claude. Regras gerais: `AGENTS.md`. Ambientes: `docs/operations/AGENT-OPERATIONS.md`.

## Antes de começar

1. Leia `AGENTS.md`. Git: push direto em `origin/dev` a cada entrega; PR só com pedido explícito. A instrução de "PR por fase" do claude.ai está desatualizada; não a siga.
2. Regras do escopo: `docs/operations/AGENT-POLICY-INDEX.md`. Não leia `docs/history/`.
3. Confirme status no código e no git.
4. Decisões do responsável: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md` e `docs/decisions/`. Decisão vale sobre plano; registre o conflito.

## Ponteiros

- **Navegador:** `docs/operations/NAVEGADOR-TESTES.md`. Firefox ESR visível para aceite; headless para gates repetitivos, declarado; Chromium só complementar.
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

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.
