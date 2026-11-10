# Roadmap do fórum Growroom420 (ACULTA420 V2)

> **Corte ADR-054 concluído (2026-11-09):** o tema tem as cinco regiões finais, o shell v2 é o padrão e o motor de container foi removido. Layout Builder cobre 22 dos 24 displays compatíveis, a landing Links está publicada no LB e a UI Library oferece prévias reais. Pendências: revisão visual humana, conteúdo editorial das demais landings e dívida de boundary dos módulos do Portal. Rollback documentado em `docs/architecture/ACULTA420-V2-P9-CUTOVER.md`.


Status espelhado de `TODO.md`. Plano visual: `docs/architecture/GROWROOM420-VISUAL-PLANO.md`.

## Entregas

| Entrega | Escopo | Estado |
| --- | --- | --- |
| 1 | Lista de tópicos, página de tópico, cabeçalho de categoria | Aplicada e publicada. Captura Firefox ESR: 76/76 |
| 2 | Painel lateral da lista, diretrizes e acabamento do `/criar-topico` | Aplicada em CSS. Captura do formulário pendente |

## Pendências

- Migração do vocabulário de tokens para `--aculta-*`, conforme decidido. Pendente, em etapas.
- Painéis de destaque, moderadores e relacionados: aguardam o contrato com o Portal.
- Refatoração de fronteira do `aculta_studio`: não commitada, aguarda renderização e testes Kernel/Unit.
