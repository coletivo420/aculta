# ACULTA — instruções para agentes

PROJECT-STATUS-INDEX-GUARD: 2026-10-21

Regras de todos os agentes. Em conflito, vale a instrução explícita mais recente do responsável e as regras de segurança abaixo. Texto integral das regras detalhadas: `docs/operations/AGENT-REGRAS-DETALHADAS.md` (não ler por padrão).

## Sessão

- **Leitura de partida:** `TODO.md` (estado do projeto) e o `TODO.md` do componente que você vai alterar. Para continuar uma fase, leia também `docs/operations/ESTADO-DA-SESSAO.md` (até 3 entradas).
- **Ao concluir uma fase:** atualize o `TODO.md` do componente e o status YAML no mesmo commit. Não acrescente histórico no TODO.
- **Handoff:** ao encerrar uma fase ou o trabalho (não a cada microfase), registre em `docs/operations/ESTADO-DA-SESSAO.md`: objetivo, arquivos, verificações, pendências e próximos passos. Máximo de 3 entradas; mova as antigas para `docs/history/operations/`. Não registre segredos.

## Git e Runtime

- Branch `dev`. Cada entrega é commitada e enviada a `origin/dev` logo após o commit. Não crie branch nem PR sem ordem; merge em `main` só com pedido explícito.
- Um commit por entrega, só com os arquivos dela. Verifique o staged antes. Mensagem em português, com o trailer `Claude-Session`.
- **Coautoria é proibida:** nada de `Co-Authored-By`.
- Nunca force push, reset destrutivo nem reescrita de histórico compartilhado. Preserve trabalho alheio.
- Após cada push, sincronize o Runtime de desenvolvimento só no código. Nunca `pm:uninstall`, `DROP` ou apagamento de dados sem ordem explícita.
- Ao concluir, informe os commits com hash e as validações. Não diga que publicou sem confirmar o push.

## Escopo e segurança

- Altere só o necessário. Preserve contratos, trabalho paralelo e dados. Não faça deploy nem alteração de produção sem autorização.
- Não invente contratos, hosts, CNPJ, endereço ou dados institucionais. Sem fonte, use placeholder ou pergunte.
- Não grave caminhos absolutos de máquina nem URLs de ambiente fixas em código, testes ou docs. Use caminhos relativos à raiz e variáveis de ambiente.
- Não grave segredos, tokens ou senhas. Credenciais ficam fora do Git.

## Ambientes e URLs

- Servidor de testes (`*.aculta.toca.net.br`, Homelab) não é produção. Produção (`*.aculta.org`, Hostinger) recebe deploy só com todos os módulos e temas em RC; até lá, verificações de produção são ensaios. Referência: `docs/architecture/environments.md`.
- URL pública de conteúdo ou ação humana usa slug em português, minúsculas, hífen e sem acento. Diretiva: `docs/portal/FRIENDLY-PORTUGUESE-SLUGS.md`; gate `scripts/validate-public-slugs.php` (requer `vendor/`).

## Arquitetura e validação

- Endereço centralizado em `aculta_portal_cep` e país exclusivo Brasil (BR), conforme ADR-034 (`docs/decisions/ADR-034-pais-unico-brasil-endereco-central.md`).
- Padrão Drupal 11+ e 12 ready: `docs/portal/DRUPAL-12-STANDARDS.md`.
- Rotas `/studio` e `/studio/*` respondem 404. Rotas administrativas e de integração existem só no domínio principal.
- **Tema de validação:** valide só no tema ACULTA ativo. Não alterne `system.theme` para outros temas.
- **Olivero:** não habilitar, selecionar, testar ou exigir Olivero em gates, suítes ou aceite. Execuções antigas são histórico.
- **HTMX no tema:** padrão para interações assíncronas novas (`core/drupal.htmx`, `data-hx-*`). Drupal AJAX (`core/drupal.ajax`, `#ajax`, `use-ajax`) é legado: não introduza novos usos. Preserve links e formulários nativos como fallback.
- Aceite único de Termos e Política: `docs/portal/ACEITE-UNICO.md`.
- Antes de concluir, rode as verificações relevantes e registre o resultado. Não alegue teste que não executou.
- **Gates por escopo:** só os do diff e dos módulos dependentes, listados em `docs/operations/GATES.md`. Gate fora do escopo vai como N/A com motivo. Gate que não roda no ambiente vai como DEFERRED com motivo.
- **Saída verbosa** (PHPUnit, PHPStan, lint de projeto): `python3 scripts/agent-output.py --lines 60 -- COMANDO`.
- **Não ler por padrão:** `data/`, `references/`, `vendor/`, `handoff/`, `docs/history/`, logs, `web/core/` e `web/modules/contrib/`. Lista em `docs/portal/AGENT-TOKEN-ECONOMY.md`.

## Referências (só quando a tarefa pedir)

- Regras detalhadas: `docs/operations/AGENT-REGRAS-DETALHADAS.md`. Ambientes, Drush e banco de teste: `docs/operations/AGENT-OPERATIONS.md`.
- Regras por escopo: `docs/operations/AGENT-POLICY-INDEX.md`. Mapa do projeto: `docs/operations/MAPA-PARA-AGENTES.md`.
- Decisões do responsável: `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`.
- Procedimentos repetidos (gates, handoff, TODO, commit, navegador): `docs/agent-skills/<nome>/SKILL.md`. O Claude Code carrega pelo link em `.claude/skills/`.
- Gates: `docs/operations/GATES.md`. Saída de comandos: `docs/operations/AGENT-OUTPUT.md`. Economia de tokens: `docs/portal/AGENT-TOKEN-ECONOMY.md`.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- A committed graphify-out/graph.json is rewritten whole on every build, so a large diff to it in a pull request is expected. Do not flag it as churn or suggest skipping the graph rebuild or its hooks.
