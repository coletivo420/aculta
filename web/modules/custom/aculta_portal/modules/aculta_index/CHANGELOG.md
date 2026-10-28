# Changelog — ACULTA Content Index

## 0.1.0-dev.177 — painel somente de leitura do estado do índice (2026-10-28)

- Adicionado `/painel-administrativo/indice`, com geração ativa, última reconstrução concluída e contagem de trabalho pendente. A permissão é restrita e a tela não executa rebuild, retry ou outra escrita.
- Kernel access: 1 teste / 1 assertion PASS (1 depreciação).

## 0.1.0-dev.176 — revalidar acesso por página e endurecer integridade/cursors (2026-10-26)

- `ContentIndexAccessFilter` limpa os handlers de Entity Access dos tipos
  candidatos no início de cada página. O cache interno do Core não é
  invalidado por save, Group ou grant; sem isso, um consumidor de longa duração
  podia reutilizar um ALLOW após revogação. O reset por purpose canônico
  continua antes/depois de cada grupo.
- Cursors de conteúdo e de relação incluem o fingerprint dos providers no hash,
  além de conta, filtros e geração. Mudança de versão/bundle recusa o token
  emitido sob o inventário anterior.
- `ContentIndexIntegrityChecker` ignora relações de gerações `discarding` nas
  verificações de fonte ausente. A limpeza remove relações e entradas em lotes
  separados; a inconsistência transitória não deve invalidar rebuild/auditoria.
- O harness Wiki deriva o número de lotes do registry bootado (9 providers, 11
  tuplas) em vez do limite fixo de 10.
- `ContentIndexGenerationManager::start()` bloqueia a linha singleton de estado
  e recusa uma segunda geração `building`; um rebuild abandonado precisa ser
  descartado antes de iniciar outro.
- Novo Kernel representativo com dois providers sintéticos, dois idiomas, dois
  domínios, estados editoriais, relações, atualização, exclusão, cursor e
  resíduo de provider desregistrado. Duas execuções opt-in: rebuild 402–429 ms;
  auditoria ~46,2 ms; consulta fria 5,7–6,8 ms; aquecida p50/p95
  4,7–5,0 / 5,8–6,2 ms; pico 82,5 MiB. Fixture pequena/média, sem SLO.
- Submódulo opcional `aculta_index_file` (provider `file:file`, versão 1):
  indexa somente arquivos permanentes e não projeta nome, URI, MIME, tamanho
  nem conteúdo. Torna `media.files.requests` alcançável pelo bridge quando
  habilitado; sem o submódulo, o provider não existe. Kernel do bridge 3/55 e
  Unit do submódulo 2/21.
- Contrato de vídeo definido em `docs/portal/ACULTA-CONTENT-INDEX-VIDEO-RELATIONS.md`.
  O LMS passou a permitir `remote_video` por `field_lms_videos` e a projetar
  `course_video` (provider v4 com o plugin PDF Group, v3 sem ele; update `8001`
  no owner). Unit LMS 13/70 e Kernel LMS 5/259.
- Evidência: Unit 51/265 + submódulo 2/21; Kernel 119/2.019 PASS; benchmark
  Fórum em 100 candidatos, duas amostras: membro 371–494 / 407–550 ms e
  visitante 178–243 / 220–290 ms p50/p95; a variação não isola a mudança;
  Runtime local read-only 145 entradas / 110 relações, fila vazia e auditoria
  válida. Sem SLO e sem operação persistente no Runtime.
- Documentação: worklog do Agente 3, relatórios de benchmark, matriz de
  providers e contrato de vídeo.

## 0.1.0-dev.175 — enumerar tipo registrado sem schema instalado (2026-10-25)

- `ContentIndexEntityEnumerator` considera vazio um tipo de entidade cuja
  tabela base não existe, em vez de cair em EntityQuery e lançar erro SQL.
  É o caso de um módulo presente no grafo de dependências mas com schema não
  instalado no banco corrente (fixture parcial ou update pendente). Storages
  não SQL mantêm o caminho de EntityQuery.
- O Kernel Wiki-owned de rebuild passou a percorrer tuplas de provider com
  limite explícito maior, porque o inventário compartilhado cresceu com o novo
  `aculta_forum_post`; a asserção de geração completa permanece. Classe Wiki
  completa 30/1.661 PASS; Unit do enumerador 7/59 PASS.

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

