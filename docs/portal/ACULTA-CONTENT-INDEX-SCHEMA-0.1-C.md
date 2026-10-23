# ACULTA Content Index — esquema IDX 0.1-C

**Estado:** implementado localmente em `aculta_index.install`; ainda não instalado nem homologado no Runtime.
**Decisões aplicadas:** identidade por entidade/idioma; nenhuma identidade de revisão; `generation_id` em dados projetados e ponteiro único para geração ativa.

## Tabelas

| Tabela | Uso | Identidade/índices |
|---|---|---|
| `aculta_index_generation` | Estado do rebuild, status, timestamps, fingerprint, checkpoint keyset e marca CAS de conclusão do backfill | PK `generation_id`; índice de status e criação |
| `aculta_index_state` | Uma linha (`state_id = 1`) aponta para a geração ativa e mantém revisão do ponteiro | PK singleton; troca transacional |
| `aculta_index_entry` | Projeção mínima de entidade atual/idioma: provider, tipo/ID, bundle, UUID, label, domínio, publicação, datas, hash e sequência monotônica da origem | Unicidade por geração + entity type + entity ID + langcode; índices de bundle, domínio/publicação e provider |
| `aculta_index_relation` | Relação dirigida e tipada entre identidades originais | Unicidade por geração + SHA-256 da tupla; índices separados de origem, alvo e tipo |

As relações guardam a identidade do alvo mesmo quando o alvo não tem adapter e não possui entrada no índice. `target_langcode = und` significa relação ao nível da entidade, sem apontar uma tradução específica; para este caso a consulta de relações não deve presumir correspondência com uma única linha de idioma. Não há FK a entidade Drupal nem a outra linha de `entry`: o armazenamento original pode ser de um módulo opcional, o alvo pode não ser indexável e excluir/reconstruir o catálogo não pode alterar o conteúdo original.

## Gerações

Todos os registros de entrada e relação pertencem a uma geração. Consultas públicas do serviço de storage devem obter o `active_generation` e restringir a consulta a esse valor. O worker incremental grava nas gerações `active`, `building` e `validated`, para manter deltas até a troca do ponteiro; o writer rejeita alterações obsoletas por sequência. Cada geração captura fingerprint SHA-256 dos providers, tipos e bundles habilitados; resume recusa um inventário diferente. A geração ativa permanece disponível durante o backfill e a validação. A ativação espera que a fila dos providers atuais esteja drenada e bloqueia dead-letters. Schedule e ativação bloqueiam a mesma linha singleton, ordenando eventos concorrentes antes ou depois da troca. A ativação atualiza o estado da geração anterior e conserva dados para rollback. Rollback repõe o ponteiro anterior enquanto os dados forem mantidos.

O `ContentIndexEntityEnumerator` identifica o progresso por provider, tipo de entidade e último ID; percorre todas as traduções da entidade dentro do mesmo lote. Não usa OFFSET nem serializa entidades. `ContentIndexRebuildCheckpointManager` persiste e retoma esse cursor por CAS, impedindo que outro worker com revisão antiga faça regressão. O orquestrador grava todas as projeções antes de avançar o checkpoint e registra `backfill_completed` por CAS na última página. `ContentIndexRebuilder::validate()` exige conclusão e inventário estável, depois confere integridade estrutural antes de marcar a geração validada. A ativação exige que os estados queued, processing, retry e dead dos providers ativos cheguem a zero; cron recupera jobs processing sem progresso e a reparação dead-letter é interna, ainda sem interface de operação. Reconciliação permanece pendente.

`ContentIndexIntegrityChecker` consulta somente as tabelas do catálogo e verifica ponteiro/gerações e fontes de relações. Ele reporta contagens e não corrige nem expurga automaticamente. Em 2026-10-22, a suíte Unit/Kernel do Index e Activity passou com SQLite em memória (65 testes, 533 assertions), cobrindo instalação e consulta do schema, paginação e relações. MariaDB, Runtime e portabilidade sob volume ainda precisam de validação própria.

## Índices e portabilidade

Entradas têm consultas seletivas por geração, tipo/bundle, domínio/estado e provider. Relações são consultáveis por origem ou alvo sem carregar toda a tabela. Chaves compostas preservam IDs string (inclusive tipos que não sejam Node) e idioma.

Schema foi declarado com Database Schema API, sem SQL de criação específico do driver. A instalação e consultas exercitadas nos Kernel tests passaram no SQLite em memória; falta repetir os gates em MariaDB. Não há medição de latência, volume ou tamanho de índice nesta fase.

Consultas 0.1 usam `generation_id`, filtros allowlisted, `changed DESC` e `entry_id DESC` para keyset. O índice `gen_changed` serve esse percurso. O cursor não contém identidade da origem: um token aleatório por conta/filtros/geração localiza uma linha com hash SHA-256, posição, revisão CAS e expiração de 15 minutos. O cron apaga tokens expirados por lotes. O leitor revalida cada entidade pela API de acesso; as respostas não são armazenáveis em cache neste primeiro contrato.

## Limites de projeção

O schema não guarda corpo, URI de arquivo, usuário, histórico de revisão, notas de estudante ou métricas. `published` é nullable porque algumas entidades não têm publicação; zero/um representam estado explícito quando o provider pode determiná-lo. `domain_id` vazio representa ausência de domínio. A presença da linha não concede acesso; APIs de consulta futuras precisam carregar o original, validar Entity Access e filtrar resultados sem expor totais/facetas privados.

`projection_hash` permite pular gravações quando a projeção não mudou. `source_sequence` ordena operações CRUD por identidade entidade/idioma e impede overwrite de job atrasado; o timestamp de alteração não é usado como único token de concorrência. A sequência é estado técnico de atualização e não cria identidade por revisão. O `ContentIndexWriter` e o filtro de Entity Access estão implementados; a fila ainda precisa alimentar a sequência em cada evento.

## Próximos gates

- IDX 0.1-C: writer idempotente, comparação de versão, persistência de relações e provider Magazine implementados; falta ligar o writer ao backfill.
- IDX 0.1-D: Core Queue + estado mínimo de falha; remover/invalidar síncronamente mudanças que reduzam exposição; validar versionamento em gravação concorrente.
- IDX 0.1-E: rebuild keyset, catch-up de delta, ativação transacional, rollback e reconciliação.
- IDX 0.1-R: kernel/schema nos bancos de teste, concorrência, portabilidade, acesso e benchmark medido.
