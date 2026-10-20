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
## `0.1.0-dev.10` — validar dependências dos Group roles — 2026-10-20

- Unit confronta os Group roles do Config Sync com as duas relationship types
  entregues pelo adapter e verifica o mapeamento LMS de Educador para turma.
- Unit do adapter: 12 testes / 141 assertions PASS. Config Sync não importado;
  instalação real do módulo continua pendente do teste do grafo completo.
  RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.9` — validar metadados de cache do contexto LMS — 2026-10-20

- Kernel confirma contextos `user`, `user.permissions` e
  `user.group_permissions`, além da dependência cacheável do tópico, ao compor
  as decisões de leitura/escrita/moderação para Educador, estudante e outsider.
- Nenhuma rota ou fila foi criada; o adapter e a Config Sync continuam fora do
  Runtime. Kernel adapter: 2 testes / 67 assertions PASS, 38 depreciações
  Core/contrib. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.8` — limitar tópicos LMS pelos Group roles — 2026-10-20

- Roles de estudante/educador/criador recebem permission Group Node somente
  nas relações do adapter (curso/turma); herança de `grade students` leva o
  acesso de Educador às turmas. Outsiders e role global sem membership não
  acessam tópicos.
- Kernel lê e cria roles a partir do Config Sync; 2 testes / 63 assertions
  PASS, 38 depreciações. Config não importada, adapter segue desabilitado.

## `0.1.0-dev.7` — validar Comment e moderação contextual em Kernel — 2026-10-20

- Kernel cria tópico LMS em Group de turma, persiste Comment compartilhado,
  permite escrita de membro, restringe o marcador de moderação a Educador do
  curso e bloqueia escrita com campo fechado. Outsider não lê. Kernel adapter:
  2 testes / 57 assertions PASS, 38 depreciações Core/contrib; Unit 11/123.
- Adapter e Config Sync continuam desabilitados/não importados no Runtime.
  Nenhuma fila foi criada. RUNTIME STATUS: DEFERRED.

## `0.1.0-dev.6` — herdar permissão do Educador nas turmas — 2026-10-20

- Config Sync de `lms_classes.settings` concede permissão Group Node de tópico
  na turma quando o membro tem `grade students` no curso pai.
- Kernel Group/LMS comprova a herança real: Educador do curso passa; outsider e
  pessoa com apenas papel global `lms_teacher` não passam. 1 teste / 24
  assertions PASS; 37 depreciações Core/contrib. Config não importada no
  Runtime; adapter segue desabilitado.

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

