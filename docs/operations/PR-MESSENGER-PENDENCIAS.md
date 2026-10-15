# PR: pendências do Messenger e da conta

Branch: `chore/messenger-pendencias` (a partir da `main` em 47cf202).

## Escopo, um commit por item

1. Exportação de referência limpa do runtime, com hosts de teste fora do `config/sync`. Sem importação completa enquanto `core.extension` e os registros de domínio divergirem do runtime.
2. Validação do fluxo de mensagens com usuário comum; correções se houver.
3. Alinhamento dos botões "Criar" da visão geral de avisos no mobile (tema).
4. Quebra de "Configuraçõ/es" no menu lateral da conta (tema).

## Regras para quem trabalhar nesta branch

- Um commit por item, com prefixo `chore|fix|feat(<área>)` e mensagem em português.
- Antes de cada commit: lint PHP dos arquivos alterados, testes unitários do Messenger e os gates que tocam a área.
- Não alterar `config/sync` sem a referência de exportação do item 1.
- Não mesclar sem autorização explícita.
