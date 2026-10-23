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

## Comparativo de índice composto

Foi comparado, em três runs completos por escala, o plano atual com um índice
composto temporário `(generation_id, domain_id, published, entry_id)`. O índice
foi criado e removido apenas nas tabelas prefixadas do Kernel; nenhuma alteração
foi feita ao schema do módulo. A amostra de escrita mede 250 projeções por
variante através do `ContentIndexWriter` em uma geração descartável.

| Entradas | p50 frio: atual / alternativo (ms) | p50 aquecido: atual / alternativo (ms) | plano alternativo (runs) | espaço adicional mediano | criação mediana | writer atual / alternativo (linhas/s, mediana) |
|---:|---:|---:|---|---:|---:|---:|
| 10.000 | 4,561 / 4,512 | 2,931 / 2,967 | índice composto (3/3) | 1,59 MB | 52 ms | 503 / 548 |
| 100.000 | 4,610 / 4,961 | 2,999 / 2,861 | `PRIMARY` (3/3) | 6,86 MB | 501 ms | 381 / 364 |
| 1.000.000 | 4,240 / 7,980 | 2,724 / 6,864 | `PRIMARY` (3/3) | 59,38 MB | 5,83 s | 544 / 532 |

A alternativa não apresentou ganho consistente. Em 10k, quando o otimizador a
selecionou, as medianas de consulta praticamente empataram. Em 100k e 1M o
otimizador manteve `PRIMARY`; o índice adicional ocupou espaço sem ser usado.
A amostra curta do writer variou entre runs e não demonstra benefício. A decisão
provisória para 0.1 é manter o schema atual; reabrir apenas com telemetria de
consulta real que indique seletividade/custo diferente do fixture.

A latência alternativa em 1M variou mais que a atual apesar do mesmo plano
`PRIMARY`; como a coleta mede variantes sequencialmente, isso é ruído temporal e
não deve ser interpretado como regressão causada pelo índice. Bytes de storage
são estimativas alocadas do InnoDB após `ANALYZE TABLE`, não tamanho lógico exato.
Amostras brutas baseline/alternativa estão em
[CSV comparativo](aculta-index-alternative-2026-10-22.csv).

### Segunda composição de índice

Após a primeira comparação, foi medida outra composição temporária:
`(generation_id, entity_type, bundle, domain_id, published, entry_id)`. O índice
anterior `(generation_id, domain_id, published, entry_id)` foi repetido como
controle. Cada variante foi criada e removida nas tabelas temporárias do Kernel;
nenhuma delas altera o schema do módulo.

| Entradas | Composição | p50 frio / aquecido (ms) | plano (runs) | espaço adicional mediano | criação mediana | writer baseline / variante (linhas/s) |
|---:|---|---:|---|---:|---:|---:|
| 10.000 | domínio/status | 4,350 / 3,041 | índice (3/3) | 1,59 MB | 51 ms | 523 / 518 |
| 10.000 | tipo/bundle/domínio/status | 5,747 / 6,585 | índice (3/3) | 1,59 MB | 62 ms | 528 / 522 |
| 100.000 | domínio/status | 5,066 / 3,098 | `PRIMARY` (3/3) | 6,86 MB | 516 ms | 401 / 393 |
| 100.000 | tipo/bundle/domínio/status | 4,850 / 3,344 | `PRIMARY` (3/3) | 7,91 MB | 477 ms | 474 / 490 |
| 1.000.000 | domínio/status | 9,394 / 2,927 | `PRIMARY` (3/3) | 59,47 MB | 5,75 s | 353 / 493 |
| 1.000.000 | tipo/bundle/domínio/status | 5,883 / 4,477 | `PRIMARY` (3/3) | 72,12 MB | 6,89 s | 502 / 457 |

O otimizador selecionou cada índice alternativo somente em 10k. Nessa escala,
a composição com `entity_type` e `bundle` teve p50 pior, e a amostra do writer
ficou praticamente empatada. Em 100k e 1M, ambas as alternativas mantiveram
`PRIMARY`; a composição mais larga alocou cerca de 12,65 MB a mais em 1M que a
composição anterior. A variação entre runs do writer e das latências é alta e
não demonstra vantagem consistente. A decisão permanece manter o schema atual
e reavaliar com telemetria de consultas reais.

### Entity Access real

O harness também mediu páginas com 25 candidatos correspondentes a entidades
Media reais: 12 publicadas e visíveis pelo papel de teste, 13 não publicadas e
negadas. Foram feitas 30 consultas frias e 30 aquecidas por escala; medianas
cold p50 foram 9,805 / 8,785 / 9,456 ms e warm p50 6,799 / 7,105 / 7,349 ms
para 10k / 100k / 1M. Cada consulta revalidou acesso e carregou no máximo 25
entidades. Isso confirma o caminho de autorização no fixture Media, mas não
representa grants de Group/LMS, domínio, revisão, outros bundles nem volume
operacional; esses cenários seguem pendentes para homologação.

Uma execução de fumaça separada, em 10k, contou as consultas SQL do mesmo
caminho: 8 no cache miss e 6 no cache hit. A contagem é exploratória e de uma
única amostra; ela não foi adicionada como gate ou SLO.

Os resultados brutos das duas composições e das consultas com autorização estão
em [CSV de composições](aculta-index-alternative-compositions-2026-10-22.csv).

## Escopo ainda não medido

- Writer incremental e operações idempotentes via `ContentIndexWriter`.
- Backfill/rebuild e catch-up de deltas com entidades Drupal de origem.
- Reconciliação por providers reais, memória/throughput de worker e fila.
- Entity Access medido em throughput com providers/grants reais de domínio,
  Group/LMS e outros bundles. Um Kernel funcional do Fórum agora exercita
  Entity Access, Group grants e Domain canônico, mas não mede desempenho.
- Medição de consultas SQL por request e benchmarks de páginas intermediárias/keysets profundos.

Portanto, este resultado qualifica a gravação do fixture, candidate query/cache,
edge reads e um fixture limitado de Entity Access em Media. A homologação
IDX 0.1-R permanece pendente.

## Arquivos brutos

- [CSV de query/catálogo](benchmarks/aculta-index-query-2026-10-22.csv).
- [CSV de relações](benchmarks/aculta-index-relations-2026-10-22.csv).
