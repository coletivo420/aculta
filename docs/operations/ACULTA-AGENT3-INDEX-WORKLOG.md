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
- Medição opt-in em duas execuções: rebuild 402–429 ms; auditoria ~46,2 ms;
  consulta fria 5,7–6,8 ms; aquecida p50/p95 4,7–5,0 / 5,8–6,2 ms; pico
  82,5 MiB.
- Limitações: fixture sintética pequena/média, sem SLO, sem EXPLAIN e sem
  owners reais nesta classe específica (os owners seguem cobertos nos Kernels
  focais). Relatório:
  [`ACULTA-CONTENT-INDEX-REPRESENTATIVE-2026-10-26.md`](../portal/benchmarks/ACULTA-CONTENT-INDEX-REPRESENTATIVE-2026-10-26.md).

### 7. Correções da revisão focada (`c80b9fa64`, `90d50c6cc`, `25e0a5958`)

- A revisão independente apontou que o reset de cache por tipo candidato não
  cobria checagens aninhadas de tipos ausentes da página. O filtro agora limpa
  todos os handlers de conteúdo a cada chamada; o teste Unit inclui um tipo
  não-candidato para provar o reset.
- A exclusão de gerações `discarding` passou de uma consulta separada para uma
  subquery `NOT IN` na mesma instrução do count/auditoria, eliminando a janela
  de transição entre a leitura dos IDs e a contagem.
- A recusa de `start` quando validação/descarte concorre é retryável e foi
  documentada; a invariante de uma única geração `building` permanece.

### 8. Submódulo opcional `aculta_index_file` (`162b093f7`, `3fa562e01`)

- A métrica `media.files.requests` exige `file:file`, que não tinha provider.
  O provider foi adicionado como submódulo opt-in para não indexar arquivos em
  todo site com o Index nem quebrar os laços de lote dos testes existentes.
- Projeta somente arquivos permanentes: identidade, bundle, UUID, idioma,
  datas, estado e `owner_uid`. Não projeta nome, URI, MIME, tamanho, conteúdo,
  relações ou domínio.
- O bridge Statistics passou a cobrir a métrica quando o submódulo está
  habilitado e a conta tem `administer aculta_statistics` (métrica INTERNAL).
  Unit do submódulo 2/21; bridge 3/55.
- Config Sync/ativação do submódulo ficam para o agente de integração.

### 9. Vídeo: contrato e LMS (`2f664cca9`, `6d35bd40a`)

- Contrato definido em
  [`ACULTA-CONTENT-INDEX-VIDEO-RELATIONS`](../portal/ACULTA-CONTENT-INDEX-VIDEO-RELATIONS.md):
  `embedded_video` para embeds editoriais e `course_video` para o recurso de
  curso; alvo sempre `media:remote_video`, sem URL/corpo/miniatura.
- O owner `aculta_lms_resources` ganhou `field_lms_videos` (somente
  `remote_video`), update `8001`, componente de formulário/visualização e a
  relação `course_video`. A versão de projeção passou a 4 com o plugin PDF
  Group ou 3 sem ele.
- Kernel LMS real: estudante e educador veem o vídeo, terceiro é negado;
  revalidação de origem e alvo pelo Index. Unit LMS 13/70; Kernel LMS 5/259.
- Revista tem o formato editorial pronto, mas ainda não emite a relação; Wiki e
  Fórum precisam decidir se os formatos permitem `remote_video`. Config
  Sync/update do LMS ficam para o agente de integração.

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
| `lms_course` | 4 (plugin PDF presente) / 3 | `group:lms_course` | `courses` | `course_reading`, `course_gallery`, `course_document`, `course_video` (`field_lms_videos`) |
| `file` (submódulo opt-in) | 1 | `file:file` | request atual | — |

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
- `media.files.requests` (Statistics, dimensão `file:file`) é coberta pelo
  submódulo opt-in `aculta_index_file`; sem ele, não há provider e a métrica
  não é alcançável pelo bridge. A conta precisa de
  `administer aculta_statistics` porque a métrica é INTERNAL.
- O LMS passou a permitir `remote_video` por `field_lms_videos` e a projetar
  `course_video`; o contrato completo está em
  [`ACULTA-CONTENT-INDEX-VIDEO-RELATIONS`](../portal/ACULTA-CONTENT-INDEX-VIDEO-RELATIONS.md).

## Achados fora do escopo (revisão de 2026-10-26)

A primeira revisão independente foi executada no checkout principal, não no
worktree, e apontou defeitos em outros módulos. Nenhum foi alterado ou
reproduzido pelo Agente 3; ficam para triagem dos owners. O escopo desta frente
continua sendo `aculta_index`.

| Severidade | Arquivo:linha | Achado |
| --- | --- | --- |
| high | `aculta_document_viewer/js/pdf-viewer.mjs:229` | Listeners em controles removidos por `#access`; `querySelector` nulo impede a renderização do PDF. |
| high | `aculta_commerce_melhorenvio/src/Http/MelhorEnvioClient.php:391` | Fallback de `SecretsManager` usa o Key ID em vez do nome do contrato; OAuth e webhooks podem responder não configurado/503. |
| high | `aculta_lms_h5p/src/Plugin/Field/FieldFormatter/aculta_H5PDefaultFormatter.php:250` | `getEntity()` sem checagem de nulo em relação Group obsoleta; erro fatal na página do curso. |
| medium | `aculta_user/src/Hook/ProfileNicknameValidationHooks.php:1` | Validador do apelido legado removido, mas o campo continua exposto em `/dados`; apelidos inválidos/duplicados. |
| medium (plausível) | `aculta_commerce/src/Catalog/GreenStashProductReader.php:96` | Sonda de continuação perdida; produtos gerenciáveis após os primeiros 500 podem ficar ocultos. |
| low | `aculta_user/src/Avatar/AvatarAssetCleanup.php:48` | Contadores sobem mesmo quando `FileRemover::delete()` retorna FALSE. |
| low | `aculta_patcher/src/PatchDiffValidator.php:40` | `escapesPackage()` não cobre `--- ../foo`; validação inconsistente com o exportador. |
| low (plausível) | `aculta_deployer/config/deploy.json:301` | Regra de rodapé virou identidade no-op para satisfazer `validateProfiles`. |
| low | `aculta_portal/src/Hook/FormHooks.php:195` e `aculta_commerce/.../GreenStashProductReader.php:24` | Docblocks órfãos/duplicados. |

Caminhos relativos a `web/modules/custom/aculta_portal/modules/`;
`aculta_commerce_melhorenvio` está em `aculta_commerce/modules/`.

## Revisão de código

Revisão independente focada no diff do `aculta_index`, no worktree isolado:

- Nenhum bypass de autorização, deadlock ou regressão de paginação confirmado.
- Achados plausíveis: reset de cache sem tipos aninhados (medium; corrigido em
  `c80b9fa64`), janela entre a leitura de gerações em descarte e o count (low;
  corrigido em `90d50c6cc`) e recusa retryável de `start` concorrente com
  validação/descarte (low; documentado em `25e0a5958`).
- Cobertura: o Unit passou a incluir um tipo não presente na página; a
  serialização real de `start` em duas conexões não é exercitada pelo Kernel de
  conexão única e fica registrada como lacuna de teste.

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
tópicos, Group real, Domain Fórum, 5 aquecimentos + 30 medições por conta/página).
Duas execuções válidas no tamanho 100:

| Conta | Baseline 2026-10-23 | Run 1 | Run 2 |
| --- | ---: | ---: | ---: |
| Membro p50/p95 (ms) | 386 / 567 | 371 / 407 | 494 / 550 |
| Visitante p50/p95 (ms) | 182 / 293 | 178 / 220 | 243 / 290 |

A run 2 tem p50 maior e p95 menor que a baseline; a run 1 é menor nas duas. Não
há evidência suficiente para declarar melhoria causal nem regressão além do
ruído; a carga concorrente no banco de teste não foi isolada. O custo do reset
por página é proporcional ao número de tipos (até três aqui) e precisa de
telemetria em Alpha. Amostras sintéticas, sem SLO. CSV com as duas execuções:
`docs/portal/benchmarks/aculta-index-forum-access-2026-10-26.csv`.

## Runtime — leitura e homologação autorizada (IDX-7)

Ambiente: checkout local `/home/piradopirata/projetos/aculta`, Drush 13.8.0,
Drupal 11.4.10, banco `aculta_runtime`, site URI `http://default`. Não é o
servidor `toca` e não é produção. O responsável autorizou, em 2026-10-26,
executar a homologação de rebuild/ativação diretamente nesse Runtime local.

Diagnóstico inicial (somente leitura):

- `aculta_index`, `aculta_portal` e `aculta_statistics` habilitados; versão do
  Index `0.1.0-dev.175`.
- 145 entradas, 110 relações, 1 geração ativa, 0 trabalhos pendentes.
- Geração ativa `fceee33d-cb34-4c24-add1-56619c426b5a`; auditoria `complete`,
  `valid=true`, todos os contadores de achado em zero.
- `activation_ready=yes`; fila sem queued/processing/retry/dead.
- 11 providers descobertos, fingerprint
  `098831a171e6be765f914778fb5306a11f99ca58c1dcaab41cff3b01882bb0e6`.
- Divergência observada: o `config/sync/core.extension.yml` do checkout local
  não contém `aculta_index`, enquanto a branch de integração contém. Nenhum
  `cim` foi executado; a reconciliação pertence ao agente 1.

Backup antes da operação:

- `/home/piradopirata/aculta-backups/aculta-runtime-before-index-homolog-20261026.sql`
  (55 MB), dump completo do banco.

Homologação autorizada:

1. A auditoria pendente foi concluída (`complete`, `valid=true`, zero achados).
2. `aculta-index:rebuild start` criou a geração
   `25eb002d-bccd-4dee-b88d-4fa0957744a3`.
3. 14 lotes de `batch --limit=100` projetaram 145 entradas; a fila ficou vazia.
4. `validate` retornou `valid=true` com zero órfãos, zero projeções obsoletas e
   zero fontes ausentes.
5. `activate` trocou o ponteiro; a geração anterior
   `fceee33d-cb34-4c24-add1-56619c426b5a` ficou `retained` para rollback.
6. A nova geração tem 145 entradas e 110 relações; `activation_ready=yes` e
   fila vazia.
7. Uma nova auditoria da geração ativa terminou `complete`, `valid=true`, com
   zero achados em todos os contadores.

Rollback: reativar `fceee33d-cb34-4c24-add1-56619c426b5a` (retida) com
`aculta-index:rebuild activate`; o dump completo é a última linha de defesa.
Nenhum título, corpo ou dado pessoal foi impresso. Não foram executados `cim`,
`updatedb`, instalação, desinstalação ou alteração de conteúdo original. O
Runtime local roda o código do checkout principal, não os novos recursos
LMS/file desta branch; a homologação acima valida o ciclo de catálogo atual.

## Testes e gates executados

| Gate | Resultado |
| --- | --- |
| PHP lint (módulo) | PASS |
| PHPCS Drupal/DrupalPractice (arquivos alterados) | PASS |
| Unit `aculta_index` | 51 testes / 265 assertions PASS; 14 depreciações PHPUnit |
| Kernel `aculta_index` (MariaDB) | 119 testes / 2.019 assertions PASS; 276 depreciações Core/contrib e 56 PHPUnit |
| Unit `aculta_index_file` | 2 testes / 21 assertions PASS |
| Bridge Statistics (file:file) | 3 testes / 55 assertions PASS |
| Unit `aculta_lms_resources` | 13 testes / 70 assertions PASS; 10 depreciações PHPUnit |
| Kernel LMS `course_video` | 5 testes / 259 assertions PASS; 53 depreciações Core/contrib |
| Revisão de código focada | Concluída; achados corrigidos/documentados |
| PHPCS do módulo (`src` + `tests`) | PASS |
| Gate Drupal 11+ | PASS (1.160 checks) |
| Índice de status do projeto | PASS (41 componentes) |
| Simulação de merge com a integração atual | PASS, sem conflitos |
| Benchmark Fórum opt-in | 1 teste / 395 assertions PASS |
| Fixture representativa (com medição opt-in) | 1 teste / 75 assertions PASS |
| Runtime leitura e rebuild/ativação autorizados | PASS (145/110, auditoria válida, rollback retido) |

Depreciações são de Core/contrib e PHPUnit; nenhuma falha própria.

## Integração com o agente principal

- Em 2026-10-26 o agente principal revisou e integrou os 13 commits publicados
  até `44de71674` em `chore/aculta-m0-integridade`, como cherry-picks com SHAs
  próprios. Antes das correções da revisão, o diff do módulo e da documentação
  entre a integração e esta branch estava vazio.
- O status integrado (`docs/operations/status/aculta_index.yml`, commit
  `e3c34b675`) permanece `0.1.0-dev.175`/Dev e registra cinco arquivos não
  commitados. Esses arquivos foram publicados em `c80b9fa64`, `90d50c6cc` e
  `25e0a5958`; a nota do status está desatualizada e a reexecução da integração
  cabe ao agente principal.
- O agente principal relatou Kernel integral na integração excedendo timeout em
  63/118, sem resultado conclusivo, por bootstrap compartilhado de Core. A
  execução isolada deste worktree passou 118/2.001 em MariaDB; a diferença é o
  `web/core` local e o autoload do vendor apontando para o worktree, conforme a
  seção de ambiente.
- Verificação final: o cherry-pick dos quatro commits `c80b9fa64`,
  `90d50c6cc`, `25e0a5958` e `e6937ef18` sobre `e3c34b675` aplicou limpo em
  uma branch temporária local, sem conflitos. Eles permanecem pendentes de
  integração no momento desta verificação.
- O novo submódulo `aculta_index_file` tem status próprio
  (`docs/operations/status/aculta_index_file.yml`). O validador de status desta
  branch falha apenas porque a tabela gerada do README raiz ainda não contém a
  nova linha; o agente de integração deve rodar
  `php scripts/validate-project-status-index.php --write` depois de integrar o
  status. O gate Drupal 11+ herda essa mesma falha. Os gates de código do
  `aculta_index`/LMS passam.
- Nenhuma operação Runtime foi executada por nenhum dos lados.

## Pendências e bloqueios

- Carga representativa com conteúdo real dos owners ainda não existe; a fixture
  sintética cobre diversidade, mas os benchmarks continuam sintéticos.
- Homologação operacional de grants/Domain/Group com conteúdo real dos owners e
  telemetria antes de SLO continua pendente.
- Rebuild/ativação sobre catálogo não vazio foi homologado no Runtime local
  autorizado em 2026-10-26; a validação em Alpha/toca continua pendente.
- Revista tem o formato pronto, mas ainda precisa emitir `embedded_video`; Wiki
  e Fórum precisam decidir se os formatos permitem `remote_video`.
- Config Sync/update `8001` do LMS e ativação do submódulo `aculta_index_file`
  no Runtime pertencem ao agente de integração.
- Cursor continua vinculado à geração; não é reutilizável após troca de geração
  (testado).
- A fixture representativa é sintética e pequena/média. Conteúdo real dos
  owners, com Group/Media/Domain reais em volume, continua pendente.
- Operação: um rebuild abandonado em `building` precisa ser descartado antes de
  outro `start`; a guarda está implementada e testada.

## Proposta de status (não aplicada)

O status global continua `Dev` e a decisão é do agente 1. Proposta para
`docs/operations/status/aculta_index.yml`:

- `version: 0.1.0-dev.176`.
- `state: Dev`.
- Registrar: correções de integridade, revalidação de acesso por página, cursor
  vinculado ao inventário, guarda de rebuild concorrente, fixture representativa
  sintética, revisão de código concluída, submódulo opt-in `aculta_index_file`,
  contrato de vídeo e `course_video` no LMS, Kernel 119/2.019, Unit 51/265, duas
  amostras de benchmark e Runtime local read-only (145/110).
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
- `a62f7ea1b` — docs(index): record rebuild fence and representative fixture
- `03c5cac27` — docs(index): record two representative benchmark samples
- `44de71674` — docs(index): record two Forum access samples and caveats
- `c80b9fa64` — fix(index): clear nested entity access handlers on every page
- `90d50c6cc` — fix(index): make discard exclusion atomic in integrity queries
- `25e0a5958` — docs(index): note retryable rebuild start refusal
- `162b093f7` — feat(index): add optional file provider for the file metric
- `3fa562e01` — test(index): cover file metric through the statistics bridge
- `2f664cca9` — feat(lms): allow remote video references on courses
- `6d35bd40a` — test(lms): cover course_video access through the content index
- Commit documental do worklog: HEAD final da branch
  `feat/aculta-index-fechamento` (registrado no relatório de entrega).

Todos estão publicados em `origin/feat/aculta-index-fechamento`. Nenhum merge,
PR nova, force push, deploy ou alteração da branch da PR #298.
