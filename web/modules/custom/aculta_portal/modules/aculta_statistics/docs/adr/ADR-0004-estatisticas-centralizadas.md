# ADR-0004 — Estatísticas centralizadas no aculta_statistics

Status: aceita pelo responsável (2026-10-21).

## Contexto

Contadores de uso já existem em módulos produtores: cliques de links
(`aculta_links`, tabela `aculta_links_stat_daily`), contagens de fórum
(`ForumAdminOverviewController`), resumo de envios (`MailSummary`,
`MailDashboard`) e contagens de turma do LMS. Cada um guarda o próprio agregado,
com regra de retenção e painel próprios. Isso duplica fonte, divide a retenção
e impede uma visão única.

## Decisão

- `aculta_statistics` é a **única fonte e central** de estatísticas agregadas do
  ACULTA. Métricas entram pelo catálogo (`MetricCatalog`) e são gravadas pelo
  `StatisticsRecorder` ou por um provedor/conciliador que lê a fonte de origem.
- Módulos produtores **mantêm os dados de origem** (pedidos, matrículas,
  registros de envio, cliques de origem). Deixam de manter **agregados
  estatísticos próprios**.
- Painéis locais de operação (ex.: painel da turma do LMS, log de envios) podem
  continuar, mas leem o dado da origem ou do `aculta_statistics`; não criam
  contador paralelo.
- Remoção dos agregados antigos só depois de migração: copiar o histórico ainda
  dentro da janela de retenção para o `aculta_statistics`, conferir os totais e
  só então remover a tabela, o código de gravação e o painel antigo. Nenhuma
  tabela é apagada sem autorização do responsável e sem backup verificado.

## Mapa de migração

| Origem atual | Destino | Situação |
|---|---|---|
| `aculta_links` (`aculta_links_stat_daily`, `LinkAnalytics`) | `links.link.clicks` no `aculta_statistics` | Migrar histórico, depois remover tabela e fila própria. Convergência já prevista para a 0.7.0; passa a ser pré-requisito. |
| `aculta_forum` (`ForumAdminOverviewController`, contagens) | `forum.*` no `aculta_statistics` | Séries temporais viram métrica; contagem atual vira leitura da fonte. |
| `aculta_mailer` (`MailSummary`, `MailDashboard`, linhas do resumo) | `email.*` (0.5.0) | Resumo semanal passa a ler o `aculta_statistics`; o log de envios continua como registro. |
| `aculta_lms` (painel da turma) | `lms.*` (0.4.0) | Contagens agregadas vão ao `aculta_statistics`; painel individual e privado não entra. |
| `aculta_statistics` (`aculta_statistics_daily`) | — | Já é o destino. |

## Consequências

- Retenção, permissão e auditoria de estatísticas ficam em um lugar só (ADR-0003).
- Gate a criar: nenhum módulo grava agregado estatístico fora do `aculta_statistics`.
- O `aculta_links` deixa de ser dono do agregado de cliques; o registro de
  origem e a fila continuam conforme a LGPD do módulo.
