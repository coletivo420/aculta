# MESSENGER-PART 0.1-C2 — caracterização da idempotência dos avisos do Fórum

**Data:** 2026-10-22 · **Estado:** caracterização concluída; nenhuma mudança funcional,
de DI, de schema ou de Runtime.
**Base:** [ADR-XK-02](../../../../../../../docs/architecture/ACULTA-XK-0.1-B-SHARED-CONTRACTS.md)
e [roadmap do submódulo](ROADMAP.md).

O contrato XK-C2 pede adaptar um evento existente sem duplicar aviso, com testes.
A auditoria mostrou que os produtores atuais já deduplicam por marcador monotônico
no próprio aviso persistente. Alterar o produtor seria uma mudança de
comportamento sem defeito comprovado, proibida nesta fase. A entrega, portanto,
fixa por testes de persistência o contrato que já existe:

- o evento de **primeira publicação** de um `aculta_forum_post` revisionável;
- o evento de **tópico atualizado**, que compartilha o mesmo aviso agregado.

Comentários legados mantêm o caminho próprio (`ForumAvisoHooksTest`, Unit) e não
foram reimplementados aqui.

## Contratos caracterizados

### Serviço

`Drupal\aculta_messenger\Aviso\AvisoService`, métodos `registrarPost()` e
`notificarAtualizacao()`. Dependências reais: `entity_type.manager`. Dependências
isoladas no teste: `flag.flag` (`FlagServiceInterface`) e
`ForumNoticeAccessPolicyInterface`.

### Hooks e eventos

| Evento | Entrada | Caminho |
| --- | --- | --- |
| `aculta_forum_post_insert` | `ForumPost` publicado | `ForumAvisoHooks::postInsert()` → `registrarPost()` |
| `aculta_forum_post_update` | primeira publicação | `ForumAvisoHooks::postUpdate()` → `registrarPost()` |
| Tópico publicado ou com nova revisão publicada | `aculta_forum_topic` | `MessageSubscribeEventHooks` → fila → `notificarAtualizacao()` |

O hook de update só chama o serviço quando a transição é
`não publicado → publicado` **e** `first_published` era vazio e passou a
preenchido. Edições de post já publicado não geram aviso; retirar e republicar
também não gera, porque `first_published` é imutável.

### Marcadores e chave de deduplicação

| Tipo de aviso | Marcador | Comparação |
| --- | --- | --- |
| `forum_resposta` (posts) | `ultimo_post` = ID da revisão publicada | `revisao > ultimo_post` |
| `forum_atualizacao` (tópicos) | `ultimo_evento` = ID da revisão do tópico | `revisao > ultimo_evento` |
| `forum_resposta` (comentários) | `ultimo_comentario` | `comentario > ultimo_comentario` |

Um aviso por par `(uid, assunto_nid, tipo)`; a consulta usa `accessCheck(FALSE)`
porque o destinatário é revalidado pela política antes de contar. Os contadores
`sitio`/`email` só avançam quando o marcador avança. Reenvio do mesmo evento
devolve `0` e não altera o registro. Avisos de resposta e de atualização do
mesmo tópico são registros distintos e não somam entre si.

### Destinatários

Follower é cada flagging de `seguir_topico` no tópico. São excluídos: uid 0,
o autor do post, quem perdeu acesso ao post (`canViewPost()`) ou ao tópico
(`canViewTopic()`), tópico não suportado pela política e ausência de flag.

### Falha conservadora

| Situação | Resultado caracterizado |
| --- | --- |
| Post pendente, revisão não-default ou em sincronização | nenhum aviso |
| Post sem tópico node ou tópico não suportado | nenhum aviso |
| Flag inexistente ou sem flaggings | nenhum aviso |
| Tópico não publicado | nenhum aviso de atualização |
| Destinatário sem acesso no momento do evento | não é contado |
| Evento repetido (mesma revisão) | nenhum aviso novo, contadores intactos |

## Teste entregue

`tests/src/Kernel/ForumNoticeIdempotencyKernelTest.php` — Kernel, SQLite em
memória, 8 testes / 148 assertions.

| Teste | Célula |
| --- | --- |
| `testFirstPublicationRegistersOneNoticePerFollower` | 1 aviso por follower; autor e uid 0 fora; contadores e marcador |
| `testRepeatedDeliveryOfTheSameEventIsIgnored` | entrega repetida, inclusive entidade recarregada |
| `testPendingAndNonDefaultRevisionsRegisterNothing` | post pendente, update sem original e revisão não-default |
| `testFirstPublicationUpdateCountsOnce` | transição de primeira publicação contada uma vez |
| `testEditOfPublishedPostDoesNotRegisterANotice` | edição revisionada de post publicado não soma |
| `testRecipientWithoutPostAccessIsSkipped` | política de acesso negando o post |
| `testTopicUpdateNoticesAreDeduplicated` | mesma revisão uma vez, revisão seguinte soma, tipos isolados |
| `testTopicWithoutEligiblePublicationRegistersNothing` | tópico não publicado, política sem suporte e flag ausente |

### Ambiente do teste

- `aculta_forum_post_test` (já existente) registra `ForumPost` por Reflection.
- `tests/modules/aculta_messenger_aviso_test` registra a entidade de produção
  `Aviso` pelo atributo `ContentEntityType`, com provider do fixture. É o único
  código de fixture novo; não entra em produção.
- `content_moderation` não é habilitado: `isPublished()` lê o campo `status`, e
  o teste controla `first_published` e `setNewRevision()` diretamente.
- Flag e política são mocks de interface; entidades, revisões, marcadores e
  contadores são persistidos de verdade.
- O bootstrap do PHPUnit registra PSR-4 de todas as extensões descobertas, o que
  permite carregar classes de `aculta_messenger` e `aculta_forum` sem habilitar
  os módulos completos.

## Fora desta fase

- Validação Runtime e revisão visual: continuam pendentes.
- Lock persistente do worker de tópicos (`aculta_messenger:forum_topic_updated:*`)
  não é exercitado; o teste cobre a segunda barreira, no serviço.
- Nenhum aviso, tipo, campo, tabela ou hook novo foi criado.
- Wiki, LMS e Revista não têm produtor nesta fase e não foram tocados.
- `PublishedParticipationLookupInterface` continua não implementada e não é
  citada como API existente.
