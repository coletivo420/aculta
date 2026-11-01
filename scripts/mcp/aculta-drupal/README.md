# MCP read-only para o Drupal ACULTA

Servidor MCP local em stdio para oferecer a agentes metadados do Drupal Runtime de desenvolvimento do checkout atual. Não consulta entidades/conteúdo, não devolve valores de configuração e não expõe execução arbitrária de Drush, SQL ou PHP.

## Ferramentas

- `drupal_site_status`: versões do Drupal/PHP, driver, site e URI.
- `drupal_extensions`: nomes, versões e estado de módulos, temas e profiles.
- `drupal_config_differences`: somente nomes/estados de config/sync; nunca valores.
- `drupal_pending_database_updates`: metadados de updates pendentes; não aplica updates.

As únicas chamadas Drush permitidas são `status`, `pm:list`, `config:status` e `updatedb:status`, com URI `http://default`. Alterações persistentes continuam seguindo `AGENTS.md` e `docs/operations/AGENTES-PROCEDIMENTO.md`.

## Configuração dos agentes

- Claude Code recebe o servidor pelo `.mcp.json` versionado, que resolve o script usando `CLAUDE_PROJECT_DIR`. Aprovar o servidor de escopo de projeto no primeiro uso interativo, conforme o cliente.
- Codex CLI recebe o mesmo servidor via `~/.codex/config.toml` local desta máquina; essa configuração não contém credenciais. O caminho local deve apontar para `scripts/mcp/aculta-drupal/server.py` no checkout de desenvolvimento.
- Reiniciar/recarregar sessões existentes para que redescubram ferramentas MCP.

## Teste local do protocolo

Use o handshake `initialize`, `tools/list` e chamadas de leitura do MCP em stdio. `codex mcp list` verifica a configuração do cliente, mas a descoberta e chamadas efetivas precisam ser verificadas separadamente; não use um agente como motivo para atualizar config ou banco.
