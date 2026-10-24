# Contrato Wiki → ACULTA Messenger — avisos editoriais

**Estado:** eventos e fila Wiki implementados; consumer e integração de entrega
ao Messenger ainda pendentes.
**Escopo:** avisos pessoais obrigatórios no site e canais de e-mail governados
por preferência da pessoa (desativado, direto ou resumo/digest) e política
administrativa do ACULTA Mailer. O estado atual ainda contém e-mails legados;
eles só serão removidos junto da migração comprovada para evitar duplicação.

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
- A gravação do aviso não envia SMTP diretamente. Após a migração, e-mail direto
  ou resumo usa somente ACULTA Mailer e a preferência compartilhada. O aviso no
  site continua mesmo sem e-mail.

## Ciclo de vida e semântica de entrega

- Persistir o envelope apenas após a gravação da revisão. Se houver transação
  ativa, inserir a fila nela, para que rollback da decisão também reverta o
  envelope. O worker só consegue reclamar o item depois do commit; em particular
  o resultado da proposta não pode ser publicado enquanto
  `WikiProposalDecision` ainda puder reverter a publicação do verbete.
- Provisionar a tabela compartilhada Core `queue` no install/update da Wiki,
  antes de qualquer transação editorial. O backend DatabaseQueue cria a tabela
  sob demanda na primeira inserção; DDL MariaDB dentro da decisão pode confirmar
  implicitamente operações que deveriam sofrer rollback.
- Usar fila persistente do Drupal e QueueWorker em cron. Uma exceção do listener
  deixa o item disponível novamente depois do lease; não liberar para retry
  imediato em loop. A fila pode entregar mais de uma vez, portanto o consumidor
  precisa deduplicar.
- O consumidor persiste um aviso por destinatário e versão do evento. Repetir a
  mesma entrega não cria outro aviso; uma decisão posterior é outro evento.
- `MessengerNoticePublisherInterface` publica envelopes para consumidores; ele
  não persiste avisos por si só. O Messenger ainda precisa publicar um contrato
  genérico para registro/idempotência e leitura/contagem. A Wiki não deve gravar
  diretamente na entidade `aculta_aviso` específica do Fórum nem usar a API de
  registro do adapter LMS como se fosse transversal.
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
payload v1 validado por Unit (4 testes / 5 asserções). Dev.113 adiciona enqueue
nas transições de revisão/decisão e QueueWorker com Queue API confiável. Dev.114
adiciona `aculta_wiki_messenger`: consumer produtor valida revisão e destinatário,
consumer Messenger persiste em Message com lock/deduplicação, Hook integra
central/contador, acesso é revalidado na abertura e resumo; worker direto usa
ACULTA Mailer e revalida preferência. Unit: 3 testes / 20 assertions PASS;
PHPCS Drupal/DrupalPractice PASS. O
install/update 10002 prepara a tabela Queue antes das transações; Kernel MariaDB
isolado confirmou que rollback também remove o item (1/8). Uma
exceção do consumer deixa o item reclamável após lease de 60 segundos; não há
limite de tentativas nem retry imediato. Sem listener registrado, o worker lança
exceção e conserva o item. O submódulo ainda não foi habilitado em Runtime nem
validado por Kernel completo. Ao habilitá-lo, ele suprime os dois notifiers
legados; quando permanece desabilitado, os fluxos legados continuam ativos.
