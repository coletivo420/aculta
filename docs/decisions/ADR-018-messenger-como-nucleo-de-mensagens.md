# ADR-018 — Messenger como núcleo de mensagens e avisos

**Status:** vigente desde 2026-10-24.
**Escopo:** Portal e módulos produtores de mensagens/avisos, incluindo Fórum,
Wiki, LMS, Revista, Loja e Doações.
**Origem:** `0.1.1-dev.97`, [histórico do Messenger](../history/changelog/aculta_messenger.md), e política F-D21 de avisos do Fórum em [seu histórico arquivado](../history/changelog/aculta_forum.md).

## Contexto

Vários módulos precisavam enviar avisos e e-mails. Implementações isoladas
duplicariam a persistência de mensagens, preferências, idempotência e regras de
acesso; tornar o transporte de e-mail parte de cada produtor também acoplaria
domínios à infraestrutura.

## Decisão

1. `aculta_messenger` centraliza mensagens, avisos e a orquestração de entrega
   por e-mail; módulos de domínio publicam eventos e continuam donos dos dados
   que os originam e dos destinatários elegíveis.
2. Drupal Message/Private Message permanecem a persistência das mensagens;
   não criar uma tabela paralela de inbox.
3. `aculta_mailer` é dono de transporte, templates e infraestrutura de envio.
   Messenger seleciona a política de entrega e delega a chamada ao Mailer.
4. A autorização e o contexto do domínio de origem são revalidados ao exibir e
   enviar; um aviso não concede acesso ao conteúdo de origem.

## Consequências

- Adapters vivem no Messenger, enquanto produtores permanecem em seus módulos
  de domínio.
- Módulos não dependem de implementação concreta do transporte.
- Contratos e limites operacionais detalhados ficam na
  [arquitetura do Messenger](../portal/MESSENGER.md) e em
  [EMAIL-TRANSPORT.md](../portal/EMAIL-TRANSPORT.md).

## Verificação no código

O Messenger persiste avisos como entidades Message e seus workers chamam
`AcultaMailerInterface`; Mailer fornece o serviço de transporte. Adapters de
Wiki, LMS e Fórum mantêm a checagem de acesso no domínio de origem.
