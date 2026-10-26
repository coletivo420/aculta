# Melhor Envio: separação de chaves por ambiente

Contrato de credenciais do Melhor Envio. Segue `docs/operations/SECRETS.md`. Este documento não contém valores de
credencial, só nomes.

## Regra

- **Teste (sandbox):** valores em `secrets/aculta.secrets.env` (ignorado pelo Git). O Drupal lê pelo Key provider
  `env` e pelos Key IDs abaixo. Nada de valor no código, em `config/sync` ou em `config/install`.
- **Produção:** valores no banco, criptografados. O painel não grava nesse modo. Provisionamento pelo
  `aculta_deployer`, após o deploy (fase 9 do roadmap do deployer, ainda não implementada).
- Chave de produção nunca fica em `secrets/`, e nunca é usada no ambiente de teste. Teste nunca usa chave de produção.
- O ambiente ativo é definido pelo modo configurado do módulo (`environment`), não pelo formato da chave.

## Key IDs e variáveis

| Key ID | Variável de ambiente (teste) | Ambiente | Sensível | Uso |
| --- | --- | --- | --- | --- |
| `melhorenvio_sandbox_client_id` | `MELHORENVIO_SANDBOX_CLIENT_ID` | Teste (sandbox) | Não | URL de autorização OAuth2 |
| `melhorenvio_sandbox_client_secret` | `MELHORENVIO_SANDBOX_CLIENT_SECRET` | Teste (sandbox) | Sim | troca de código, renovação e assinatura do webhook |
| `melhorenvio_production_client_id` | (banco, provisionado pelo deployer) | Produção | Não | URL de autorização OAuth2 |
| `melhorenvio_production_client_secret` | (banco, provisionado pelo deployer) | Produção | Sim | troca de código, renovação e assinatura do webhook |

Tokens de acesso e de renovação não são chaves de configuração: ficam no armazenamento de tokens do módulo
(State API), por ambiente, e expiram. Nunca entram em `secrets/`, no código ou na configuração.

## Mapeamento no módulo

`keys.sandbox.client_id` e `keys.sandbox.client_secret` (em `aculta_commerce_melhorenvio.settings`) recebem os Key IDs
de teste. `keys.production.*` recebe os de produção. Só os nomes ficam na configuração; os valores ficam no Key.

## Estado

- Teste: credenciais reais de produção que tinham sido colocadas no servidor de teste foram removidas. `secrets/` não
  contém nenhuma credencial do Melhor Envio. Webhook responde 503 até a chave de sandbox ser provisionada.
- Pendente, de quem cadastra: chave de sandbox (client_id e client_secret do aplicativo de teste).
- Pendente, do deployer: provisionar as chaves de produção no banco criptografado, fora deste ciclo de teste.
- Pendente, do agente principal: incorporar esta separação à tabela central de `docs/operations/SECRETS.md`.

## Servidor MCP da documentação do Melhor Envio

- O `.mcp.json` da raiz configura o servidor `melhorenvio` (`https://docs.melhorenvio.com.br/mcp`, via `npx mcp-remote`).
- O token não vai para o repositório. O arquivo lê `${MELHORENVIO_MCP_TOKEN}` do ambiente de quem executa o Claude Code. Defina a variável no shell antes de iniciar a sessão.
- Sem a variável, o servidor não conecta; o restante do projeto não depende dele.
- Este nome não faz parte do contrato de segredos do servidor. Incluí-lo lá exige decisão separada.
