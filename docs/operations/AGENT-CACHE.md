# Uso econômico de contexto — Claude, Codex e DeepSeek

`AGENTS.md` é o contrato compartilhado. Este arquivo é referência operacional, **não** precisa ser lido em toda tarefa.

- Fixe o modelo por sessão no CLI/perfil local; não altere provedor/modelo automaticamente. Chaves e endpoints ficam fora do Git.
- Mantenha instruções e definições de ferramentas estáveis para favorecer cache de prefixo; alterações em histórico, ferramentas e compactações podem causar misses.
- Leia apenas os arquivos e trechos necessários; use busca direcionada e saída limitada. Evite despejar árvores, documentação, testes ou logs completos.
- Reaproveite a sessão quando o histórico continuar útil; compacte quando o contexto acumulado custar mais que a possível reutilização. Não prolongue sessões apenas para manter cache.
- Use subagentes somente quando o ganho superar o contexto adicional. Evite instruções duplicadas em cada chamada.
- Não armazene segredos, dumps ou dados pessoais em prompts, arquivos de contexto ou métricas.
- Meça tokens de entrada, saída, cache hit/miss e tarefas validadas, quando a API disponibilizar esses dados. Não assuma taxa de cache nem equivalência de cobrança entre provedores.

**Configuração de modelos:** Claude Code/Anthropic, Codex/OpenAI e Claude Code ou Codex com DeepSeek devem usar perfis/lançadores locais separados e IDs de modelos verificados na API atual. Este repositório não impõe credenciais nem substitui modelos das sessões do usuário.
