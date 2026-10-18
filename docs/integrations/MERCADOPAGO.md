# Mercado Pago

Gateway de pagamento do Commerce: submódulo próprio `aculta_pagamentos_mercadopago` (porte do `commerce_mercado_pago` 3.0.0-rc3; plugin `mercado_pago_checkout_pro`).
Até a versão 1.0, o gateway é **habilitado com chaves de teste** (`MERCADOPAGO_TEST_PUBLIC_KEY` e
`MERCADOPAGO_TEST_ACCESS_TOKEN`, por variável de ambiente; nunca na configuração). O gateway fica ativo
quando o ambiente tem o access token correspondente (regra do `aculta_deployer`). Chaves de produção só
entram por decisão do responsável.

## Credenciais

Documentação oficial: [Credenciais](https://www.mercadopago.com.br/developers/pt/docs/your-integrations/credentials),
[Notificações (webhooks)](https://www.mercadopago.com.br/developers/pt/docs/your-integrations/notifications/webhooks).
O README original do upstream (`aculta_pagamentos_mercadopago/README.original-commerce_mercado_pago.md`) também indica Public Key e Access Token como as credenciais necessárias.

| Credencial | Uso | Ambiente | Variável (Key) |
| --- | --- | --- | --- |
| Public Key | frontend (meios de pagamento, criptografia dos dados do cartão) | teste e produção, valores diferentes | `MERCADOPAGO_TEST_PUBLIC_KEY` / `MERCADOPAGO_PRODUCTION_PUBLIC_KEY` |
| Access Token | backend (gera pagamentos); chave privada | teste e produção, valores diferentes | `MERCADOPAGO_TEST_ACCESS_TOKEN` / `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN` |
| Assinatura secreta do webhook | valida a `x-signature` das notificações | aplicação (uma para a aplicação, não por ambiente) | `MERCADOPAGO_WEBHOOK_SECRET` |

Observações:
- Client ID e Client Secret não são usados pela integração Checkout Pro: o Public Key e o Access Token bastam.
  Não entram no contrato nem no painel. Só existem no formulário de configuração do módulo, que os deixa vazios.
- Credenciais de teste não precisam de ativação. Credenciais de produção exigem ativação na aplicação.
- Assinatura do webhook (documentação oficial de notificações): a chave é **por aplicação** ("assinatura
  secreta exclusiva para a sua aplicação"). Ela aparece em **Suas integrações > aplicação > Webhooks >
  Configurar notificações**, e só é gerada depois de salvar a configuração de notificações. Por isso não
  aparece antes da configuração. Para renovar, use o botão de redefinição ao lado da assinatura.
- A validação da `x-signature` é feita pelo SDK oficial (`mercadopago/dx-php`, ver `WebhookGuard`): manifesto
  `id:{data.id};request-id:{x-request-id};ts:{ts};` assinado com HMAC-SHA256 pela chave da aplicação.
- A página de notificações pede URLs de recebimento distintas para teste e produção. Cada URL é usada com as
  credenciais correspondentes ao seu ambiente.

## Ambiente e modo do gateway

O ambiente vem do ACULTA Deployer (`var/deployer/environment.json`). Nenhuma parte do código troca de ambiente.

| Ambiente (Deployer) | Modo do gateway | Campos preenchidos a partir de |
| --- | --- | --- |
| `test` | `test` (conta de teste) | `MERCADOPAGO_TEST_PUBLIC_KEY`, `MERCADOPAGO_TEST_ACCESS_TOKEN` |
| `production` | `live` (conta e credenciais de produção) | `MERCADOPAGO_PRODUCTION_PUBLIC_KEY`, `MERCADOPAGO_PRODUCTION_ACCESS_TOKEN` |

- O modo `stage` do módulo (conta de produção com credenciais de teste) não é usado.
- Os valores entram por `MercadoPagoEnvironmentOverride` (`aculta_portal`) em memória. Não são exportados
  nem gravados na configuração do gateway.
- Campo ausente fica vazio no gateway. Com a validação `EntitySaveHooks` (`entity_presave`), o gateway não pode
  ser ativado num ambiente sem Public Key e Access Token do modo correspondente.

## Dados de teste

Fonte: [Realizar compras de teste](https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/integration-test/test-purchase).

### Cartões de teste (Brasil)

| Tipo | Bandeira | Número | CVV | Validade |
| --- | --- | --- | --- | --- |
| Crédito | Mastercard | 5480 8328 0103 3311 | 123 | 11/30 |
| Crédito | Visa | 4235 6477 2802 5682 | 123 | 11/30 |
| Crédito | American Express | 3753 651535 56885 | 1234 | 11/30 |
| Débito | Elo | 5067 7667 8388 8311 | 123 | 11/30 |

### Status de pagamento (nome do titular do cartão)

Para simular o resultado, use o status no nome do titular. Documento de teste (CPF): `12345678909`.

| Código | Resultado |
| --- | --- |
| APRO | Pagamento aprovado |
| OTHE | Recusado por erro geral |
| CONT | Pagamento pendente |
| CALL | Recusado com validação para autorizar |
| FUND | Recusado por quantia insuficiente |
| SECU | Recusado por código de segurança inválido |
| EXPI | Recusado por problema com a data de vencimento |
| FORM | Recusado por erro no formulário |
| CARD | Rejeitado por falta de card_number |
| INST | Rejeitado por parcelas inválidas |
| DUPL | Rejeitado por pagamento duplicado |
| LOCK | Rejeitado por cartão desabilitado |
| CTNA | Rejeitado por tipo de cartão não permitido |
| ATTE | Rejeitado por excesso de tentativas de PIN |
| BLAC | Rejeitado por estar na lista negra |
| UNSU | Não suportado |
| TEST | Usado para aplicar regra de valores |

### Usuário de teste comprador

| Campo | Valor |
| --- | --- |
| Nome | Test User |
| País | Brasil |
| User ID | 373592987 |
| Usuário (login) | TESTUSER5593837592941390773 |
| Senha | **não versionada**: fica na variável `MERCADOPAGO_TEST_BUYER_PASSWORD`, no painel "Credenciais do ambiente", seção Ambiente de Testes |
| Código de verificação | **não guardado**: é temporário e vem do painel da conta de teste (Suas integrações > aplicação > Contas de teste). Para Pix ou Boleto, são os últimos 6 dígitos do User ID |

#### Comprador criado por API (2026-10-16): não autorizado

Foi criado um comprador de teste por `POST /users/test_user` sem autorização do responsável. Ele não deve ser usado. A credencial local foi removida de `secrets/`. A conta continua existindo no Mercado Pago e não pode ser apagada pela API (ver DT-MP-07).

Só a conta de `secrets/mercadolivre_test_user` está autorizada para os testes.

Credenciais do comprador de teste da aplicação (login e senha) estão no arquivo local `secrets/mercadolivre_test_user`, ignorado pelo Git (`/secrets/`). Esse arquivo não é lido por código nem versionado. A variável padrão `MERCADOPAGO_TEST_BUYER_PASSWORD` ainda não está no carregador de segredos do ambiente (`secrets/aculta.secrets.env` pertence ao usuário `aculta`). (que descreve a criação pelo painel).

Como usar: entrar no Mercado Pago Developers com o usuário e a senha da conta de teste, em janela anônima do
navegador, para evitar erros de duplicidade de credenciais.

## Pendências

- Informar, no painel "Credenciais do ambiente" (seção Ambiente de Testes), o Public Key e o Access Token de teste.
- Validar o Checkout Pro de ponta a ponta no ambiente de testes, com os cartões e status acima, antes de ativar o gateway.
- Configurar as notificações da aplicação no painel do Mercado Pago (URL de teste e URL de produção), salvar e
  copiar a assinatura secreta para `MERCADOPAGO_WEBHOOK_SECRET`.
- Provisionar as credenciais de produção pelo ACULTA Deployer (não pelo painel do Runtime de teste).
- Endereço de notificação do ambiente de teste: `https://aculta.toca.net.br/integracoes/pagamentos/mercado_pago/notificacao`
  (a rota de `commerce_payment.notify` é reescrita pelo Portal). Cadastrar esse endereço no painel do Mercado Pago
  para os tópicos `payment`, `subscription_preapproval`, `subscription_preapproval_plan` e `subscription_authorized_payment`.
  Produção usa o endereço equivalente do domínio de produção, declarado pelo `aculta_deployer`.
- Assinatura de doação recorrente: pendente o vínculo com o pedido (DT-MP-02) e o e-mail do comprador de teste (DT-MP-03).
- Registro das dívidas desta integração: `docs/operations/DEBT-REGISTER.md`, seção "Mercado Pago".
