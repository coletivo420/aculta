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

1. Definir uma única subfase e o menor conjunto de arquivos-alvo.
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
