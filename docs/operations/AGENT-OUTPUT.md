# Saída limitada de comandos para agentes

Para comandos verbosos, use `python3 scripts/agent-output.py --lines 60 -- COMANDO ARGUMENTOS`.

O wrapper preserva o código de saída e o log completo em diretório temporário local com permissões restritas, mostrando apenas as últimas linhas. **Logs podem conter segredos**: não os comite nem envie integralmente ao agente; examine somente os trechos necessários. A execução não tem timeout próprio; comandos interativos não são adequados. Para saídas muito grandes ou com dados sensíveis, prefira opções nativas de relatório/filtragem do comando.

Exemplo: `python3 scripts/agent-output.py --lines 40 -- php scripts/validate-project-status-index.php`.

Este utilitário limita tokens enviados ao modelo pela saída do terminal; não altera o cache do provedor.

## Obrigatório para comandos verbosos

Use o wrapper sempre que o comando puder gerar mais de algumas dezenas de linhas:

- PHPUnit: `python3 scripts/agent-output.py --lines 60 -- vendor/bin/phpunit ...`
- PHPStan: `python3 scripts/agent-output.py --lines 60 -- vendor/bin/phpstan ...`
- Lint de projeto inteiro (`php -l` em muitos arquivos, ou `find ... -exec php -l`): envolva o comando inteiro, por exemplo `python3 scripts/agent-output.py --lines 60 -- sh -c 'find web/modules/custom -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"'`.

Para um gate isolado de poucas linhas, o wrapper é opcional. Em caso de falha, leia as últimas linhas e, se preciso, o log completo indicado pelo wrapper, sem colar o log inteiro na resposta.
