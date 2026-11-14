# Avaliações das Skills UI Suite

> Matriz de cenarios historica para UI Suite, nao catalogo completo das 23 skills atuais. O validador confere a estrutura; avaliacoes comportamentais em Claude/Codex e evidencias de runtime devem ser registradas por execucao. Em 2026-11-13, perguntas redundantes e limites de tamanho foram flexibilizados, mas os testes de seguranca e contratos permanecem obrigatorios.

Este arquivo registra cenários de comportamento e o que precisa ser observado. A validação de links/frontmatter não prova que um agente carregou a Skill. Não marque avaliação como aprovada sem execução e evidência.

| ID | Solicitação de avaliação | Resultado esperado |
| --- | --- | --- |
| A | Criar superfície com faixa tricolor lateral | Procurar SDC existente, reutilizar tokens, schema estrito e story; sem classe paralela. |
| B | Disponibilizar SDC existente no Layout Builder | Usar integração `ui_patterns_layouts`/Blocks aplicável, sem duplicar componente. |
| C | Criar variação visual com opções existentes | Reutilizar UI Style/token/variante antes de declarar classe nova. |
| D | Adicionar ícone selecionável ao SDC | Usar packs ACULTA existentes; não alegar UI Icons Picker instalado; acessibilidade correta. |
| E | Auditar bibliotecas pesadas/duplicadas | Inspeção direcionada com evidências; assets condicionais e sem ocultar componente da Library. |
| F | Invocar explicitamente Skills locais em Claude Code e Codex | Cada CLI localiza a fonte canônica, carrega instruções e não colide com Skills globais; testar versão real. |
| G1 | Tornar SDC um bloco editorial | Diferenciar SDC de bloco Drupal; apontar `ui_patterns_blocks`; preservar acesso/cache. |
| G2 | Escolher entre Blocks, Layouts, Field, Formatters e Views | Seleção pelo alvo real, sem duplicar plugin. |
| G3 | Configurar component em formatter | Explicar per-item/campo multivalorado, mapeamento, acesso, tradução e cache. |
| G4 | Persistir árvore via UI Patterns Field | Identificar `ui_patterns_source`, widgets e configuração, mas condicionar detalhes a release/runtime; explicar tradução/revisão/acesso/limites editoriais. |
| G5 | Aplicar UI Styles no Layout Builder | Reconhecer integração exportada e validar runtime, classes já definidas e permissões. |
| G6 | Preservar CSS por componente | Manter bibliotecas SDC condicionais; não mover CSS para carga global sem evidência. |
| G7 | Usar pack de ícones | Reusar Phosphor/Fluent e SDC `icon`; não criar picker/emoji paralelo. |
| G8 | Comparar stories e examples | Distinguir UI Patterns Library de UI Examples; não manter previews próprios ACULTA. |
| G9 | Recomendar Display Builder/UI Skins | Reconhecer não instalados e opcionais; sem instalação/migração automática; analisar ADR-054/tokens. |
| G10 | Validar alias de UI Patterns | Verificar o redirecionamento ao overview oficial, guarda administrativa no MAIN e ausência de rota de preview própria. |
| G11 | Conteúdo multidomínio/purposes | Preservar host MAIN para rotas administrativas, purposes e `data-theme`; não expor config em host de conteúdo indevido. |
| G12 | Prevenir sobreposição de biblioteca | UI Patterns Library é a biblioteca única de SDCs e stories; não criar catálogos/previews paralelos nem ocultar componentes para performance. |

## Execução local

- Estática: `python3 scripts/validate-agent-skills.py` verifica frontmatter, nomes, referências, symlinks e casos documentados.
- Fonte: revisar `composer.json`, `composer.lock`, `config/sync/core.extension.yml` e YAML/arquivos do tema.
- Runtime/browser: DEFERRED nesta auditoria neste worktree sem Core/contrib/vendor executáveis. O registro de sessão de 10-11 relata PHPUnit da UI Library 9/9 e homologação das prévias no runtime em trabalho anterior; isso não é uma execução desta avaliação.
- Smoke test executado: Claude Code `2.1.322`, `/ui-patterns` com ferramenta Read permitida retornou a regra de não passar entidades/dados sensíveis indiscriminadamente às props. Codex CLI `0.167.0`, `debug prompt-input '$ui-patterns'` incluiu os nomes `ui-patterns`, `sdc`, `ui-styles` e `ui-icons` no contexto. Isto comprova um caso explícito por agente e descoberta estrutural/amostral; a invocação explícita de cada uma das 18 Skills em ambos os CLIs não foi executada, portanto avaliação F completa permanece DEFERRED.
- Não rodar conteúdo de Graphify que apague artefatos. A avaliação de análise de grafo só pode usar modo não destrutivo autorizado e corpus restrito.
