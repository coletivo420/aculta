# Plano de benchmark — ACULTA Content Index

Estado: planejado; nenhuma medição de volume foi executada por este documento.

## Matriz fixada

Usar catálogos sintéticos de **10.000, 100.000 e 1.000.000 entradas**. Executar
somente no MariaDB de teste isolado (`aculta_simpletest`), nunca no Runtime.
Entidades, títulos, UUIDs e relações de benchmark serão sintéticos e sem dados
pessoais. A geração de fixtures deve usar um identificador exclusivo e remover
apenas as próprias linhas ao terminar.

## Operações a medir

| Operação | Cenário | Evidência |
|---|---|---|
| Consulta keyset | Primeira página e páginas intermediárias, filtros por provider/type/bundle/idioma/domínio | p50/p95, linhas examinadas e tempo SQL |
| Cache de candidatos | Aquecimento, hit, revisão alterada e repovoamento | latência e consultas SQL por chamada |
| Relações de saída | Fontes com 1, 5 e 20 arestas por entidade | p50/p95 e linhas examinadas |
| Escrita incremental | Inserção, atualização idempotente e exclusão de projeção | entidades/segundo e tempo transacional |
| Reconciliação | Auditoria keyset com lotes, checkpoint e projeções divergentes | entidades/segundo, tempo por lote e memória máxima |
| Reconstrução | Backfill de uma geração nova e catch-up de deltas | entidades/segundo, duração total, memória e tamanho das tabelas |

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

## Critérios de saída

- Apresentar resultados por tamanho, operação e repetição, incluindo memória e
  tamanho do banco.
- Inspecionar planos SQL das consultas mais lentas e verificar se os índices
  compostos esperados são utilizados.
- Corrigir regressões demonstradas e repetir a matriz afetada.
- Não declarar desempenho adequado sem medições executadas. Limites de latência
  e throughput serão definidos após observar os resultados e o perfil de uso
  dos consumidores.

