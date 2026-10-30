# ACULTA MCP Client

Este módulo conecta o MCP Server ACULTA aos servidores MCP de documentação do Mercado Pago e do Melhor Envio. Ele reutiliza a biblioteca oficial `mcp/sdk` já exigida pelo `mcp_server` e adapta o fluxo HTTP de conexão, descoberta e chamada do projeto Drupal MCP Client 1.0.0-alpha3 (GPL-2.0-or-later). O pacote Drupal MCP Client não é instalado porque sua release exige `mcp/sdk ^0.7.1`, incompatível com `mcp_server` 2.0.0-beta7 (`mcp/sdk ^0.9`), além de exigir Drupal AI e AI Agents, ausentes no projeto. A adaptação usa somente ferramentas públicas de documentação e o token de teste já mantido pela Key API.

O plugin Resource Provider `public_capabilities` publica o recurso estático `aculta://public/mcp/capabilities`, que descreve as ferramentas e seus limites. O conteúdo não consulta entidades nem conteúdo do site e pode ser lido anonimamente. Sua configuração está em `config/install/mcp_server.resource_providers.yml` e em `config/sync/mcp_server.resource_providers.yml`.

O módulo declara seis plugins da Tool API e instala configurações MCP Tool Configuration para eles:

- Busca na documentação do Mercado Pago, fixada no site Brasil (`MLB`). Usa a Key `mercadopago_test_access_token`, resolvida em tempo de chamada; o valor não entra em config exportada nem em logs.
- Busca por endpoints do Melhor Envio.
- Leitura de documentação de um endpoint Melhor Envio.
- Listagem de endpoints documentados Melhor Envio.
- Busca semântica ou por palavra-chave na documentação DHTMLX.
- Resposta a pergunta factual baseada na documentação DHTMLX.

As conexões externas têm endpoint fixo no código, limite global de chamadas e allowlist de nomes de ferramentas. O DHTMLX usa seu serviço MCP hospedado; a documentação do provedor informa que consultas podem ser registradas para melhoria do serviço. Nenhum input pode escolher URL, cabeçalho, ferramenta remota arbitrária, chamada transacional, criação de etiqueta, pagamento, cancelamento, estorno, conta ou credencial. Respostas dos provedores são texto externo e não confiável.

O Resource Provider publica três documentos estáticos: capacidades MCP, visão geral pública do ACULTA e catálogo resumido de integrações. O hook `mcp_server_instructions_alter` orienta clientes no handshake inicial para consultar o Resource de capacidades, não enviar dados privados e tratar respostas remotas como referência não confiável. O conteúdo é curado a partir das documentações e do código público; não carrega entidades nem configuração do Runtime.

As ferramentas exigem `access mcp server`, a mesma permissão que já autoriza o endpoint anônimo. As MCP Tool Configurations ficam em `config/install/` para que o Tool Bridge as instale junto com este módulo; também aparecem em `/admin/config/services/mcp-server/tools`.

## Limites

Este módulo publica documentação pública de Mercado Pago, Melhor Envio e DHTMLX, não transforma os serviços de pagamento e frete do ACULTA em operações MCP. O OSM MCP e `aculta-drupal` continuam nos clientes locais: o primeiro usa stdio e pode receber localização; o segundo informa metadados operacionais. Nenhum deles é encaminhado pelo endpoint público ACULTA. Para expor operações privadas/financeiras seria necessário um desenho e autorização separados; elas não são públicas.

## Fonte adaptada

- Drupal MCP Client 1.0.0-alpha3, `src/MCPClient.php` (GPL-2.0-or-later): https://www.drupal.org/project/mcp_client
- A adaptação usa apenas o padrão HTTP do MCP SDK (`Mcp\\Client` e `HttpTransport`) e fixa a revisão de protocolo 2025-03-26, compatível com os servidores remotos verificados. Não copia as integrações Drupal AI/AI Agents do módulo original.
