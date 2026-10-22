# Changelog — ACULTA Content Index

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
