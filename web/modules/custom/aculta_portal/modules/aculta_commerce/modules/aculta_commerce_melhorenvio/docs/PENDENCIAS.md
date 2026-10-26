# Pendências do Melhor Envio: estado e evidência

> Registro de estado por item. Cada linha diz o que está provado no código ou na documentação e o que ainda falta.
> Não é homologação: "Concluído" aqui significa teste automatizado passando, não checkout validado em navegador.

## Testes executados nesta branch

| Suite | Resultado |
| --- | --- |
| Unit `aculta_commerce_melhorenvio` | 85 testes, 204 asserções, OK |
| Kernel de wiring (MariaDB dedicado, sem `commerce_shipping`) | 9 testes, 45 asserções, OK; 129 deprecações de Core e contrib |
| PHPCS Drupal/DrupalPractice nos arquivos alterados | sem erros; avisos de comprimento de linha |

## Itens

| Item | Estado | Evidência | Falta |
| --- | --- | --- | --- |
| Descoberta do método sem `commerce_shipping` | Concluído | Kernel de wiring carrega a cadeia sem o módulo | Nada |
| Precisão monetária da cotação | Concluído | `QuoteResponseParser::decimal()` sem `float`; `testMoneyStaysExactAndRejectsSubCentPrecision` | Nada |
| Cotação: 403, resposta vazia, 5xx, timeout | Concluído (Unit) | `testQuoteForbiddenIsNotRetried`, `testQuoteEmptyResponseIsEmptyList`, `testQuoteServerErrorIsRetriedThenReported`, `testQuoteTimeoutIsNetworkAndSanitized` | Confirmar com a API real do sandbox |
| Cotação: 401 e token expirado | Concluído (Unit) | `testUnauthorizedRefreshesOnceAndRetries`, `testTokenNearExpiryIsRefreshedBeforeRequest`, `testRejectedRefreshClearsTokensAndRequiresAuthorization` | Nada |
| Cotação: ausência de credenciais | Concluído (Unit) | `testMissingCredentialsAreNotConfigured` | Nada |
| Cotação: serviço com erro, `custom_price` e fallback | Concluído (Unit) | `testSandboxResponseSkipsServicesWithError`, `testCustomPriceAndInvalidItems` | Nada |
| Retentativas: quem pode ser repetido | Parcial | POST de cotação marcado como idempotente; compra, geração e cancelamento não são repetidos (`testNonIdempotentPostIsNotRetried`) | Contrato do Melhor Envio não declara idempotência da cotação; a documentação só descreve "Cálculo de Fretes" |
| Cotação idempotente por contrato | Pendente externo | Referência oficial (`/api/v2/me/shipment/calculate`) não traz garantia de idempotência | Confirmação escrita do fornecedor ou teste de repetição no sandbox |
| Assinatura do webhook | Parcial | HMAC-SHA256 do corpo com o segredo, confirmado na documentação oficial; exemplo publicado é base64; teste de base64 e hex (`testValidSignatureInBase64AndHex`) | Codificação real: validar com um webhook enviado pelo sandbox |
| Webhook: assinatura, corpo, segredo ausente, repetição | Concluído (Unit) | `MelhorEnvioWebhookControllerTest`: 503 sem segredo, 403 com assinatura incorreta ou ausente, 400 com corpo inválido, 204 com evento válido e log sem protocolo, reenvio aceito sem estado | Nada no controller atual |
| Webhook: fora de ordem, referência inexistente, etiqueta de outra conta, ambiente incorreto | Não aplicável ainda | O controller só valida e registra; não há processamento de etiqueta nem vínculo com remessa | Implementar com a fase logística (ver abaixo) |
| OAuth: `state` de uso único (replay) | Concluído por desenho | `MelhorEnvioOAuthController::callback()` remove o `state` da sessão antes de qualquer verificação; log guarda só hash | Teste de regressão em Kernel com sessão real |
| OAuth: retorno sem código | Concluído por desenho | Mensagem de erro e retorno à página de estado | Teste de regressão em Kernel |
| OAuth: redirects não cacheáveis | Concluído (Unit) | `testOauthRoutesAreNeverCached`, `testEachAuthorizeIssuesFreshUncacheableRedirect` | Nada |
| Armazenamento de tokens | Parcial | `MelhorEnvioTokenStoreInterface` com implementação `StateMelhorEnvioTokenStore`; State API não é armazenamento criptografado | Decisão arquitetural sobre criptografia e integração com `aculta_secrets_manager`; sem aprovação, não há criptografia própria |
| Checkout funcional (tarifas, seleção, total, recálculo) | Pendente | Nenhum teste de checkout nesta branch | Kernel de checkout e homologação de navegador |
| Testes de navegador (Firefox ESR, 320/390 px) | Pendente | Não executado | Ambiente de teste com a sessão X11 e o servidor de testes |
| Logística (etiqueta, rastreio, cancelamento) | Pendente por desenho | Fora do escopo desta entrega; operações que geram custo continuam sem implementação | Fases futuras, com mocks até autorização específica |
| Green Stash, Messenger e Statistics | Pendente | Sem contrato publicado nesta branch | Contratos com os módulos donos (Messenger, Statistics) e o Studio |
| Secrets Manager | Pendente externo | Cliente ainda usa `aculta_portal.secrets_manager` | Coordenação com o agente dono de `aculta_secrets_manager` |
