# ACULTA — Agente 3 — Worklog do Content Index

Atualizado em 2026-10-26. Registro operacional da frente exclusiva `aculta_index`.
Não altera o status global do componente; a proposta de atualização está na
seção final.

## Ambiente e isolamento

- Repositório real: `/home/piradopirata/projetos/aculta` (o comando de missão
  citava `/home/aculta`; o worktree e os comandos foram adaptados ao path real).
- Branch de integração: `origin/chore/aculta-m0-integridade` em `69dc7c75a`.
- Branch exclusiva: `feat/aculta-index-fechamento`.
- Worktree exclusivo: `/tmp/aculta-index-fechamento`.
- O worktree da integração (`/tmp/aculta-m0-integridade`) já existia com
  alterações de outro agente em `aculta_mail_confirmer`; não foi tocado.
- `web/core` e `vendor` do worktree foram isolados do checkout principal
  (cópia local do core e vendor com autoload próprio). Sem esse isolamento o
  phpunit carregava o módulo do checkout principal e os resultados não
  correspondiam à branch.
- Banco de Kernel: `SIMPLETEST_DB` de `secrets/simpletest-db.env`
  (`aculta_simpletest`, MariaDB local). Nenhum teste usou `aculta_runtime`.
- Gates: PHP lint, PHPCS Drupal/DrupalPractice e PHPUnit.

## Auditoria do núcleo (IDX-1)

Serviços revisados: `Cache`, `Generation`, `Hook`, `Integrity`, `Plugin`,
`Provider`, `Query`, `Queue`, `Rebuild`, `Statistics`, `Storage`, `Drush`,
schema e update hooks.

Contratos observados:

- Consulta seleciona candidatos SQL e revalida Entity Access no original.
- Gerações `building/validated/active/retained/discarding` com CAS, fence de
  ativação e gate de fila drenada.
- Writer idempotente por identidade, sequência por fonte e revisão monotônica
  de candidatos.
- Rebuild keyset, checkpoint CAS, fingerprint de providers e auditoria
  incremental nas duas direções.
- Fila Core Queue com retry/backoff, dead-letter e recuperação de processamento
  preso.
- Sem rotas, UI ou permissões próprias; comandos Drush restritos ao shell.

## Correções desta sessão

### 1. Limite de lotes do harness Wiki (`8dabc66ad`)

- Sintoma: três cenários de `ContentIndexWikiAccessKernelTest` falhavam em
  “backfill should complete”.
- Causa: o harness inicializa 9 providers e 11 tuplas provider/tipo, mas o laço
  tinha limite fixo de 10 lotes. Cada lote avança no máximo uma tupla.
- Correção: limite derivado do registry bootado, com folga para tuplas maiores
  que a página.
- Evidência: classe Wiki 7/245 PASS.

### 2. Integridade ignorando gerações em descarte (`9426e80bd`)

- Sintoma potencial: `check()` e a fase de relações da auditoria contavam uma
  relação cuja fonte já tinha sido removida em lote anterior da **mesma**
  geração `discarding`. A limpeza remove relações e entradas em lotes separados;
  entre lotes existe essa inconsistência transitória.
- Risco: validação de rebuild concorrente com cron/Drush de limpeza podia falhar
  espuriamente.
- Correção: `relationAuditRows()` e `countMissingRelationSources()` excluem
  gerações `discarding`; gerações `active`, `retained`, `validated` e `building`
  continuam integralmente verificadas.
- Evidência: novo Kernel verifica que a linha em descarte não conta, que a
  auditoria completa sem achados e que, ao virar `retained`, o mesmo cenário é
  reportado. Suíte Schema/MariaDB PASS.

### 3. Recomputar Entity Access a cada página (`40a4e26b8`)

- Sintoma: despublicar uma entidade entre duas consultas no **mesmo processo**
  não era detectado; o cache interno de Entity Access do Core não é invalidado
  por `save()`, por mudança de Group nem por grant.
- Risco: consumidores de longa duração (worker, Drush, múltiplas páginas na
  mesma requisição) podiam reutilizar um ALLOW antigo depois da revogação.
- Correção: `ContentIndexAccessFilter` limpa os handlers dos tipos presentes na
  página no início de cada chamada, além dos resets antes/depois dos grupos de
  purpose canônico.
- Testes: página de candidatos aquecida e depois despublicada continua negando;
  cursor de geração substituída é rejeitado; integração Wiki ajustada porque o
  hook real do owner publica a imagem de destaque vinculada a verbete publicado
  (o teste antigo passava apenas pelo cache obsoleto).
- Evidência: Query 7/88, OwnerIntegration 1/43, Unit 51/282, Kernel completa
  114/1.890 PASS.

### 4. Cursor vinculado ao inventário de providers (`fcf006fca`)

- Risco: mudança de versão/bundles de provider altera os candidatos correntes,
  mas o cursor só estava vinculado a conta, filtros e geração.
- Correção: hash do cursor inclui `provider_fingerprint()` na consulta de
  conteúdo e na de relações.
- Teste: provider de fixture com versão mutável; cursor emitido antes da
  mudança é recusado e a página nova não retorna linhas da versão antiga.
- Evidência: Query 7/88 PASS.

### 5. Serialização de rebuilds concorrentes (`8b5d41a8a`)

- Risco documentado: duas chamadas de `start` criavam duas gerações `building`,
  ambas recebendo escritas incrementais e duplicando backfill.
- Correção: `ContentIndexGenerationManager::start()` bloqueia a linha singleton
  de estado (`FOR UPDATE`), recusa uma segunda geração em construção e exige
  concluir, validar ou descartar a existente. A mesma linha serializa com a
  ativação e o fence de agendamento.
- Teste: segunda chamada lança `LogicException`; após `discard`/`discard-batch`
  um novo build inicia. Schema 1/10 PASS; suíte completa 118/2.001 PASS.

### 6. Fixture representativa mista (`0db86be20`)

- Novo módulo de teste `aculta_index_representative_test` com dois providers
  sintéticos sobre Node (`representative_content` e `representative_target`) e
  um Kernel que usa Entity API e `content_translation` reais.
- Diversidade: 28 entradas de conteúdo + 14 de alvo, 30 relações, dois idiomas,
  dois domínios, publicados/rascunhos, atualização, exclusão e resíduo de
  provider desregistrado. Consulta de conteúdo, cursor, relação, acesso negado
  a rascunho e auditoria completa sem achados.
- Medição opt-in (uma execução): rebuild 402,275 ms; auditoria 46,191 ms;
  consulta fria 5,652 ms; aquecida p50/p95 4,672/5,768 ms; pico 82,5 MiB.
- Limitações: fixture sintética pequena/média, sem SLO, sem EXPLAIN e sem
  owners reais nesta classe específica (os owners seguem cobertos nos Kernels
  focais). Relatório:
  [`ACULTA-CONTENT-INDEX-REPRESENTATIVE-2026-10-26.md`](../portal/benchmarks/ACULTA-CONTENT-INDEX-REPRESENTATIVE-2026-10-26.md).

## Homologação de providers (IDX-2)

Providers descobertos no container local (11 providers, 13 tuplas):

| Provider | Versão | Tipos/bundles | Purpose | Relações |
| --- | ---: | --- | --- | --- |
| `magazine` | 2 | `node:revista` | `magazine` | `related_activity`, `editorial_image`, `editorial_gallery` |
| `wiki` | 4 | `node:wiki_entry` | `wiki` | `related_entry`, `wiki_category`, `wiki_image` |
| `wiki_category` | 1 | `taxonomy_term:wiki_category` | `wiki` | — |
| `forum` | 2 | `node:aculta_forum_topic`, `aculta_forum_post:aculta_forum_post`, `comment:aculta_discussion_post` | `forum` | `forum_topic`, `forum_parent_post`, `forum_comment_parent` |
| `forum_attachment` | 2 | `media:forum_attachment` | `forum` | — |
| `gallery` | 2 | `media_gallery:media_gallery` | `magazine` | `gallery_image`, `gallery_cover` |
| `image` | 2 | `media:image` | request atual | — |
| `document` | 2 | `media:document` | request atual | — |
| `course_document` | 2 | `media:course_document` | `courses` | — |
| `remote_video` | 2 | `media:remote_video` | request atual | — |
| `lms_course` | 3 (plugin PDF presente) / 2 | `group:lms_course` | `courses` | `course_reading`, `course_gallery`, `course_document` |

Observações de homologação:

- `course_document` continua sob `aculta_document_viewer_group`; o bundle Media
  mantém a dependência histórica do PDF Group. Storage/plugin não foram movidos.
- `lms_course` varia a versão conforme o plugin Group existir; o fingerprint
  acompanha a capacidade.
- Wiki projeta três relações; categoria Wiki tem provider mínimo próprio e
  imagem permanece no provider `image`.
- Portal é owner de `media:remote_video`; URL oEmbed, corpo e miniatura não são
  projetados. Relações editoriais de vídeo seguem adiadas por decisão do
  responsável.
- O provider de `document`/`image`/`remote_video` não força purpose; o acesso é
  revalidado no contexto da consulta.
- Integração real descoberta: `WikiEntryImageHooks` (owner) publica a Media de
  `field_wiki_image` quando o verbete está publicado. O Index apenas revalida o
  estado atual; consumidores não devem tratar a projeção como estado definitivo
  de publicação.
- Nenhum provider projeta e-mail, CPF/CNPJ, telefone, endereço, token, corpo,
  arquivo, URL privada ou URL oEmbed. `owner_uid` é a única referência pessoal,
  é interno ao filtro e não retorna nos DTOs. Wiki omite `owner_uid` por decisão
  de privacidade do owner.
- Lacuna documentada: `media.files.requests` (Statistics, dimensão `file:file`)
  não tem provider `file:file` no Index; a métrica não é alcançável pelo bridge
  até existir provider desse tipo. Consumidores devem usar o Statistics direto.

## Segurança (IDX-3)

- Entity Access é revalidado em cada chamada; o cache de acesso do Core agora é
  limpo por tipo candidato antes da página.
- Purpose canônico é aplicado por grupo; Domain é restaurado mesmo em exceção
  (`DomainPurposeManager::runInPurpose`); purpose ausente falha fechado.
- Group/grants: testes reais de membership, revogação e Domain para Fórum,
  Gallery, LMS e `course_document`; consulta a relação revalida origem e até 100
  alvos.
- Despublicação, revisão pendente, tradução removida, entidade ausente, provider
  desabilitado e Media privada são negados.
- Cursor: token aleatório, só hash persistido, vinculado a conta, filtros,
  geração e inventário de providers, com TTL e CAS de avanço.
- Privacidade: colunas SQL e DTOs auditados; nenhum dado pessoal além de
  `owner_uid` interno.
- Field Access permanece responsabilidade da renderização; o Index devolve
  entidades originais, não valores de campo serializados.

## Integridade, rebuild e manutenção (IDX-4)

- Ativação exige geração validada/retida, fingerprint compatível e fila drenada.
- Rollback mantém somente uma geração anterior `retained`; as demais viram
  `discarding` e são expurgadas em lotes.
- Checkpoint e conclusão de backfill usam CAS; fingerprint divergente bloqueia
  retomada/validação.
- Auditoria incremental em duas direções, com teto de IDs por ciclo e
  reenfileiramento após o checkpoint.
- Correção desta sessão: linhas de geração em descarte não contaminam a
  integridade de uma geração retida/ativa.
- Não foi identificada corrupção de conteúdo original: descarte afeta apenas
  tabelas derivadas.

## Consulta, relações e paginação (IDX-5)

- Keyset por `entry_id` imutável, ordem decrescente, limites 1..100.
- Cursor opaco, não transferível entre contas/filtros/gerações/inventário.
- Inserção posterior não desloca a travessia; exclusão/despublicação some na
  revalidação; duplicidade de arestas colapsa por alvo.
- Relações são unidirecionais, um salto, sem totais/facetas.
- Cache interno guarda somente identidades por até 60 s; acesso nunca é cacheado.

## Performance representativa (IDX-6)

Benchmark de acesso do Fórum repetido nesta sessão (fixture sintética, 100
tópicos, Group real, Domain Fórum, 5 aquecimentos + 30 medições por conta/página):

| Candidatos | Membro p50/p95 (ms) | Visitante p50/p95 (ms) |
| ---: | ---: | ---: |
| 1 | 3,958 / 5,216 | 2,222 / 2,869 |
| 12 | 55,035 / 78,601 | 25,792 / 34,457 |
| 25 | 90,626 / 118,283 | 43,162 / 66,945 |
| 50 | 193,460 / 291,482 | 91,892 / 263,097 |
| 100 | 371,114 / 406,681 | 178,307 / 220,385 |

Baseline de 2026-10-23 no tamanho 100: membro 386/567 ms e visitante
182/293 ms. A limpeza adicional de cache de acesso não degradou a medição
(p95 menor nesta amostra). É uma execução única, sintética, sem SLO; não
estabelece desempenho de produção. CSV:
`docs/portal/benchmarks/aculta-index-forum-access-2026-10-26.csv`.

## Runtime — somente leitura (IDX-7)

Ambiente: checkout local `/home/piradopirata/projetos/aculta`, Drush 13.8.0,
Drupal 11.4.10, banco `aculta_runtime`, site URI `http://default`. Não é o
servidor `toca` e não é produção.

Verificado apenas leitura:

- `aculta_index`, `aculta_portal` e `aculta_statistics` habilitados; versão do
  Index `0.1.0-dev.175`.
- 145 entradas, 110 relações, 1 geração, 0 trabalhos pendentes, 0 cursores.
- Geração ativa `fceee33d-cb34-4c24-add1-56619c426b5a`; auditoria `complete`,
  `valid=true`, todos os contadores de achado em zero.
- `activation_ready=yes`; fila sem queued/processing/retry/dead.
- 11 providers descobertos, fingerprint
  `098831a171e6be765f914778fb5306a11f99ca58c1dcaab41cff3b01882bb0e6`.
- Divergência observada: o `config/sync/core.extension.yml` do checkout local
  (HEAD `ff92ca0aa`) não contém `aculta_index`, enquanto a branch de integração
  contém. Nenhum `cim` foi executado. A reconciliação pertence ao agente 1.

Nenhum rebuild, ativação, descarte, retry, `cim`, `updatedb`, instalação ou
desinstalação foi executado no Runtime.

## Testes e gates executados

| Gate | Resultado |
| --- | --- |
| PHP lint (módulo) | PASS |
| PHPCS Drupal/DrupalPractice (arquivos alterados) | PASS |
| Unit `aculta_index` | 51 testes / 282 assertions PASS; 14 depreciações PHPUnit |
| Kernel `aculta_index` (MariaDB) | 118 testes / 2.001 assertions PASS; 276 depreciações Core/contrib e 56 PHPUnit |
| PHPCS do módulo (`src` + `tests`) | PASS |
| Benchmark Fórum opt-in | 1 teste / 395 assertions PASS |
| Runtime leitura | PASS (métricas acima) |

Depreciações são de Core/contrib e PHPUnit; nenhuma falha própria.

## Pendências e bloqueios

- Carga representativa com relações reais e perfis de acesso ainda não existe;
  os benchmarks continuam sintéticos.
- Homologação operacional de grants/Domain/Group com conteúdo real e telemetria
  antes de SLO continua pendente.
- Rebuild/ativação sobre catálogo não vazio no Runtime exige autorização,
  pré-condições e rollback; não executado.
- Relações editoriais de vídeo aguardam formatos e contratos dos consumidores.
- Métrica `media.files.requests` não tem provider `file:file` no Index.
- Cursor continua vinculado à geração; não é reutilizável após troca de geração
  (testado).
- A guarda contra duas gerações `building` foi implementada e testada; um
  rebuild abandonado precisa ser descartado antes de iniciar outro.
- A fixture representativa é sintética e pequena/média. Conteúdo real dos
  owners, com Group/Media/Domain reais em volume, continua pendente.

## Proposta de status (não aplicada)

O status global continua `Dev` e a decisão é do agente 1. Proposta para
`docs/operations/status/aculta_index.yml`:

- `version: 0.1.0-dev.176`.
- `state: Dev`.
- Registrar: correções de integridade, revalidação de acesso por página, cursor
  vinculado ao inventário, Kernel 114/1.890, Unit 51/282, benchmark pós-correção
  e Runtime local read-only (145/110).
- Manter como pendentes: grants operacionais, carga representativa, execução
  sobre catálogo no Runtime e relações de vídeo.

## Commits para integração

- `8dabc66ad` — test(index): derive wiki rebuild batch bound from provider tuples
- `9426e80bd` — fix(index): ignore rows awaiting discard in integrity checks
- `40a4e26b8` — fix(index): recompute entity access for every candidate page
- `fcf006fca` — fix(index): bind cursors to the provider inventory
- `fbeb677e7` — docs(index): publish closing validation, provider matrix and benchmark
- `e94e0fe0c` — test(index): cover Statistics bridge order, cursor and zero totals
- `7dcad8547` — test(index): drop unused database variable from bridge test
- `44fec1247` — test(index): drop unused translation variable
- `8b5d41a8a` — fix(index): serialize rebuild starts behind the state fence
- `0db86be20` — test(index): add representative mixed-catalog fixture
- Commit documental do worklog: HEAD final da branch
  `feat/aculta-index-fechamento` (registrado no relatório de entrega).

Todos estão publicados em `origin/feat/aculta-index-fechamento`. Nenhum merge,
PR nova, force push, deploy ou alteração da branch da PR #298.
