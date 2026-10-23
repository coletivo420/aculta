# Entity Access do Fórum com Group grants — ACULTA Content Index (2026-10-23)

Esta medição opt-in avalia o `ContentIndexAccessFilter` com os hooks reais de
grants do Fórum registrados no `ModuleHandler` do Kernel, membership do Group
e mudança para o Domain canônico `forum`. O módulo owner completo ainda não é
ativado neste harness; um hook de teste encaminha aos métodos de produção.
A medição não é SLO e não inclui a consulta SQL de candidatos do catálogo.

## Ambiente e método

- Drupal Core `11.4.10`, PHP `8.4.26`, MariaDB `11.8.6-MariaDB-0+deb13u1`.
- Banco dedicado `localhost/aculta_simpletest`; cada execução Kernel usa
  tabelas temporárias prefixadas.
- 100 tópicos sintéticos associados ao mesmo Group restrito, no Domain Fórum.
  Uma conta comum, diferente do UID 1, tem membership ativa e permissão Group;
  outro usuário não pertence ao grupo.
- O hook de teste encaminha `entity_access`, `node_grants` e
  `node_access_records` às classes reais do Fórum. A tabela `node_access` e o
  `ModuleHandler` são consultados no teste; a autorização do filtro é executada
  em ambos os sentidos, incluindo a troca e restauração do Domain.
- Cinco aquecimentos e 30 medições por usuário e tamanho de página, em três
  processos separados. Cada execução passou com 13.196 assertions e 22
  depreciações de Core/contrib.
- Percentis nearest-rank: índice `ceil(p × n) - 1` em amostras ordenadas;
  os valores da tabela são a mediana dos três percentis por execução. As 900
  latências individuais estão no [CSV bruto]
  (aculta-index-forum-access-2026-10-23.csv).

## Resultados

| Candidatos | Acesso do membro | p50 mediano (ms) | p95 mediano (ms) | Acesso do visitante | p50 mediano (ms) | p95 mediano (ms) |
|---:|---:|---:|---:|---:|---:|---:|
| 1 | 1 permitido | 5.334 | 9.939 | 0 permitido | 2.815 | 5.040 |
| 12 | 12 permitidos | 66.737 | 139.859 | 0 permitido | 46.504 | 79.832 |
| 25 | 25 permitidos | 188.933 | 276.198 | 0 permitido | 102.048 | 165.852 |
| 50 | 50 permitidos | 305.501 | 522.265 | 0 permitido | 178.137 | 282.105 |
| 100 | 100 permitidos | 703.354 | 1009.602 | 0 permitido | 357.591 | 501.705 |

Com os hooks realmente registrados e sem usar UID 1, a latência de 100
candidatos ficou em aproximadamente 0,70 s p50 para membro e 0,36 s p50 para
visitante. O custo do membro cresceu com o número de candidatos e merece
otimização; o limite fixo de 100 permanece uma decisão de segurança e não deve
ser elevado com base nesta medição. Os resultados variam entre processos e
hardware. A fixture usa um Group compartilhado e não representa diversidade de
papéis, múltiplos grupos nem cache de uma instalação real.

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
