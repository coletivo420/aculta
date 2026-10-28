# Servidores MCP do projeto

Este documento é o inventário comum. Cada cliente MCP mantém seu próprio arquivo de conexão; não copie tokens entre clientes.

## Inventário

| Servidor | Transporte e destino | Uso | Credencial |
| --- | --- | --- | --- |
| `melhorenvio` | HTTP via `mcp-remote`; `https://docs.melhorenvio.com.br/mcp` | Documentação da API do Melhor Envio, somente consulta. | `MELHORENVIO_MCP_TOKEN` no ambiente do cliente. |
| `mercadopago` | HTTP via `mcp-remote`; `https://mcp.mercadopago.com/mcp` | Documentação e recursos disponíveis ao access token. `get_application`, `create_application` e `get_credentials` exigem OAuth e não aparecem com access token. | `MERCADOPAGO_MCP_TOKEN` no ambiente do cliente. |
| `dhtmlx-mcp` | HTTP; `https://docs.dhtmlx.com/mcp` | Documentação atual dos produtos DHTMLX. Servidor hospedado e sem autenticação; [a política de integração](https://docs.dhtmlx.com/suite/guides/mcp-server/) informa que consultas podem ser registradas para depuração e melhoria. | Nenhuma. |
| `osm-mcp` | stdio; executável local `osm-mcp-server` | Geocodificação, busca geográfica, rotas e ferramentas de dados OSM em modo de consulta. | Nenhuma. |

O pacote OpenStreetMap instalado é `osm-mcp-server` **0.1.1**. Em 2026-10-28, essa é a versão mais recente publicada no [PyPI](https://pypi.org/project/osm-mcp-server/) e a versão declarada no [`pyproject.toml` do repositório upstream](https://github.com/jagan-shanmugam/open-streetmap-mcp/blob/main/pyproject.toml); o upstream não publica releases GitHub. Portanto, não há versão posterior para atualizar agora. O handshake MCP local foi validado: `initialize` e `tools/list` responderam, com 12 ferramentas.

## Arquivos de conexão verificados

- `.mcp.json` na raiz é a configuração de projeto do Claude Code. Tem os quatro servidores acima. O executável OSM aponta para a instalação local versionada em `~/.local/share/codex-mcp/osm-mcp-server-0.1.1/`; outro computador precisará instalar o pacote e ajustar o caminho.
- `~/.codex/config.toml` é a configuração global do Codex. Tem `osm-mcp` e `dhtmlx-mcp`; comando OSM e URL DHTMLX coincidem com `.mcp.json`. Os servidores de Melhor Envio e Mercado Pago não foram adicionados ao Codex porque dependem de tokens de ambiente que não foram provisionados nessa configuração.
- `~/.claude.json` é a configuração global do Claude Code e contém o MCP separado `pesquisa`. Ele não substitui a lista de projeto do `.mcp.json`.

Cursor, Gemini/Antigravity, OpenCode e Claude Desktop não têm arquivo de configuração MCP local encontrado nesta máquina. Para habilitá-los, use o endpoint/command desta tabela no formato documentado pelo cliente, sem copiar credenciais para o repositório. A configuração local não foi criada para esses clientes.

## Regras

- Escolha o ambiente do token pelo modo configurado, não pelo prefixo. Token `APP_USR-` pode ser de teste; siga a política local do Mercado Pago.
- Use token de teste do Mercado Pago enquanto não houver decisão de produção. Nunca grave tokens no repositório.
- `.mcp.json` e a configuração Codex são listas duplicadas por exigência dos clientes. Mantenha os nomes e destinos comuns sincronizados com este inventário; variáveis e servidores específicos de cada cliente permanecem locais.
