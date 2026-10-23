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

Os resultados brutos estão em
[CSV de três rodadas](aculta-index-maintenance-10k-2026-10-23.csv). Para
reproduzir, use `ACULTA_INDEX_MAINTENANCE_BENCHMARK=1` e
`ACULTA_INDEX_MAINTENANCE_BENCHMARK_OUTPUT` sob `sys_get_temp_dir()`. O teste
recusa qualquer `SIMPLETEST_DB` que não seja o banco local dedicado.
