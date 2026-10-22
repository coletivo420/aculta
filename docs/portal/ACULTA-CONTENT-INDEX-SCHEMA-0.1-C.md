# ACULTA Content Index — esquema IDX 0.1-C

**Estado:** implementado localmente em `aculta_index.install`; ainda não instalado nem homologado no Runtime.
**Decisões aplicadas:** identidade por entidade/idioma; nenhuma identidade de revisão; `generation_id` em dados projetados e ponteiro único para geração ativa.

## Tabelas

| Tabela | Uso | Identidade/índices |
|---|---|---|
| `aculta_index_generation` | Estado do rebuild, status, timestamps e cursor keyset por provider | PK `generation_id`; índice de status e criação |
| `aculta_index_state` | Uma linha (`state_id = 1`) aponta para a geração ativa e mantém revisão do ponteiro | PK singleton; troca será transacional |
| `aculta_index_entry` | Projeção mínima de entidade atual/idioma: provider, tipo/ID, bundle, UUID, label, domínio, publicação, datas e hash de projeção | Unicidade por geração + entity type + entity ID + langcode; índices de bundle, domínio/publicação e provider |
| `aculta_index_relation` | Relação dirigida e tipada entre identidades originais | Unicidade por geração + SHA-256 da tupla; índices separados de origem, alvo e tipo |

As relações guardam a identidade do alvo mesmo quando o alvo não tem adapter e não possui entrada no índice. Não há FK a entidade Drupal nem a outra linha de `entry`: o armazenamento original pode ser de um módulo opcional, o alvo pode não ser indexável e excluir/reconstruir o catálogo não pode alterar o conteúdo original.

## Gerações

Todos os registros de entrada e relação pertencem a uma geração. Consultas públicas do serviço de storage devem obter o `active_generation` e restringir a consulta a esse valor. O worker de rebuild grava apenas numa geração `building`; a geração ativa permanece disponível até validação. A ativação futura deve, em uma transação, validar o candidato, atualizar o estado da geração anterior e trocar o ponteiro. Rollback repõe o ponteiro anterior enquanto os dados forem mantidos. A limpeza de gerações antigas será explícita e posterior ao período de rollback.

O checkpoint identifica provider e tupla (entity type, ID, idioma) para keyset. Ele não usa OFFSET nem serializa entidades. Ainda faltam IDX 0.1-D/E: fila/delta durante o rebuild, operação atômica no driver e recuperação após falha.

## Índices e portabilidade

Entradas têm consultas seletivas por geração, tipo/bundle, domínio/estado e provider. Relações são consultáveis por origem ou alvo sem carregar toda a tabela. Chaves compostas preservam IDs string (inclusive tipos que não sejam Node) e idioma.

Schema foi declarado com Database Schema API, sem SQL de criação específico do driver. Os testes atuais conferem o contrato da definição; ainda é necessário instalar e consultar schema em MariaDB e SQLite dentro dos harnesses de teste aprovados. Não há medição de latência, volume ou tamanho de índice nesta fase.

## Limites de projeção

O schema não guarda corpo, URI de arquivo, usuário, histórico de revisão, notas de estudante ou métricas. `published` é nullable porque algumas entidades não têm publicação; zero/um representam estado explícito quando o provider pode determiná-lo. `domain_id` vazio representa ausência de domínio. A presença da linha não concede acesso; APIs de consulta futuras precisam carregar o original, validar Entity Access e filtrar resultados sem expor totais/facetas privados.

`projection_hash` permite pular gravações quando a projeção não mudou. A normalização estável do hash e a prevenção de overwrite obsoleto serão implementadas junto do writer incremental, não inferidas a partir do timestamp sozinho.

## Próximos gates

- IDX 0.1-C: definir `ContentIndexProjection`/writer e adicionar provider inicial; não criar provider genérico irrestrito.
- IDX 0.1-D: Core Queue + estado mínimo de falha; remover/invalidar síncronamente mudanças que reduzam exposição; validar versionamento em gravação concorrente.
- IDX 0.1-E: rebuild keyset, catch-up de delta, ativação transacional, rollback e reconciliação.
- IDX 0.1-R: kernel/schema nos bancos de teste, concorrência, portabilidade, acesso e benchmark medido.
