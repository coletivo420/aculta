# Content Index — catálogo representativo misto (2026-10-26)

## Objetivo

Exercitar correção e uma amostra média de diversidade que os benchmarks
sintéticos de escala não cobriam: dois tipos de conteúdo, dois idiomas, dois
domínios, estados editoriais, relações explícitas, atualização, remoção, cursor
de conteúdo, cursor de relação e resíduo de provider desregistrado. A medição é
secundária; as asserções de correção são o gate principal.

## Fixture

- Providers sintéticos de teste: `representative_content` e
  `representative_target`, ambos sobre Node com Entity API real.
- `content_translation` habilitado; idiomas `en` (padrão) e `pt-br`.
- 24 Nodes `representative_content` (16 publicados, 8 rascunhos) e 4 traduções
  `pt-br` em conteúdos publicados.
- 12 Nodes `representative_target` (8 publicados, 4 rascunhos) e 2 traduções
  `pt-br`.
- Domínios de catálogo `main` e `wiki` alternados.
- 30 relações `representative_related` no build (25 em inglês, incluindo uma
  origem com dois alvos, e 5 nas traduções).
- Após o build: atualização de título, exclusão de um conteúdo publicado e
  inserção de uma linha residual de `retired_owner`.
- Conta leitora autenticada com `access content`; rascunhos e alvo de relação
  despublicado são negados por Entity Access.

## Método

- Kernel Drupal em MariaDB dedicado (`aculta_simpletest`), Drupal 11.4.10,
  PHP 8.4.27.
- Rebuild completo pelas tuplas de provider, drenagem da fila, validação e
  ativação.
- Auditoria incremental até `complete`, exigindo `valid=true` e zero
  divergências.
- Consulta de conteúdo com limite 100 e cursor de 5 em 5; consulta de relação
  a partir de uma origem publicada.
- Uma consulta fria após invalidar o cache de candidatos; 5 aquecimentos e 30
  medições aquecidas.
- Métricas opt-in (`ACULTA_INDEX_REPRESENTATIVE_BENCHMARK=1`) gravadas fora do
  repositório. Duas execuções foram preservadas:
  [`run 1`](aculta-index-representative-2026-10-26.json) e
  [`run 2`](aculta-index-representative-2026-10-26-run2.json).

## Resultados

| Métrica | Run 1 | Run 2 |
| --- | ---: | ---: |
| Entradas construídas (`representative_content`) | 28 | 28 |
| Entradas construídas (`representative_target`) | 14 | 14 |
| Relações construídas | 30 | 30 |
| Entradas após atualização/exclusão (content) | 27 | 27 |
| Relações após exclusão | 29 | 29 |
| Página publicada após resíduo | 19 | 19 |
| Rebuild | 402,275 ms | 428,791 ms |
| Auditoria | 46,191 ms | 46,181 ms |
| Consulta fria | 5,652 ms | 6,781 ms |
| Consulta aquecida p50 | 4,672 ms | 5,029 ms |
| Consulta aquecida p95 | 5,768 ms | 6,173 ms |
| Pico de memória | 82,5 MiB | 82,5 MiB |

As duas execuções são amostras diagnósticas; a variação entre elas não deve ser
atribuída a uma mudança específica, e nenhuma das duas estabelece SLO.

## Limitações

- Amostra pequena/média e sintética; não mede escala nem produção.
- Os providers são de teste; a homologação de owners reais permanece nos
  Kernels de Wiki, Fórum, LMS, Gallery, Media, Magazine e Activity.
- Group, Media e Domain real não entram nesta fixture específica; são cobertos
  pelos testes focais dos owners.
- Uma execução única, sem SLO, sem EXPLAIN e sem contagem de consultas SQL.
- O tempo inclui a criação do catálogo derivado e a drenagem da fila; não isola
  fases como o harness de manutenção.
