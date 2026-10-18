# ACULTA Pagamentos Mercado Pago

Gateway Mercado Pago Checkout Pro do Commerce, código próprio do Portal. Substitui o módulo contrib `commerce_mercado_pago` (3.0.0-rc3), que foi portado para cá e removido do projeto. Mesmo padrão do `aculta_portal_cep`.

## O que faz

- Plugin de gateway `mercado_pago_checkout_pro` (classe `CheckoutPro`), tipo de meio de pagamento `mercado_pago_checkout_pro` e formulário de redirecionamento (`RedirectCheckoutForm`).
- Configuração por ambiente (`test`, `stage`, `live`) com chaves de API ligadas a Drupal Key, formulário de parcelamento e exclusão de meios de pagamento.
- Retorno de popup (`/commerce-mercado-pago/checkout-return`) que fecha a janela e leva a aba principal ao retorno do pedido.
- Biblioteca `aculta_pagamentos_mercadopago/checkout_popup` e `mp_sdk` (SDK JavaScript oficial, `https://sdk.mercadopago.com/js/v2`).

## Por que IDs estáveis

Os IDs de plugin (`mercado_pago_checkout_pro`), a chave de dados do pedido (`commerce_mercado_pago_checkout`) e o caminho `/commerce-mercado-pago/checkout-return` foram mantidos iguais ao contrib. Pedidos, pagamentos, gateway configurado e retornos já enviados ao Mercado Pago continuam válidos. Só o módulo, o namespace PHP, os nomes de rota e de biblioteca e o canal de log mudaram.

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

- Lint PHP e YAML do submódulo.
- Testes Kernel do porte em `tests/src/Kernel` (rodar no banco `aculta_simpletest`, conforme `docs/operations/BANCO-DE-TESTE.md`).
- Gate de fronteira: `php scripts/validate-module-theme-boundary.php`.
