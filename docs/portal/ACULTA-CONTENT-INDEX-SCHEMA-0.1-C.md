# ACULTA Content Index — esquema IDX 0.1-C

**Estado:** implementado localmente em `aculta_index.install`; ainda não instalado nem homologado no Runtime.
**Decisões aplicadas:** identidade por entidade/idioma; nenhuma identidade de revisão; `generation_id` em dados projetados e ponteiro único para geração ativa.

## Tabelas

| Tabela | Uso | Identidade/índices |
|---|---|---|
| `aculta_index_generation` | Estado do rebuild, status, timestamps, fingerprint, checkpoint keyset e marca CAS de conclusão do backfill | PK `generation_id`; índice de status e criação |
| `aculta_index_state` | Uma linha (`state_id = 1`) aponta para a geração ativa e mantém revisão do ponteiro | PK singleton; troca transacional |
| `aculta_index_entry` | Projeção mínima de entidade atual/idioma: provider, tipo/ID, bundle, UUID, label, domínio, publicação, datas, hash e sequência monotônica da origem | Unicidade por geração + entity type + entity ID + langcode; índices de bundle, domínio/publicação e provider |
| `aculta_index_relation` | Relação dirigida e tipada entre identidades originais | Unicidade por geração + SHA-256 da tupla; índices compostos de origem/tipo, alvo e tipo |

As relações guardam a identidade do alvo mesmo quando o alvo não tem adapter e não possui entrada no índice. `target_langcode = und` significa relação ao nível da entidade; a consulta resolve a tradução para o idioma padrão do site (`pt_BR` atualmente). Não há FK a entidade Drupal nem a outra linha de `entry`: o armazenamento original pode ser de um módulo opcional, o alvo pode não ser indexável e excluir/reconstruir o catálogo não pode alterar o conteúdo original.

## Gerações

Todos os registros de entrada e relação pertencem a uma geração. Consultas públicas do serviço de storage devem obter o `active_generation` e restringir a consulta a esse valor. O worker incremental grava nas gerações `active`, `building` e `validated`, para manter deltas até a troca do ponteiro; o writer rejeita alterações obsoletas por sequência. Cada entrada registra `provider_version`; consultas e relações exigem igualdade com a versão atual do provider, ocultando a projeção antiga desse provider até ela ser reescrita, enquanto outros providers continuam visíveis. Cada geração captura fingerprint SHA-256 dos IDs e versões dos providers, tipos de entidade e bundles habilitados; resume e validate recusam um fingerprint diferente. A geração ativa permanece disponível durante o backfill e a validação. A ativação espera que a fila dos providers atuais esteja drenada e bloqueia dead-letters. Schedule e ativação bloqueiam a mesma linha singleton, ordenando eventos concorrentes antes ou depois da troca. A ativação atualiza o estado da geração anterior e conserva dados para rollback. Rollback repõe o ponteiro anterior enquanto os dados forem mantidos.

O `ContentIndexEntityEnumerator` identifica o progresso por provider, tipo de entidade e último ID; percorre todas as traduções da entidade dentro do mesmo lote. Não usa OFFSET nem serializa entidades. `ContentIndexRebuildCheckpointManager` persiste e retoma esse cursor por CAS, impedindo que outro worker com revisão antiga faça regressão. O orquestrador grava todas as projeções antes de avançar o checkpoint e registra `backfill_completed` por CAS na última página. `ContentIndexRebuilder::validate()` exige conclusão e inventário estável, depois confere integridade estrutural antes de marcar a geração validada. A ativação exige que os estados queued, processing, retry e dead dos providers ativos cheguem a zero; cron recupera jobs processing sem progresso. A operação de dead-letter está disponível via Drush `aculta-index:queue dead --limit=50` e `aculta-index:queue retry WORK_ID`; a saída inclui IDs de entidades e deve ficar restrita a operadores autorizados. A reconciliação incremental está implementada, com verificação abrangente por adapter e Runtime ainda pendentes.

`ContentIndexIntegrityChecker::check()` valida ponteiro, gerações, relações e versões das projeções sem alterar dados. A auditoria incremental, separada, percorre entradas e fontes em lotes com checkpoint: refileira hashes divergentes e fontes sem entrada, e agenda remoção apenas quando o provider ativo confirma fonte/tradução ausente ou fora de seu ownership. Providers desabilitados ou sem ownership verificável são preservados. Essa reconciliação está implementada e tem Kernel tests com provider de fixture; cobertura abrangente dos adapters, grants reais, Runtime e volume continua pendente. O comando operacional é `drush aculta-index:audit status|start|batch`.

## Índices e portabilidade

Entradas têm consultas seletivas por geração, tipo/bundle, domínio/estado e provider. Relações são consultáveis por origem ou alvo sem carregar toda a tabela. Chaves compostas preservam IDs string (inclusive tipos que não sejam Node) e idioma.

Schema foi declarado com Database Schema API, sem SQL de criação específico do driver. A instalação e consultas passaram anteriormente nos Kernel tests com MariaDB. Em 2026-10-23, a suíte Kernel completa do Index também passou em SQLite em memória (64 testes / 834 assertions), como execução suplementar local; isso não substitui os gates MariaDB do Portal nem homologa SQLite como backend operacional. Há medições sintéticas do catálogo e de Entity Access documentadas no plano de benchmark; não há homologação de latência/volume de produção nem medição de tamanho em dados operacionais.

Consultas de entidades usam `generation_id`, filtros allowlisted e `entry_id DESC` para keyset imutável. Relações deduplicam por identidade do alvo resolvida e usam o `entry_id` do alvo para manter a mesma ordenação estável; o índice `gen_source` atende origem e tipo de relação na consulta nomeada. O cursor não contém identidade da origem: um token aleatório por conta/filtros/geração localiza uma linha com hash SHA-256, posição, revisão CAS e expiração de 15 minutos. O cron apaga tokens expirados por lotes. O leitor revalida cada entidade pela API de acesso; as respostas não são armazenáveis em cache neste primeiro contrato.

Um Kernel test no MariaDB deixa deliberadamente uma projeção pública obsoleta após despublicar a entidade e confirma que a página com cursor não a expõe antes da execução do worker incremental. Isso valida a revogação efetiva na leitura apesar do atraso da fila; não valida a latência do worker nem os demais mecanismos externos de grants.

## Limites de projeção

O schema não guarda corpo, URI de arquivo, usuário, histórico de revisão, notas de estudante ou métricas. `published` é nullable porque algumas entidades não têm publicação; zero/um representam estado explícito quando o provider pode determiná-lo. `domain_id` vazio representa ausência de domínio. A presença da linha não concede acesso; a API de consulta carrega candidatos em lotes limitados e revalida Entity Access na entidade original. Totais e facetas não fazem parte do contrato 0.1.

`projection_hash` inclui a versão de projeção e permite pular gravações quando a projeção não mudou. `source_sequence` ordena operações CRUD por identidade entidade/idioma e impede overwrite de job atrasado; o timestamp de alteração não é usado como único token de concorrência. A sequência é estado técnico de atualização e não cria identidade por revisão. O `ContentIndexWriter`, a fila incremental e o filtro de Entity Access estão implementados; falhas de grants externos e concorrência adicional seguem pendentes de homologação.

## Próximos gates

- IDX 0.1-C: schema, writer idempotente/versionado, persistência de relações e providers implementados; writer integrado ao backfill keyset.
- IDX 0.1-D: Core Queue, deduplicação, sequência de origem, retentativas limitadas, dead-letter e remoção imediata da projeção ao reduzir exposição implementados; grants externos e cenários de concorrência pendentes.
- IDX 0.1-E: rebuild por geração, checkpoints CAS, catch-up de deltas, ativação protegida, rollback, auditoria incremental e comandos Drush de inspeção/retry de dead-letter implementados; homologação Runtime pendente.
- IDX 0.1-R: suíte Kernel combinada de Index/Activity/User em MariaDB passou em 2026-10-22 (73 testes, 943 assertions, 6 deprecações upstream); homologação completa de concorrência, grants reais, Runtime, volume e benchmarks de manutenção permanece pendente.
