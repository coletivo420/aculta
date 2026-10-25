# Saída limitada de comandos para agentes

Para comandos verbosos, use `python3 scripts/agent-output.py --lines 60 -- COMANDO ARGUMENTOS`.

O wrapper preserva o código de saída e o log completo em diretório temporário local com permissões restritas, mostrando apenas as últimas linhas. **Logs podem conter segredos**: não os comite nem envie integralmente ao agente; examine somente os trechos necessários. A execução não tem timeout próprio; comandos interativos não são adequados. Para saídas muito grandes ou com dados sensíveis, prefira opções nativas de relatório/filtragem do comando.

Exemplo: `python3 scripts/agent-output.py --lines 40 -- php scripts/validate-project-status-index.php`.

Este utilitário limita tokens enviados ao modelo pela saída do terminal; não altera o cache do provedor.
