# ACULTA UGC (`aculta_ugc`)

Módulo transversal para gestão e consolidação de Conteúdo Gerado por Usuários (UGC), denúncias e moderação entre purposes e entidades.

## Capacidades

- **Entidade `aculta_ugc_report`**: Registra sinalizações e denúncias protegendo estritamente a identidade e privacidade do denunciante.
- **Entidade `aculta_ugc_recipients`**: Mapeia destinatários e filas de moderação por purpose (`main`, `account`, `support`, etc.) de forma desacoplada e configurável.
- **Serviço de Notificação (`aculta_ugc.moderation_notifier`)**: Despacha avisos a moderadores sem expor o e-mail ou dados do denunciante.
- **Fila Unificada (`aculta_ugc.report_queue`)**: Agrupa denúncias por entidade alvo e processa a fila de moderação de forma idempotente e segura contra abuso.
