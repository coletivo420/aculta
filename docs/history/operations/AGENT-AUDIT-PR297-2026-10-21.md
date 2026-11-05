# Auditoria especializada de instruções de agentes — 2026-10-21

Escopo: revisão documental das regras de agentes na PR #297. **Não** é auditoria de código/Runtime nem inventário exaustivo de todos os diretórios do repositório.

## Constatações e tratamento

| Constatação | Ação | Estado |
|---|---|---|
| Monólito histórico de 57.029 caracteres e regras transversais repetidas | `AGENTS.md` curto, arquivo histórico preservado, índice por escopo | Aplicado |
| Instruções específicas de tema, Portal, Fórum, Studio e usuários misturadas com regras gerais | Extraídas para arquivos locais/de consulta sob demanda | Aplicado |
| Falta de instruções locais em estatísticas, galerias, imagens, CPF/CNPJ e Messenger | Criados cinco `AGENTS.md` locais | Aplicado |
| Modelos, endpoints, cache e custos sujeitos a mudanças externas | Não fixar valores ou identificadores em arquivos versionados; configuração por sessão | Aplicado |
| Menções históricas a versões, status de PR, hostnames e usuários de teste | Tratar como referência histórica; conferir manifest, código e ambiente antes de usar | Regra de verificação aplicada |
| Regras de Commerce, Wiki e outras áreas sem diretório confirmado na revisão | Não criar diretórios presumidos nem excluir contratos | Pendente de mapeamento |
| Duplicação semântica entre documentos do Portal, tema e políticas históricas | Não apagar contratos canônicos sem comparação textual e decisão do responsável | Pendente |
| Compatibilidade real com autocarregamento do Claude Code, Codex e agentes DeepSeek | Validar com CLI e versões instaladas | Pendente |
| Script de saída limitada | Revisão estática documental; execução no servidor e testes de falha/sinais não realizados | Pendente |

## Regras vigentes de precedência

1. Segurança, escopo e Git: `AGENTS.md` da raiz.
2. Regras específicas: `AGENTS.md` do diretório, quando o CLI as suportar; demais agentes devem consultá-las explicitamente.
3. Documentação canônica do componente e código instalado prevalecem sobre versões e estados narrados no arquivo histórico.
4. Em conflitos de decisões normativas, não escolher silenciosamente: verificar contratos, registrar divergência e solicitar decisão quando necessário.

## Próximas verificações necessárias

- Enumerar **todos** os manifests `*.info.yml` de módulos customizados e tema no checkout para detectar submódulos não cobertos.
- Comparar contratos locais e políticas realocadas por conteúdo, eliminando apenas duplicatas comprovadas, preservando decisões.
- Testar instruções hierárquicas no Codex, regras `.claude/rules` ou `CLAUDE.md` por escopo no Claude Code, e lançadores que utilizam DeepSeek.
- Executar testes do wrapper de logs, inclusive comandos que falham e encerramento por sinal; confirmar tratamento de dados sensíveis.
- Medir tokens e cache hit/miss antes/depois por tarefa equivalente.

**Conclusão:** a separação por escopo foi aplicada onde os caminhos foram confirmados. Não há evidência suficiente para declarar obsoletos todos os contratos históricos, nem para afirmar que a auditoria do repositório inteiro foi concluída.
