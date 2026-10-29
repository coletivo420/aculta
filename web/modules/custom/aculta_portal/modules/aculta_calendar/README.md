# ACULTA Calendário (`aculta_calendar`)

Submódulo reutilizável de calendário (ADR-031, D-20). Fornece o motor de recorrência, a exportação `.ics`, a allowlist de incorporação, as visões de agenda e de mês, a importação revisável de iCalendar e a visão interativa. O conteúdo vem de provedores de itens; o módulo não conhece o dono dos dados.

- Roadmap e fases: [docs/ROADMAP.md](docs/ROADMAP.md)
- Plano de implantação: [docs/PLANO-IMPLANTACAO.md](docs/PLANO-IMPLANTACAO.md)
- Recorrência: [docs/RECORRENCIA.md](docs/RECORRENCIA.md)
- Visão interativa (DHTMLX Scheduler, GPL): [docs/DHTMLX.md](docs/DHTMLX.md) e [docs/ESTUDO-DHTMLX-SCHEDULER.md](docs/ESTUDO-DHTMLX-SCHEDULER.md)
- Documentação geral: [docs/README.md](docs/README.md)
- Histórico de alterações: [CHANGELOG.md](CHANGELOG.md)

## Validação visual (2026-10-29)

ADM8 autenticada e visão interativa foram revisadas no candidato atual `a4f878d55`
(calendar dev.4), servido no checkout de teste com `drush cr`. Scripts reutilizáveis:
[tests/browser/aculta-calendar-admin](../../../../../../tests/browser/aculta-calendar-admin/README.md)
e [tests/browser/aculta-calendar-interactive](../../../../../../tests/browser/aculta-calendar-interactive/README.md).
A revisão cobre o markup `data-portal-ui` do dev.4. O dev.5 corrige os achados
da revisão de código (sobreposição de eventos, UID numérico, janela de 366 dias,
isolamento de série inválida, `RECURRENCE-ID`, `UNTIL`, fonte órfã, fuso da
revisão, filtros da exportação e dia inteiro). A revalidação visual do candidato
corrigido está pendente porque o checkout de teste tem trabalho concorrente não
commitado. No dev.6, eventos de vários dias aparecem em cada dia coberto na
agenda e na grade do mês.
