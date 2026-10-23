# Changelog — ACULTA Content Index

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
