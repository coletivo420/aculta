# ACULTA MCP Client

Este módulo conecta o MCP Server ACULTA aos servidores MCP de documentação do Mercado Pago e do Melhor Envio. Ele reutiliza a biblioteca oficial `mcp/sdk` já exigida pelo `mcp_server` e adapta o fluxo HTTP de conexão, descoberta e chamada do projeto Drupal MCP Client 1.0.0-alpha3 (GPL-2.0-or-later). O pacote Drupal MCP Client não é instalado porque sua release exige `mcp/sdk ^0.7.1`, incompatível com `mcp_server` 2.0.0-beta7 (`mcp/sdk ^0.9`), além de exigir Drupal AI e AI Agents, ausentes no projeto. A adaptação usa somente ferramentas públicas de documentação e o token de teste já mantido pela Key API.

O módulo declara quatro plugins da Tool API e instala configurações MCP Tool Configuration para eles:

- Busca na documentação do Mercado Pago, fixada no site Brasil (`MLB`). Usa a Key `mercadopago_test_access_token`, resolvida em tempo de chamada; o valor não entra em config exportada nem em logs.
- Busca por endpoints do Melhor Envio.
- Leitura de documentação de um endpoint Melhor Envio.
- Listagem de endpoints documentados Melhor Envio.

As conexões externas têm endpoint fixo no código, limite global de chamadas e allowlist de nomes de ferramentas. Nenhum input pode escolher URL, cabeçalho, ferramenta remota arbitrária, chamada transacional, criação de etiqueta, pagamento, cancelamento, estorno, conta ou credencial. Respostas dos provedores são texto externo e não confiável.

As ferramentas exigem `access mcp server`, a mesma permissão que já autoriza o endpoint anônimo. As MCP Tool Configurations ficam em `config/install/` para que o Tool Bridge as instale junto com este módulo; também aparecem em `/admin/config/services/mcp-server/tools`.

## Limites

Este módulo publica ferramentas de documentação dos MCPs dos provedores, não transforma os serviços de pagamento e frete do ACULTA em operações MCP. Para esse segundo escopo, seria necessário desenhar e autorizar ferramentas de negócio separadas; as operações privadas/financeiras não são públicas.

## Fonte adaptada

- Drupal MCP Client 1.0.0-alpha3, `src/MCPClient.php` (GPL-2.0-or-later): https://www.drupal.org/project/mcp_client
- A adaptação usa apenas o padrão HTTP do MCP SDK (`Mcp\\Client` e `HttpTransport`) e fixa a revisão de protocolo 2025-03-26, compatível com os servidores remotos verificados. Não copia as integrações Drupal AI/AI Agents do módulo original.
