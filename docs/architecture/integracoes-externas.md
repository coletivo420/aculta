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
