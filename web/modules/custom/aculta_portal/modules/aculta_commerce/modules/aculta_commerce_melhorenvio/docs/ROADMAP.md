# Roadmap — ACULTA Commerce Melhor Envio

Versão atual: `0.1.0-dev.1`. Plano de referência: `docs/operations/` e `docs/portal/CEP-SHARED-LOOKUP-0.1.md`.

## Dependências explícitas

- **Serviço central de CEP (ME-CEP 0.1): concluído.** O adaptador usa `aculta_portal_cep.lookup`. Não há fallback de
  consulta de CEP no submódulo.
- Não iniciar ME-0.2-A (cálculo de frete) sem o adaptador de CEP e seus testes em verde.
- **Endereço único do ACULTA (ME-ADDRESS 0.1): concluído na parte de código e Unit.** Contrato e matriz de campos em
  `docs/ADDRESS-CONTRACT.md`. O Melhor Envio não tem formulário de endereço. Pendências de Kernel e de dados reais estão
  em DT-ME-02 a DT-ME-06 no registro central.

## Fases

| Fase | Escopo | Estado |
|---|---|---|
| ME-CEP 0.1-A | Auditoria do `aculta_portal_cep` e contratos | Concluída (`docs/portal/CEP-SHARED-LOOKUP-0.1.md`) |
| ME-CEP 0.1-B | Serviço PHP `aculta_portal_cep.lookup`, Controller delegando | Concluída |
| ME-CEP 0.1-C | Adaptador de endereço do Melhor Envio | Concluída |
| ME-CEP 0.1-T | Testes unitários do serviço e do adaptador | Unit concluídos; Kernel pendente (DT-CEP-01) |
| ME-0.1-A | Auditoria de Melhor Envio Shipping, CodeSpar e API oficial | Pendente (fontes baixadas, inventário a documentar) |
| ME-0.1-B | Fundação do submódulo (manifesto, serviços, permissões) | Parcial: manifesto, serviço do adaptador e README |
| ME-0.1-C | Cliente REST OAuth2, sandbox, tokens em Key | Pendente |
| ME-0.2-A | Plugin Commerce Shipping e cotação | Pendente (bloqueada por ME-0.1-C) |
| ME-0.2-B | Integração ao checkout existente | Pendente |
| ME-0.3-A a ME-0.7-A | Envios, compra autorizada, etiquetas, impressão, rastreamento, Green Stash, MCP, Statistics e Messenger | Pendente |
| ME-0.8-T | Testes completos no homelab | Pendente |

## Matriz de testes do adaptador

| Caso | Teste | Estado |
|---|---|---|
| CEP válido e completo, sem consulta | `testCompleteAddressDoesNotLookUp` | Unit OK |
| CEP inválido | `testInvalidPostalCodeDoesNotLookUp`, `testMissingPostalCodeIsInvalid` | Unit OK |
| CEP com máscara | `testMaskedPostalCodeIsNormalized` | Unit OK |
| Sugestão só nos campos vazios | `testSuggestionFillsOnlyMissingFields` | Unit OK |
| CEP não encontrado | `testNotFoundReturnsNoSuggestion` | Unit OK |
| Falha de consulta não interrompe | `testLookupFailureIsUnavailableNotFatal` | Unit OK |
| Consulta local, ViaCEP, BrasilAPI, fonte desabilitada, cache, falha externa | `CepLookupServiceTest` | Unit OK |
| Endpoint AJAX sem requisição interna | Kernel | Pendente (DT-CEP-01) |
| Gate de conta | `scripts/validate-account-address.php` | PASS |
| Checkout e Mercado Pago sem regressão | Kernel e Functional no homelab | Pendente (DT-CEP-01) |

## Limitações de runtime

- Sem credenciais do Melhor Envio neste ambiente. Sandbox e produção não foram testados.
- Testes Kernel e Functional precisam de instalação de teste isolada. Não rodam sobre o banco de desenvolvimento.
- PHPStan não está configurado no repositório. Não há gate de PHPStan a executar.
