# P5-extra-2 — Economia de tokens e roteamento de modelos

Projeto: **Modernização Drupal 11+ Aculta Portal**. Diretriz para agentes humanos e de IA; não altera runtime Drupal.

## Roteamento proporcional ao risco

| Tarefa | Modelo preferencial |
| --- | --- |
| Pesquisa, busca de arquivos/linhas, inventário, resumos e documentação simples | Econômico disponível |
| Transformação curta, determinística e verificável | Econômico, com validação |
| Implementação DI, Entity API, Views e Form API | Intermediário/capaz, conforme risco |
| Revisão final, access, cache privado, Domain, OAuth, Commerce, segredos, conflitos e merge | Maior capacidade/raciocínio disponível |

Começar pelo modelo econômico **somente quando seguro e verificável**. Escalar diante de risco, incerteza técnica ou falha persistente. Revisões formais `-R` e P10 exigem revisão independente com modelo de maior capacidade e testes reais. Nomes, disponibilidade e preços variam; conferir no provedor. Os scripts não escolhem nem trocam modelos automaticamente.

## Economia de contexto e tokens

1. Definir uma única subfase e o menor conjunto de arquivos-alvo. Forneça ao agente apenas o `TODO.md` do componente (ou o `TODO.md` raiz, se a tarefa for transversal) e o arquivo que será alterado; o resto entra por `rg` quando a tarefa pedir.
2. Usar `rg -n` e leitura por linha; não carregar repositório, vendor, Core, contrib ou logs inteiros sem motivo.
3. Preferir `git diff --stat` e `git diff --check`; preservar erros e linhas úteis.
4. Não repetir roadmap extenso; citar caminhos canônicos e só registrar novas decisões.
5. Agrupar leituras independentes e aproveitar evidências verificadas.
6. Reportar sucintamente objetivo, arquivos, SHA, verificações executadas, pendências e próximo passo.
7. Após duas tentativas equivalentes sem progresso, reformular ou escalar.
8. **Nunca economizar tokens sacrificando segurança, testes, documentação, revisão ou honestidade sobre resultados.**

## CLI somente leitura

```sh
python3 scripts/portal-agent-budget.py route research
python3 scripts/portal-agent-budget.py route docs
python3 scripts/portal-agent-budget.py route implementation
python3 scripts/portal-agent-budget.py route final-review
python3 scripts/portal-agent-budget.py context P5.2-A 3000
```

`route` sugere categoria de modelo para seleção manual. `context` gera texto resumido com limite em caracteres. Nenhum comando executa IA, usa rede, altera configuração de conta ou escreve no repositório. Ler os arquivos-alvo integralmente quando necessário.

## Segurança e separação de escopo

Aplicar `docs/portal/DRUPAL-11-STANDARDS.md`, `AGENTS.md`, PR #90 e roadmap. Não introduzir service locators, storage paralelo nem enfraquecer segurança/checkout. A portabilidade entre bancos pertence somente ao projeto independente DBTNG-2. Não declarar PASS sem execução.

## Não ler por padrão

Estes caminhos não entram no contexto sem motivo específico da tarefa. Quando precisar, leia só o trecho ou o arquivo nomeado.

| Caminho | Tamanho (2026-11-05) | Motivo |
| --- | --- | --- |
| `data/` | 93 MB | dados de inventário e importação; não é código |
| `references/` | 4,6 MB | material de referência externo |
| `vendor/` | (Composer) | dependências; leia só a API usada |
| `web/core/`, `web/modules/contrib/` | (Core e contrib) | somente leitura; use `rg` no símbolo |
| `handoff/` | 784 KB | ativos de marca (podplant420); consulte pelo caminho exato |
| `docs/history/` | 2,5 MB | arquivos históricos (worklogs, changelogs antigos, estados, rodadas) |
| logs de execução (`$SUITE_OUT`, temporários) | variável | evidência privada e volumosa; use `agent-output.py` |
| `web/modules/custom/aculta_portal/modules/aculta_forum/docs/ROADMAP.md` | 142 KB | roadmap; leia só a seção pelo título: `rg -n '^## ' <arquivo>` |
| `web/modules/custom/aculta_portal/modules/aculta_studio/docs/ROADMAP.md` | 108 KB | roadmap; mesma regra de seção |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/docs/ROADMAP.md` | 93 KB | roadmap; mesma regra de seção |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/docs/TEST-MATRIX.md` | 49 KB | matriz de rodadas datadas; leia a rodada citada |
| `web/modules/custom/aculta_portal/modules/aculta_wiki/README.md` | 50 KB | README longo; leia a seção pelo título |
| `web/modules/custom/aculta_portal/modules/aculta_events/docs/ROADMAP.md` | 57 KB | roadmap; mesma regra de seção |
| `web/modules/custom/aculta_portal/modules/aculta_messenger/docs/ROADMAP.md` | 48 KB | roadmap; a evolução por dev.N está arquivada |
| `web/modules/custom/aculta_portal/modules/aculta_document_viewer/docs/ROADMAP.md` | 34 KB | roadmap; mesma regra de seção |
| `docs/portal/ROADMAP.md` | 34 KB | roadmap do Portal; leia só a fase citada (`portal-agent-budget.py` lê este arquivo) |
| `docs/portal/TOKENS-E-ATRIBUTOS.md` | 102 KB | referência de tokens; consulte a classe ou o atributo com `rg` |
| `docs/architecture/ROUTES-SITEMAP.md` | 119 KB | mapa de rotas; consulte a rota com `rg` |
| `README.md` (raiz) | 101 KB | leia só a seção necessária (`rg -n '^## ' README.md`) |
| `docs/operations/DEBT-REGISTER.md` | 68 KB | registro de dívidas; busque o ID com `rg -n "DT-"` e leia só a linha |
| `docs/modules/ACULTA-LMS-ROADMAP.md` | 78 KB | roadmap do LMS; leia só a seção citada |
| `docs/operations/ACULTA-MODULES-CLOSEOUT.md` | 71 KB | fechamento de módulos; leia só o módulo citado |
| `docs/portal/ACULTA-USER-PLAN.md` | 66 KB | plano de usuário; leia só a seção citada |
| `CHANGELOG.md` de módulos acima de 40 KB | variável | leia só a primeira entrada, com `head -40` |

Roadmaps e matrizes arquivados (rodadas e fases encerradas): `docs/history/modules/`. Não leia por padrão.

Regra complementar: em documento ativo, cite o caminho canônico, não o histórico. Histórico só se a tarefa for auditar o passado.

## Orçamento de leitura por tipo de tarefa

Teto de leitura inicial para o contexto de partida, antes da tarefa. Valores são **estimativas de planejamento**, não medições; revisar depois de algumas rodadas. Ultrapassar o teto exige motivo escrito no relatório.

| Tipo de tarefa | Leitura de partida (teto) | Além disso |
| --- | --- | --- |
| Pesquisa ou localização | `AGENTS.md` raiz (~8 KB) e o índice da área; resto por `rg -n` | trecho do arquivo encontrado |
| Documentação ou status | o documento alvo e a linha da dívida (`rg -n "DT-"`) | seção citada, não o arquivo inteiro |
| Mudança no Portal | `docs/portal/GUARDRAILS.md` e a seção do `DRUPAL-11-STANDARDS.md` da área; `AGENTS.md` do módulo (~7 KB) | símbolos usados via `rg` |
| Mudança no tema V2 | `web/themes/custom/aculta420_v2/AGENTS.md` (~8 KB) e a seção do `docs/architecture.md` tocada | `docs/gates.md` só para o gate do escopo |
| Revisão final | `git diff --stat` e `git diff --check`; o diff dos arquivos alterados | nenhuma leitura ampla de árvore |
| Gate ou teste | o script do gate e a saída via `scripts/agent-output.py` | logs completos só se a saída resumida falhar |

Regra de corte: se a tarefa precisar de mais de três documentos de partida, o escopo está largo. Reduzir a subfase antes de ler mais.

## Ideias externas avaliadas (2026-11-05)

Fontes pesquisadas: [Composio, "9 Ways to Cut Token Consumption in Claude Code"](https://composio.dev/blog/ways-to-cut-token-consumption-in-claude-code) e [PostHog, "What nobody tells you about writing agent skills"](https://posthog.com/newsletter/writing-agent-skills).

| Ideia | Decisão | Onde está no projeto |
| --- | --- | --- |
| Skills com divulgação progressiva: só nome e descrição entram no início | Adotado | `docs/agent-skills/`; gate `validate-agent-token-budget.php` limita descrição (200 caracteres) e SKILL.md (4000 bytes) |
| Poucas skills, para evitar escolha errada | Adotado | Gate limita a 7 skills |
| Filtrar a saída de comando (`grep`, `head`) | Adotado | `scripts/agent-output.py`; `AGENT-OUTPUT.md`; skill `aculta-leitura-economica` |
| Subagente com caminhos definidos e resposta com limite | Adotado | Skill `aculta-leitura-economica`, item 6 |
| Handoff em arquivo antes de `/clear` ou `/compact` | Adotado | Skill `aculta-handoff`; `ESTADO-DA-SESSAO.md` |
| CLAUDE.md mínimo | Adotado | Gate limita CLAUDE.md a 3000 bytes |
| Mapa do repositório para o agente | Já existia | `docs/operations/MAPA-PARA-AGENTES.md`; não duplicado |
| Limitar pensamento estendido com variável de ambiente | Não adotado | Configuração da conta e da ferramenta, não do repositório. Pode ser decidido pelo responsável |
| Modelo menor para subagentes com variável de ambiente | Não adotado | Mesma razão; o roteamento por risco já está na seção "Roteamento proporcional ao risco" |
| Consolidar integrações em um servidor MCP de terceiros | Não adotado | Produto externo e sem necessidade do projeto; não ampliar integrações sem decisão |
| Procedimento de migração de classe para token | Adotado como skill | `docs/agent-skills/aculta-data-portal-ui/` |
| Permissão de leitura com aprovação para arquivos pesados (`permissions.ask` com `Read(...)`) | Adotado (2026-11-05) | `.claude/settings.json`: `data/`, `references/`, `handoff/`, `docs/history/`, `vendor/`. A regra usa `ask` e não `deny`, para não bloquear leitura deliberada. Gate de regra: não há; verificar no `/status` |
| Filtro de saída que preserva linhas de falha fora da cauda (`--match`) | Adotado (2026-11-05) | `scripts/agent-output.py`; `AGENT-OUTPUT.md` |
| Ferramenta externa de compressão de saída (binário no PATH que reescreve comandos) | Não adotado | Exige instalar binário e confiar em terceiros para reescrever comandos; o wrapper do projeto cobre o caso sem dependência |
| Mapa do repositório gerado (repomix, aider repo map, agentmap) | Não adotado | `docs/operations/MAPA-PARA-AGENTES.md` já cumpre a função; gerar mapa automático acrescenta arquivo a ser lido e sem revisão |
| Contexto isolado por subagente com resposta limitada | Adotado | Skill `aculta-leitura-economica` (já na tabela acima) |
| Recuperação sob demanda e CLAUDE.md curto (princípio de "conciseness over completeness") | Adotado | Gate de tamanho de CLAUDE.md e AGENTS.md; regras de módulo em subdiretórios |

Fontes desta rodada: [Composio, 9 ways](https://composio.dev/blog/ways-to-cut-token-consumption-in-claude-code); [Morph, context engineering](https://www.morphllm.com/context-engineering); [documentação de configurações do Claude Code](https://code.claude.com/docs/en/settings) (sintaxe de `permissions.ask`, `deny` e `allow`).
