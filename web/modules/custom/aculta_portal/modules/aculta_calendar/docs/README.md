# ACULTA Calendário (`aculta_calendar`)

Submódulo reutilizável de agenda. Decisão: D-20. Arquitetura: ADR-031.

| Arquivo | Conteúdo |
| --- | --- |
| [PLANO-IMPLANTACAO.md](PLANO-IMPLANTACAO.md) | Escopo, API de itens, rotas, embed, testes e riscos |
| [RECORRENCIA.md](RECORRENCIA.md) | Motor de recorrência (regra, expansão e limites) |
| [ROADMAP.md](ROADMAP.md) | Roadmap único do submódulo |

Regra de uso: nenhum módulo gera `.ics` ou embed próprios. Quem tem datas registra um provedor de itens.
