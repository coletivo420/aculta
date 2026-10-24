# ACULTA Wiki Messenger

Adapter entre eventos editoriais da Wiki e o ACULTA Messenger. O submódulo
consome revisões enviadas à fila e resultados de propostas, revalida editor,
autoria, revisão e acesso, e persiste avisos em entidades Message. Não cria
inbox nem storage de notificação separado.

Avisos no site são obrigatórios. A pessoa escolhe e-mail desativado, resumo
diário ou envio direto na página de preferências do Messenger; o ACULTA Mailer
aplica políticas administrativas. O worker direto revalida preferência e
acesso antes de enviar texto mínimo. O digest usa o provider central do
Messenger.

Quando habilitado, o adapter impede que a Wiki também envie pelos antigos
notifiers independentes. Se o adapter estiver desabilitado, a Wiki preserva o
comportamento legado para evitar perda de avisos durante migração gradual.

## Estado e validação

Dev.1. Unit: 3 testes / 20 assertions PASS; PHPCS Drupal/DrupalPractice e YAML
PASS. Kernel de persistência/acesso e Runtime não executados. Nenhum e-mail real
foi enviado. Testes de replay, ordem concorrente, revogação entre fila e leitura,
preferência de e-mail e política Mailer continuam gates para a habilitação no
Runtime.
