# Integrações externas: caminhos de retorno

Regra do projeto: toda autorização OAuth e todo retorno de integração externa usam o caminho

    /integracoes/<nome da integração>/<ação>

Exemplos:

| Integração | Autorização | Retorno OAuth |
| --- | --- | --- |
| Melhor Envio | `/integracoes/melhorenvio/autorizar` | `/integracoes/melhorenvio/retorno` |
| Mercado Pago (retorno do checkout) | — | `/integracoes/mercadopago/checkout-retorno` |
| Google (e demais redes sociais) | `/integracoes/login/google` | `/integracoes/google/retorno` |

Regras:

1. Integração nova nasce no padrão. Não criar `/commerce-...`, `/painel-administrativo/...` ou outro prefixo para retorno externo.
2. O nome `<nome da integração>` é minúsculo, sem acento e sem hífen (`melhorenvio`, `mercadopago`, `google`).
3. A URL de retorno precisa estar cadastrada no console do provedor com a mesma forma (esquema, host e caminho).
   Trocar o caminho de uma integração existente quebra o retorno até o console ser atualizado.
4. Retorno OAuth valida o parâmetro `state` antes de trocar o código (ver `OAuthState` no módulo do Melhor Envio).
5. **Proibido caminho administrativo em qualquer chamada de integração.** Isso vale para todas as chamadas, não só para o retorno OAuth:
   - ida (autorização, popup), retorno e webhook;
   - páginas de configuração e de estado da integração (ex.: origem da expedição, conexão);
   - endpoints JSON e AJAX que a integração expõe ao site.

   Nenhuma dessas rotas usa `/admin/...` nem `/painel-administrativo/...`. Motivo: o módulo `rename_admin_paths`
   (habilitado, `admin_path_value: painel-administrativo`) reescreve todo `/admin/...` para `/painel-administrativo/...`
   em tempo de build (prioridade -2048), e o `DomainRouteSubscriber` traduz os segmentos para português. Um caminho de
   integração assim muda sem aviso e quebra o que está cadastrado no provedor.
6. Página administrativa de uma integração fica em `/integracoes/<nome>/<ação>` e usa `_admin_route: TRUE` (tema
   administrativo) com a permissão própria da integração. O prefixo de tema vem da opção, não do caminho.
7. Rota pública de loja que consulta uma integração (ex.: simulador de frete) não é de integração: usa o prefixo da
   loja (`/frete/...`), nunca `/admin` nem `/painel-administrativo`.

Rotas atuais do Melhor Envio (conferir com `drush ev` ao alterar):

| Função | Caminho | Permissão |
| --- | --- | --- |
| Estado e conexão (página administrativa) | `/integracoes/melhorenvio/configuracao` | `administer melhorenvio` |
| Origem da expedição (formulário) | `/integracoes/melhorenvio/configuracao/origem` | `administer melhorenvio` |
| Autorização (popup) | `/integracoes/melhorenvio/autorizar` | `administer melhorenvio` |
| Retorno OAuth | `/integracoes/melhorenvio/retorno` | `administer melhorenvio` |
| Webhook (POST, sem sessão) | `/integracoes/melhorenvio/webhook` | `_access: TRUE`, valida assinatura |
| Simulador de frete (JSON, loja) | `/frete/simular` | `access content` |

Ambiente de teste: host `https://aculta.toca.net.br`. Produção: `https://aculta.org`, com o mesmo caminho.

## Padrão de ida e retorno de integrações (popup)

Regra do projeto, para Melhor Envio, Mercado Pago, Google e qualquer integração futura:

- **Ida:** a plataforma externa abre num popup. Link com `data-aculta-popup="<nome-da-janela>"` e a biblioteca `aculta_portal/integration-popup`. Se o navegador bloquear o popup, o link segue na mesma aba. Não usar `noopener`: o retorno precisa de `window.opener`.
- **Retorno:** a rota de retorno grava o resultado nas **mensagens do Drupal** (`messenger`: `addStatus` para sucesso, `addError` para falha) e responde com `Drupal\aculta_portal\Integration\PopupReturnPage::response($request, $destino)`. Essa página manda a janela de origem ao destino e fecha o popup; sem popup, a própria aba vai ao destino. O tema exibe a mensagem no destino.
- **Destino:** caminho do próprio site (de preferência a página da integração, que mostra o estado atualizado). Qualquer outro valor cai na página inicial.
- **Cache:** rotas de autorização e de retorno têm `options: no_cache: TRUE`. Redirecionamento externo com `TrustedRedirectResponse` precisa de `max-age` 0, porque essa classe é cacheável por padrão e o cache serviria um `state` já consumido.
- Textos de mensagem passam por `t()` e não expõem tokens, códigos ou detalhes técnicos. Detalhes vão para o log do canal da integração, com hash em vez do valor.
- **Webhooks de serviço** (chamados pela plataforma externa) respondem só com o código HTTP adequado e corpo vazio ou mínimo.

Situação atual:

| Integração | Ida | Retorno | Situação |
| --- | --- | --- | --- |
| Melhor Envio | `/integracoes/melhorenvio/configuracao` (estado; botão em popup) → `/integracoes/melhorenvio/autorizar` | `/integracoes/melhorenvio/retorno` | Conforme. |
| Mercado Pago | Popup do Checkout Pro (`checkout_popup.js`, próprio do módulo) | `/integracoes/mercadopago/checkout-retorno` | Retorno usa `PopupReturnPage`. A ida ainda usa o script próprio; migrar para `integration-popup` é pendência. As mensagens do pagamento vêm do retorno do Commerce. |
| Google | Link de login do Social Auth | `/integracoes/google/retorno` (Social Auth) | Pendente de revisão: verificar se a ida pode ser popup e se as mensagens são as do Drupal. |
