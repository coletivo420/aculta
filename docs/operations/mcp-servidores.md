# Servidores MCP do projeto

Configurados em `.mcp.json` na raiz. Nenhum token vai para o repositório: cada servidor lê o token de uma variável de ambiente de quem executa o Claude Code. Defina as variáveis no shell antes de iniciar a sessão e aprove os servidores quando o Claude Code pedir.

| Servidor | Endpoint | Variável de ambiente | Observação |
| --- | --- | --- | --- |
| `melhorenvio` | `https://docs.melhorenvio.com.br/mcp` | `MELHORENVIO_MCP_TOKEN` | Documentação da API do Melhor Envio. Somente consulta. |
| `mercadopago` | `https://mcp.mercadopago.com/mcp` | `MERCADOPAGO_MCP_TOKEN` | Com access token, as ferramentas de gestão de aplicação (`get_application`, `create_application`, `get_credentials`) não ficam disponíveis; elas exigem OAuth. |

## Regras

- Escolha o ambiente do token pelo modo configurado, não pelo prefixo. Token `APP_USR-` pode ser de teste (ver `docs/operations/` e a política de Mercado Pago do projeto).
- Para o Mercado Pago, use o token de teste enquanto não houver decisão de produção. Não grave o token em arquivo do repositório.
- Servidores de Google ainda não configurados: o servidor precisa de endpoint e escopo definidos, e de um cliente OAuth criado no console do Google. Pendente de escolha.
