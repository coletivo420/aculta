# Servidores MCP do projeto

Este documento é o inventário comum. Cada cliente MCP mantém seu próprio arquivo de conexão; não copie tokens entre clientes.

## Inventário

| Servidor | Transporte e destino | Uso | Credencial |
| --- | --- | --- | --- |
| `aculta` | HTTP; `https://aculta.toca.net.br/integracoes/mcp` | Ferramentas públicas de consulta à documentação do Mercado Pago e Melhor Envio; sem acesso a dados ou operações da conta ACULTA. | OAuth Authorization Code com PKCE; o servidor usa uma Key de teste do Mercado Pago nas chamadas remotas. |
| `melhorenvio` | HTTP via `mcp-remote`; `https://docs.melhorenvio.com.br/mcp` | Documentação da API do Melhor Envio, somente consulta. | `MELHORENVIO_MCP_TOKEN` no ambiente do cliente. |
| `mercadopago` | HTTP via `mcp-remote`; `https://mcp.mercadopago.com/mcp` | Documentação e recursos disponíveis ao access token. `get_application`, `create_application` e `get_credentials` exigem OAuth e não aparecem com access token. | `MERCADOPAGO_MCP_TOKEN` no ambiente do cliente. |
| `dhtmlx-mcp` | HTTP; `https://docs.dhtmlx.com/mcp` | Documentação atual dos produtos DHTMLX. Servidor hospedado e sem autenticação; [a política de integração](https://docs.dhtmlx.com/suite/guides/mcp-server/) informa que consultas podem ser registradas para depuração e melhoria. | Nenhuma. |
| `osm-mcp` | stdio; executável local `osm-mcp-server` | Geocodificação, busca geográfica, rotas e ferramentas de dados OSM em modo de consulta. | Nenhuma. |
| `aculta-drupal` | stdio local; `scripts/mcp/aculta-drupal/server.py` | Metadados read-only do Runtime ACULTA: status, extensões, diferenças de configuração (sem valores) e updates pendentes. Sem conteúdo, SQL/PHP arbitrário ou escrita. | Nenhuma. |

O pacote OpenStreetMap instalado é `osm-mcp-server` **0.1.1**. Em 2026-10-28, essa é a versão mais recente publicada no [PyPI](https://pypi.org/project/osm-mcp-server/) e a versão declarada no [`pyproject.toml` do repositório upstream](https://github.com/jagan-shanmugam/open-streetmap-mcp/blob/main/pyproject.toml); o upstream não publica releases GitHub. Portanto, não há versão posterior para atualizar agora. O handshake MCP local foi validado: `initialize` e `tools/list` responderam, com 12 ferramentas.

## Arquivos de conexão verificados

- `.mcp.json` na raiz é a configuração do Claude Code e inclui `aculta` e `aculta-drupal`. O script local usa `${CLAUDE_PROJECT_DIR}` para resolver o próprio checkout. O usuário pode aprovar o servidor de projeto na primeira utilização interativa. O executável OSM aponta para uma instalação local em `~/.local/share/codex-mcp/osm-mcp-server-0.1.1/`; outro computador precisará instalar esse pacote e ajustar o caminho.
- `~/.codex/config.toml` é a configuração global local do Codex. Nesta máquina contém `aculta`, `aculta-drupal`, `osm-mcp`, `dhtmlx-mcp`, `melhorenvio` e `mercadopago`. Os conectores externos usam helpers locais para enviar tokens sem armazená-los no repositório. `aculta-drupal` é somente leitura de metadados; `aculta` é o endpoint HTTP público. Reinicie sessões existentes para redescobrir ferramentas MCP.
- `~/.claude.json` é a configuração global do Claude Code e contém o MCP separado `pesquisa`. Ele não substitui a lista de projeto do `.mcp.json`.

Cursor, Gemini/Antigravity, OpenCode e Claude Desktop não têm arquivo de configuração MCP local encontrado nesta máquina. Para habilitá-los, use o endpoint/command desta tabela no formato documentado pelo cliente, sem copiar credenciais para o repositório. A configuração local não foi criada para esses clientes.

O endpoint ACULTA MCP responde somente no domínio principal (`main`), em `/integracoes/mcp`; nos subdomínios responde 404. Clientes externos usam OAuth Authorization Code com PKCE e precisam ser pré-cadastrados como Consumers Drupal. As ferramentas publicadas são apenas busca/listagem de documentação dos provedores; não leem dados privados nem executam ações financeiras, de frete ou de conta.

O módulo próprio `aculta_mcp` implementa chamadas MCP de saída para os servidores de documentação do Mercado Pago, Melhor Envio e DHTMLX. Seis plugins da Tool API são publicados no ACULTA por meio do MCP Server Tool Bridge e de entidades MCP Tool Configuration. Três Resources estáticos descrevem capacidades, a plataforma ACULTA e as fronteiras das integrações; três MCP Prompts reutilizam esses Resources anexando-os às mensagens e orientam pesquisa documental sem dados sensíveis. Prompts são modelos e não chamam ferramentas automaticamente. Endpoints e nomes das ferramentas remotas têm allowlist fixa, limite global de chamadas e saída limitada. O servidor não encaminha chamadas de API de pagamento, frete, mapas ou Drupal.

`osm-mcp` e `aculta-drupal` continuam em `.mcp.json` e na configuração global Codex como conexões locais para agentes. O OSM usa stdio e pode receber dados de localização; `aculta-drupal` fornece metadados operacionais read-only. Esses servidores não são encaminhados pelo endpoint ACULTA público.

## MCP Client no Drupal

O projeto oficial [MCP Client](https://www.drupal.org/project/mcp_client) não é instalado porque sua versão consultada requer `mcp/sdk ^0.7.1`, em conflito com o MCP Server instalado (`^0.9`), além de Drupal AI/AI Agents que este projeto não usa. `aculta_mcp` adapta o padrão de cliente MCP com o SDK já instalado e usa HTTP para os provedores remotos; não copia a camada Drupal AI do contrib. Reavaliar o uso direto do contrib se as versões do SDK forem compatíveis ou o ACULTA adicionar Drupal AI.

## Regras

- Escolha o ambiente do token pelo modo configurado, não pelo prefixo. Token `APP_USR-` pode ser de teste; siga a política local do Mercado Pago.
- Use token de teste do Mercado Pago enquanto não houver decisão de produção. Nunca grave tokens no repositório.
- `.mcp.json` e a configuração Codex são listas duplicadas por exigência dos clientes. Mantenha os nomes e destinos comuns sincronizados com este inventário; variáveis e servidores específicos de cada cliente permanecem locais.
