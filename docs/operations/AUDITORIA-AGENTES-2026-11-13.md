# Auditoria continuada das instruções e skills ACULTA

Data: 2026-11-13. Base inicial da revisão: `840b3fd4625e5ad1a0a2afc6e5d9f372537d5f10` na branch `dev`. Conferir o HEAD vigente ao retomar. Esta auditoria tem escopo de governança de agentes, sem escrita no Runtime.

## Inventário e validações observadas

- 23 skills canônicas em `.agents/skills/`, descobertas por symlinks em `.claude/skills/` e `.codex/skills/`.
- Execução real do job PHP lint no SHA inicial: `validate-agent-token-budget.php` registrou **AGENT CONTRACT: PASS (23 skills; 3 avisos)**. As skills `css`, `sdc` e `ui-patterns` ficaram acima da meta de tamanho, sem bloquear.
- `validate-agent-skills.py` foi executado no mesmo job; sem falha no passo. PHP lint e descoberta PHPUnit passaram naquele workflow; o resultado não substitui testes de comportamento do agente.
- No CI da revisão inicial, actionlint, Bats e qualidade de shell passaram; PHPStan falhou com 1 erro, PHPUnit Unit teve falhas, Stylelint registrou 33 problemas e Semgrep registrou 1 achado bloqueante. São falhas de código/gates globais e precisam de triagem por diff; não atribuir à política dos agentes sem evidência.
- Runs de push podem aparecer como sucesso apesar de todos os jobs funcionais serem `skipped` pelo guard de deduplicação. Verificar o run de pull_request com o mesmo SHA; não inferir PASS a partir do status nominal.

## Defeitos corrigidos nesta continuação

1. O gate `scripts/validate-project-status-index.php` exigia a string literal `PROJECT-STATUS-INDEX-GUARD` em `AGENTS.md`, removida pela refatoração. O script agora valida os dados reais do índice sem depender desse marcador de texto.
2. O contrato Brasil/BR, CEP centralizado, domínio MAIN, slugs públicos e aceite único voltou ao `AGENTS.md`, e a referência do Portal foi explicitada, preservando as ADRs proprietárias.
3. O guia de economia ainda impunha teto implícito de três documentos, dez skills, limite de 4 KB e handoff obrigatório; foi reconciliado com a política de revisão proporcional.
4. O tema V2 ainda impunha `Claude-Session` a todos os agentes e declarava HTMX obrigatório para formulários apesar da política global. Essas duplicações foram alinhadas.
5. A matriz de gates mantinha estados datados como se fossem execução atual e versão antiga do Stylelint. Agora é rotulada como referência histórica.

## Riscos residuais e homologação pendente

- **R2: revisão independente.** O contrato exige segundo revisor antes de publicar mudanças sensíveis, mas push direto em `dev` não possui mecanismo automático que imponha esse aceite. O autor deve registrar a revisão externa; CI pós-push não equivale a aprovação prévia. As alterações anteriores de governança não têm comprovação de segunda revisão independente. Não classificar essa exigência como homologada.
- **Comportamento das skills.** O validador cobre inventário, frontmatter, links e aliases; ainda falta executar cenários reais em Claude Code e Codex: mudança R1 simples sem entrevistas, detecção R2 e bloqueio R3, uso correto de UI Suite, Graphify opcional, autorização de sync e preservação de worktree concorrente. Registrar logs e avaliação por cenário.
- **Runtime.** Nenhuma execução real de sincronização, cache, Drush ou mudanças estruturais foi feita por esta auditoria. Scripts simulados não comprovam estado do Runtime.
- **CI por escopo.** O workflow PHP ainda pode executar suíte integral em alterações apenas documentais; avaliar otimização por paths/jobs mantendo validação de contratos e segurança. Não alterar filtros sem testar casos de detecção de dependentes.
- **Segredos.** A regra `permissions.deny` da CLI ajuda a evitar leituras diretas, mas não substitui permissões do sistema, higiene dos comandos, revisão de staged e controles do provedor.

## Critério para encerrar a homologação de agentes

- Dois CLIs detectam e aplicam uma amostra representativa de skills e instruções, com evidências.
- Mudanças R0/R1 fluem autonomamente; mudança R2 tem revisão registrada; R3 permanece bloqueada sem autorização específica.
- Validação estrutural e testes negativos dos gates de política passam; CI de contratos executa efetivamente, e não apenas um guard com jobs pulados.
- Contradições normativas são corrigidas na fonte dona; resultados históricos não são reutilizados como PASS.
