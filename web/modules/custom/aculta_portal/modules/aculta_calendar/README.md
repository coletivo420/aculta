# ACULTA Calendário (`aculta_calendar`)

Submódulo reutilizável de calendário (ADR-031, D-20). Fornece o motor de recorrência, a exportação `.ics`, a allowlist de incorporação, as visões de agenda e de mês, a importação revisável de iCalendar e a visão interativa. O conteúdo vem de provedores de itens; o módulo não conhece o dono dos dados.

- Roadmap e fases: [docs/ROADMAP.md](docs/ROADMAP.md)
- Plano de implantação: [docs/PLANO-IMPLANTACAO.md](docs/PLANO-IMPLANTACAO.md)
- Recorrência: [docs/RECORRENCIA.md](docs/RECORRENCIA.md)
- Visão interativa (DHTMLX Scheduler, GPL): [docs/DHTMLX.md](docs/DHTMLX.md) e [docs/ESTUDO-DHTMLX-SCHEDULER.md](docs/ESTUDO-DHTMLX-SCHEDULER.md)
- Documentação geral: [docs/README.md](docs/README.md)
- Histórico de alterações: [CHANGELOG.md](CHANGELOG.md)

## Validação visual (2026-10-29)

ADM8 autenticada e visão interativa foram revisadas no candidato RC
`e36140e63`, servido no checkout de teste com `drush cr`. Scripts reutilizáveis:
[tests/browser/aculta-calendar-admin](../../../../../../tests/browser/aculta-calendar-admin/README.md)
e [tests/browser/aculta-calendar-interactive](../../../../../../tests/browser/aculta-calendar-interactive/README.md).
Resultado: ADM8 anônimo 403/autenticado 200, tabela somente leitura sem overflow
em 1270/500 px; visão interativa com DHTMLX 7.2.15, 7 dias, contraste e fallback
móvel; smoke de eventos 129/129. O candidato inclui as correções da revisão de
código, a publicação explícita de cancelamentos importados e a exibição de
eventos de vários dias em cada dia coberto.
