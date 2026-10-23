# Rebuild e auditoria — ACULTA Content Index (2026-10-23)

## Escala 1M — resultado válido após otimizar descoberta (dev.150)

O benchmark MariaDB concluiu 1.000.000 fontes sintéticas com zero fontes
ausentes, entradas ausentes ou projeções divergentes. O tempo de setup fica
fora dos tempos medidos de rebuild e auditoria:

| Fontes | Setup (s) | Rebuild (s) | Projeções/s | Auditoria (s) | Batches de auditoria | Pico auditoria (MiB) | ID query p50/p95 (ms) | Divergências |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 1.000.000 | 129,094 | 2.137,817 | 468 | 724,020 | 4.002 | 214,5 | 1,518 / 2,154 | 0 |

As 2.001 páginas do rebuild usaram descoberta `base_table`. A auditoria fez
2.000 batches para entradas, 1 para relações e 2.001 para fontes. As métricas
de indexador continuam incluindo carga/projeção via Entity API e gravação
transacional; a leitura de IDs é somente uma parte do custo. Uma execução
sintética, sem relações, sem SLO e sem inferência de carga de produção. O
processo PHPUnit levou 49m55s incluindo inicialização e setup.

## Keyset pela tabela base — diagnóstico 10k/100k (dev.149)

O EntityQuery do Drupal para Node revisionável juntava a tabela base à tabela
de dados, agrupava e ordenava as revisões. Em 1M, depois de 33 lotes de 500 em
541,174 s, o `EXPLAIN` estimou ~994k linhas inspecionadas por página e indicou
temporary/filesort; a tentativa foi interrompida sem JSON final. Isso não é um
tempo de benchmark válido. A limpeza removeu somente as 17 tabelas efêmeras do
prefixo PHPUnit `test80597948` no banco `aculta_simpletest`.

O dev.149 consulta a tabela base via Database API quando o mapeamento real do
storage confirma as colunas de ID e bundle. Entity API segue carregando e
projetando as entidades. A estratégia é confirmada por `base_table` em cada
lote; storage sem esse mapeamento segue no fallback EntityQuery.

| Escala | Setup (s) | Rebuild (s) | Projeções/s | Auditoria (s) | Pico auditoria (MiB) | ID query p50/p95 (ms) | Estratégias de lote | Divergências |
|---:|---:|---:|---:|---:|---:|---:|---|---:|
| 10k | 1,462 | 17,781 | 562 | 6,858 | 126,5 | 0,703 / 0,781 | base_table 21/21 | 0 |
| 100k | 11,646 | 181,043 | 552 | 68,983 | 137,5 | 1,103 / 1,557 | base_table 201/201 | 0 |

As amostras são únicas e não definem SLO. A consulta de ID mede apenas seleção
de candidatos; os tempos totais ainda incluem carga pela Entity API, projeção,
gravação, invalidadores/cache e demais estágios. Uma consulta SQL direta no
banco de teste leu 500 IDs por chave primária em 1,3 ms (e sem hint, com bundle,
em 5,9 ms); esse microteste explica o ganho potencial, mas não substitui o
benchmark de rebuild.

## Repetição 100k após limpeza de cache da auditoria (dev.147)

Uma execução MariaDB, com a mudança dev.146 que limpa caches de fontes e alvos
entre lotes da fase catálogo→fonte, usou o limite operacional do enumerador
5.000. Rebuild: 204,842 s (488 projeções/s); auditoria: 92,913 s em 402
batches; pico da auditoria: 137,5 MiB; zero fontes ausentes, entradas ausentes
ou projeções divergentes. A fase de entradas levou 29,658 s; a de relações
4 ms; a fase source→catalog, 63,178 s (p50 289 ms, p95 645 ms, máximo 885 ms).

Uma execução anterior em 100k, com limite 500 e código anterior à limpeza da
fase de entradas, levou 471,662 s na auditoria, 437,109 s em source→catalog,
379,890 s em cache reset do enumerador e pico 492 MiB. Em 10k, três rodadas
anteriores também mostraram picos de lote menores com 500, mas p95 típico um
pouco maior. A comparação 100k é diagnóstica e mistura duas mudanças; não
prova causalidade nem define SLO. O limite operacional permanece 5.000.

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

## Tentativas de 1M interrompidas — diagnóstico, sem resultado

A execução antiga iniciou em 2026-10-23 às 04:17 BRT e carregou o harness antes
dos commits `c401f504` (05:57 BRT), que desativa temporariamente o registry
durante a criação sintética para não criar jobs incrementais, e `9bc4f952`
(06:03 BRT), que mede a cauda da auditoria por fase. Após decisão do responsável,
foi interrompida em `node:220500`, com 220.500 entradas e 1.000.000 jobs ainda
queued. O resultado parcial é somente diagnóstico; não comparar nem reportar
como tempo do Index.

A repetição corrigida iniciou em 2026-10-23 no banco dedicado
`localhost/aculta_simpletest`, com o código que suprime a fila no setup e mede
setup, rebuild e auditoria separadamente. O teste exige zero jobs criados para
as fontes sintéticas e zero divergências ao concluir. Foi interrompida após
cerca de 58 minutos e não produziu resultado de 1M; ver seção abaixo.

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


## Tentativa de 1M interrompida — diagnóstico, sem resultado

A repetição corrigida foi iniciada às 08:56 BRT e interrompida por orientação do
responsável às 09:54:53 BRT, após cerca de 58 minutos. O processo terminou com
exit code 130 e não escreveu o JSON final. Uma leitura somente de metadados
InnoDB imediatamente antes da interrupção estimou 994.564 linhas em
`node_field_data` e 548.455 em `aculta_index_entry`. São estimativas de tabela,
não contagens exatas, timings, taxa ou divergências. Não usar esses números como
benchmark nem para declarar desempenho adequado. O diagnóstico bruto está em
`/tmp/aculta-index-maintenance-1000000-interrupted-2026-10-23.json`.

O responsável aprovou semear tabelas Node em lote, mantendo Entity API no
caminho de enumeração, carga, projeção, rebuild e auditoria. A implementação
cria uma entidade-semente pela Entity API, deriva colunas do `TableMapping` do
storage e insere registros sintéticos em transações limitadas a 1.000 entidades
por lote. O teste carrega a primeira e a última entidades semeadas novamente
pela Entity API antes de iniciar as medições. Essa técnica acelera a fixture,
não substitui nem altera a implementação medida do Index.

## Validação da semeadura em lote — uma execução, sem SLO

| Escala | Setup (ms) | Rebuild (ms) | Projeções/s | Rebuild p50 / p95 / max (ms) | Audit (ms) | Lotes de audit | Pico total (MiB) | Divergências |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 10.000 | 1.414 | 18.564 | 539 | 407 / 5.500 / 5.668 | 10.829 | 102 | 142,5 | 0 |

Picos por fase: rebuild 122,5 MiB e auditoria 142,5 MiB. Auditoria p50/p95/máximo:
68 / 93 / 4.352 ms. Setup mediu apenas criação das fontes; não entra no tempo
de rebuild/auditoria. Uma única repetição não permite inferir tendência nem
SLO. JSON bruto: `/tmp/aculta-index-maintenance-10000-bulk-2026-10-23.json`.


### Tentativa de validação em 100k interrompida

A repetição com semeadura SQL em lote foi iniciada após a validação 10k e
interrompida manualmente depois de aproximadamente 15 minutos, sem produzir o
JSON final. Nenhum tempo parcial de rebuild/auditoria foi medido ou será tratado
como resultado. O worker permanecia ativo em CPU; a demora ocorre depois do
setup, no fluxo real de manutenção. Como o teste anterior de 100k já reportou
auditoria de 723 s e a nova tentativa não concluiu em tempo comparável, fica
pendente analisar o custo da reconciliação bidirecional antes de outra execução
de grande escala. O método de setup não é o gargalo remanescente.


## Reconciliação com checagem rápida de elegibilidade — uma execução, sem SLO

Após o diagnóstico 100k, providers ganharam o contrato opcional
`ContentIndexIndexabilityProviderInterface`. Na passagem catálogo→fonte, a
auditoria agora verifica existência, bundle, tradução e elegibilidade sem
reconstruir a projeção completa; a passagem fonte→catálogo ainda projeta todas
as fontes e confere fingerprints/versões. Providers sem o contrato preservam a
checagem anterior.

| Cenário | Auditoria total (ms) | Batches (entries/relations/sources) | Pico audit (MiB) | Divergências |
|---|---:|---:|---:|---:|
| Antes do contrato, lote 500 | 10.103 | 20 / 1 / 21 | 146,5 | 0 |
| Contrato rápido, lote 500 | 9.299 | 20 / 1 / 21 | 152,5 | 0 |

A redução total observada foi ~8%; a fase entries caiu de 3,06 s para 1,99 s,
enquanto sources permaneceu dominante (~7,2 s). Pico subiu ~6 MiB. São duas
execuções individuais, não pareadas; não isolam causalidade e não definem SLO.
A auditoria de 100k precisa ser medida novamente antes de considerar 1M. JSON
bruto: `/tmp/aculta-index-maintenance-10000-indexability-fast-2026-10-23.json`.

## Auditoria após otimização em 100k — uma execução, sem SLO

Após a decisão de repetir 100k com `ContentIndexIndexabilityProviderInterface`,
o harness foi executado no banco MariaDB de teste em 2026-10-23. A fixture foi
semeada por SQL em lotes; enumeração, carga, projeção, rebuild e auditoria
usaram o caminho real do Index e a Entity API. Esta rodada também inclui o
campo opcional `owner_uid`. O setup levou 12,26 s e ficou fora dos tempos de
manutenção.

| Escala | Rebuild (s) | Projeções/s | Lotes rebuild | Auditoria (s) | Lotes audit | Pico rebuild / audit (MiB) | Divergências |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 100.000 | 209,542 | 477 | 201 | 724,666 | 402 | 136 / 558 | 0 |

O rebuild p50/p95/máximo por lote foi 565 / 5.507 / 6.176 ms. A auditoria
distribuiu-se em entries: 34,814 s (200 lotes), relations: 0,005 s (1 lote),
sources: 689,766 s (201 lotes). Na fase sources, p50 foi 225 ms, p95 28,31 s e
máximo 72,71 s por lote. A memória de pico da auditoria foi 558 MiB. O teste
concluiu em 15m50s, com zero fontes ausentes, zero entradas ausentes e zero
projeções obsoletas; PHPUnit passou (1 teste / 14 assertions).

Embora a checagem rápida tenha reduzido trabalho na fase entries, a fase
sources segue dominante e apresenta cauda longa nesta amostra. Uma execução não
permite afirmar causalidade nem extrapolar linearmente para 1M. O próximo passo
é analisar e otimizar essa passagem antes de outra rodada de grande escala. O
JSON bruto está em
`/tmp/aculta-index-maintenance-100000-indexability-owneruid-2026-10-23.json`;
checkpoints foram gravados no arquivo `.progress.json` correspondente.

## Instrumentação por etapa da auditoria — 10k, uma execução

No dev.143, o harness passou a capturar as métricas estruturadas do checker. A
rodada 10k levou 9,150 s na auditoria (20 lotes de entries, 1 de relations e 21
de sources) e concluiu com zero divergências. Os tempos detalhados confirmam
que a passagem fonte→catálogo é dominada por `scan` — Entity Query, carga,
projeção e limpeza de cache — e não pela comparação SQL do catálogo:

| Fase | Scan (s) | Reconciliação (s) | Checkpoint (s) | Fila (s) | Total instrumentado (s) |
|---|---:|---:|---:|---:|---:|
| Entries | 0,071 | 1,952 | 0,012 | 0,019 | 2,076 |
| Relations | 0,000 | 0,000 | 0,001 | 0,001 | 0,003 |
| Sources | 6,751 | 0,212 | 0,012 | 0,023 | 7,045 |

Na fase sources, o scan teve p50/p95/máximo de 106 / 537 / 4.333 ms por lote;
a reconciliação teve p50/p95/máximo de 10 / 12 / 12 ms. A amostra identifica
`ContentIndexEntityEnumerator::nextBatch()` e seus loads/projeções como o
próximo alvo de profiling fino. Não prova qual operação interna domina e não
deve ser extrapolada linearmente para 100k ou 1M. O JSON está em
`/tmp/aculta-index-maintenance-10000-stages-2026-10-23.json`.

## Comparação diagnóstica do limite de cache — 10k, três execuções por limite

O harness permite variar o limite de identidades enfileiradas antes de chamar
`resetCache()`. O padrão atual de produção é 5.000; `500` foi usado somente no
enumerador criado pelo benchmark. As rodadas foram alternadas entre limites
para reduzir viés de ordem. Todas concluíram sem divergências.

| Limite | Auditoria mediana (s) | Fase sources mediana (s) | Lote sources p50 / p95 / max (ms) | Reset agregado mediano (s) | Reset p50 / p95 / max (ms) | Pico audit (MiB) |
|---:|---:|---:|---:|---:|---:|---:|
| 5.000 | 9,06 | 6,96 | 119 / 549 / 4.351 | 4,68 | 0 / 448 / 4.237 | 152,5 |
| 500 | 8,63 | 6,55 | 289 / 706 / 711 | 4,54 | 180 / 570 / 598 | 150,5 |

Com limite 500, a mediana total caiu 4,7% e o máximo por lote caiu 83,7%, mas
o p95 de lote subiu 28,7%: os resets custam moderadamente em quase todos os
lotes, em vez de se concentrarem nos poucos lotes que descarregam 5.000 IDs.
O tempo agregado de reset ficou próximo (diferença mediana de 3%), portanto o
principal ganho observado é reduzir a cauda, não reduzir substancialmente o
trabalho total. São três amostras por cenário, em fixture 10k, sem SLO; não
justificam por si só mudar o limite operacional, especialmente antes da
validação em 100k. JSONs ficam em `/tmp/aculta-index-maintenance-10000-*2026-10-23.json`.
