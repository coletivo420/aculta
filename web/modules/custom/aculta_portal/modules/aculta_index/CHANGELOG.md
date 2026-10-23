# Changelog — ACULTA Content Index

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
