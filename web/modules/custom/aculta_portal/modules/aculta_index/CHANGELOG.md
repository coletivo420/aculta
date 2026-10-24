# Changelog — ACULTA Content Index

## 0.1.0-dev.174 — fechar relações Wiki sem alvo indexável

- O Kernel real agora testa categoria cujo vocabulário não possui provider:
  ela não entra na geração e a consulta outgoing a omite. Também testa Media
  publicada ligada a verbete em rascunho: a consulta nega a origem e não revela
  a relação. A classe Wiki passou em MariaDB com 7 testes / 238 asserções;
  201 depreciações Drupal/Core/contrib.
- Nenhuma alteração de produção no Index e nenhum rebuild Runtime.

## 0.1.0-dev.173 — atualizar contrato de privacidade do provider Wiki

- A projeção `node:wiki_entry` agora omite `owner_uid`; a alteração é do owner
  Wiki e incrementa sua versão para 4, mudando o fingerprint do registry.
- Kernel Wiki/Index completo: 7 testes / 236 asserções; a prova de bootstrap
  owner adicional: 1 teste / 42 asserções. Os testes genéricos de backfill e
  ativação recusam fingerprint antigo (2 / 16). Sem alteração Runtime.

## 0.1.0-dev.172 — sincronizar relação Wiki em update/delete real

- O cenário owner real agora confirma que alterar `field_wiki_related` troca
  o destino da projeção e que excluir o Node remove as relações de saída.
- Classe completa MariaDB: 7 testes / 234 asserções; 201 depreciações upstream.
  Nenhuma mudança na infraestrutura do Index ou no Runtime.

## 0.1.0-dev.171 — cobrir ciclo Wiki no provider owner real

- `ContentIndexWikiAccessKernelTest` agora comprova no mesmo bootstrap real o
  backfill, fingerprint capturado pela geração, update de rótulo/hash e delete
  incremental da projeção Wiki.
- Classe completa MariaDB: 7 testes / 231 asserções; 201 depreciações upstream.
  O teste não altera Runtime nem executa rebuild sobre catálogo instalado.

## 0.1.0-dev.170 — provar revisão padrão e ausência de Domain na Wiki

- A consulta real comprova que revisão pendente não padrão não substitui a
  projeção pública e que a ausência do Domain canônico Wiki bloqueia o acesso.
- Classe MariaDB com Wiki, Image, Portal e Index reais: 6 testes / 185
  asserções; 201 depreciações Drupal/Core/contrib. Sem rebuild integral ou
  Runtime.

## 0.1.0-dev.169 — testar acesso Wiki sem bridges de fixture

- `ContentIndexWikiAccessKernelTest` substitui o registro manual de services e
  fixture de acesso pelo bootstrap real de Wiki, Image, Portal e Index. A closure
  real compila, descobre providers por tag e consulta relações pela API real.
- MariaDB: 4 testes / 125 asserções; 201 depreciações Drupal/Core/contrib.
  Sem rebuild integral, mudança de schema ou Runtime.

## 0.1.0-dev.168 — endurecer fixture privada de Media da Wiki

- O Kernel owner verifica diretamente que a Media fixture está não publicada
  e que a conta leitora não tem `view` nela antes de testar o filtro real.
  Isso torna explícita a premissa da consulta outgoing que omite destinos sem
  acesso.
- A classe owner passou em MariaDB isolada: 2 testes / 88 assertions; 243
  depreciações upstream. A suíte direcionada completa Wiki/Index não foi
  reexecutada neste incremento.

## 0.1.0-dev.167 — consultar relações Wiki no bootstrap owner real

- `ContentIndexOwnerIntegrationKernelTest` semeia uma geração isolada pelos
  serviços reais de geração e escrita e consulta `related_entry`,
  `wiki_category` e `wiki_image` pela `ContentIndexRelationQuery` real. O teste
  confirma a descoberta dos providers Wiki, categoria e Media, filtra destinos
  não autorizados sob os grants e restaura o Domain de origem.
- O cenário cobre apenas as entidades relevantes às relações; não executa
  rebuild integral nem auditoria de inventário. Focal: 1 teste / 40 assertions;
  classe owner: 2 testes / 86 assertions. Suíte direcionada Wiki/Index:
  6 testes / 187 assertions, aprovada em MariaDB; 243 depreciações upstream.
  PHP lint e PHPCS do arquivo de teste aprovados.

## 0.1.0-dev.166 — Wiki com bootstrap e acesso reais

- `ContentIndexOwnerIntegrationKernelTest` agora cria verbetes, categorias e
  mídias com a closure real Wiki + Portal + Index; usa a descoberta de hooks e
  serviços sem bridge de acesso. O provider Wiki real projeta relações v3
  deduplicadas, e `ContentIndexAccessFilter` autoriza apenas alvos visíveis em
  `wiki`, nega-os em `main` e restaura o Domain de origem.
- Focal: 1 teste / 34 assertions; suíte Wiki/Index: 6 testes / 181 assertions,
  aprovada em MariaDB isolada; 243 depreciações agregadas upstream.
- O teste não executa rebuild completo do inventário, não importa config e não
  altera o Runtime. A consulta outgoing end-to-end permanece no harness de
  acesso por fixture.

## 0.1.0-dev.165 — integrar `remote_video` do owner Portal

- O Portal fornece defaults do bundle Media `remote_video` (`oembed:video`),
  campo e displays-base, e registra o provider genérico do Index no service YAML
  do owner. As telas de Media Library ficam em `config/optional` para não criar
  dependência obrigatória de `media_library`.
- Service-map e Kernel transversal validam descoberta/tag, configuração do
  bundle, campo de origem e tipo do provider. O provider não copia URL, HTML,
  thumbnail nem dados pessoais. Relações de embeds permanecem nos módulos
  consumidores e ainda não foram implementadas. Entity Access é revalidado no
  contexto da consulta, sem purpose fixo de Magazine, Wiki, Fórum ou LMS.
- Service-map: 1 teste / 3 assertions. Kernel owner: 1 teste / 46 assertions,
  com 239 depreciações Core/contrib.
- Kernel Media: 5 testes / 101 assertions; criação, atualização e exclusão de
  `media:remote_video` percorrem a fila e a projeção não armazena a URL oEmbed.

## 0.1.0-dev.164 — integrar relações Wiki de categoria e imagem

- `WikiContentIndexProvider` projeta `related_entry`, `wiki_category` e
  `wiki_image`; o provider mínimo `taxonomy_term:wiki_category` pertence à
  Wiki e Media usa o provider existente de `aculta_image`.
- Kernel MariaDB consulta as três relações e revalida Entity Access de Node,
  Term e Media (3 testes / 84 assertions); Unit Wiki: 61/277. O boot combinado
  confirma o registro do provider de categoria (1/36; 239 depreciações
  Core/contrib). Nenhuma entidade ou configuração do Runtime foi ativada ou
  alterada.

## 0.1.0-dev.163 — validar DI real da ponte Statistics

- O boot combinado do `ContentIndexOwnerIntegrationKernelTest` inclui agora o
  owner `aculta_statistics` e resolve pelo container real o bridge do Index e
  `AuthorizedEntityMetricAggregator` registrado pelo Statistics. MariaDB
  dedicado: 1 teste / 35 assertions; 239 depreciações Core/contrib, sem falhas.
- Isso comprova a composição DI owner↔contrato, não executa agregação, não
  instala a configuração/schema completo de Statistics e não homologa grants
  por domínio/Group/LMS nem Runtime. A correlação funcional segue coberta pelo
  `ContentIndexStatisticsBridgeKernelTest` (1/17).

## 0.1.0-dev.162 — provar relação Group do owner PDF

- O harness transversal do Index agora também instala schemas auxiliares e
  configura entidades Group/Media reais para descobrir o plugin
  `aculta_pdf_viewer_group:course_document`, criar seu relationship type e
  persistir uma relação de curso→documento. MariaDB dedicado: 1 teste / 33
  assertions; 239 depreciações Core/contrib, sem falhas.
- O boot agrupado é apenas uma estratégia do teste sob `aculta_index` para
  carregar os owners simultaneamente. Ownership permanece em `aculta_portal`;
  LMS, Fórum, Wiki, Magazine, Gallery, Image e PDF Group fornecem adapters por
  seus próprios módulos. O teste não instala toda a configuração dos owners nem
  valida acesso operacional em Runtime.

## 0.1.0-dev.161 — validar 1M e boot integrado dos owners

- Benchmark MariaDB 1M passou no HEAD atual: rebuild de 1.000.000 de fontes
  em 2.020,081 s (495 projeções/s), auditoria bidirecional em 741,071 s,
  pico de 216,5 MiB e zero divergências. As 2.001 páginas de IDs usaram
  `base_table`; a carga e projeção continuam na Entity API. Execução sintética,
  sem SLO; veja o relatório de manutenção.
- `ContentIndexOwnerIntegrationKernelTest` passou em 1 teste / 8 assertions.
  O container compilou a closure de 103 módulos dos owners Portal, LMS, Fórum,
  Wiki, Magazine, Gallery, Image e PDF Group; o registry descobriu seus
  providers reais por DI/tag. Não instala schemas/configuração nem homologa
  Runtime. PHPUnit reportou 83 depreciações Core/contrib.
- O Kernel LMS isolado foi preservado após uma tentativa de ampliar seu setup
  falhar antes das assertions por falta do handler `default:media_gallery`;
  a repetição do harness anterior passou (4 testes / 208 assertions, 53
  depreciações upstream). O teste combinado cobre a descoberta de serviços sem
  misturar essa fixture de policy.

## 0.1.0-dev.160 — revalidar concorrência entre processos PHP

- `ContentIndexConcurrencyKernelTest` passou novamente no MariaDB dedicado:
  3 testes / 56 assertions, incluindo quatro processos produtores, disputa de
  claim e proteção contra conclusão de sequência obsoleta. Sem falhas.
- Confirma no HEAD atual o gate IDX 0.1-D de processos PHP independentes;
  a execução não altera o escopo nem a política de retries.

## 0.1.0-dev.159 — repetir benchmark 100k com descoberta keyset

- O harness de manutenção remove, pela Queue API e pela identidade exata, o
  único job que pode ser emitido ao salvar a entidade-semente via Entity API;
  a semeadura SQL em lote continua fora da medição e não cria fila por fonte.
- Kernel de rebuild passou com 21 testes / 295 assertions. Benchmark MariaDB
  100k passou: rebuild 182,150 s (549 projeções/s), auditoria 68,991 s,
  137,5 MiB, zero divergências e `base_table` em 201/201 consultas de IDs.
  Consulta de IDs p50/p95: 1,101/1,684 ms. Uma amostra sintética, sem SLO.

## 0.1.0-dev.158 — cobrir páginas de múltiplos purposes

- Unit confirma página mista Wiki/Fórum: o filtro entra nos dois Domains
  canônicos, conserva a ordem original dos candidatos e restaura o Domain de
  entrada. Unit completo passou com 51 testes / 262 assertions; 14 depreciações
  PHPUnit e nenhuma falha.

## 0.1.0-dev.157 — agrupar revalidação pelo purpose

- `ContentIndexAccessFilter` agora limpa caches de Entity Access uma vez antes
  e depois do grupo por purpose; cada entidade continua passando pela checagem
  própria, e falha sem Domain continua negada.
- Unit cobre duas entidades em `forum` com uma resolução do Domain e exatamente
  dois ciclos de reset. Forum Kernel 4/199, Wiki Kernel 2/32; suítes completas
  Unit 50/250 e Kernel MariaDB 103/1.526 passaram.
- Três medições por processo reduziram p50/p95 em 100 candidatos para membro de
  703/1.010 ms a 386/567 ms e visitante de 358/502 ms a 182/293 ms. Evidências
  e amostras em `docs/portal/benchmarks/ACULTA-CONTENT-INDEX-FORUM-ACCESS-2026-10-23.md`.

## 0.1.0-dev.156 — repetir Unit após migração dos hooks

- Suíte Unit inteira passou: 50 testes / 241 assertions; 14 depreciações
  PHPUnit, sem falhas.
- Kernel completo permanece em 103 testes / 1.526 assertions; Drupal 11+
  gate passou com 1.159 checks.

## 0.1.0-dev.155 — validar execução do hook cron OOP

- Kernel agora chama `cron` pelo dispatcher do Drupal e verifica que o hook
  OOP remove cursors expirados: 2 testes / 28 assertions.
- Suíte Kernel completa MariaDB passou com 103 testes / 1.526 assertions;
  39 depreciações Core/contrib e 54 PHPUnit, sem falhas.

## 0.1.0-dev.154 — validar consulta paginada em MariaDB

- `ContentIndexQueryKernelTest` passou em MariaDB: 4 testes / 52 assertions,
  cobrindo cursor opaco, paginação por ID imutável, revalidação de Entity
  Access, cache de candidatos e filtros allowlisted.
- A repetição não produziu deprecações nem falhas.

## 0.1.0-dev.153 — migrar hooks runtime para OOP com DI

- Removidos hooks procedurais de CRUD e cron de `aculta_index.module`.
- Hooks de entidade e manutenção agora são descobertos pelo Core por atributos
  `#[Hook]` e recebem registry, fila e serviços de manutenção por DI.
- Kernel de traduções passou (1 teste / 20 assertions); suíte Kernel completa
  passou em MariaDB (102 / 1.518). PHPCS Drupal e lint passaram.

## 0.1.0-dev.152 — executar suíte Kernel completa em MariaDB

- Suíte Kernel inteira do `aculta_index` passou em MariaDB: 102 testes / 1.518
  assertions, sem falhas. Drupal/Core/contrib reportaram 39 depreciações e
  PHPUnit reportou 54 depreciações.
- A suíte inclui integração dos providers, acesso, rebuild, auditoria, schema,
  filas, relações e bridge. Isso não valida boot/DI completo de todos os owners
  nem o Runtime.

## 0.1.0-dev.151 — consolidar o resumo de status

- README inicial agora resume a implementação, integração, benchmark 1M e
  pendências atuais sem repetir métricas históricas que foram superadas.
- Nenhum comportamento de runtime foi alterado.

## 0.1.0-dev.150 — concluir benchmark de manutenção 1M

- Com a descoberta `base_table`, rebuild/auditoria 1M concluíram em MariaDB:
  setup 129,094 s; rebuild 2.137,817 s (468 projeções/s); auditoria 724,020 s;
  pico 214,5 MiB; zero fontes/entradas ausentes e zero projeções divergentes.
- As 2.001 páginas do enumerador usaram `base_table`; ID query p50/p95
  1,518/2,154 ms. Auditoria percorreu 2.000 batches de entradas, 1 de relações
  e 2.001 de fontes. Benchmark passou (1 / 16 assertions), sem SLO e com uma
  única execução sintética.
- O gate de escala 1M agora tem resultado válido; Runtime, volumes reais,
  cargas com relações e homologação completa continuam pendentes.

## 0.1.0-dev.149 — usar keyset pela tabela base quando seguro

- A descoberta de IDs consulta a tabela base do storage quando o mapeamento
  confirma que ID e bundle residem nela; outros storages continuam com
  EntityQuery. Carga e projeção permanecem na Entity API.
- A tentativa de 1M com EntityQuery foi encerrada após 33 lotes/541 s; o plano
  estimava ~994k linhas por página devido a join, group e filesort. Sem JSON
  final, não é um resultado do benchmark. As 17 tabelas do prefixo temporário
  foram removidas do banco isolado após o SIGINT.
- Com a nova descoberta, 10k passou (1 / 16): rebuild 17,781 s, auditoria
  6,858 s, zero divergências; 21/21 lotes usaram `base_table`, ID query p95
  0,781 ms. Em 100k passou (1 / 16): rebuild 181,043 s, auditoria 68,983 s,
  pico 137,5 MiB e zero divergências; 201/201 lotes usaram `base_table`, ID
  query p50/p95 1,103/1,557 ms. Amostras únicas, sem SLO.
- Unit enumerador passou (7 / 59); Rebuilder Kernel MariaDB passou
  (21 / 295). PHPCS Drupal e lint passaram. Tentativa de Kernel SQLite não
  iniciou: este repositório proíbe SQLite para testes Drupal e exige
  `SIMPLETEST_DB` MariaDB.

## 0.1.0-dev.148 — repetir gates Unit e Kernel após otimização de auditoria

- Unit completo passou (50 testes / 241 assertions; 14 depreciações PHPUnit).
- `ContentIndexSchemaKernelTest` passou no MariaDB (53 / 570; 1 depreciação
  Drupal Core e 54 PHPUnit). `ContentIndexRebuilderKernelTest` passou (21 /
  295), incluindo a regressão de limpeza de cache adicionada em dev.146.
- Nenhuma falha. O aviso de diretório browser_output não gravável é do setup
  PHPUnit e não afeta estas suítes de Kernel.

## 0.1.0-dev.147 — validar reconciliação 100k após limpeza de cache

- Repetição MariaDB em 100k com limite operacional 5.000 e a limpeza de cache
  da auditoria: rebuild 204,842 s; auditoria 92,913 s; pico 137,5 MiB; zero
  divergências. A fase source→catalog levou 63,178 s (p95 645 ms; máximo
  885 ms). Uma execução diagnóstica, sem SLO.
- Comparação com a execução 100k anterior de limite 500: auditoria 471,662 s,
  fase source→catalog 437,109 s, limpeza de cache do enumerador 379,890 s e
  pico 492 MiB. São execuções únicas e houve mudança de código; a comparação
  mostra que 500 é uma escolha ruim neste harness, não isola causalidade.
- Benchmark, suíte Kernel Rebuilder (21 / 295), PHPCS, lint e índice de status
  do projeto passaram. O padrão operacional permanece 5.000.

## 0.1.0-dev.146 — limitar cache de entidades na reconciliação

- A auditoria de entradas agora descarta em lotes limitados as entidades fonte
  e alvos de relações carregados para comparação. Projeções que falham limpam
  os caches de entidades de conteúdo e descartam a fila pendente.
- Kernel de rebuild/auditoria passou em MariaDB (21 testes / 295 assertions);
  o novo teste confirma que o cache de fonte é liberado na transição de fase.
  PHPCS e lint passaram.
- A rodada 100k cache 500 concluída antes desta alteração levou 471,662 s na
  auditoria (437,109 s na fase source→catalog), pico 492 MiB e zero divergências.
  Ela é baseline, não valida a correção recém-introduzida. Após a mudança, o
  benchmark 10k passou (1 / 16): auditoria 6,352 s, pico 128,5 MiB e zero
  divergências. Uma execução não define SLO nem comprova comportamento em 100k.

## 0.1.0-dev.145 — tornar métricas detalhadas opt-in

- Diagnósticos do checker e do enumerador agora exigem
  `$settings['aculta_index_diagnostics'] = TRUE`; ficam desligados por padrão
  para evitar uma mensagem `debug` por lote no dblog/watchdog.
- O benchmark e testes habilitam a opção explicitamente. A medição do limite
  de cache segue restrita ao harness; o default operacional permanece 5.000.
- Unit comprova que os logs ficam desligados sem a configuração.
- Kernel Schema MariaDB passou (53 testes / 570 assertions; 1 depreciação Core,
  54 PHPUnit); Unit do enumerador passou (7 / 59; 3 depreciações PHPUnit);
  benchmark opt-in 10k passou (1 / 16). PHPCS e lint passaram.

## 0.1.0-dev.144 — medir etapas e limite de cache do enumerador

- O enumerador registra timings `debug`, quando habilitado, para Entity Query, `loadMultiple`,
  projeção de traduções e limpeza de cache, sem registrar IDs. A configuração
  de produção continua com o limite de 5.000 identidades.
- O harness permite variar o limite por `ACULTA_INDEX_MAINTENANCE_CACHE_RESET_LIMIT`.
  Em três execuções 10k por limite, 500 reduziu a mediana da auditoria de 9,06
  para 8,63 s e o máximo de lote de 4.351 para 711 ms; o p95 de lote subiu de
  549 para 706 ms. O pico de memória foi 152,5 vs 150,5 MiB. Cenários
  diagnósticos, sem SLO; a escolha do limite operacional segue pendente.
- Unit do enumerador passou (7 testes / 58 assertions; 3 depreciações PHPUnit);
  benchmark 10k passou
  em seis execuções (1 / 16 cada); PHPCS e lint passaram.
- Após a injeção do logger de enumerador no container, Kernel Schema MariaDB
  passou (53 testes / 570 assertions; uma depreciação Core e 54 PHPUnit).

## 0.1.0-dev.143 — instrumentar lotes da auditoria

- Cada lote concluído emite, quando os diagnósticos estão habilitados, uma
  mensagem `debug` com fase, quantidade lida e
  projetada, tempos de leitura/projeção, reconciliação, checkpoint, fila e
  duração total. Não registra IDs nem metadados de conteúdo.
- O harness de manutenção captura esses logs em `audit_stage_metrics` no JSON,
  com totais e p50/p95/máximo para cada etapa e fase. A amostra 10k levou 9,15 s
  na auditoria; 6,75 s ficaram no scan fonte→catálogo e 0,21 s na comparação
  contra o catálogo. Uma execução, sem SLO.
- O detalhamento operacional permite separar custo do enumerador/provider do
  custo de comparação do catálogo em futuras auditorias. Não altera o estado
  persistido da auditoria nem seu contrato de consulta.
- O benchmark opt-in passou em 10k (1 teste / 15 assertions); PHPCS Drupal
  passou; Kernel Schema MariaDB passou (53 testes / 570
  assertions; uma depreciação Core e 54 depreciações PHPUnit). O teste focal
  verifica os campos do log e a ausência de identidade/título.

## 0.1.0-dev.142 — medir auditoria otimizada em 100k

- Repetição MariaDB da auditoria incremental com 100.000 fontes, após o
  contrato rápido de elegibilidade: rebuild 209,542 s; auditoria 724,666 s;
  zero divergências. A passagem source→catalog consumiu 689,766 s, com p95 de
  lote 28,31 s e máximo de 72,71 s; pico da auditoria 558 MiB.
- PHPUnit do benchmark passou (1 teste / 14 assertions). É uma medição
  diagnóstica isolada, sem SLO; não extrapolar para 1M. Análise/otimização da
  passagem source→catalog precede nova rodada de grande escala.
- Relatório detalhado e plano atualizados em `docs/portal/benchmarks/` e
  `docs/portal/ACULTA-CONTENT-INDEX-BENCHMARK.md`.

## 0.1.0-dev.141 — adicionar filtro interno de owner UID

- `owner_uid` opcional foi adicionado ao projection, schema do catálogo e
  `update_10018`; providers incrementam projection version para reconstrução.
- Activity, Forum, Revista, Wiki, Gallery, Media e LMS projetam apenas o owner
  declarado por `EntityOwnerInterface`; ownership ausente fica NULL. Não há
  índice SQL novo até medição específica.
- Query aceita qualquer UID unsigned 32-bit como filtro, mantém Entity Access
  como autorização final e não acrescenta o UID ao DTO de resultado.
- Benchmark harness grava snapshots atômicos `.progress.json` com fase, lote,
  tempo e contadores; o smoke test 10k concluiu.
- Query/Schema Kernel MariaDB passou (56 testes / 603 assertions); providers e
  Index Unit passou (320 / 1.667; 42 depreciações PHPUnit).

## 0.1.0-dev.140 — reduzir custo da reconciliação de entradas

- Novo contrato opcional `ContentIndexIndexabilityProviderInterface` deixa
  adapters de owner responderem rapidamente se uma entidade continua
  indexável, sem construir relações/metadados completos no percurso catálogo→
  fonte. Hashes completos continuam conferidos no percurso fonte→catálogo.
- Activity, Fórum, Revista, Wiki, Gallery, Media e LMS implementam o contrato;
  providers sem ele mantêm a verificação anterior.
- Kernel de rebuild/auditoria passou em MariaDB (20 testes / 285 assertions),
  incluindo fonte que deixa de ser indexável. Unit dos módulos/providers e
  Index passou (312 / 1.656; 40 depreciações PHPUnit).
- Em 10k, uma execução com lote de auditoria 500 levou 9,18 s (42 batches,
  pico 152,5 MiB), contra 10,10 s/42 batches antes do caminho rápido (153,6
  MiB). Resultado diagnóstico; uma repetição por cenário não define SLO. Ver
  relatório de benchmark.

## 0.1.0-dev.139 — validar concorrência PHP e semear fixture em lote

- O worker subprocesso registra o autoload do driver MySQL Core e dos
  namespaces `aculta_index`/`aculta_portal`; o teste de estresse passou em
  MariaDB (3 testes / 56 assertions).
- Kernel de schema passou em MariaDB (51 testes / 544 assertions); uma
  depreciação Core e 52 depreciações PHPUnit foram reportadas.
- A rodada corrigida de 1M foi interrompida após ~58 minutos por lentidão de
  setup; não produziu métricas válidas. Evidência parcial estimada está em
  `/tmp/aculta-index-maintenance-1000000-interrupted-2026-10-23.json`.
- A fixture do benchmark agora cria uma entidade-semente pela Entity API e
  semeia as demais linhas usando o `TableMapping` real do Node em lotes SQL de
  1.000. Entity API continua usada para validar carga de amostras; enumeração,
  projeção, rebuild e auditoria permanecem inalterados. A validação 10k passou
  em MariaDB: setup 1,414 s, rebuild 18,564 s, auditoria 10,829 s e zero
  divergências. É uma única execução diagnóstica, sem SLO.
- Uma repetição 100k com essa fixture foi interrompida após aproximadamente
  15 minutos, sem JSON final. O setup não é o gargalo; analisar a auditoria
  bidirecional antes de ampliar a escala.

## Decisões 0.1 registradas — implementação pendente

- Relações removidas aceitam consistência eventual da fila; a aresta pode
  permanecer até a projeção convergir, enquanto Entity Access da origem e dos
  alvos é revalidado a cada consulta.
- `owner_uid` opcional foi autorizado como metadado interno para filtros como
  “Minha produção”; o filtro poderá receber qualquer UID, sempre com Entity
  Access revalidado, e não retornará o UID nos DTOs. Coluna, migração, filtro e
  projeção seletiva pelos providers ainda precisam ser implementados.

## 0.1.0-dev.138 — proteger hooks iniciais de atualização parcial

- `update_10001`, `10002`, `10004` e `10008` agora ignoram tabelas legadas
  ausentes; teste Kernel cobre a atualização interrompida sem recriá-las.
- PHP lint e PHPCS Drupal passaram. Kernel aguarda o fim do benchmark 1M.

## 0.1.0-dev.137 — tornar update do índice tolerante à tabela ausente

- `update_10007` agora retorna sem erro quando `aculta_index_entry` não existe;
  teste Kernel cobre a instalação parcial sem recriar a tabela.
- PHP lint e PHPCS Drupal passaram. Kernel MariaDB segue pendente durante o
  benchmark de 1M.

## 0.1.0-dev.136 — cobrir hooks históricos do schema de fila e cursor

- Kernel tests exercitam `update_10003`, `10005`, `10006` e `10007`: criação
  idempotente de tabelas, preservação de cursor existente e criação do índice.
- PHP lint e PHPCS Drupal passaram; execução Kernel aguarda o benchmark 1M.

## 0.1.0-dev.135 — alinhar estado do plano de benchmark 1M

- O plano agora distingue a rodada antiga interrompida da repetição corrigida
  que está ativa; não atribui resultado à execução ainda incompleta.

## 0.1.0-dev.134 — testar recuperação de falha ao criar item Core Queue

- Teste Kernel comprova que falha de `createItem()` mantém a linha durável em
  `queued`, e que `recover()` recria o item após o prazo e permite claim.
- PHP lint e PHPCS Drupal passaram. A execução Kernel MariaDB segue pendente
  enquanto o benchmark de 1M usa o banco dedicado.

## 0.1.0-dev.133 — restringir teste de processos a MySQL/MariaDB

- O estresse entre processos exige `innodb_lock_wait_timeout`; em outros drivers
  Kernel o teste é explicitamente pulado, em vez de falhar ao abrir a conexão.
- PHP lint, PHPCS Drupal e o validador de status passaram. Execução MariaDB
  permanece pendente até o benchmark de 1M liberar o banco.

## 0.1.0-dev.132 — tratar status de subprocesso ausente explicitamente

- O harness só prefere o código de saída observado quando é inteiro não-negativo;
  status ausente ou `-1` usa o resultado de `proc_close()`.
- PHP lint e PHPCS Drupal passaram; o Kernel MariaDB aguarda o benchmark de 1M.

## 0.1.0-dev.131 — limitar e drenar subprocessos do teste concorrente

- O harness coleta stdout/stderr simultaneamente e impõe timeout global de 120 s;
  travamentos encerram subprocessos e removem arquivos temporários no `finally`.
- PHP lint e PHPCS Drupal passaram. Execução Kernel MariaDB aguarda o benchmark
  de 1M no banco dedicado.

## 0.1.0-dev.130 — separar descoberta Drush de execução operacional

- A verificação temporária autorizada no Runtime `toca` confirmou a descoberta
  live de `audit`, `queue` e `rebuild`; a documentação agora diferencia essa
  prova de listagem da execução operacional dos comandos contra um catálogo.
- O módulo foi desinstalado após o smoke check e permanece desativado no Runtime.

## 0.1.0-dev.129 — garantir limpeza dos arquivos do harness concorrente

- A limpeza dos arquivos temporários agora cobre falhas parciais ao alocar ou
  gravar opções do banco de teste; credenciais de conexão permanecem em arquivo
  temporário com modo `0600` e são removidas em `finally`.
- PHP lint e PHPCS Drupal passaram. O Kernel MariaDB segue aguardando o benchmark
  de 1M no banco dedicado.

## 0.1.0-dev.128 — adicionar estresse concorrente com processos PHP

- Kernel MariaDB cria quatro produtores PHP com 25 eventos cada para a mesma
  identidade, exige sequência sem perda/duplicação e disputa um claim entre
  quatro consumidores independentes; uma conclusão antiga não pode encerrar o
  evento mais recente.
- PHP lint e PHPCS Drupal passaram. A execução Kernel aguarda o benchmark 1M
  corrigido terminar, para não competir pelo banco `aculta_simpletest`.

## 0.1.0-dev.127 — repetir benchmark 1M com harness corrigido

- A execução antiga foi interrompida após a decisão do responsável, com cerca de
  220.500/1.000.000 entradas e 1.000.000 jobs pendentes; o resultado parcial é
  diagnóstico e não será usado como medição.
- Nova execução iniciada em 2026-10-23 às 08:56 BRT no `aculta_simpletest`, com
  supressão de jobs durante o setup e métricas separadas por fase. Resultado
  ainda pendente.

## 0.1.0-dev.126 — validar comandos Drush no Runtime de teste

- No Homelab `toca` (Drupal 11.4.10, Drush 13.8.0), `drush list --raw`
  exibiu audit, queue e rebuild após ativação temporária autorizada.
- Antes da desinstalação, entradas, relações e jobs eram zero e não havia geração
  ativa. `pm:uninstall` restaurou o módulo desabilitado e removeu todas as tabelas
  `aculta_index_*`; não houve rebuild nem alteração de conteúdo original.
- `system.site` mantém `langcode` e `default_langcode` em `pt-br`, iguais ao Sync.
  Não foi importada configuração; `config:status` ainda reporta diferenças em
  `core.extension` e `system.site`, sem baseline anterior para atribuí-las.

## 0.1.0-dev.125 — verificar descoberta dos comandos Drush

- Teste Unit usa a estratégia instalada no Drush 13.8 para descobrir os três
  comandos em `src/Drush/Commands` e confirma os atributos Symfony `AsCommand`.
- PASS: 1 teste / 8 assertions, PHP lint e PHPCS Drupal. O módulo permanece
  desabilitado no Runtime; sua listagem live continua pendente de instalação.

## 0.1.0-dev.124 — documentar retenção única de rollback

- README esclarece que só a geração imediatamente anterior fica `retained` e
  sincronizada; gerações mais antigas mudam para `discarding` na ativação e
  saem dos destinos incrementais antes do expurgo em lotes limitados.
- Comportamento já coberto pelo Kernel de rebuild e limpeza; nenhuma mudança de
  runtime nesta versão.

## 0.1.0-dev.123 — alinhar política de limpeza de providers desativados

- README agora registra a decisão: preservar projeções enquanto o provider owner
  estiver desativado ou sem ownership verificável; após reativação, a auditoria
  valida a ausência da fonte e só então enfileira a exclusão da projeção derivada.
- Nenhuma mudança de runtime; cenário validado no Kernel em dev.122.

## 0.1.0-dev.122 — limpar projeção órfã após reativar o owner

- Kernel simula provider Image desabilitado: a auditoria marca a fonte como
  não verificável, preserva a projeção e não agenda trabalho destrutivo.
  Reativado o registry, a auditoria confirma a fonte ausente, agenda `delete` e
  o worker remove somente a linha derivada.
- `ContentIndexSchemaKernelTest`: 44 testes / 483 assertions PASS em SQLite;
  o cenário focal passou com 1/15. PHPCS Drupal e `php -l` PASS. A classe
  registra uma depreciação Core e 45 avisos PHPUnit. Suíte Kernel completa:
  89 testes / 1.342 assertions PASS em SQLite, com 39 depreciações
  Core/contrib, 45 depreciações PHPUnit e 2 skips.

## 0.1.0-dev.121 — manter estável o cursor de relações

- A regressão de relações agora insere um novo alvo de maior ID depois da
  primeira página e confirma que o cursor ativo não o introduz na travessia.
  O mesmo caso verifica fallback `und` para o idioma padrão e deduplicação de
  arestas `und`/idioma explícito.
- `ContentIndexRebuilderKernelTest`: 19 testes / 274 assertions PASS em SQLite;
  o teste focal passou com 1/22. PHP lint e PHPCS Drupal PASS. A repetição
  MariaDB aguarda o benchmark de 1M ainda ativo.

## 0.1.0-dev.120 — corrigir providers do harness de acesso do Fórum

- O harness carrega as declarações reais de `aculta_forum.content_index_provider`
  e `aculta_forum.attachment_content_index_provider` do YAML owner. Antes, o
  provider de tópicos/posts não estava registrado e o filtro os negava
  corretamente por falta de provider ativo.
- Kernel completo em SQLite: 88 testes / 1.324 assertions PASS, 39 depreciações
  Core/contrib, 44 depreciações PHPUnit e 2 skips. Teste focal de Fórum após o
  ajuste: 2 testes / 109 assertions PASS; classe completa de acesso do Fórum:
  4 testes / 199 assertions PASS, com 38 depreciações upstream. Boot/DI owner
  completo continua pendente.

## 0.1.0-dev.119 — registrar escopo da evidência Kernel LMS

- Decisão: manter harness isolado para a relação `course_document`, que cobre
  projeção, rebuild e consulta outgoing com revalidação de acesso.
- Boot/DI completo do owner continua pendente por referências ausentes a serviços
  de módulos não relacionados ao fluxo (`aculta_user` e `commerce_cart`); fica
  como dívida de homologação, sem ampliar esta microfase.

## 0.1.0-dev.118 — corrigir mapa dos providers no Unit

- O service-map Unit agora declara o `GroupRelationTypeManagerInterface`
  sintético exigido pelo provider LMS e cobre a compilação quando o plugin PDF
  opcional não está instalado; a versão de projeção esperada é 1.
- Unit do Index: 39 testes / 204 assertions PASS, com 10 depreciações PHPUnit.

## 0.1.0-dev.117 — ocultar imagem não publicada em relação de Gallery

- A Gallery da fixture contém uma imagem publicada e outra não publicada. A
  projeção conserva ambas as relações; a consulta outgoing retorna só a imagem
  autorizada pela Entity Access da Media.
- MariaDB dedicado: `ContentIndexGalleryProviderKernelTest`, 2 testes / 51
  assertions PASS, com 9 depreciações Core/contrib. Não há validação de grants
  próprios dos módulos owner ou de arquivos protegidos no Runtime.

## 0.1.0-dev.116 — consultar relações Gallery→image com acesso revalidado

- O Kernel Gallery consulta `gallery_image` pela API outgoing após o rebuild,
  com provider Gallery e provider `media:image` carregados pelos YAMLs owner.
- A conta autenticada precisa das permissões de visualização da Gallery e da
  Media; a consulta inicia no Domain principal, autoriza a Gallery no purpose
  `magazine` e restaura o contexto. A imagem segue a Entity Access própria.
- MariaDB dedicado: `ContentIndexGalleryProviderKernelTest`, 2 testes / 48
  assertions PASS, com 9 depreciações Core/contrib. O owner completo e grants
  específicos por Gallery/Media não foram habilitados neste harness.

## 0.1.0-dev.115 — consultar relações Revista→Activity com acesso por Domain

- O Kernel de acesso compila os providers Magazine e Activity pelas declarações
  YAML reais, reconstrói uma geração e consulta `related_activity` pela API
  outgoing do Index.
- A consulta autoriza a publicação no purpose `magazine`, a atividade no
  purpose `main`, retorna a atividade publicada e restaura o Domain inicial.
- MariaDB dedicado: `ContentIndexMagazineAccessKernelTest`, 2 testes / 31
  assertions PASS, com 5 depreciações upstream. O harness não habilita os
  módulos owner completos nem valida grants específicos fora do Entity Access.

## 0.1.0-dev.114 — contenção entre conexões MariaDB

- Kernel usa duas sessões físicas com o mesmo prefixo isolado de teste e um
  timeout InnoDB de um segundo apenas na sessão concorrente. O agendamento
  espera a promoção; leitores mantêm o ponteiro antigo até o commit; a mudança
  recém-agendada bloqueia rollback até concluir a sequência.
- Dois gerenciadores de limpeza disputam a exclusão final de uma geração. O
  segundo espera o commit do primeiro e depois não encontra trabalho residual.
- MariaDB: 2 testes / 23 assertions PASS; PHPCS Drupal, lint e diff check PASS.
  Trata-se de contenção controlada entre conexões, sem teste de carga ou workers
  PHP concorrentes. Runtime permanece sem alteração.

## 0.1.0-dev.113 — cobrir providers desativados e relação LMS→documento

- Kernel de consulta confirma que projeção residual de provider sem registro
  nunca vira candidata, mesmo com fonte pública ainda existente (1 teste / 11
  assertions em MariaDB).
- O Kernel LMS percorre rebuild, relação `course_document` e consulta outgoing:
  estudante membro encontra o documento unpublished do curso e visitante
  externo não o descobre. Providers são registrados com suas declarações/tag
  owner e o plugin Group é fixture isolada (4 testes / 210 assertions na classe
  em MariaDB; 53 depreciações upstream).
- O harness do provider LMS agora inclui os dois argumentos declarados no YAML
  (`domain_purpose` e `group_relation_type.manager`).

## 0.1.0-dev.112 — serializar cleanup concorrente

- A seleção da geração `discarding` agora ocorre sob `FOR UPDATE` na transação
  que envolve a remoção do lote. Dois cron workers não podem escolher a mesma
  geração e competir pela exclusão de seu último lote.
- Kernel MariaDB Schema: 1 teste / 16 assertions PASS; Kernel Rebuilder/Drush
  `cleanup`: 1 teste / 12 assertions PASS. PHPCS Drupal e `php -l` PASS.


## 0.1.0-dev.111 — limitar rollback à geração anterior

- A ativação conserva somente a geração que estava ativa imediatamente antes;
  gerações retidas mais antigas passam a `discarding` dentro da transação do
  ponteiro e deixam de receber eventos incrementais.
- Cron e `drush aculta-index:rebuild cleanup --limit=500` removem relações,
  entradas e cursors em lotes limitados. O cleanup não altera entidades fonte.
- Kernel Schema: 43 testes / 468 assertions passaram em SQLite. O teste focal
  da transição e expurgo passou; a saída da suíte Rebuilder/PHPCS/lint não foi
  recuperável após o processo do shell terminar.


## 0.1.0-dev.110 — sincronizar gerações retidas para rollback

- Workers incrementais agora atualizam todas as gerações `retained`, além das
  ativas e em rebuild; gravações e remoções obedecem a mesma sequência de
  origem e invalidam o cache da geração correspondente.
- A ativação de uma geração retida exige fingerprint atual dos providers e
  fila sem itens queued, processing, retry ou dead. Agendamento e retry de
  trabalho compartilham o fence da linha de estado enquanto há geração retida,
  evitando uma nova mudança entre drenagem e troca do ponteiro.
- Kernel MariaDB: cenário de atualização, bloqueio de rollback enquanto há
  trabalho pendente, rollback com projeção atual e exclusão nas duas gerações
  passou (1 teste / 23 assertions). Suíte `ContentIndexRebuilderKernelTest`
  completa: 18 testes / 259 assertions PASS em MariaDB. PHPCS Drupal e
  `php -l` passaram.
- A política de retenção foi escolhida; o custo cresce com o número de
  gerações retidas e deverá ser medido antes de definir expurgo/limite.

## 0.1.0-dev.109 — registrar provider Fórum pelo YAML owner

- O Kernel tópico/ForumPost carrega `aculta_forum.content_index_provider` da
  definição e tag reais de `aculta_forum.services.yml`; a declaração duplicada
  foi removida da fixture. O módulo fixture continua somente para o entity type
  de teste `ForumPost`.
- SQLite e MariaDB: 2 testes / 51 assertions passaram em cada banco.
- PHPCS Drupal e `php -l` passaram no harness alterado.

## 0.1.0-dev.108 — registrar providers Media pelos YAMLs owner

- O Kernel Media agora compila `image` e `document` pelas definições e tags dos
  serviços reais de `aculta_image` e `aculta_document_viewer`; as tags duplicadas
  em fixture foram removidas. Providers PDF Group e Forum continuam lidos dos
  respectivos YAMLs owners.
- SQLite e MariaDB: 4 testes / 76 assertions passaram em cada banco.
- PHPCS Drupal e `php -l` passaram no harness alterado.

## 0.1.0-dev.107 — registrar providers Gallery e Image pelos YAMLs owner

- O Kernel Gallery agora carrega as definições/tag reais de
  `aculta_gallery.services.yml` e `aculta_image.services.yml`, sem duplicar as
  declarações em uma fixture de provider.
- SQLite e MariaDB: 2 testes / 44 assertions passaram em cada banco. Nove
  depreciações vêm de Media Gallery, PhotoSwipe e Drupal contrib/Core.
- PHPCS Drupal e `php -l` passaram no harness alterado.

## 0.1.0-dev.106 — limitar janela de auditoria por provider/type

- A fase de fontes captura o maior ID indexável ao entrar em cada par
  provider/entity type; lotes respeitam esse teto, deixando novas entidades
  para o próximo ciclo incremental.
- Update hook 10017 adiciona o checkpoint e reinicia como `idle` uma auditoria
  que já estava rodando sem o novo teto.
- Auditoria: Kernel focal passou em SQLite dentro da rodada de 64 testes / 728
  assertions e em MariaDB com 1 teste / 13 assertions. Update hook em MariaDB:
  1 teste / 8 assertions; uma depreciação upstream.
- O teste de janela agora confirma que um bundle não pertencente com ID maior
  não altera o teto do provider. Repetição focal atual: SQLite e MariaDB, 1
  teste / 14 assertions em cada banco.
- PHPCS Drupal e `php -l` passaram nos arquivos alterados.

## 0.1.0-dev.105 — rejeitar relações com projeção de origem obsoleta

- A consulta outgoing não devolve alvos quando o registro da origem usa uma
  versão antiga do provider, mesmo que a entidade original permaneça visível.
- `ContentIndexRebuilderKernelTest`: 16 testes / 222 assertions passaram em
  MariaDB e SQLite. PHPCS Drupal e `php -l` do teste passaram.

## 0.1.0-dev.104 — identificar limitação do benchmark 1M em andamento

- Documentado que a execução 1M ativa começou antes do isolamento dos eventos
  na criação sintética e da instrumentação por fase da auditoria.
- Snapshot observado: checkpoint `node:49.000`, revisão 98; estimativas
  InnoDB: 995.136 Nodes, 994.044 jobs e 48.156 entradas. São dados parciais,
  sem valor como resultado de desempenho final.
- Não iniciar outra execução até a atual terminar; depois repetir com o harness
  corrigido antes de comparar 1M.

## 0.1.0-dev.103 — suíte Kernel completa com bridge por DI

- SQLite: 79 testes / 1.213 assertions passaram; 39 depreciações Core/contrib
  e 42 depreciações PHPUnit.
- Inclui a ponte Statistics composta com serviços selecionados do YAML owner e
  o bridge opcional do Index via DI. MariaDB completo segue aguardando o
  benchmark de 1 milhão.

## 0.1.0-dev.102 — compor a ponte Statistics pelo DI owner

- Kernel Statistics lê o YAML real do owner e registra os serviços de catálogo,
  storage e agregador; o Index resolve sua ponte com o agregador tipado pelo DI.
- SQLite: 1 teste / 22 assertions PASS, incluindo acesso granular, fallback
  sem Statistics, negação após despublicação e correlação autorizada de totais.
- O módulo Statistics completo não é ativado; sua cadeia Commerce e o Runtime
  permanecem fora do harness.

## 0.1.0-dev.101 — suíte Kernel completa após integração Wiki

- SQLite: 79 testes / 1.210 assertions passaram; 39 depreciações Core/contrib
  e 42 depreciações PHPUnit.
- Inclui a consulta segura da relação Wiki e todas as integrações existentes.
  MariaDB completo continua pendente enquanto o benchmark de 1 milhão está
  ativo.

## 0.1.0-dev.100 — registrar policy LMS já coberta pelo filtro

- `LmsCourseContentIndexAccessKernelTest` confirmado em SQLite: 1 teste / 33
  assertions, 47 depreciações upstream. Já cobre `ContentIndexAccessFilter`,
  Group Entity Access, policy de estudante/educador, negação de terceiro e
  troca/restauração do Domain.
- A lacuna LMS restante é consulta outgoing de relações e recursos do curso
  end-to-end; o boot/DI owner completo também continua pendente.

## 0.1.0-dev.99 — registrar provider Wiki pelo YAML owner

- O Kernel de acesso e relação Wiki lê e registra a declaração/tag diretamente
  de `aculta_wiki.services.yml`; a fixture não declara mais um provider paralelo.
- SQLite: 2 testes / 32 assertions passaram. A ponte de teste para o hook de
  acesso do Portal permanece isolada; a Wiki não é habilitada e o boot/DI
  completo continua pendente.

## 0.1.0-dev.98 — consultar relações Wiki com access real

- Kernel Wiki passa `related_entry` pelo `ContentIndexRelationQuery` e pelo
  filtro de produção: source e targets são revalidados no Domain `wiki` mesmo
  quando a consulta começa em `main`; somente o target publicado é exposto e o
  Domain original é restaurado. SQLite: 2 testes / 32 assertions, 5
  depreciações do Domain contrib.
- Categorias/imagens, ativação da Wiki, boot completo do owner e Runtime seguem
  fora desta microfase.

## 0.1.0-dev.97 — repetir Kernel completo após integrações

- Suíte Kernel completa do Index em SQLite: 78 testes / 1.194 assertions
  passaram; 39 depreciações Core/contrib e 42 depreciações PHPUnit.
- Inclui Fórum grants/Domain e o novo Kernel `course_document`; sem falhas.
- MariaDB completo continua pendente enquanto o benchmark de 1 milhão está
  ativo.

## 0.1.0-dev.96 — validar acesso do Fórum e documentos LMS

- Kernel SQLite do Fórum: 4 testes / 199 assertions passaram usando o
  `ContentIndexAccessFilter`, hooks reais de grants do Fórum, Group membership,
  revogação imediata e Domains. Foram registradas 38 depreciações Core/contrib.
- Kernel SQLite de `course_document`: 1 teste / 28 assertions passou. O teste
  usa o provider e a declaração de serviço do YAML real do PDF Viewer Group e
  uma relação Group fixture com `entity_access: TRUE`; estudante associado
  recebe acesso, terceiro não, revogação é observada e o Domain `courses` é
  aplicado/restaurado. O boot/DI completo do módulo owner permanece pendente.
- Nenhum módulo foi ativado no Runtime. MariaDB segue ocupado pelo benchmark
  de manutenção de 1 milhão de entidades.

## 0.1.0-dev.95 — atualizar evidência da suíte Kernel SQLite

- Suíte Kernel completa do Index: 77 testes / 1.166 assertions passaram em
  SQLite; 39 depreciações Core/contrib e 42 depreciações PHPUnit, sem falhas.
- Inclui `ContentIndexMagazineAccessKernelTest` (1/17). MariaDB continua
  pendente enquanto o benchmark de 1M usa o banco dedicado.

## 0.1.0-dev.94 — validar Entity Access da Revista no Index

- Kernel usa o provider Magazine declarado no YAML owner, Domains e Entity
  Access reais; publicação fica visível, rascunho permanece negado, o Domain
  inicial é restaurado e a ausência de `magazine` falha fechado.
- SQLite: 1 teste / 17 assertions passou, com 5 depreciações do módulo Domain.
  PHPCS passou; Kernel MariaDB aguarda o benchmark 1M.

## 0.1.0-dev.93 — medir cauda da auditoria por fase

- O benchmark agora separa duração, p50, p95 e máximo dos lotes de auditoria
  por fase (`entries`, `relations` e `sources`) para localizar caudas longas.
- Não muda a política de lotes ou a auditoria em produção. A nova medição ainda
  não foi executada; o processo MariaDB atual começou antes desta alteração.

## 0.1.0-dev.92 — registrar Kernel SQLite da fila

- `ContentIndexRebuilderKernelTest`: 16 testes / 220 assertions passaram em
  SQLite, incluindo retry recusado para provider owner desativado.
- A execução MariaDB e o benchmark de 1M seguem pendentes enquanto o processo
  anterior continua ativo.

## 0.1.0-dev.91 — cobrir retry de owner desativado

- Kernel de fila agora registra que um dead-letter não pode ser reenviado
  quando o provider proprietário não está registrado; o estado continua `dead`.
- PHP lint, PHPCS e validadores passaram nesta fase.

## 0.1.0-dev.90 — isolar a criação de fontes do benchmark

- Durante a criação de Nodes sintéticos, o benchmark agora usa um registry
  temporariamente vazio para evitar persistir um job incremental por fonte.
  O registry real é restaurado antes do rebuild e da auditoria.
- PHP lint e PHPCS passaram. O teste focal não foi repetido porque a execução
  MariaDB de 1M iniciada antes desta alteração ainda está ativa; os resultados
  dessa execução não incluem esta mudança.

## 0.1.0-dev.89 — vincular cursor aos filtros da consulta

- O Kernel da API paginada também tenta reutilizar o cursor com outro conjunto
  de filtros, espera rejeição e confirma que o token original continua válido
  para a consulta que o criou.
- Teste focal SQLite: 2 testes / 29 assertions; PHPCS passou.

## 0.1.0-dev.88 — atualizar evidência da suíte SQLite

- Suíte Kernel completa do Index passou em SQLite: 75 testes / 1.140
  assertions; Unit completa: 39 testes / 202 assertions.
- A execução Kernel reportou 39 depreciações de Core/contrib e 42 do PHPUnit;
  Unit reportou 10 depreciações PHPUnit. Nenhuma falha. A validação MariaDB
  continua pendente enquanto o benchmark de manutenção de 1M estiver ativo.

## 0.1.0-dev.87 — registrar limite de rollback retido

- Código e README esclarecem que gerações `retained` não recebem eventos
  incrementais e, portanto, não devem ser usadas como rollback seguro após
  mudanças na geração ativa. A escolha entre sincronizar uma ou todas as
  gerações retidas ou limitar o período de rollback aguarda decisão.
- Nenhuma semântica operacional foi alterada nesta etapa.

## 0.1.0-dev.86 — cobrir consulta paginada no Kernel

- `ContentIndexQueryKernelTest` exercita a API de consulta com catálogo e fonte
  reais: cursor opaco vinculado à conta, ordem keyset estável após inserção,
  revalidação de acesso depois de despublicação, invalidação do cache de
  candidatos, limite de candidatos, filtros permitidos e resposta sem geração
  ativa (2 testes / 29 assertions em SQLite temporário).
- A execução MariaDB fica pendente enquanto o benchmark de manutenção de 1M
  continua usando o banco dedicado.

## 0.1.0-dev.85 — consultar traduções autorizadas por idioma

- O Kernel também consulta as duas traduções pela API do Index, confirma os
  títulos atuais e verifica que a remoção mantém somente `en` (1 teste / 20
  assertions no MariaDB dedicado). A fixture concede `access content` à role
  anônima para exercitar Entity Access real do Node.

## 0.1.0-dev.84 — integrar provider forum_attachment no Kernel

- Kernel do Index carrega a declaração do YAML real de `aculta_forum`, confirma
  o provider `forum_attachment`, o purpose `forum` e o ciclo CRUD para Media
  unpublished com File privado. Arquivo de providers Media: 4 testes / 76
  assertions; não ativa o owner nem testa a policy de publicação do Fórum.

## 0.1.0-dev.83 — validar atualização e remoção de tradução

- Kernel confirma que a atualização do título `pt-br` mantém intacta a
  projeção `en` e que remover a tradução exclui somente sua própria entrada
  (1 teste / 17 assertions no MariaDB dedicado).

## 0.1.0-dev.82 — cobrir exclusão de tradução no catálogo

- Kernel cria Node traduzível, reconstrói as duas identidades de idioma e
  remove apenas a entrada da tradução excluída após o worker (1 teste / 13
  assertions no MariaDB dedicado).

## 0.1.0-dev.81 — integrar a declaração do provider course_document

- Kernel do Index carrega a definição e os argumentos do YAML real de
  `aculta_pdf_viewer_group`, confirma a tag no registry e cobre backfill,
  atualização e remoção de Media `course_document` unpublished com File privado
  (3 testes / 53 assertions
  no arquivo de providers Media).
- O harness não ativa o owner completo nem prova os grants de Group; o teste
  de referência do owner continua bloqueado pela compilação de serviços do
  Portal, conforme registrado na documentação do Index.

## 0.1.0-dev.80 — validar acesso Gallery no purpose canônico

- Kernel usa o Entity Access real de `media_gallery`: permissão do owner deixa
  a conta comum visualizar publicada e nega rascunho; falta do Domain canônico
  `magazine` falha fechado e restaura o Domain original (2 testes / 44
  assertions, junto do ciclo CRUD de dev.79).
- `aculta_gallery` completo e autorização Runtime continuam fora do harness.

## 0.1.0-dev.79 — cobrir provider Gallery com entidades reais

- Kernel ativa `media_gallery` e Media do repositório, registra os providers
  Gallery e image pela tag de fixture e cobre backfill, relação `gallery_image`,
  update e delete via worker (1 teste / 21 assertions).
- O teste não habilita o módulo owner completo `aculta_gallery`; o YAML owner
  continua validado separadamente.

## 0.1.0-dev.78 — fixar teto de candidatos nas consultas

- Kernel verifica que as consultas de conteúdo e de relações rejeitam limites
  fora da faixa 1–100 antes de consultar o catálogo (teste focal 1/11).
- O arquivo Kernel de rebuild e consulta passou completo: 15 testes / 211
  assertions no MariaDB dedicado.
- Não altera os resultados válidos nem a política de autorização.

## 0.1.0-dev.77 — testar preservação de projeções sem owner ativo

- O Kernel da auditoria incremental confirma que uma projeção cujo provider
  não está registrado permanece no catálogo e não recebe job de exclusão.
- Teste focal MariaDB: 1 teste / 20 assertions PASS; uma depreciação de Core
  e duas depreciações PHPUnit. A suíte Kernel completa não foi repetida após
  essa asserção; o resultado completo anterior, em dev.76, foi 65/883.

## 0.1.0-dev.76 — provar falha fechada sem Domain canônico do Fórum

- O Kernel do filtro de acesso cria tópico público no contexto do Domain
  principal, confirma acesso no purpose `forum` e então remove o Domain
  canônico; a consulta seguinte nega o tópico e restaura o contexto principal.
- A cobertura de Fórum agora soma 2 testes / 91 assertions no MariaDB,
  incluindo hooks de produção, Group grants e revogação imediata. A suíte
  Kernel completa passou: 65 testes / 883 assertions; 23 depreciações de
  Core/contrib e 42 depreciações PHPUnit.
- O harness ainda não inicializa a cadeia DI completa do módulo owner.

## 0.1.0-dev.75 — medir memória e cauda por fase de manutenção

- Benchmark agora reseta e reporta pico de memória para setup, rebuild e
  auditoria separadamente; também registra o maior lote além de p50/p95.
- MariaDB 11.8.6, Drupal 11.4.10, PHP 8.4.26: rodada 10k passou (1/13), com
  rebuild 8,289 s, pico 122,5 MiB; auditoria 9,914 s, pico 142,5 MiB e maior
  lote 4,110 s. A criação Entity API levou 64,917 s.
- Rodada pós-cache 100k passou (1/13): rebuild 113,511 s / 881 projeções/s;
  auditoria 723,219 s / 1.002 batches; nenhuma divergência; pico total 534,0
  MiB. A auditoria teve p50/p95 de 135,161/272,519 ms, mas o máximo por lote
  não foi registrado nessa versão do harness. Uma execução não permite atribuir
  causalidade à alteração do cache; 1M segue pendente.

## 0.1.0-dev.74 — limitar cache de entidades entre batches de manutenção

- O enumerador acumula identidades de fontes e alvos declarados nas relações
  até 5.000 por processo e então limpa somente os IDs/type storages usados;
  também limpa ao terminar o provider. Erro de projeção limpa todos os caches
  de entidades de conteúdo, pois os alvos carregados podem ser desconhecidos.
- Benchmark passa a registrar separadamente tempo e memória de criação da
  fixture. MariaDB, uma repetição em 10k com a estratégia final: setup 62,96 s,
  rebuild 8,480 s (1.179 projeções/s), auditoria 10,332 s / 102 batches,
  zero divergências e pico de 142,5 MiB. Os tempos variam; não definem SLO.
- Existe um baseline anterior de 100k sem a limpeza por batches: rebuild
  126,108 s / 793 projeções/s, auditoria 135,318 s / 1.002 batches, zero
  divergências e pico 532,5 MiB. A comparação com a rodada atual ainda não
  permite conclusão causal; a medição de 1M segue pendente.
- Testes: Unit 39/202; Kernel completo MariaDB 64/852; PHPCS Drupal e PHP lint.

## 0.1.0-dev.73 — parametrizar escala do benchmark de manutenção

- O harness de rebuild/auditoria aceita apenas 10k, 100k ou 1M fontes sintéticas
  no MariaDB de teste dedicado; 10k continua padrão. Corrigidos o limite dos
  batches de auditoria em suas duas passagens e o cálculo nearest-rank dos
  percentis.
- PHPCS Drupal e PHP lint passaram. Fumaça MariaDB em 10k: 1 teste / 13
  assertions; rebuild 7.818 s (1.279 projeções/s), auditoria 4.416 s, 102
  batches e nenhuma divergência. Setup das entidades fica fora desses tempos.
- Uma tentativa de 100k foi interrompida antes de completar a criação de fontes
  (98.701 inseridas, sem JSON); as 18 tabelas do prefixo PHPUnit órfão foram
  removidas. Resultados de 100k e 1M permanecem pendentes.

## 0.1.0-dev.72 — revalidar policy LMS no MariaDB de teste

- `LmsCourseContentIndexAccessKernelTest` passou no MariaDB local dedicado:
  1 teste / 33 assertions. O cenário usa curso Group e Domain persistidos e
  confirma estudante via `takeAccess()`, educador via permissão contextual e
  negação a terceiro; 47 depreciações reportadas por Core/contrib.
- Mantido o padrão escolhido para os providers: harness de teste com classe
  de produção e verificação separada do service YAML real; o boot completo do
  owner e de toda sua cadeia de dependências segue pendente.

## 0.1.0-dev.71 — validar bridge Statistics com conta não privilegiada

- Kernel do bridge reserva UID 1 e associa o papel de métricas com `addRole()`;
  verifica explicitamente a permissão granular antes de consultar Statistics.
  A fixture registra a permissão de mesmo machine name sem ativar o módulo
  Statistics completo e sua cadeia Commerce.
- A revisão revelou que a fixture anterior criava o leitor como UID 1, fazendo
  o Core contornar access e ocultar a ausência da permissão no role. Com a
  conta comum, o bridge retorna a página autorizada, agregados, fallback sem
  Statistics e nega conteúdo despublicado.
- Kernel focal MariaDB: 1 teste / 19 assertions PASS. Suíte Kernel completa do
  Index: 64 testes / 852 assertions PASS; 23 depreciações Drupal/contrib e 42
  depreciações PHPUnit.

## 0.1.0-dev.70 — integrar os hooks reais de grants do Fórum no Kernel

- Fixture Kernel registra os hooks `entity_access`, `node_grants` e
  `node_access_records` via `ModuleHandler`, encaminhando às classes de produção
  do Fórum. O módulo owner completo continua fora do escopo do harness.
- Conta UID 1 é reservada: o teste usa membro comum com papel Group explícito e
  permissão de visualizar conteúdo do tipo. Verifica grants gravados no
  `node_access`, autorização do tópico, ForumPost e Comment legado, negação de
  visitante e revogação de membership observada pela consulta seguinte do Index.
- Benchmark refeito em três processos no MariaDB local dedicado. As medições
  anteriores usavam UID 1 e não provavam o caminho comum de grants; foram
  substituídas. Com 100 candidatos, p50 foi 703 ms para membro e 358 ms para
  visitante; resultados completos e CSV bruto documentam custo e limites.
- Kernel focado: 1 teste / 60 assertions PASS em SQLite em memória e no MariaDB
  local dedicado; 22 depreciações upstream em cada execução.
- Suíte Kernel completa do Index: 64 testes / 850 assertions PASS em SQLite em
  memória, com 23 depreciações upstream e 42 avisos PHPUnit.

## 0.1.0-dev.69 — gravação transacional em lote

- `ContentIndexWriter::writeBatch()` grava até 500 projeções em uma transação,
  valida ownership antes da escrita, mantém proteção de sequência e reverte o
  lote inteiro em conflito. A revisão do cache de candidatos avança uma vez
  por lote alterado; a invalidação ocorre após o commit.
- `ContentIndexRebuilder` usa a escrita em lote depois de carregar e projetar
  cada página keyset. Escritas incrementais unitárias preservam a API
  `write()` e delegam para o mesmo caminho.
- Kernel confirma revisão única, replay idempotente e rollback integral diante
  de conflito dentro do lote. Benchmark 10k: mediana de 1.218 projeções/s
  (8.207 ms) contra 152 projeções/s (65.968 ms) antes do batch; auditoria
  manteve latência próxima. Três rodadas e limitações estão no relatório de
  manutenção.
- Kernel de grants/Domain do Fórum cobre tópico, ForumPost e Comment Core
  legado; hook de teste encaminha ao código de produção, grants Core são
  registrados e membership revogada é negada na leitura seguinte. A fixture
  restaura o Domain após cada checagem (60 assertions).
- Kernel verifica que o cursor guarda apenas SHA-256 do token e rejeita reuso
  com outra conta, geração ou consulta.
- Harness opt-in do Fórum mede Entity Access com Group membership/grants e
  Domain `forum` em páginas até 100 candidatos, para associado e visitante;
  três execuções e CSV bruto registrados, sem definir SLO.
- Suíte Kernel completa: 64 testes / 834 assertions PASS na execução atual com
  SQLite em memória; rodada MariaDB anterior: 63/809. Ambas registraram 23
  depreciações upstream; a execução atual registrou 42 avisos PHPUnit e a
  rodada MariaDB anterior, 41. Unit do Index: 39 testes / 192
  assertions PASS. PHPCS Drupal e PHP lint passaram.

## 0.1.0-dev.68 — isolar Entity Access ao trocar o Domain canônico

- `ContentIndexAccessFilter` limpa os caches locais de Entity Access para
  entidades de conteúdo antes e depois de cada autorização com `purpose`.
  O Core não inclui cache contexts na chave desse cache interno, então uma
  avaliação anterior em outro Domain podia causar falso bloqueio ou exposição,
  inclusive em verificações aninhadas (ForumPost → tópico).
- Kernel Wiki usa `EntityHooks` real do Portal, provider de produção e Domains
  persistidos; confirma negação direta fora de `wiki`, autorização pelo Index
  em `wiki`, revalidação após cache negativo prévio e restauração do Domain
  (1 teste / 16 assertions PASS).
- Suíte Kernel completa do Index: 62 testes / 797 assertions PASS; 23
  depreciações upstream e 40 avisos PHPUnit. PHPCS Drupal e PHP lint passaram.

## 0.1.0-dev.67 — validar grants e Domain reais do Fórum

- Kernel passa pelo `ContentIndexAccessFilter` com `ForumContentIndexProvider`,
  `RestrictedForumAccessHooks`, `ForumPostAccessHooks`, Group e Domains reais.
  Associado ativo e membro consulta tópico e resposta; usuário externo é
  negado; o purpose `forum` é aplicado e o Domain anterior restaurado; as
  funções reais `node_grants` e `node_access_records` são exercitadas sobre a
  membership e o tópico persistidos (1 teste / 34 assertions PASS).
- Fixture registra as classes de política de produção e o entity type real
  `AssociadoMarcador`; não habilita a cadeia completa do owner Fórum nem mede
  desempenho. Grupos, relacionamento, membership, node grants e Domains são
  entidades reais no banco Kernel.
- Suíte Kernel completa: 61 testes / 781 assertions PASS; 23 depreciações
  upstream e 40 avisos PHPUnit. PHPCS Drupal e PHP lint passaram.

## 0.1.0-dev.66 — cobrir providers Media de imagem e documento

- Harness Kernel usa Media/File reais para exercitar `media:image` e
  `media:document` via backfill, update de nome e delete pelo worker
  (2 testes / 32 assertions). A fixture registra a classe de produção; o mapa
  separado valida os YAMLs owner de `aculta_image` e `aculta_document_viewer`.
- O teste não inicializa esses módulos owner completos nem altera sua política
  de Entity Access; valida o ciclo do catálogo e a allowlist de metadados.
- Suíte Kernel completa do diretório `aculta_index`: 60 testes / 747 assertions
  PASS; 1 depreciação upstream e 40 depreciações PHPUnit.

## 0.1.0-dev.65 — exercitar provider `media:image` no ciclo do Index

- Kernel registra `MediaContentIndexProvider` com bundle `image` e usa Media e
  File reais para backfill, atualização do nome e exclusão via worker
  (1 teste / 15 assertions). A fixture não ativa `aculta_image`; o teste de
  mapa valida separadamente a configuração real do owner.
- O cenário revelou que timestamps Media podem vir como strings numéricas;
  casts explícitos corrigem o `TypeError` na projeção.
- Suíte Kernel completa do diretório `aculta_index`: 59 testes / 730 assertions
  PASS; 1 depreciação upstream e 40 depreciações PHPUnit.

## 0.1.0-dev.64 — falhar fechado sem Domain Wiki

- Unit confirma que a ausência de `wiki420_aculta_org` nega o candidato e não
  chama Entity Access no contexto ativo. Suíte focal: 9 testes / 80 assertions.
- PHPCS Drupal e PHP lint passaram; permanece pendente validar grants reais.

## 0.1.0-dev.63 — validar purpose canônico Wiki no filtro

- Unit exercita o `WikiContentIndexProvider` real com o
  `ContentIndexAccessFilter`: Entity Access só permite a entidade sob o Domain
  `wiki420_aculta_org`, preserva o usuário e restaura o Domain anterior.
- A suíte focal passou com 8 testes / 70 assertions. Não substitui teste de
  grants reais ou Kernel com Domain instalado.

## 0.1.0-dev.62 — cobrir provider Wiki no ciclo do Index

- Kernel usa `WikiContentIndexProvider` de produção via tag de fixture sem
  habilitar `aculta_wiki`; cobre backfill, `related_entry` deduplicada, update
  de revisão padrão e delete (1 teste / 17 assertions PASS).
- Discovery pelo YAML do owner, consulta integrada com Domain/grants reais,
  Runtime e categorias/imagem seguem pendentes.

## 0.1.0-dev.61 — cobrir ForumPost revisável e bundle fixo

- Kernel cobre ForumPost de produção sem ativar o módulo Forum: backfill,
  relações `forum_topic`/`forum_parent_post`, nova revisão padrão e exclusão
  (2 testes / 51 assertions PASS junto do caso Comment/topic).
- Enumerator aceita entity types sem bundle key apenas quando o provider declara
  exatamente o próprio entity type como bundle fixo. Unit: 5 testes / 31
  assertions PASS; outras declarações continuam falhando fechadas.
- Regressão de rebuild no MariaDB: 14 testes / 200 assertions PASS.

## 0.1.0-dev.60 — cobrir Comments legados do Fórum

- Kernel cobre tópico e Comment Core ligado: backfill, relação `forum_topic`,
  update, delete e exclusão da relação (1 teste / 21 assertions PASS).
- Comment marcado `field_aculta_rejected` não entra no catálogo. A projeção do
  timestamp usa inteiro, corrigindo o tipo retornado por `Comment::getCreatedTime()`.
- Fixture usa provider de produção com tag de teste; Fórum completo, ForumPost
  revisionável e grants/Domain reais continuam pendentes.

## 0.1.0-dev.59 — integrar tópico do Fórum ao ciclo do Index

- Kernel usa `ForumContentIndexProvider` de produção registrado por fixture,
  reconstrói tópico, processa update e delete pelo worker: 1 teste / 13
  assertions PASS em MariaDB. Purpose canônico `forum` é confirmado no provider.
- A cadeia completa do módulo owner e grants/Domain reais não são ativados neste
  harness; service-map e testes de política permanecem evidências separadas.

## 0.1.0-dev.58 — cobrir expiração e poda de cursores

- Kernel confirma que cursor expirado é rejeitado e removido ao ser apresentado.
- Kernel confirma poda em lotes limitados, preservando cursores válidos, e
  rejeição de limites vazios ou acima do máximo. Suíte de schema: 39 testes /
  416 assertions PASS; 1 depreciação de hook upstream e avisos PHPUnit existentes.

## 0.1.0-dev.57 — integrar Magazine no backfill e CRUD do Index

- Kernel registra a classe de produção `MagazineContentIndexProvider` via tag,
  reconstrói uma publicação, aplica update de título/relação e remove entrada e
  relação após delete pelo worker. 1 teste / 14 assertions PASS.
- A fixture isola a cadeia de dependências do módulo owner; o teste de mapa
  separado confirma o service YAML real. Purpose/domain e grants reais seguem
  pendentes de homologação.

## 0.1.0-dev.56 — preservar a página autorizada sem Statistics

- Kernel confirma no mesmo fluxo que a ausência do aggregator deixa a página
  autorizada e o cursor disponíveis, com `available=false` e sem totais.
- Regressão `ContentIndexStatisticsBridgeKernelTest`: 1 teste / 17 assertions
  PASS, incluindo correlação real, fallback e revogação de acesso da fonte.

## 0.1.0-dev.55 — correlacionar a página autorizada com Statistics em Kernel

- Kernel usa `ContentIndexQuery`/Entity Access reais e o aggregator/storage
  Statistics reais com provider de fixture `wiki_entry`.
- A página pública devolve seus totais; após a policy da fonte revogar acesso,
  a projeção candidata não devolve entidade nem total. 1 teste / 14 assertions
  PASS em MariaDB; grants reais dos módulos owner continuam pendentes.

## 0.1.0-dev.54 — validar o Kernel do Statistics

- Executa `DailyAggregateStorageKernelTest` no MariaDB dedicado de testes:
  15 testes / 27 assertions PASS. A evidência cobre Statistics isoladamente;
  bridge ponta a ponta Index→Statistics permanece pendente.

## 0.1.0-dev.53 — evidência atual da ponte Statistics

- Registra decisão: incompatibilidade de tipo/bundle rejeita a página integral,
  sem totais parciais ou remoção silenciosa de itens.
- Regressão Unit conjunta Index bridge + Statistics aggregator: 8 testes /
  47 assertions PASS; Kernel ponta a ponta e latência seguem pendentes.

## 0.1.0-dev.52 — mapa Unit dos providers ACULTA registrados

- Compila em um registry tagueado os service YAMLs dos nove módulos owner e
  valida os dez registros provider/type/bundle atuais, detectando tags ausentes,
  declarações divergentes e conflitos de ownership.
- Suíte Unit do Index: 35 testes / 155 assertions PASS, 10 deprecações PHPUnit.
- A ponte Statistics rejeita a página inteira quando algum item é incompatível
  com a métrica selecionada; não devolve totais parciais nem filtra itens.

## 0.1.0-dev.51 — regressão Unit do submódulo

- Suíte `aculta_index/tests/src/Unit` executada após os harnesses de Statistics
  e Fórum: 34 testes / 153 assertions PASS, 9 depreciações PHPUnit.

## 0.1.0-dev.50 — harness do filtro usa o provider real do Fórum

- Os testes de purpose canônico e Domain ausente agora registram o
  `ForumContentIndexProvider` de produção no registry e passam pelo
  `ContentIndexAccessFilter`; o acesso continua isolado com doubles de storage,
  negotiator e entidades.
- Unit focado: 7 testes / 60 assertions PASS. Group/grants reais e Runtime
  continuam pendentes, conforme o recorte de harness reduzido escolhido.

## 0.1.0-dev.49 — contrato de filtros da ponte Statistics

- Documenta que os filtros da página precisam selecionar o tipo/bundle
  compatível com a métrica; uma página incompatível é rejeitada pelo Statistics,
  sem descarte silencioso de entidades.
- Unit da ponte com o aggregator real confirma a rejeição antes de consultar o
  storage de métricas.

## 0.1.0-dev.48 — descartar rebuilds incompletos em lotes

- Adiciona o descarte explícito de gerações ainda em `building`, primeiro
  bloqueando novas escritas e removendo a geração do conjunto que recebe eventos.
- Remove entradas, relações e cursors derivados em lotes limitados; gerações
  ativa, validada ou retida para rollback são recusadas.
- Retry manual de dead-letter só re-enfileira itens cujo provider está registrado;
  owners desativados permanecem em dead para inspeção até reativação.
- Adiciona cobertura Kernel para o limite de descarte e preservação da geração
  ativa, e para retry recusado quando o provider owner não está registrado.

## 0.1.0-dev.47 — alinhar status de reconciliação e testes Kernel

- Corrige a documentação do schema: auditoria incremental/reconciliação está
  implementada e coberta por providers de fixture; grants reais, cobertura dos
  adapters, Runtime e volume continuam pendentes.
- Atualiza resultado Kernel combinado Index/Activity/User para 73 testes e 943
  assertions; registra separadamente a ausência de concorrência entre processos.

## 0.1.0-dev.46 — proteger retry diante de evento mais recente

- Kernel cobre um novo evento que reabre uma identidade dead, incrementa sua
  sequência e torna indisponível o retry da dead-letter anterior.
- O worker recebe e processa a operação atual enfileirada, sem reviver estado
  operacional obsoleto.

## 0.1.0-dev.45 — cobrir reparação de dead-letter via Drush

- Kernel valida listagem, limite e retry operacional de dead-letter pela
  `aculta-index:queue`; fluxo usa as cinco tentativas/recovery reais.
- Corrige a documentação do schema, que ainda dizia não haver interface de
  operação, embora os comandos já estivessem implementados.

## 0.1.0-dev.44 — contar queries da validação de acesso

- Benchmark de Media registra a contagem de consultas SQL em amostras com cache
  de candidatos miss/hit, aquecendo metadados de Drupal antes da coleta.
- Uma fumaça observou 8/6 queries; o resultado é exploratório e não estabelece
  SLO. Limites de latência/throughput aguardam telemetria de uso real.

## 0.1.0-dev.43 — medir segunda composição e Entity Access Media

- Compara duas composições de índice temporárias no benchmark, sem alterar o
  schema do módulo; a composição com `entity_type` e `bundle` não demonstrou
  ganho consistente.
- Acrescenta fixture de Media real com acesso permitido e negado para medir o
  caminho de Entity Access; grants de domínio/Group/LMS permanecem pendentes.
- Registra três runs por escala, resultados brutos e decisão provisória de
  manter o schema atual.

## 0.1.0-dev.42 — registrar resultado do comparativo de índice

- Registra três repetições por escala do índice temporário e do writer.
- Mantém o schema do módulo sem índice composto alternativo: não houve ganho
  consistente e o otimizador preferiu `PRIMARY` a partir de 100k entradas.
- Guarda CSV bruto e limita explicitamente o alcance da conclusão ao fixture.


## 0.1.0-dev.41 — comparação experimental de índice candidato

- Expande o harness opt-in com um índice composto criado somente no schema
  temporário do Kernel; coleta plano, leituras, espaço, tempo de criação e uma
  amostra de 250 gravações pelo writer com/sem o índice.
- Não altera o schema de produção. O estudo comparativo está em repetição antes
  de registrar uma decisão de desempenho.


## 0.1.0-dev.40 — harness opt-in de benchmark

- Adiciona Kernel Performance Test opt-in e restrito a `localhost/aculta_simpletest`.
- Mede inserção sintética, páginas keyset frias/aquecidas e leituras de relações
  para 10k/100k/1M entradas; fan-out usa amostra fixa de 10k fontes e até 200k
  edges. Resultados JSONL vão para o diretório temporário.
- Writer incremental, Entity Access real, rebuild e reconciliação permanecem
  fora deste harness e não têm resultado de desempenho ainda.

## 0.1.0-dev.39 — plano da matriz de benchmark

- Define catálogos sintéticos de 10k/100k/1M no MariaDB de teste isolado,
  workloads de consulta, cache, relações, escrita, reconciliação e rebuild.
- Especifica medições e evidências; não registra resultados nem declara
  desempenho aprovado. O plano evita criar um milhão de entidades Drupal.

## 0.1.0-dev.38 — sequência da fila e ownership na reconciliação

- Kernel cobre evento de exclusão recebido após claim de sequência antiga:
  conclusão/falha antigas não podem encerrar o novo trabalho enfileirado.
- Decisão: limpeza de projeções de provider desativado aguarda a reativação do
  módulo proprietário. O provider revalidado confirma existência/ownership; o
  Portal não recebe manifesto para excluir projeções sem essa autoridade.
- Decisão: Entity Access é revalidado em cada consulta; eventos extras de grants
  externos não são requisito de 0.1. A fila mantém responsabilidade por
  metadados e projeções, não pela segurança de leitura.
- Regressão combinada Kernel Index/Activity/User: 71 testes, 899 assertions;
  seis deprecações upstream observadas.

## 0.1.0-dev.37 — reconciliação bidirecional

- Auditoria acrescenta fase keyset por provider/entity type para enumerar fontes
  elegíveis sem entrada na geração ativa e refileirá-las pela fila idempotente.
- O cursor de origem é persistido sem aparecer na saída operacional; providers
  habilitados durante um ciclo após seu cursor serão alcançados no ciclo seguinte.
- Update hook 10016 adiciona cursores provider/type/entity e contador de entradas
  ausentes no catálogo; Kernel cobre migração e descoberta de fonte ausente.

## 0.1.0-dev.36 — reconciliação incremental de fontes

- A auditoria carrega, em grupos limitados, fontes da geração ativa e revalida o
  ownership do provider antes de qualquer reparação ou exclusão.
- Hash canônico divergente agenda reindexação; fonte ausente, idioma removido ou
  bundle fora do ownership atual agenda exclusão pela fila deduplicada. Providers,
  tipos e bundles sem ownership verificável são preservados e contabilizados.
- Novos contadores de fontes ausentes/não verificáveis têm update hook 10015.
- Kernel cobre source delete, source drift, ownership não verificável e migração.

## 0.1.0-dev.35 — reparação automática de projeções antigas

- Auditoria incremental reenfileira identidades da geração ativa quando a versão
  armazenada diverge da versão de um provider ainda registrado. A fila deduplica
  o reparo e o worker projeta novamente o estado atual da fonte.
- Reenfileiramento ocorre depois de persistir o checkpoint e só se a geração
  continuar ativa; entradas de providers desabilitados permanecem sem remoção
  automática até confirmação de ownership/configuração e validação da fonte.
- Kernel verifica a identidade e o estado do reparo criado pela auditoria.

## 0.1.0-dev.34 — revisão monotônica do cache de candidatos

- Cache de candidatos passa a usar revisão monotônica da geração, avançada na
  mesma transação das projeções. A consulta só grava resultados se a revisão
  permanecer igual ao longo da leitura, impedindo repovoamento obsoleto após
  invalidação concorrente.
- Update hook 10014 adiciona `candidate_revision`; Kernel verifica migração,
  incremento idempotente no writer e invalidação após remoção.
- A validação recebe a geração-alvo do rebuild e compara as versões dos
  providers nessa geração, permitindo substituir uma ativa antiga e defasada.

## 0.1.0-dev.33 — cache de candidatos e versão de projeção

- A auditoria estrutural conta projeções da geração ativa com versão divergente
  do provider atualmente registrado; providers desabilitados e gerações retidas
  não entram nessa contagem. O resultado continua sendo somente diagnóstico.
- Adicionado cache interno de páginas limitadas de identidades candidatas, com
  TTL de 60 segundos e invalidação por geração após escrita/remoção pelo writer.
  Entidades e Entity Access continuam sendo reavaliados em toda consulta.
- Kernel cobre detecção de versão antiga e update hook do contador.

## 0.1.0-dev.32 — escopo da auditoria operacional

- Documenta que a auditoria incremental atual verifica integridade estrutural
  do catálogo e não enumera fontes, detecta projeções ausentes/antigas ou faz
  reparação automática.
- Registra reconciliação fonte↔catálogo, cursor por provider, detecção de
  `provider_version` obsoleta e reparação segura como trabalho pendente de
  IDX 0.1-E; nenhuma capacidade de runtime foi declarada como implementada.

## 0.1.0-dev.31 — índice de relações composto

- O índice `gen_source` passa a incluir `relation_type`, alinhando a estrutura
  SQL ao filtro de consulta de relações nomeadas. Update hook 10012 migra
  instalações existentes sem recriar a tabela.
- Kernel cobre a atualização de schema.

## 0.1.0-dev.30 — consulta de relações deduplicada e estável

- Alvos alcançados por mais de um edge que resolve para a mesma tradução são
  retornados uma vez. Paginação ordena pelo `entry_id` imutável do alvo e vincula
  cursores ao contrato `target_entry_id_desc_v1`.
- Kernel cobre edges `und` e idioma padrão explícito para a mesma entidade.

## 0.1.0-dev.29 — isolamento de versões e paginação por ID

- Cada entrada registra a versão do provider; consultas e relações ignoram
  projeções antigas até serem reescritas, sem ocultar providers não alterados.
- Atualização de schema adiciona `provider_version` e troca o índice SQL do
  keyset mutável para ID imutável.
- Cursor usa `entry_id DESC`, com vínculo de hash ao contrato de ordenação.
- Kernel cobre update hooks, atualização de projeção versionada, exclusão de
  relações obsoletas e estabilidade de página sob alteração concorrente.

## 0.1.0-dev.28 — fingerprint inclui versão da projeção

- Gerações capturam a versão declarada por cada provider; mudança de versão
  impede validar ou retomar um rebuild feito com projeção anterior.
- Registry rejeita versão inválida e teste confirma fingerprints distintos.
- Unit dos providers: 121 testes / 536 assertions; Kernel Index/Activity no
  MariaDB: 33 testes / 399 assertions.

## 0.1.0-dev.27 — revalidação de acesso com cursor

- Kernel no MariaDB comprova que uma publicação tornada privada não aparece na
  página seguinte enquanto a projeção pública antiga ainda aguarda o worker
  incremental (1 teste / 12 assertions).
- A suíte combinada Unit/Kernel de Index e Activity passou no MariaDB dedicado:
  67 testes / 555 assertions; Runtime e volume seguem pendentes.

## 0.1.0-dev.26 — acesso canônico fail-closed e validação MariaDB

- Teste Unit garante que provider com Domain canônico ausente nega exposição,
  sem chamar Entity Access no domínio ativo.
- Suíte combinada Unit/Kernel de Index e Activity passou no MariaDB dedicado:
  66 testes / 543 assertions. Uma depreciação upstream e 10 depreciações do
  PHPUnit; Runtime e volume seguem pendentes.

## 0.1.0-dev.25 — Kernel harness para adapters e providers tagged

- Kernel schema tests agora carregam o adapter Media real `aculta_image`; fixtures constroem `DomainPurposeManager` tipado sem habilitar o conjunto integral do Portal.
- Rebuilder/Schema Kernel: 31 testes / 369 assertions passaram em SQLite temporário; Unit Index/Activity: 31 testes / 136 assertions. Adapter Activity também tem Kernel próprio.
- Removido valor de relação calculado e não utilizado em `ContentIndexWriter`; nenhuma mudança de payload.

## 0.1.0-dev.24 — adiar bundle sem módulo proprietário

- `media:remote_video` deixa de ser anunciado como integração ativa. O provider
  temporário no Portal foi removido até que o módulo proprietário do bundle
  seja definido; a ausência de provider mantém a exposição de entradas antigas
  fechada.

## 0.1.0-dev.23 — adapters LMS de curso e policy contextual

- O filtro combina Entity Access com a policy opcional do provider e mantém o contexto canônico.
- `Group:lms_course` recebe projeção mínima e relações allowlisted para leituras e galerias; exige `takeAccess()` ou permissão de educador além de Entity Access.
- Testes Unit do Index e de LMS Resources passam; validação Kernel/DI e Runtime pendentes.

## 0.1.0-dev.22 — providers do Fórum, mídia e galerias

- Providers opcionais projetam tópicos, ForumPosts e Comments legados do Fórum
  durante a coexistência; projeção exclui corpos, autores, rejeitados e revisões
  não padrão. Relations explícitas ligam posts/comentários aos tópicos e pais.
- Providers de mídia cobrem `image`, `document`, `remote_video`,
  `course_document` e `forum_attachment`, registrados pelos módulos ACULTA
  proprietários. Metadados de arquivos, URLs, alt e conteúdo binário são
  excluídos.
- Provider Gallery indexa somente título, idioma, datas e estado e emite
  relações nomeadas para imagens da galeria e capa.
- O filtro de acesso resolve o provider da entidade e revalida Entity Access no
  purpose canônico informado, preservando a conta e falhando fechado se o
  Domain não puder ser ativado.
- Testes unitários e PHPCS locais passaram; módulos não foram habilitados no
  Runtime. Kernel/integração de DI pendentes.

## 0.1.0-dev.21 — fallback preserva a página do Index

- Sem o Statistics, o bridge ainda consulta e devolve a página autorizada e seu
  cursor; `available=false` e totais vazios indicam que os indicadores faltam.
- A ausência do módulo não interrompe conteúdo nem paginação do Index.

## 0.1.0-dev.20 — resultado correlacionado do bridge

- `ContentIndexStatisticsResult` retorna as entidades autorizadas da página
  usada na agregação, além dos totais e do cursor.
- Consumidores podem renderizar uma única página sem repetir a consulta e
  avançar novamente o cursor.
- Fallback sem Statistics continua indisponível, sem entidades ou totais.

## 0.1.0-dev.19 — bridge opcional para Statistics

- Serviço `aculta_index.statistics_bridge` seleciona uma página por filtros e
  encaminha até 100 identidades já autorizadas ao contrato tipado do Portal;
  retorna as mesmas entidades, totais e cursor em uma resposta correlacionada.
- Statistics valida permissão, catálogo e dimensões allowlisted, e reutiliza
  sua própria agregação SQL. Sem Statistics, o bridge responde indisponível.
- Sem junção direta entre tabelas, métricas no Index ou dependência obrigatória.
- Runtime não ativado; testes Kernel não executados por falta de `SIMPLETEST_DB`.

## 0.1.0-dev.18 — consulta segura de relações de saída

- Serviço de consulta de uma relação nomeada, unidirecional e limitada a um
  salto; fonte e alvos passam por Entity Access no momento da consulta.
- Alvos precisam estar na geração ativa; `und` resolve para o idioma padrão do
  site. Paginação usa cursor opaco persistido e não calcula totais/facetas.
- A integração foi validada em Kernel; não há rota ou interface visual nesta
  fase.

## 0.1.0-dev.17 — auditoria incremental agendada

- Checkpoint persistente percorre entradas e relações por ID em lotes limitados
  e registra apenas contagens de inconsistências.
- Cron processa no máximo um lote por invocação e inicia um novo ciclo diário.
- Comando `aculta-index:audit` permite iniciar, avançar e inspecionar auditorias.
- A validação completa usada pelo rebuild permanece independente; o Runtime
  ainda não foi atualizado nem verificado.

## 0.1.0-dev.16 — comandos Drush de rebuild e fila

- `aculta-index:rebuild` fornece ações separadas `start`, `batch`, `validate`
  e `activate`; concluir backfill não ativa geração automaticamente.
- `aculta-index:queue` reporta o gate de ativação, lista dead-letters por
  página e reenvia um job pelo ID. A saída de dead-letter inclui IDs de
  entidades e fica restrita à CLI administrativa.
- Comandos implementados em código e documentação; descoberta no Drush do
  Runtime não foi verificada.

## 0.1.0-dev.15 — gate de ativação e recuperação da fila

- Ativação de geração validada aguarda a fila dos providers ativos: queued,
  processing, retry e dead-letter bloqueiam a troca; rollback para geração
  retained continua permitido.
- Ativação também recalcula o fingerprint dos providers para rejeitar mudanças
  ocorridas entre validação e publicação.
- A linha singleton do ponteiro funciona como fence: schedule durante um
  rebuild é serializado com ativação. Eventos escrevem também na geração
  validated até a troca atômica do ponteiro.
- Cron move jobs `processing` sem progresso por cinco minutos a retry com
  backoff; após cinco falhas passam a dead. `listDead()` e `retryDead()` dão
  suporte interno de reparação, sem UI/comando administrativo nesta fase.
- Kernel cobre bloqueio por fila pendente, drenagem antes de ativação, inspeção
  e reenvio de dead-letter e recuperação de worker interrompido.

## 0.1.0-dev.14 — validação estrutural de rebuild

- A geração persiste `backfill_completed`; a conclusão é gravada com CAS da
  revisão do checkpoint e impede validar backfill interrompido ou concorrente.
- `ContentIndexRebuilder::validate()` verifica fingerprint dos providers e
  integridade estrutural antes de marcar a geração como validada.
- Update hook 10008 adiciona a marca às instalações existentes.
- Kernel cobre o fluxo backfill → validação → ativação. Drenagem/catch-up da
  fila e reconciliação permanecem pendentes.

## 0.1.0-dev.13 — consulta paginada e cursor opaco

- `ContentIndexQuery` seleciona no máximo 100 candidatos por página, em ordem
  keyset `changed DESC, entry_id DESC`, e retorna somente traduções aprovadas
  pelo Entity Access atual. Não calcula totais/facetas e usa cache max-age 0.
- Cursor aleatório vinculado a conta, filtros e geração; somente SHA-256 fica
  armazenado. Expira após 15 minutos de inatividade, avança por CAS e cron
  remove expirados em lotes limitados.
- Próximo token só é emitido quando a página de candidatos atinge o limite; essa
  política mantém custo limitado e o token não revela IDs nem metadados do item.
- Update hooks 10005–10007 criam cursor, CAS do cursor e índice keyset.
- Kernel cobre páginas, cursor não legível no banco, filtro de acesso de item
  privado e remoção do cursor ao final. Custo real/volume e concorrência seguem
  pendentes.
- Kernel também cobre rejeição de cursor por filtros/conta diferentes e revoga
  resultado após o conteúdo tornar-se privado, antes de a fila incremental rodar.

## 0.1.0-dev.12 — inventário estável e consultas sem agregados

- A geração persiste fingerprint SHA-256 do inventário de providers/tipos/
  bundles; resume falha explicitamente se o inventário habilitado mudou.
- Update hook 10004 adiciona o fingerprint a instalações existentes. Kernel
  cobre o update e rejeição de drift.
- Kernel cobre backfill por lotes keyset sobre Nodes reais, checkpoint/retomada,
  geração ainda não ativa e ausência de providers.
- Decisão da API 0.1: páginas por cursor sem totais nem facetas; resultados
  serão autorizados na entidade original.

## 0.1.0-dev.11 — coordenação inicial de backfill retomável

- `ContentIndexRebuilder` percorre providers/tipos em ordem estável, escreve
  um lote keyset e só então avança o checkpoint por CAS. Repetição do lote é
  idempotente; sequência incremental mais nova prevalece sobre o backfill.
- Cada chamada processa no máximo um lote/tipo e informa conclusão sem marcar
  a geração como validada ou ativá-la. Integridade, drenagem/catch-up e o gate
  explícito de publicação continuam necessários.

## 0.1.0-dev.10 — fila incremental e projeção contínua

- Hooks de insert/update/delete registram somente identidades e operação; o
  payload do Core Queue contém apenas o ID do estado durável do trabalho.
- `ContentIndexWorkManager` deduplica por provider/entidade/idioma, avança uma
  sequência monotônica e mantém estado mínimo queued/processing/retry/dead.
- Worker relê a entidade atual, aplica projeções às gerações ativa e building e
  usa a sequência no writer para rejeitar trabalho obsoleto. Isso mantém deltas
  durante backfill; o orquestrador e o gate de validação ainda serão ligados.
- Cron recupera dispatch perdido e retries. Política inicial: cinco tentativas,
  backoff exponencial de 30 segundos até seis horas (confirmada pelo
  responsável); registros dead exigem
  reparação operacional futura.
- Não instalar contrib. Core Queue e contratos próprios ACULTA são usados;
  as referências contrib continuam fonte de padrões e proveniência.
- Kernel: deduplicação, sequência e claim/complete; integração geral permanece
  Dev e não foi validada no Runtime.

## 0.1.0-dev.9 — writer transacional e revalidação de Entity Access

- `ContentIndexWriter` grava uma identidade por entidade/idioma/geração e
  substitui relações na mesma transação. Hash canônica permite evitar escrita
  repetida e reparar relações divergentes.
- `source_sequence` em BIGINT impede jobs antigos de sobrescreverem uma versão
  nova; conflito de conteúdo com a mesma sequência falha fechado. Exclusões
  também são condicionadas à sequência.
- Relação SHA-256 inclui provider e identidade da origem; múltiplas origens
  podem referenciar o mesmo alvo sem colidir.
- `ContentIndexAccessFilter` carrega candidatos em lotes de até 100, verifica
  Entity Access no original e devolve apenas entidades autorizadas com cache
  tags/contexts/max-age agregados de resultados permitidos e negados.
- Schema update hook 10002 adiciona `source_sequence`. Kernel cobre gravação,
  idempotência, relações compartilhadas, exclusão e ordem obsoleta.

## 0.1.0-dev.8 — checkpoint keyset com compare-and-swap

- `ContentIndexRebuildCheckpointManager` retoma posição por provider, tipo e ID
  de entidade, após cada lote ter sido gravado pelo futuro orquestrador.
- Cada avanço exige a revisão lida; workers concorrentes com checkpoint antigo
  recebem conflito e não substituem um cursor mais novo.
- Adicionado `cursor_revision` ao schema e update hook idempotente 10001.
- Kernel cobre avanço, recarga, recusa de checkpoint obsoleto e aplicação do
  update hook a uma instalação com o campo ainda ausente.

## 0.1.0-dev.7 — propriedade exclusiva de projeção por bundle

- O registry rejeita providers que disputem o mesmo tipo/bundle. O schema
  mantém uma linha por identidade entidade/idioma/geração, portanto projeções
  concorrentes seriam sobrescritas e tornariam a origem ambígua.
- Unit cobre a recusa de providers sobrepostos.

## 0.1.0-dev.6 — checker de integridade do catálogo

- `ContentIndexIntegrityChecker` valida coerência do ponteiro e conta entradas
  ou relações sem geração e relações sem uma entrada de origem. Retorna somente
  status e contagens, sem conteúdo projetado.
- Kernel cobre estado vazio e divergências representativas. O checker é
  diagnóstico: nenhuma correção ou remoção é feita automaticamente.

## 0.1.0-dev.5 — enumeração keyset para backfill

- `ContentIndexEntityEnumerator` descobre somente tipos e bundles declarados
  pelo provider, com Entity Query keyset, limite de 500 e processamento de
  todas as traduções por entidade. A consulta interna declara explicitamente
  `accessCheck(FALSE)`; nenhum resultado é exposto por essa API.
- O enumerador verifica identidade/bundle retornados pelo provider e fornece
  cursor pela última entidade do lote. Persistência do checkpoint e writer
  seguem pendentes.
- Unit e Kernel verificam paginação, traduções e registro DI sem providers.

## 0.1.0-dev.4 — integração inicial da Revista

- O provider Magazine implementa o contrato neutro do Portal e é descoberto
  pelo serviço `aculta_index.provider_registry` quando ambos os módulos estão
  ativos. Não foi criada dependência do Magazine ao índice opcional.
- A projeção fica limitada à revisão padrão, aos metadados editoriais mínimos e
  às relações explícitas para atividade e imagem. Persistência aguarda o writer.

## 0.1.0-dev.3 — ciclo inicial de gerações

- Adicionado `ContentIndexGenerationManager` para iniciar geração, registrar
  validação e trocar/retomar o ponteiro entre gerações validadas.
- A troca mantém a geração anterior em `retained`, atualiza o ponteiro por CAS
  dentro de transação e reverte integralmente se o estado de origem divergir.
- Kernel cobre o ciclo de ativação/rollback e a recusa transacional de geração
  ainda em construção.

## 0.1.0-dev.2 — iniciar ponteiro vazio de geração

- A instalação cria a linha singleton de `aculta_index_state` com geração ativa
  vazia e revisão zero. Nenhum conteúdo é considerado publicado no catálogo
  antes da futura validação e ativação de um rebuild.
- O provider registry valida as declarações de tipo de entidade e bundles.
- Os contratos públicos de provider, projeção e relação estão no namespace
  neutro do Portal; o submódulo permanece opcional.
- Schema instalado em Kernel no banco MariaDB de teste; Runtime não alterado.
