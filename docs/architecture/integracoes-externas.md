# Integrações externas: caminhos de retorno

Regra do projeto: toda autorização OAuth e todo retorno de integração externa usam o caminho

    /integracoes/<nome da integração>/<ação>

Exemplos:

| Integração | Autorização | Retorno OAuth |
| --- | --- | --- |
| Melhor Envio | `/integracoes/melhorenvio/autorizar` | `/integracoes/melhorenvio/retorno` |
| Mercado Pago (retorno do checkout) | — | `/integracoes/mercadopago/checkout-retorno` |
| Google (e demais redes sociais) | `/user/login/google` | `/integracoes/google/retorno` |

Regras:

1. Integração nova nasce no padrão. Não criar `/commerce-...`, `/painel-administrativo/...` ou outro prefixo para retorno externo.
2. O nome `<nome da integração>` é minúsculo, sem acento e sem hífen (`melhorenvio`, `mercadopago`, `google`).
3. A URL de retorno precisa estar cadastrada no console do provedor com a mesma forma (esquema, host e caminho).
   Trocar o caminho de uma integração existente quebra o retorno até o console ser atualizado.
4. Retorno OAuth valida o parâmetro `state` antes de trocar o código (ver `OAuthState` no módulo do Melhor Envio).

Ambiente de teste: host `https://aculta.toca.net.br`. Produção: `https://aculta.org`, com o mesmo caminho.

## Resposta ao usuário no retorno de integrações

Regra do projeto, para Melhor Envio, Mercado Pago, Google e qualquer integração futura:

- Retornos de navegador (autorização, retorno de login, retorno de pagamento) **não** devolvem texto puro nem tela própria. O resultado vai para as **mensagens do Drupal** (`messenger`: `addStatus` para sucesso, `addError` para falha), e a resposta redireciona para a página inicial, onde o tema exibe o bloco de mensagens (`aculta_messages`, região `highlighted`).
- Textos de mensagem passam por `t()` e não expõem tokens, códigos ou detalhes técnicos. Detalhes vão para o log do canal da integração, com hash em vez do valor.
- Respostas de **serviço** (webhooks chamados pela plataforma externa) respondem só com o código HTTP adequado e corpo vazio ou mínimo, pois não há usuário para ler a página.
- Nova integração segue este padrão desde o início; retorno que ainda não segue a regra deve ser registrado como pendência, não copiado.

Situação atual:

| Integração | Retorno de navegador | Situação |
| --- | --- | --- |
| Melhor Envio | `/integracoes/melhorenvio/retorno` e `/autorizar` | Conforme (mensagens do Drupal + redirecionamento). |
| Google | `/integracoes/google/retorno` (Social Auth) | Mensagens do próprio Social Auth; verificar se o texto é o do Drupal. Pendente de revisão. |
| Mercado Pago | `/integracoes/mercadopago/checkout-retorno` | Página de popup que fecha a janela e avisa a origem. Fora da regra até decisão do responsável, por envolver o fluxo de pagamento. |
