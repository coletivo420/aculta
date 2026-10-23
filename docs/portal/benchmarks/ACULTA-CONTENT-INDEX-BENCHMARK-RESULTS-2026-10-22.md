# Resultados iniciais — ACULTA Content Index (2026-10-22)

Executados três runs por escala no MariaDB de teste isolado. Os arquivos CSV anexos guardam as nove linhas de consulta e as 27 combinações de leitura de relações. São medições locais de catálogo sintético; **não** são SLO, benchmark de Entity Access real nem aprovação de desempenho.

## Ambiente

- Drupal Core `11.4.10`; PHP `8.4.26`; MariaDB `11.8.6-MariaDB-0+deb13u1`.
- Linux `6.12.111+deb13-amd64`, x86_64, 4 CPUs reportadas; Intel Celeron N5095 @ 2.00 GHz; memória total reportada: 7.6 GiB.
- Banco `localhost/aculta_simpletest`; as entidades fonte sintéticas não existem, logo Entity Access nega após o load limitado de até 100 IDs.
- Cada página mede 30 chamadas frias (cache de candidatos invalidado antes) e 30 aquecidas; cada fan-out de relações usa 30 fontes amostradas e 10.000 fontes no fixture.

## Consulta, gravação do fixture e armazenamento

| Entradas | Inserção (mediana linhas/s) | Página fria p50 / p95 (ms) | Página aquecida p50 / p95 (ms) | PHP peak (MiB) | Entradas alocadas (MB, faixa) | EXPLAIN key / linhas estimadas |
|---:|---:|---:|---:|---:|---:|---|
| 10,000 | 13,716 | 5.909 / 9.953 | 5.835 / 8.061 | 92.5 | 10.6–10.6 | PRIMARY / 479–492 |
| 100,000 | 16,539 | 4.671 / 7.045 | 3.005 / 3.941 | 92.5 | 66.4–66.4 | PRIMARY / 280–280 |
| 1,000,000 | 16,167 | 4.282 / 6.922 | 2.882 / 7.576 | 92.5 | 600.5–638.3 | PRIMARY / 284–308 |

A métrica de inserção é a carga sintética direta em batches de 500 pelo Database API, não o writer transacional. `DATA_LENGTH + INDEX_LENGTH` do `information_schema` é uma estimativa alocada do InnoDB; pode variar por run/reutilização de páginas. O `EXPLAIN` usou `PRIMARY` com acesso `index` e `Using where` nas três escalas; as linhas estimadas variaram conforme estatísticas do MariaDB.

## Relações de saída

| Entradas | Fan-out | Edges no fixture | p50 mediana (ms) | p95 mediana (ms) | Bytes alocados da tabela de relações (faixa por run) |
|---:|---:|---:|---:|---:|---:|
| 10,000 | 1 | 10,000 | 0.327 | 0.515 | 40.3–103.6 MB |
| 10,000 | 5 | 50,000 | 0.339 | 0.573 | 40.3–103.6 MB |
| 10,000 | 20 | 200,000 | 0.469 | 1.968 | 40.3–103.6 MB |
| 100,000 | 1 | 10,000 | 0.363 | 0.656 | 35.9–97.0 MB |
| 100,000 | 5 | 50,000 | 0.302 | 0.486 | 35.9–97.0 MB |
| 100,000 | 20 | 200,000 | 0.574 | 1.365 | 35.9–97.0 MB |
| 1,000,000 | 1 | 10,000 | 0.308 | 0.410 | 99.3–117.3 MB |
| 1,000,000 | 5 | 50,000 | 0.372 | 0.663 | 99.3–117.3 MB |
| 1,000,000 | 20 | 200,000 | 0.458 | 1.385 | 99.3–117.3 MB |

As leituras de relação são Database API contra a tabela de edges, sem resolver/autorizar entidades alvo. O fan-out 20 grava no máximo 200.000 edges. Variação observada: percentis de página e tamanho InnoDB não são monotônicos entre runs; não há evidência para definir limite de latência ou aprovar volume de produção.

## Escopo ainda não medido

- Writer incremental e operações idempotentes via `ContentIndexWriter`.
- Backfill/rebuild e catch-up de deltas com entidades Drupal de origem.
- Reconciliação por providers reais, memória/throughput de worker e fila.
- Entity Access com mistura realista de entidades permitidas/negadas e grants de módulos.
- Medição de consultas SQL por request e benchmarks de páginas intermediárias/keysets profundos.

Portanto, este resultado qualifica apenas a gravação do fixture, candidate query/cache com IDs fonte ausentes e edge reads. A homologação IDX 0.1-R permanece pendente.

## Arquivos brutos

- [CSV de query/catálogo](benchmarks/aculta-index-query-2026-10-22.csv).
- [CSV de relações](benchmarks/aculta-index-relations-2026-10-22.csv).
