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
