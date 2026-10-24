# Contrato Wiki → ACULTA Messenger — avisos editoriais

**Estado:** escopo funcional Wiki v1 decidido; API consumidora aguarda revisão do
owner `aculta_messenger`.
**Escopo:** avisos pessoais no site. Os e-mails transacionais da Wiki continuam
ativos e independentes; este contrato não os substitui.

## Eventos da Wiki

Os eventos são fatos persistidos, publicados pelo `aculta_wiki`. A integração
usa classes imutáveis com `NAME = self::class`, seguindo os eventos do LMS. A
entrega ao Messenger é responsabilidade de um adapter; a Wiki não grava
entidades internas do Messenger nem importa classes de `aculta_lms_messenger`.

| Nome estável | Fato | Destinatários |
| --- | --- | --- |
| `Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent` | Uma revisão padrão de `node:wiki_entry` entrou em `review` por ação editorial, inclusive o primeiro envio e reenvio após ajustes. Não emite ao salvar/revisar algo que já estava em `review`, publicação direta, sincronização ou importação. | Contas ativas com papel `content_editor`, permissão efetiva `edit any wiki_entry content`, transição Wiki editorial e acesso à revisão válida. O adapter revalida a fila e o acesso à revisão; papel sozinho não autoriza abrir o conteúdo. |
| `Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent` | Uma nova revisão padrão da proposta registra `needs_changes`, `declined` ou `accepted`. `accepted` só existe depois que a transação da decisão também aplicou os campos autorizados e publicou a revisão oficial do verbete. | A conta autora indicada por UID, se ativa e ainda autorizada a ver a própria proposta. |

Campos mínimos dos eventos:

| Evento | Payload estável |
| --- | --- |
| `WikiEntryReviewRequestedEvent` | UUID do verbete, ID da revisão enviada. |
| `WikiProposalOutcomeRecordedEvent` | UUID da proposta, ID da revisão de decisão, UID da autoria, estado allowlisted. |

O ID da revisão é a versão do fato e a base de deduplicação; uma nova revisão
submetida ou uma nova decisão gera outro aviso. O listener deve ser idempotente
para a combinação evento + UUID da origem + revisão + destinatário.

O envelope de fila tem `schema_version=1`, `event`, `source_uuid` e
`source_revision_id`; para resultado de proposta, inclui também `recipient_uid`
e `outcome`. A revisão é positiva; o estado de proposta aceita apenas
`needs_changes`, `declined` ou `accepted`. O ID de deduplicação para cada
destinatário é `event name + source UUID + revision ID + recipient UID`.

## Privacidade, acesso e apresentação

- Não incluir título, corpo, fontes, imagem, campos propostos, nota editorial,
  nome/e-mail da autoria, URL livre ou token no payload.
- Usar texto genérico no resumo. O link deve ser produzido pelo adapter com
  rota canônica do Studio/conta para editores ou rota protegida de estado da
  proposta para sua autoria.
- Revalidar Entity Access e o purpose `wiki` na leitura, abertura e marcação de
  lido. Se a conta perdeu acesso, o aviso deixa de aparecer e não conta como
  não lido.
- Não permitir que um UID recebido no evento autorize acesso. Para a fila
  editorial, resolver contas ativas com papel `content_editor`, permissão
  efetiva `edit any wiki_entry content`, transição de revisão e acesso à revisão
  específica através do serviço de fila Wiki. Para propostas, conferir
  propriedade e `view` da revisão da proposta.
- A gravação do aviso não dispara e-mail por si só. Permanecem os e-mails Wiki
  existentes: resultado privado à autoria e aviso de revisão aos endereços
  editoriais configurados. As preferências de e-mail existentes continuam
  propriedade de seus canais.

## Ciclo de vida e semântica de entrega

- Persistir o envelope apenas após a gravação da revisão. Se houver transação
  ativa, inserir a fila nela, para que rollback da decisão também reverta o
  envelope. O worker só consegue reclamar o item depois do commit; em particular
  o resultado da proposta não pode ser publicado enquanto
  `WikiProposalDecision` ainda puder reverter a publicação do verbete.
- Usar fila persistente do Drupal e QueueWorker em cron. Uma exceção do listener
  deixa o item disponível novamente depois do lease; não liberar para retry
  imediato em loop. A fila pode entregar mais de uma vez, portanto o consumidor
  precisa deduplicar.
- O consumidor persiste um aviso por destinatário e versão do evento. Repetir a
  mesma entrega não cria outro aviso; uma decisão posterior é outro evento.
- A API pública do Messenger para registrar avisos ainda não está definida. O
  owner deve fornecer o contrato de escrita/idempotência correspondente aos
  hooks de leitura/contagem já existentes; a integração não deve escrever
  diretamente na entidade privada de avisos do Fórum nem usar a API do adapter
  LMS como se fosse transversal.
- O envio do evento não reverte a decisão editorial já confirmada. Falhas ficam
  registradas e o item continua pendente para retry, sem busy-loop na mesma
  execução de cron.

## Fora desta versão

- Avisos sobre proposta recém-enviada para moderação; o contrato cobre a revisão
  de verbete e os resultados da proposta.
- Avisos de publicação destinados a leitores/seguidores e métricas Statistics;
  estes exigem contratos separados.
- Criação de caixa de mensagens, substituição de Content Moderation ou mudança
  dos e-mails transacionais.

## Estado de implementação

Os value objects dos dois eventos estão implementados em `src/Event/`, com
payload v1 validado por Unit (4 testes / 5 asserções). O owner Wiki mantém os
notifiers de e-mail, mas ainda não enfileira nem emite estes eventos. O
Messenger fornece hooks de apresentação/contagem e sua integração LMS, mas
ainda não há API pública genérica de registro para esta integração. Próximos
passos: revisão do owner Messenger, implementar enqueue pós-save e QueueWorker
retry na Wiki, fechar o contrato de escrita/idempotência do adapter e ligar os
avisos pessoais sem retirar os e-mails atuais.
