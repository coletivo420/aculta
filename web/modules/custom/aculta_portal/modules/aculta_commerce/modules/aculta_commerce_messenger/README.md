# ACULTA Commerce Messenger

Adapter de eventos de pedidos da Loja para o ACULTA Messenger. O submódulo
ouve a transição de pedido `place` do Drupal Commerce para o tipo configurado
como Loja em `CheckoutSettings`, publica `store.order_placed` no contrato
compartilhado e consome o evento em `Message`, sem criar inbox ou armazenamento
paralelo.

Avisos na central do site são obrigatórios para clientes registrados ativos.
Para e-mail, a categoria `loja` respeita a escolha do usuário (desativado,
direto ou resumo diário) e a política administrativa do ACULTA Mailer. A
preferência administrativa nativa do tipo de pedido para recibo é respeitada.
Quando o evento compartilhado é aceito, o recibo nativo duplicado do Commerce é
suprimido; o recibo nativo continua para pedidos de convidados e como fallback
se a publicação falhar. Reenvio administrativo de recibos continua sendo
operação explícita do Commerce.

A abertura do aviso confirma novamente propriedade do pedido, tipo de pedido e
acesso atual. O link leva à visualização do pedido do cliente, no propósito
`shop`. Doações e pedidos do LMS não são consumidos.

## Estado

Dev.1 implementa publisher, consumer, persistência idempotente em `Message`,
central/contador, digest, e-mail direto com Mailer e revalidação de acesso.
Testes Unit cobrem roteamento do envelope e modos direto/desativado, mas ainda
não há prova Kernel do container, fila, configuração instalada ou rotas. Runtime
e envio real não foram executados. Ver [roadmap de Commerce](../../docs/ROADMAP.md)
e [roadmap de integração](../../../aculta_messenger/docs/ROADMAP.md).
