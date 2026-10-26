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

| Candidatos | Membro autorizados | Membro p50/p95 (ms) | Visitante autorizados | Visitante p50/p95 (ms) |
| ---: | ---: | ---: | ---: | ---: |
| 1 | 1 | 3,958 / 5,216 | 0 | 2,222 / 2,869 |
| 12 | 12 | 55,035 / 78,601 | 0 | 25,792 / 34,457 |
| 25 | 25 | 90,626 / 118,283 | 0 | 43,162 / 66,945 |
| 50 | 50 | 193,460 / 291,482 | 0 | 91,892 / 263,097 |
| 100 | 100 | 371,114 / 406,681 | 0 | 178,307 / 220,385 |

CSV: [`aculta-index-forum-access-2026-10-26.csv`](aculta-index-forum-access-2026-10-26.csv).

## Comparação

Baseline documentada em 2026-10-23 (após o agrupamento por purpose), tamanho 100:

| Conta | Antes p50/p95 (ms) | Agora p50/p95 (ms) |
| --- | ---: | ---: |
| Membro | 386,011 / 566,662 | 371,114 / 406,681 |
| Visitante | 182,232 / 293,314 | 178,307 / 220,385 |

A diferença está dentro da variação entre execuções; não há evidência de
regressão pela limpeza adicional. A queda de p95 nesta amostra não deve ser
atribuída causalmente à mudança: o ambiente e a carga não foram isolados.

## Limitações

- Amostra sintética, execução única por configuração; não define SLO.
- Não cobre consulta SQL de candidatos, rebuild, auditoria nem catálogo grande.
- O custo do reset é proporcional ao número de tipos de entidade na página (até
  três neste cenário), não ao número de candidatos.
- Produção pode ter mais tipos, grants e contexts; a medição não substitui
  telemetria em Alpha.
