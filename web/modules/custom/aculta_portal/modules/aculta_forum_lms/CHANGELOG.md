## `0.1.0-dev.3` — configuração instalável de tópico — 2026-10-20

- Adiciona bundle Node, campo Comment compartilhado, displays e relações
  Group Node específicas para curso e turma. Sem Config Sync de ativação,
  permissão de roles, rotas ou Runtime.
- Validação de config: 2 testes / 29 assertions; suíte completa: 8 testes /
  48 assertions PASS.

## `0.1.0-dev.2` — acesso direto fail-closed — 2026-10-20

- Protege a visualização direta do Node: exige relação Group única e, para
  turma, um curso pai único. Com relação válida, delega autorização ao Group.
- Testes Unit novos: 3 testes / 11 assertions PASS; suíte do adapter: 6 testes / 19 assertions. Não habilitado no Runtime.

## `0.1.0-dev.1` — contrato inicial do adapter — 2026-10-20

- Implementa resolução fail-closed de tópico LMS vinculado a Group e o acesso
  contextual de Educador ao marcador de moderação compartilhado.
- O adapter não possui configuração instalável nem deve ser habilitado antes
  das provas Kernel e da configuração específica de Group Node.
## `0.1.0-dev.5` — corrigir composição de acesso contextual — 2026-10-20

- Corrige a combinação de acesso: membership e permissão `grade students` são
  alternativas explícitas, sem `orIf()` que deixava uma negação de matrícula
  dominar uma permissão válida de Educador. A negação acontece somente quando
  nenhum dos caminhos autoriza; moderação segue negada para não Educadores.
- Escrita exige status Comment aberto e permission `post comments`; os dois
  caminhos negados são explícitos e preservam cache contexts.
- Unit: 2 testes / 50 assertions PASS cobrindo estudante, Educador, escrita,
  moderação e campo fechado. Runtime DEFERRED; adapter segue desabilitado.

## `0.1.0-dev.4` — provar permissões Group Node — 2026-10-20

- Kernel com Group 3.3.5/gnode prova que permission contextual do Group concede
  acesso ao membro e que outsider não lê o tópico. 1 teste / 17 assertions
  PASS; 17 depreciações do Core/Group/contrib. Sem instalação no Runtime.

