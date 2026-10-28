# Arquitetura do aculta_mailer

`aculta_mailer` é dono dos tipos de mensagem, políticas de entrega, filas,
transportes, templates, registros e supressões. Os módulos produtores fornecem
eventos e conteúdo segundo os contratos do Mailer e do Messenger.

## Decisões vigentes

- **2026-10-20 — A API ACULTA usa Hostinger como transporte primário e SMTP2GO
  como contingência temporária.** Situação: vigente. Motivo: uma falha temporária
  pode ser retentada em outro provedor, enquanto falhas permanentes não devem
  duplicar envios. Origem: `0.1.0-dev.6`, [histórico arquivado](../history/changelog/aculta_mailer.md).
  `TransportFailover` é injetado em `AcultaMailer` e `DeliveryPlanner`; o caminho
  legacy do Core mantém sua configuração de transporte própria, descrita em
  [EMAIL-TRANSPORT.md](../portal/EMAIL-TRANSPORT.md).

## Fronteiras

- O Mailer não decide elegibilidade de destinatários; o Messenger e os
  produtores revalidam política e acesso.
- Credenciais são obtidas pelo provider de segredos; não são armazenadas em
  configuração versionada.
