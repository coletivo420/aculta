# ACULTA Pagamentos Mercado Pago

Gateway Mercado Pago Checkout Pro do Commerce, código próprio do Portal. Substitui o módulo contrib `commerce_mercado_pago` (3.0.0-rc3), que foi portado para cá e removido do projeto. Mesmo padrão do `aculta_portal_cep`.

## O que faz

- Plugin de gateway `aculta_mercadopago_checkout_pro` (classe `CheckoutPro`), tipo de meio de pagamento `aculta_mercadopago_checkout_pro` e formulário de redirecionamento (`RedirectCheckoutForm`).
- Configuração por ambiente (`test`, `stage`, `live`) com chaves de API ligadas a Drupal Key, formulário de parcelamento e exclusão de meios de pagamento.
- Retorno de popup (`/integracoes/mercadopago/checkout-retorno`) que fecha a janela e leva a aba principal ao retorno do pedido.
- Notificações de pagamento por Webhooks modernos assinados. O checkout não força `source_news=ipn`; o Portal valida a assinatura e adapta o payload aceito à interface do Commerce.
- Cobranças recorrentes são consultadas pela API, validadas contra a assinatura/pedido e registradas uma vez por ID remoto, com lock contra entregas concorrentes.
- Biblioteca `aculta_pagamentos_mercadopago/checkout_popup` e `mp_sdk` (SDK JavaScript oficial, `https://sdk.mercadopago.com/js/v2`).

## Método de pagamento único

O único método de pagamento do Portal é `aculta_mercadopago_checkout_pro` (tipo de meio de pagamento e plugin do gateway, classe `CheckoutPro`). O ID do gateway configurado continua `mercado_pago`. A chave de dados do pedido (`commerce_mercado_pago_checkout`) e o caminho canônico de retorno é `/integracoes/mercadopago/checkout-retorno`; o caminho anterior não é mantido como alias e a URL de callback do provedor deve ser atualizada antes de publicar.

## Biblioteca oficial (SDK)

- O SDK é `mercadopago/dx-php`, declarado diretamente no `composer.json` do projeto (`^3`), porque o módulo contrib deixou de existir como intermediário.
- Atualização: `composer update mercadopago/dx-php` (dentro da faixa `^3`) ou `composer require mercadopago/dx-php:^N` para outra major. Antes de subir a versão, conferir o changelog oficial do SDK (`github.com/mercadopago/sdk-php`) e rodar o teste de navegador do checkout no Runtime de teste.
- A integração envia o identificador de integrador do SDK (`CheckoutPro::INTEGRATOR_ID`); não alterar sem conferir a documentação oficial.

## Dependências

- `commerce:commerce_payment`, `address:address` (estrutura do endereço de cobrança) e `key:key` (segredos).
- Pacote Composer: `mercadopago/dx-php`.
- Não depende do `aculta_portal`. O `aculta_portal` depende deste submódulo.

## Credenciais

Tokens e chaves públicas ficam na configuração do gateway, por ambiente, e são referenciados por Drupal Key com provider `env`. Nenhum segredo fica no código ou no repositório. Ver `docs/integrations/MERCADOPAGO.md` e o ACULTA Secrets Contract.

## Atribuição

Porte de `drupal/commerce_mercado_pago` 3.0.0-rc3 (GPL-2.0-or-later). Licença em `LICENSE.txt` e documentação original em `README.original-commerce_mercado_pago.md`.

## Validação

- Suíte Unit: `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/tests/src/Unit`.
- Regressões Checkout Pro: cobrem payload inválido, status remoto pendente, referências conflitantes, retorno adulterado e retorno aprovado já processado sem duplicar pagamento. Nenhuma credencial ou chamada externa é usada.
- Testes Kernel do porte em `tests/src/Kernel` (rodar somente no banco existente `aculta_simpletest`, conforme `docs/operations/BANCO-DE-TESTE.md`).
- Homologação visual X11 deve usar Firefox ESR visível e geckodriver; consultar `tests/browser/README.md` e `docs/operations/NAVEGADOR-TESTES.md`. Smoke público não comprova aprovação de pagamento nem sandbox.
- Gate de fronteira: `php scripts/validate-module-theme-boundary.php`.
