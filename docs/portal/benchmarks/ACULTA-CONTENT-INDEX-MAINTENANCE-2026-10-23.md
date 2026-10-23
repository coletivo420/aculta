# Rebuild e auditoria — ACULTA Content Index (2026-10-23)

Medição opt-in da reconstrução por provider e da auditoria incremental
bidirecional sobre 10.000 Nodes sintéticos em MariaDB de teste. São três
repetições executadas sequencialmente; não representam capacidade de produção
nem definem SLO.

## Método e escopo

- Drupal Core `11.4.10`; PHP `8.4.26`; MariaDB `11.8.6-MariaDB-0+deb13u1`.
- Banco dedicado `localhost/aculta_simpletest`; cada Kernel usa tabelas com
  prefixo temporário.
- Nodes `sample_content` criados via Entity API e descobertos por
  `ContentIndexEntityEnumerator`; projeção pelo provider fixture
  `test_sample_content`; gravação pelo `ContentIndexWriter`.
- Rebuild em lotes keyset de 500, incluindo a chamada terminal que confirma o
  fim do provider.
- Auditoria completa de entradas, relações e fontes em lotes de 200.
- Criação das fontes, bootstrap do Kernel e troca do ponteiro ativo ficam fora
  dos tempos. Para isolar manutenção, o teste troca o ponteiro diretamente; o
  gate de ativação continua coberto pelos Kernel tests de rebuild.
- Não há traduções adicionais nem relações no fixture. Falhas, concorrência
  entre processos, outros providers, Domains e grants não foram medidos.

## Execução de 1M em andamento — evidência intermediária, não resultado

A execução MariaDB iniciada em 2026-10-23 às 04:17 BRT carregou o harness antes
dos commits `c401f504` (05:57 BRT), que desativa temporariamente o registry
durante a criação sintética para não criar jobs incrementais, e `9bc4f952`
(06:03 BRT), que mede a cauda da auditoria por fase. Portanto ela não mede essas
duas alterações e não serve como resultado final da versão corrigida.

Na observação às 06:50 BRT, o InnoDB estimava 995.136 Nodes, 994.044 linhas de
fila incremental e 81.056 entradas no catálogo; o checkpoint do rebuild estava
em `node:82.500`, revisão 98, estado `building`. A observação das 06:41
registrava 60.990 entradas e checkpoint `node:62.000`, portanto o rebuild
continua avançando. `TABLE_ROWS` é estimativa do `information_schema`, não
contagem exata. O volume de fila decorre do harness antigo e não deve ser
atribuído ao custo medido do rebuild/auditoria. A execução deve terminar antes
de repetir 1M com o harness atual; não comparar essa rodada com os resultados de
10k/100k nem registrar seu tempo como capacidade do Index.

## Resultados

| Run | Rebuild total (ms) | Projeções/s | Lote rebuild p50 / p95 (ms) | Auditoria total (ms) | Lotes auditados | Lote audit p50 / p95 (ms) | Pico memória (MiB) |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 62.554,699 | 160 | 3.195,845 / 4.548,410 | 4.357,660 | 102 | 38,064 / 79,938 | 148,5 |
| 2 | 68.404,638 | 146 | 3.564,147 / 4.847,477 | 4.649,783 | 102 | 38,017 / 85,958 | 148,5 |
| 3 | 65.968,250 | 152 | 3.370,800 / 4.697,937 | 4.573,084 | 102 | 37,588 / 83,551 | 148,5 |
| Mediana | 65.968,250 | 152 | 3.370,800 / 4.697,937 | 4.573,084 | 102 | 38,017 / 83,551 | 148,5 |

As três auditorias concluíram com zero fontes ausentes, zero entradas
ausentes e zero projeções divergentes. O custo do rebuild inclui 10.000
transações de projeção em até 500 identidades por batch. O audit percorreu 102
lotes, pois as fases usam checkpoints separados e incluem confirmações de fim.
O pico de memória é o máximo do processo PHPUnit, que também mantém a fixture
Drupal carregada; não deve ser atribuído somente ao worker.

## Comparação após `writeBatch()` (0.1.0-dev.69)

O baseline acima grava cada projeção numa transação. A versão `dev.69` faz o
rebuild gravar até 500 projeções em uma transação, avança a revisão do cache
uma vez por lote alterado e invalida páginas após o commit. Foram repetidos o
mesmo fixture e as mesmas três rodadas. A criação das fontes continuou fora do
tempo:

| Implementação | Rebuild mediano (ms) | Projeções/s | Lote p50 / p95 (ms) | Auditoria mediana (ms) | Lote audit p50 / p95 (ms) | Pico memória (MiB) |
|---|---:|---:|---:|---:|---:|---:|
| Writer por projeção | 65.968,250 | 152 | 3.370,800 / 4.697,937 | 4.573,084 | 38,017 / 83,551 | 148,5 |
| Writer em lote de até 500 | 8.207,047 | 1.218 | 406,693 / 448,049 | 4.485,881 | 36,646 / 82,709 | 146,5 |

No fixture de 10k, o rebuild em lote foi aproximadamente 8 vezes mais rápido.
Tempo e percentis da auditoria ficaram próximos; sua memória máxima caiu de
148,5 para 146,5 MiB. O resultado sustenta o batching neste cenário, não uma
garantia operacional. Não mede contenção entre rebuild e eventos incrementais,
concorrência entre processos nem backfill com traduções/relations. O CSV com as
seis rodadas está em
[comparação antes/depois](aculta-index-maintenance-batch-comparison-10k-2026-10-23.csv).

Os resultados brutos estão em
[CSV de três rodadas](aculta-index-maintenance-10k-2026-10-23.csv). Para
reproduzir, use `ACULTA_INDEX_MAINTENANCE_BENCHMARK=1` e
`ACULTA_INDEX_MAINTENANCE_BENCHMARK_OUTPUT` sob `sys_get_temp_dir()`. O teste
recusa qualquer `SIMPLETEST_DB` que não seja o banco local dedicado.

## Escala e cache do enumerador — diagnóstico de uma repetição

Os resultados abaixo são uma única execução por cenário, sem SLO. O JSON agora
separa o tempo/memória de criação sintética dos intervalos de rebuild e
auditoria. A gravação de fontes segue pela Entity API; não representa o custo
do índice.

| Escala / estratégia | Setup (s) | Rebuild (s) | Projeções/s | Pico rebuild (MiB) | Auditoria (s) | Batches | Audit p50 / p95 / max (ms) | Pico audit (MiB) | Pico total (MiB) | Divergências |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 10k, antes de limitar cache | não medido | 7,818 | 1.279 | não medido | 4,416 | 102 | 36,092 / 83,386 / não medido | não medido | 146,5 | 0 |
| 10k, cache acumulado até 5k IDs | 62,963 | 8,480 | 1.179 | não medido | 10,332 | 102 | 63,750 / 86,940 / não medido | não medido | 142,5 | 0 |
| 100k, antes de limitar cache | não medido | 126,108 | 793 | não medido | 135,318 | 1.002 | 154,450 / 332,304 / não medido | não medido | 532,5 | 0 |
| 10k, cache limitado; picos por fase | 64,917 | 8,289 | 1.206 | 122,5 | 9,914 | 102 | 60,273 / 78,832 / 4.110,203 | 142,5 | 142,5 | 0 |
| 100k, cache limitado; pico por fase não medido | 740,821 | 113,511 | 881 | não medido | 723,219 | 1.002 | 135,161 / 272,519 / não medido | não medido | 534,0 | 0 |

No cache limitado, o enumerador limpa IDs de fontes e alvos explícitos em lotes
de até 5.000 identidades e descarrega ao terminar cada provider. Em falha de
projeção, limpa todos os storages de entidades de conteúdo, pois as referências
carregadas podem não ser conhecidas. A repetição 10k mostrou um máximo de lote
de auditoria de 4,11 s apesar do p95 de 78,8 ms, justificando registrar a cauda.
Em 100k pós-cache, o total da auditoria foi 723,2 s apesar de p50/p95 menores
que no baseline; como o maior lote não foi capturado naquele run, não há base
para explicar a diferença nem atribuí-la ao cache. O pico global ficou parecido
com o baseline, mas aquela rodada não isolava memória por fase. Comparações são
diagnósticas, de uma repetição e sem SLO; 1M permanece pendente. Unit verifica o
reset por identidade; Kernel Index MariaDB passou 64/852.
