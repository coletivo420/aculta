# Content Index — acesso a Fórum após recomputar Entity Access (2026-10-26)

## Objetivo

Medir se a limpeza do cache interno de Entity Access por página, adicionada para
garantir revalidação após revogação, altera o custo do filtro de candidatos do
Fórum. O benchmark existente foi repetido sem mudar o harness.

## Método

- Fixture sintética de 100 tópicos `aculta_forum_topic` no Domain Fórum,
  associados ao mesmo Group restrito.
- Contas: membro real do Group e visitante (não membro).
- 5 execuções de aquecimento e 30 medições por conta e tamanho de página.
- Tamanhos: 1, 12, 25, 50 e 100 candidatos.
- `ContentIndexAccessFilter` com providers reais, Group e Domain, sem consulta
  SQL de catálogo.
- Ambiente: Drupal 11.4.10, PHP 8.4.27, MariaDB 11.8.6, banco dedicado
  `aculta_simpletest`.
- Execução opt-in (`ACULTA_INDEX_FORUM_ACCESS_BENCHMARK=1`), uma única rodada.

## Resultados

### Run 1

| Candidatos | Membro autorizados | Membro p50/p95 (ms) | Visitante autorizados | Visitante p50/p95 (ms) |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 1 | 3,958 / 5,216 | 0 | 2,222 / 2,869 |
| 12 | 12 | 55,035 / 78,601 | 0 | 25,792 / 34,457 |
| 25 | 25 | 90,626 / 118,283 | 0 | 43,162 / 66,945 |
| 50 | 50 | 193,460 / 291,482 | 0 | 91,892 / 263,097 |
| 100 | 100 | 371,114 / 406,681 | 0 | 178,307 / 220,385 |

### Run 2

| Candidatos | Membro autorizados | Membro p50/p95 (ms) | Visitante autorizados | Visitante p50/p95 (ms) |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 1 | 5,277 / 10,656 | 0 | 2,603 / 3,852 |
| 12 | 12 | 63,163 / 101,908 | 0 | 31,281 / 69,683 |
| 25 | 25 | 129,418 / 187,772 | 0 | 63,238 / 85,887 |
| 50 | 50 | 261,703 / 308,904 | 0 | 121,635 / 148,419 |
| 100 | 100 | 493,761 / 549,640 | 0 | 242,957 / 289,547 |

CSV com as duas amostras:
[`aculta-index-forum-access-2026-10-26.csv`](aculta-index-forum-access-2026-10-26.csv).

## Comparação

Baseline documentada em 2026-10-23 (após o agrupamento por purpose), tamanho 100:

| Conta | Baseline p50/p95 (ms) | Run 1 p50/p95 (ms) | Run 2 p50/p95 (ms) |
| --- | ---: | ---: | ---: |
| Membro | 386,011 / 566,662 | 371,114 / 406,681 | 493,761 / 549,640 |
| Visitante | 182,232 / 293,314 | 178,307 / 220,385 | 242,957 / 289,547 |

A run 2 tem p50 maior que a baseline, mas p95 menor; a run 1 é menor nas duas
métricas. As duas amostras ficam dentro de uma faixa plausível de variação e não
isolam a carga do banco nem a atividade concorrente no ambiente de teste. Não há
evidência suficiente para declarar melhoria causal nem regressão além do ruído.
O custo do reset adicional existe, é proporcional ao número de tipos na página e
precisa de telemetria em Alpha para ser caracterizado.

## Limitações

- Amostras sintéticas, duas execuções por configuração; não definem SLO.
- Uma tentativa adicional durante atividade concorrente no banco de teste falhou
  no setup e foi repetida com sucesso; apenas as duas execuções válidas estão
  registradas.
- Não cobre consulta SQL de candidatos, rebuild, auditoria nem catálogo grande.
- O custo do reset é proporcional ao número de tipos de entidade na página (até
  três neste cenário), não ao número de candidatos.
- Produção pode ter mais tipos, grants e contexts; a medição não substitui
  telemetria em Alpha.
