# Roadmap do fórum Growroom420 (ACULTA420 V2)

> **Plano transversal vigente (2026-11-09):** ADR-054 e `docs/architecture/ACULTA420-V2-REFATORACAO-SDC-LAYOUT-BUILDER.md`. Este roadmap segue focado no Fórum. O painel administrativo de container foi removido do código, mas o renderizador antigo continua até homologação da nova estrutura. O shell v2 (cinco áreas finais) está implementado como opt-in e testado por HTTP e Playwright Firefox; os layouts SDC são descobertos pelo Layout Builder; a UI Library ganhou prévias reais administrativas. Homologação visual completa, habilitação do Layout Builder por display, landing pages por purpose e cutover seguem pendentes (`docs/architecture/ACULTA420-V2-P8-HOMOLOGACAO.md`).


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
