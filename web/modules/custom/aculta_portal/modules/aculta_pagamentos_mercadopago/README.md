# ACULTA Pagamentos Mercado Pago

Gateway Mercado Pago Checkout Pro do Commerce, código próprio do Portal. Substitui o módulo contrib `commerce_mercado_pago` (3.0.0-rc3), que foi portado para cá e removido do projeto. Mesmo padrão do `aculta_portal_cep`.

## O que faz

- Gestão de planos da Conta em `/conta/mercado-pago/planos` no purpose `account`; a submissão usa `/integracoes/mercadopago/planos` no MAIN. Lista, cria e atualiza modelos remotos somente por gateway ativo em sandbox; requer `access aculta portal` e `administrar doacoes`, sem armazenamento paralelo. A URL antiga `/studio/conta/mercado-pago/planos` não possui alias.

- Plugin de gateway `aculta_mercadopago_checkout_pro` (classe `CheckoutPro`), tipo de meio de pagamento `aculta_mercadopago_checkout_pro` e formulário de redirecionamento (`RedirectCheckoutForm`). O gateway associa pagamentos ao tipo Commerce `aculta_mercadopago`, cujo workflow inclui `pending` para aguardarem a confirmação do provedor.
- Configuração nativa do gateway com modo e opções de pagamento; as chaves são fornecidas exclusivamente pelo ambiente ativo e não são editáveis nem exibidas no formulário. O antigo controle `autofee` foi removido: o gateway não calcula taxas de adquirência, e ao salvar a configuração limpa o valor legado sem efeito.
- O gateway permanece ativo em todos os ambientes; sem o par de credenciais daquele ambiente, a criação do checkout falha fechada antes de chamar o Mercado Pago.
- O `config/sync` do gateway aceita os tipos de pedido Commerce `curso`, `default` e `doacao`, que foram conferidos com o Runtime ativo em 2026-10-28. Nenhuma importação foi necessária; o restante do `config:status` contém divergências fora do escopo Mercado Pago.
- Retorno de popup (`/integracoes/mercadopago/checkout-retorno`) que fecha a janela e leva a aba principal ao retorno do pedido. Retornos e notificações adquirem o lock compartilhado pelo ID remoto antes de consultar pagamento e Merchant Order e o mantêm até a conciliação Commerce; lock ocupado não inicia consulta. Ambos exigem que os IDs de pagamento e Merchant Order devolvidos pela API correspondam aos IDs consultados, e não associam um ID de pagamento já usado por outro pedido Commerce. Uma referência de Merchant Order presente, mas malformada na resposta da API, causa retry e nunca cai para associação apenas por `external_reference`; no retorno, uma referência externa do pagamento divergente do pedido local também é rejeitada antes do storage Commerce. `pending`, `in_process` e `authorized` são registrados como pendentes e atualizam `remote_state` sem regredir pagamentos concluídos, `cancelled` e `rejected` encerram o pagamento Commerce pendente como `authorization_voided`, e só `approved` conclui o pagamento.
- Os valores de pagamento, estorno e cobrança recorrente passam por `MercadoPagoAmount`, que consulta os dígitos fracionários ISO da moeda, aceita notação científica, compara com BCMath e rejeita precisão menor que a unidade monetária ou formatos/expoentes excessivos antes da persistência, no refresh, webhook, retorno e solicitação de estorno. A conversão para `float` ocorre só na chamada do SDK de estorno, cuja assinatura exige esse tipo.
- Notificações de pagamento, assinatura e contestação por Webhooks modernos assinados. IDs de fatura `subscription_authorized_payment` precisam ser numéricos positivos, conforme [contrato oficial do endpoint de faturas](https://www.mercadopago.com.br/developers/pt/reference/online-payments/subscriptions/get-authorized-payment/get). Em `topic_chargebacks_wh`, o módulo busca o caso usando o ID assinado, associa pelo `payment_id` retornado pela API, consulta e concilia o pagamento Commerce existente. Falhas temporárias respondem 500 para reentrega; handler de assinatura ausente responde 503, sem consumir o evento; não se confia no `payment_id` do corpo não assinado. O checkout não força `source_news=ipn`.
- Preferências mantêm `binary_mode: false`, permitindo que o Mercado Pago conclua pagamentos pendentes ou em análise antes da confirmação no Commerce.
- Criação da assinatura de doação mensal é serializada por pedido; um retry consulta e reutiliza a assinatura pendente somente após validar ID e referência do pedido. A resposta de criação não sofre coerção de IDs/status/URLs; um `init_point` não vazio deve ser HTTPS em `mercadopago.com`, `mercadopago.com.br` ou `mercadopago.com.ar` antes de ser enviado ao comprador. Uma resposta remota ambígua ou malformada mantém um marcador no `data` do pedido e bloqueia novos POSTs até conciliação manual, evitando duplicidade quando não há resposta nem busca remota por referência. Cobranças recorrentes são consultadas pela API, validadas contra a assinatura/pedido e registradas uma vez por ID remoto, com lock contra entregas concorrentes. A homologação com comprador sandbox segue pendente (DT-MP-02/DT-MP-03).
- A API de planos de assinatura oferece criação com ID de intenção estável, consulta, atualização idempotente e busca paginada (1–100 resultados), com validação dos IDs remotos. A gestão operacional é apresentada pelo ACULTA Studio.
- Ações e webhooks de assinatura usam lock por ID remoto e recarregam o pedido. O campo `mp_subscription_version`, no `data` de Commerce já existente, impede que eventos atrasados revertam a situação; estado conflitante na mesma versão é rejeitado para retry. Se o webhook da assinatura chegar durante a criação remota antes de salvar o ID no pedido, o serviço devolve retryable em vez de confirmar e descartar o evento. Ações validam ID, referência externa, status e avanço de versão antes de persistir.
- Retry de ação já aplicada reconcilia status/versão sem repetir `PUT`; o saldo e a moeda das cobranças precisam corresponder ao pedido. Doações não aceitam estorno.
- Estornos usam cliente criado com as credenciais do gateway ativo, lock por pagamento e chave idempotente por total acumulado. Só atualizam Commerce após resposta remota `approved` com ID de estorno positivo e `payment_id` correspondente ao pagamento; erros remotos não são registrados com corpo ou mensagem privada.
- Reconciliação manual e cancelamento também exigem que a `external_reference` remota identifique o mesmo pedido Commerce antes de alterar o estado local.
- Preferências calculam os itens com o total ajustado de cada linha e incorporam ajustes gerais para que produtos, promoções e taxas coincidam com o total Commerce; o frete é enviado separadamente. O checkout falha antes da API se os valores não puderem ser representados com segurança.
- Biblioteca `aculta_pagamentos_mercadopago/checkout_popup` e `mp_sdk` (SDK JavaScript oficial, `https://sdk.mercadopago.com/js/v2`).

## Método de pagamento único

O único método de pagamento do Portal é `aculta_mercadopago_checkout_pro` (tipo de meio de pagamento e plugin do gateway, classe `CheckoutPro`). O ID do gateway configurado continua `mercado_pago`. A chave de dados do pedido (`commerce_mercado_pago_checkout`) e o caminho canônico de retorno é `/integracoes/mercadopago/checkout-retorno`; o caminho anterior não é mantido como alias e a URL de callback do provedor deve ser atualizada antes de publicar.

## Biblioteca oficial (SDK)

- O SDK é `mercadopago/dx-php`, declarado diretamente no `composer.json` do projeto (`^3`), porque o módulo contrib deixou de existir como intermediário.
- Atualização: `composer update mercadopago/dx-php` (dentro da faixa `^3`) ou `composer require mercadopago/dx-php:^N` para outra major. Antes de subir a versão, conferir o changelog oficial do SDK (`github.com/mercadopago/sdk-php`) e rodar o teste de navegador do checkout no Runtime de teste.
- A integração envia o identificador de integrador do SDK (`CheckoutPro::INTEGRATOR_ID`); não alterar sem conferir a documentação oficial.

## Dependências

- `commerce:commerce_payment`, `address:address` (estrutura do endereço de cobrança) e `key:key` (segredos).
- `aculta_portal:aculta_portal` (contratos e rotas compartilhados). O módulo base `aculta_portal` não depende deste submódulo; Loja, LMS e doações consomem o gateway compartilhado por seus contratos próprios.
- Pacote Composer: `mercadopago/dx-php`.

## Credenciais

Tokens e chaves públicas vêm das variáveis de ambiente declaradas em `MercadoPagoCredentials`; o override injeta os valores em memória. O hook de pré-save mantém o gateway ativo e limpa campos de credencial antes de qualquer gravação da entidade Commerce, para que nem um `save()` administrativo persista chaves no config ativo. Salvar configuração não depende da presença de segredos; operações financeiras sem token falham fechadas antes da chamada remota. Nenhum segredo fica no código, no config exportado ou no repositório. Ver `docs/integrations/MERCADOPAGO.md` e o ACULTA Secrets Contract.

## Atribuição

Porte de `drupal/commerce_mercado_pago` 3.0.0-rc3 (GPL-2.0-or-later). Licença em `LICENSE.txt` e documentação original em `README.original-commerce_mercado_pago.md`.

## Validação

- Suíte Unit: `vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/aculta_portal/modules/aculta_pagamentos_mercadopago/tests/src/Unit`.
- Regressões Checkout Pro, operações e contestações: cobrem payload inválido, estados pendente/aprovado/rejeitado/cancelado, referências conflitantes, retorno adulterado, idempotência, falhas API/transporte fail-closed, estornos parcial/total repetidos e concorrentes, seller `X-Caller-Id` da consulta de contestação, associação única pelo caso retornado na API e retry para erro transitório. Nenhuma credencial ou chamada externa é usada.
- Testes Kernel do porte em `tests/src/Kernel` (rodar somente no banco existente `aculta_simpletest`, conforme `docs/operations/BANCO-DE-TESTE.md`).
- Execução 2026-10-28: Unit MP 258 testes / 1.156 assertions PASS (1 depreciação PHPUnit); Unit Studio 62/502 PASS; PHPCS Drupal/DrupalPractice e PHP lint focais PASS. Firefox ESR/X11 confirmou tela Studio em ACULTA420, listagem remota, labels, POST para o domínio MAIN e reflow em 390/1280 px; uma atualização do plano sandbox existente foi confirmada por consulta remota. `CheckoutProTest` 3/135 PASS em execução anterior; nesta sessão o Kernel não pôde ser repetido porque o banco preexistente `aculta_simpletest` não está disponível e não foi criado. Aprovações, pagamentos, webhooks, estornos e recorrência ainda não foram homologados com o comprador.
- Homologação visual X11 deve usar Firefox ESR visível e geckodriver; consultar `tests/browser/README.md` e `docs/operations/NAVEGADOR-TESTES.md`. Smoke público não comprova aprovação de pagamento nem sandbox.
- `tests/browser/aculta-mercadopago/checkout-smoke.py` carregou produto, carrinho, cadastro AJAX e etapa de endereço/frete no servidor de teste. Não envia endereço, não cria conta nem conclui pedido; preferência e gateway continuam sem evidência visual ponta a ponta.
- Reexecução visual em 2026-10-27 após remover `required` e o form aninhado do simulador: `guest_registration_and_shipping_stage_without_captcha`. Produto, carrinho e cadastro AJAX passaram; Google aparece antes do e-mail, e o fluxo chega à etapa de endereço sem CAPTCHA. Não enviou endereço, cotação de frete nem pagamento; a preferência do Mercado Pago não foi criada.
- Reexecução visual em 2026-10-27 no Firefox ESR visível: produto, adição ao carrinho, carrinho compartilhado, início do checkout e cadastro AJAX para e-mail `.invalid` alcançaram a etapa de endereço sem CAPTCHA. O smoke criou o pedido anônimo de rascunho 220 com um item e o removeu ao terminar; não enviou endereço, não cotou frete, não criou conta nem iniciou preferência/pagamento. O gateway Mercado Pago estava ativo e o módulo habilitado no Runtime durante a verificação somente leitura.
- Preferência sandbox direta pelo client factory criou `init_point`; Firefox ESR abriu “Escolha como pagar” (HTTP 200, sem CAPTCHA) e a etapa seguinte de identificação (HTTP 200). Autenticação autorizada a partir do checkout terminou `login_incomplete`; nenhuma sessão ou pagamento foi confirmado. Isso não executa `RedirectCheckoutForm` nem comprova pedido Commerce.
- Tentativa anterior de autenticação na página principal do Mercado Pago terminou em `manual_security_challenge`; nenhum desafio foi contornado.
- Gate de fronteira: `php scripts/validate-module-theme-boundary.php`.
