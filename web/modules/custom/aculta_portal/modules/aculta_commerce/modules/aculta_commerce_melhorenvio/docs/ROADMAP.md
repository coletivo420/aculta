# Roadmap — ACULTA Commerce Melhor Envio

Versão do manifesto: `0.1.0-dev.6`. Este componente é um adaptador de transportadora; o motor, métodos, tarifas, pedidos e
remessas são propriedade de `aculta_commerce`. O submódulo permanece fora do Config Sync conforme decisão operacional.

## Estado comprovado

- O cliente OAuth2, o armazenamento de tokens por ambiente e o webhook estão implementados. Credenciais são resolvidas
  primeiro pelo Drupal Key e depois pelo serviço central `aculta_portal.secrets_manager`.
- A cotação usa o motor ACULTA e preserva valores decimais sem conversão por `float`; precisão além de centavos é
  rejeitada. Cobertura automatizada: Unit 85 testes / 204 assertions e Kernel 11 testes / 57 assertions, ambos PASS na
  PR #298, sem chamadas externas.
- Os testes cobrem erros HTTP, ausência de credenciais, assinatura do webhook, resposta sem código OAuth e replay do
  state. Eles não comprovam comportamento real do fornecedor nem checkout funcional.
- PHPCS Drupal/DrupalPractice não reporta erros nos arquivos alterados; restam avisos de linhas longas.

## Fases

| Fase | Escopo | Estado e evidência | Próxima ação |
|---|---|---|---|
| ME-CEP | Lookup de CEP e adaptador de endereço | Código e testes Unit existentes; contrato compartilhado é do `aculta_portal_cep` | Manter regressões ao testar checkout |
| ME-0.1 | OAuth2, credenciais, tokens e webhook | Código e testes Unit/Kernel PASS; sem validação externa nesta execução | Confirmar caminhos, `expires_in` e codificação HMAC em teste autorizado |
| ME-0.1-C | Simulador de frete: cliente com interface, estados explícitos, endpoint, origem em formulário, encaixes e recálculo no carrinho | Unit 119 PASS; não validado no navegador nem com cotação real | Pacote por produto (peso e dimensões) e origem real no servidor de testes; RUNTIME STATUS: DEFERRED |
| ME-0.2-A | Plugin de cotação para o motor ACULTA | Implementado e coberto por fixture/mocks; instalação/wiring Kernel PASS | Validar configuração e uma cotação no servidor de testes com sandbox, sem contratar envio |
| ME-0.2-B | Tarifas no checkout Green Stash | Pendente; o Kernel deste adaptador não valida o fluxo integral de checkout | Testar mudança de endereço/itens, seleção da tarifa, total e recálculo |
| ME-0.3 | Compra de envio e criação de etiqueta | Não implementado/homologado | Definir contrato com `aculta_commerce`; somente mocks até autorização externa |
| ME-0.4 | PDF/impressão de etiqueta | Pendente | Implementar depois da compra segura de envio |
| ME-0.5 | Rastreamento, cancelamento e eventos | Webhook recebe e valida, mas apenas registra eventos; processamento de remessa pendente | Vincular evento à remessa com idempotência e autorização |
| ME-0.6 | Integração Green Stash e UI operacional | Pendente | Definir o fluxo no Studio com o proprietário Commerce |
| ME-0.7 | Statistics e Messenger | Pendente | Definir contratos com os módulos proprietários antes de integrar |
| ME-0.8 | Homologação | Functional, Firefox ESR e Runtime de teste não homologados nesta rodada | Executar no servidor de teste após preparar configuração sandbox |

## Pendências e limites

O registro por item, com nomes dos testes e requisitos externos, está em [PENDENCIAS.md](PENDENCIAS.md). Em resumo:

- Nenhum teste desta integração chamou a API. A documentação oficial não comprova a idempotência da cotação nem a
  codificação do HMAC do webhook; base64 aparece no exemplo documentado, hexadecimal é aceito pelo código sem evidência.
- A seleção e persistência da tarifa, checkout, pedidos pagos, criação de etiqueta e rastreamento não foram validados
  no navegador nem em operação sandbox nesta execução.
- Tokens cifrados em repouso no State API desde 0.1.0-dev.5 (`encrypted_v1`), com chave do contrato central
  (`aculta_melhorenvio_token_encryption`). Rotação de chave exige migração dos registros; ainda não automatizada.
- Não há migração de remessas legadas. O motor de Shipping pertence a `aculta_commerce`; `commerce_shipping` não é
  dependência funcional deste adaptador.
- O módulo continua em Dev. Não há evidência suficiente para RC.

## Matriz de testes

| Camada | Evidência atual | Falta |
|---|---|---|
| Unit | `tests/src/Unit/`: 85 / 204 PASS | Manter ao alterar os contratos |
| Kernel | `MelhorEnvioWiringKernelTest`: 11 / 57 PASS, MariaDB dedicado | Kernel de checkout e de sincronização com remessas |
| PHPCS / lint | Zero erros PHPCS; PHP lint PASS nos arquivos alterados | Corrigir avisos de linha longa somente junto a alterações pertinentes |
| Functional | Não executado para o checkout | Rotas, permissões e fluxo de compra em instalação isolada |
| Browser | Não executado nesta integração | Firefox ESR visível, desktop/mobile e acessibilidade |
| Sandbox | Não executado nesta integração | Validação autorizada sem compra de etiqueta ou custo real |

