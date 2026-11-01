# Entity Access por Domain — ACULTA Content Index (2026-10-23)

Esta medição opt-in avalia o custo do `ContentIndexAccessFilter` ao alternar
para o Domain canônico da Wiki, validar acesso de conteúdo real e restaurar os
caches dos handlers de Entity Access do Core. Não é SLO e não inclui a consulta
SQL de candidatos do catálogo.

## Ambiente e método

- Drupal Core `11.4.10`, PHP `8.4.26`, MariaDB `11.8.6-MariaDB-0+deb13u1`.
- Banco isolado `localhost/aculta_simpletest`.
- Provider Wiki e hooks do Portal de produção; Domains principal e Wiki reais.
- 100 Nodes `wiki_entry`, metade publicada e autorizada para o leitor de teste,
  metade não publicada e negada.
- Cinco aquecimentos e 30 medições por tamanho de página; percentis nearest-rank.
- Cache de página candidata não participa; cada chamada revalida Entity Access.

## Resultados

| Candidatos | Autorizados por chamada | p50 (ms) | p95 (ms) |
|---:|---:|---:|---:|
| 1 | 1 | 0,212 | 0,257 |
| 12 | 6 | 2,518 | 3,095 |
| 25 | 13 | 5,397 | 6,897 |
| 50 | 25 | 10,624 | 12,718 |
| 100 | 50 | 23,314 | 25,925 |

O harness contou dois tipos de entidade de conteúdo cujos handlers são limpos
por candidato. O tempo inclui carga/autorização das entidades e o trabalho do
filtro; não isola o custo da limpeza. A proporção autorizada é determinada pela
fixture, não por um conjunto representativo de produção. Os resultados mostram
crescimento aproximadamente proporcional ao número de candidatos neste caso,
mas não justificam ainda limite de latência nem otimização específica.

O teste grava JSON bruto apenas no diretório temporário do sistema. Foi
executado uma vez; isso é evidência localizada, sem repetição suficiente para
conclusões de capacidade. A execução teve cinco depreciações vindas do módulo
contrib Domain/Drupal; não foram tratadas como falhas do Index.
