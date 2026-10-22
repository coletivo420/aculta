# Cache e contexto dos agentes CLI

As regras obrigatórias continuam em `AGENTS.md`; este documento descreve somente otimizações de consumo.

## Claude Code, Codex e DeepSeek

1. Use **um modelo fixo por sessão**, escolhido explicitamente na inicialização. Não introduza troca automática de modelo no projeto.
2. Preserve instruções iniciais estáveis; carregue somente os contratos pertinentes à tarefa.
3. Leia arquivos seletivamente; evite `cat` de diretórios inteiros, logs extensos e repetição de contexto.
4. Mantenha uma sessão por tarefa/PR, mas compacte quando o contexto ficar obsoleto ou grande demais.
5. Não copie segredos, `.env`, bancos, dumps ou dados pessoais para prompts ou telemetria.
6. Verifique consumo e cache hit/miss nas métricas do provedor quando disponíveis; não prometa percentuais de cache.
7. Subagentes podem ter contextos e cache distintos; use-os apenas quando justificarem custo.

## Modelo fixo: configuração fora do repositório

- **Claude Code com Anthropic:** selecione explicitamente o modelo no lançador/sessão do usuário.
- **Codex CLI:** fixe o modelo no perfil local do Codex ou no argumento de inicialização, conforme a versão instalada.
- **Claude Code com DeepSeek API:** use lançador separado, com endpoint e identificadores de modelo confirmados na documentação da API vigente; não armazene chaves no Git.
- **Codex com DeepSeek API:** configure provedor e modelo no perfil local específico; não sobrescreva o perfil OpenAI.

Configurações de usuário, credenciais e modelos disponíveis variam por ambiente e não devem ser impostas por arquivos versionados do projeto. Mudanças de modelo ou endpoint exigem reinício explícito da sessão para preservar previsibilidade.

## Verificação

Compare tokens de entrada, saída e cache por tarefa concluída, além de testes aprovados. Cache de prefixo depende de correspondência de tokens e retenção pelo provedor, não apenas de manter o terminal aberto.
