# Plano de benchmark — ACULTA Content Index

Estado: consulta de candidatos, leitura de relações, duas composições experimentais de índice e fixture limitado de Entity Access em Media executados; benchmark amplo de escrita, rebuild, reconciliação e autorização nos providers/grants reais continuam pendentes.

## Matriz fixada

Usar catálogos sintéticos de **10.000, 100.000 e 1.000.000 entradas**. Executar
somente no MariaDB de teste isolado (`aculta_simpletest`), nunca no Runtime.
Entidades, títulos, UUIDs e relações de benchmark serão sintéticos e sem dados
pessoais. A geração de fixtures deve usar um identificador exclusivo e remover
apenas as próprias linhas ao terminar.

## Operações a medir

| Operação | Cenário | Evidência |
|---|---|---|
| Consulta keyset | Primeira página e páginas intermediárias, filtros por provider/type/bundle/idioma/domínio | p50/p95, plano `EXPLAIN`, linhas estimadas e tempo SQL |
| Cache de candidatos | Aquecimento, hit, revisão alterada e repovoamento | latência e consultas SQL por chamada |
| Relações de saída | Fontes com 1, 5 e 20 arestas por entidade | p50/p95 e linhas examinadas |
| Escrita incremental | Inserção, atualização idempotente e exclusão de projeção | entidades/segundo e tempo transacional |
| Reconciliação | Auditoria keyset com lotes, checkpoint e projeções divergentes | entidades/segundo, tempo por lote e memória máxima |
| Reconstrução | Backfill de uma geração nova e catch-up de deltas | entidades/segundo, duração total, memória e tamanho das tabelas |

Para relações, cada densidade (`1`, `5` e `20` arestas por fonte) usa a mesma
amostra fixa de **10.000 fontes**. O teto é 200.000 edges por execução dessa
operação; a quantidade de entradas do catálogo não multiplica esse volume.

Executar pelo menos três repetições por tamanho e operação, separando primeira
execução (cache frio) de execuções aquecidas. Registrar versões de Drupal/PHP/
MariaDB, configuração do banco, hardware, tamanho dos lotes e dispersão dos
dados. Guardar resultados brutos CSV e um resumo legível junto da revisão de
homologação; não inferir throughput de uma única amostra.

## Acesso e segurança

Entity Access continuará sendo testado através das entidades originais e não
pode ser simulado como autorização pelo SQL do catálogo. Medir autorização em
fixtures realistas separadas, com públicos permitidos e negados, e registrar
consultas por página. Os tamanhos de um milhão medem principalmente catálogo,
relações, fila e manutenção; não exigem criar um milhão de Nodes no ambiente de
teste.

O harness opt-in em
`web/modules/custom/aculta_portal/modules/aculta_index/tests/src/Performance/`
mede atualmente a gravação sintética da tabela de entradas, consulta keyset
pelo serviço público (SQL de candidatos, cache e loads limitados de fontes
ausentes) e leitura de arestas com os índices do schema. Também consulta 25
entidades Media reais (12 autorizadas e 13 negadas no fixture), revalidando
Entity Access em 30 leituras frias e aquecidas. Registra p50/p95, plano
`EXPLAIN`, linhas estimadas e bytes alocados. Compara o plano atual com duas
composições de índice criadas e removidas dentro do schema temporário, medindo
espaço/build e 250 escritas pelo writer em cada variante. O resultado de três
repetições está em [resultados](benchmarks/ACULTA-CONTENT-INDEX-BENCHMARK-RESULTS-2026-10-22.md):
nenhuma composição demonstrou ganho consistente, e o schema de produção
permanece sem índices adicionais. O fixture Media não substitui medições com
grants de domínio/Group/LMS, nem um benchmark amplo de escrita, rebuild ou
reconciliação; esses workloads continuam pendentes antes da homologação final.
Uma fumaça de 10k contou 8/6 consultas SQL em cache miss/hit, após aquecer
metadados do Drupal. Essa amostra única serve para observar o caminho e não
define meta. Decisão do responsável: registrar métricas, mas definir limites de
latência e throughput só após telemetria de uso real.

Execução controlada do harness, após carregar o arquivo local de configuração
do banco de teste sem imprimir a URL:

```sh
cd web/core
set -a && . ../../secrets/simpletest-db.env && set +a
export BROWSERTEST_OUTPUT_DIRECTORY=/tmp/aculta-browser-out
mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY"
ACULTA_INDEX_BENCHMARK=1 \
  ../../vendor/bin/phpunit -c phpunit.xml.dist \
  ../modules/custom/aculta_portal/modules/aculta_index/tests/src/Performance
```

O padrão executa 10k/100k/1M. `ACULTA_INDEX_BENCHMARK_SIZES=10000` permite uma
execução curta de fumaça. Os resultados JSONL são gravados somente em `sys_get_temp_dir()`;
`ACULTA_INDEX_BENCHMARK_OUTPUT` pode escolher outro nome dentro desse diretório.
O teste verifica antes do boot que `SIMPLETEST_DB` aponta para
`localhost/aculta_simpletest`. O cenário de consulta distribui as linhas entre
quatro domínios e marca 90% como publicadas; o filtro `main` + publicado mede
uma seleção aproximada de 22,5%, além do limite keyset de 100 candidatos.

## Resultados de 2026-10-22

A primeira rodada foi repetida três vezes em cada escala. O resumo, CSVs e
limitações estão em [Resultados iniciais](benchmarks/ACULTA-CONTENT-INDEX-BENCHMARK-RESULTS-2026-10-22.md).
O plano usou a chave `PRIMARY` nas três escalas; a inserção sintética ficou em
13,7k–16,5k linhas/s, e a página aquecida teve p50 mediano de 2,9–5,8 ms.
Esses números não são SLO: a consulta carregou IDs fonte ausentes e, portanto,
não mede throughput de Entity Access nem uso com grants reais. A tabela de
resultados discrimina cada run e a amostra fixa de relações de 10.000 fontes.

## Critérios de saída

- Apresentar resultados por tamanho, operação e repetição, incluindo memória e
  tamanho do banco.
- Inspecionar planos SQL das consultas mais lentas e verificar se os índices
  compostos esperados são utilizados.
- Corrigir regressões demonstradas e repetir a matriz afetada.
- Não declarar desempenho adequado sem medições executadas. Limites de latência
  e throughput serão definidos após observar os resultados e o perfil de uso
  dos consumidores.

