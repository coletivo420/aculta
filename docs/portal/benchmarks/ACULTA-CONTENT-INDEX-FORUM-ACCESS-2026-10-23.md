# Entity Access do Fórum com Group grants — ACULTA Content Index (2026-10-23)

Esta medição opt-in avalia o `ContentIndexAccessFilter` com os hooks reais de
grants do Fórum, membership do Group e mudança para o Domain canônico `forum`.
Não é SLO e não inclui consulta SQL de candidatos do catálogo.

## Ambiente e método

- Drupal Core `11.4.10`, PHP `8.4.26`, MariaDB `11.8.6-MariaDB-0+deb13u1`.
- Banco dedicado `localhost/aculta_simpletest`; cada execução Kernel usa
  tabelas temporárias prefixadas.
- 100 tópicos sintéticos associados ao mesmo Group restrito, no Domain Fórum.
  O associado ativo é membro; um segundo usuário não pertence ao grupo.
- O filtro começa no Domain principal, executa a política canônica `forum` e
  restaura o Domain ao final.
- Cinco aquecimentos e 30 medições por usuário e tamanho de página, em três
  processos separados. Cada execução passou com 13.196 assertions e 22
  depreciações de Core/contrib.
- O p50/p95 de cada execução usa nearest-rank. A tabela mostra a mediana entre
  as três execuções. As 900 latências individuais estão no
  [CSV bruto](aculta-index-forum-access-2026-10-23.csv).

## Resultados

| Candidatos | Acesso do membro | p50 mediano (ms) | p95 mediano (ms) | Acesso do visitante | p50 mediano (ms) | p95 mediano (ms) |
|---:|---:|---:|---:|---:|---:|---:|
| 1 | 1 permitido | 0,137 | 0,153 | 0 permitido | 0,757 | 1,326 |
| 12 | 12 permitidos | 1,528 | 2,099 | 0 permitido | 9,845 | 11,576 |
| 25 | 25 permitidos | 3,370 | 3,996 | 0 permitido | 21,221 | 23,970 |
| 50 | 50 permitidos | 6,612 | 7,507 | 0 permitido | 42,406 | 52,144 |
| 100 | 100 permitidos | 13,596 | 14,417 | 0 permitido | 85,065 | 100,798 |

O cenário negado foi mais lento que o autorizado nesta fixture; aos 100
candidatos, p50 mediano foi 85,065 ms contra 13,596 ms. A amostra usa um único
Group compartilhado pelos tópicos e não representa a diversidade de grants,
grupos, papéis ou cache de uma instalação real. O tempo inclui loads, Entity
Access, hooks de grants, limpeza dos caches locais dos handlers e troca de
Domain. Não justifica alterar o limite fixo de 100 candidatos nem define SLO;
telemetria real continua necessária.

## Reprodução

Usar somente a configuração local do banco dedicado, sem imprimir a URL:

```sh
cd web/core
set -a && . ../../secrets/simpletest-db.env && set +a
export BROWSERTEST_OUTPUT_DIRECTORY=/tmp/aculta-browser-out
mkdir -p "$BROWSERTEST_OUTPUT_DIRECTORY"
ACULTA_INDEX_FORUM_ACCESS_BENCHMARK=1 \
ACULTA_INDEX_FORUM_ACCESS_BENCHMARK_OUTPUT=/tmp/aculta-index-forum-access.json \
  ../../vendor/bin/phpunit -c phpunit.xml.dist \
  ../modules/custom/aculta_portal/modules/aculta_index/tests/src/Performance/ContentIndexForumAccessBenchmarkTest.php \
  --filter testForumGrantsAndDomainAccessAtProgressivePageSizes
```

O harness recusa outro host ou banco e escreve JSON bruto, incluindo as 30
amostras de cada cenário, apenas em `sys_get_temp_dir()`.
