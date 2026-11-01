# THEME-UX 0.8-B0 — Matriz de execução e evidências UI Suite

**Estado:** etapa de preparação documental; auditoria no Runtime pendente. **Data:** 2026-10-22.
**Dependências:** [Roadmap visual](visual-cross-product-roadmap.md) · [Auditoria A/B0](theme-ux-0.8-a-ui-suite-audit.md) · [UI Suite](ui-suite-migration-plan.md) · [Shell](shell.md).

## Regra de preservação
Institution Bar + Domain Header multidomínio são permanentes em **todos os purposes**. B0 não cria header alternativo; B1, se necessário, acrescenta somente uma região contextual **abaixo** do shell. Não remover nem contornar o cabeçalho com AJAX ou layouts fullscreen da página.

## Matriz de evidências a preencher no Runtime
| Purpose/tela | Route/URL confirmada | Shell (Institution + Domain) | Breadcrumb/H1 | SDC/render array efetivo | UI Patterns/display | UI Styles | Acesso/cache | 320px light/dark | 1280px light/dark | Gate |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Minha Conta | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| GanjaLab | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| GanjaLab420 Dashboard | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| GanjaLab420 Sala/H5P | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| Revista | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| Wiki | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| Fórum | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| Galeria | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |
| Loja | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | PENDENTE | NÃO AUDITADO |

Não inventar rotas/URLs, percentuais, resultados ou checks. Se uma tela não existir ou não houver acesso de teste, marcar **NÃO APLICÁVEL / BLOQUEADO com motivo**, não PASS.

## Inventário das ferramentas — formulário de auditoria
| Superfície | Pergunta de inspeção | Resultado |
| --- | --- | --- |
| Core SDC | Quais componentes têm props/slots, CSS/JS e consumidores reais? | PENDENTE |
| UI Patterns | Quais View displays, fields, formatters, blocks e layouts integram SDC? | PENDENTE |
| UI Patterns Library | Quais stories efetivas existem e funcionam com permissão administrativa? | PENDENTE |
| UI Styles | Quais estilos semânticos do tema estão em uso e onde? | PENDENTE |
| Fences | Há wrappers de campo cujo markup já resolve a necessidade? | PENDENTE |
| SDC Devel | Ferramenta disponível no ambiente dev? Quais warnings gerou? | PENDENTE |
| VVJ | Quais Views usam adaptadores aprovados sem violar acessibilidade? | PENDENTE |
| Section Library | Há consumidor real e permissão adequada? | PENDENTE |

## Classificação das lacunas
Para cada problema anexar **captura ou caminho de arquivo + linha**, consumidor inicial, segundo consumidor real, elemento Core/Bootstrap/SDC já testado, resolução decidida: **reutilizar / variante / integração UI Patterns / UI Styles / novo SDC / adaptador de domínio**. Validar cache, Group/usuário, fallback sem JS e impactos de CSS.

## Sequência operacional em pequenas fatias
1. **B0-R1:** congelar fotografia da branch/HEAD e módulos UI Suite ativos. Documentar divergência Runtime/sync, sem importar configuração global.
2. **B0-R2:** auditar shell multidomínio em todas as rotas acessíveis, mobile e desktop; listar problemas reais (sticky, overflow, dupla navegação e H1).
3. **B0-R3:** mapear consumidores de SDC, UI Patterns/Styles, stories, Fields, Views, Fences e VVJ; classificar o reuso.
4. **B0-R4:** completar matriz, gates e decisão de primeiro slice transversal B1; fechar auditoria somente com evidência.
5. **B1 (posterior):** implementar o menor padrão de cabeçalho contextual **quando** lacuna comprovada; no mínimo 2 consumidores reais de diferentes áreas, story, documentação, tokens, dark/light e regressões.

## Gate para iniciar B1
B0-R1 a R4 com evidências armazenadas nos relatórios do tema; shell global preservado; Core/Bootstrap e UI Suite avaliados primeiro; inclusão de novos SDCs justificada. Nenhum gate foi executado nesta atualização.

## Revisão dos critérios de auditoria — SDC/UI Suite primeiro

Acrescentar classificação obrigatória a **cada candidato**: `SDC-CONFORME`, `SDC-PARCIAL`, `LEGADO-MIGRAR` ou `DESCARTAR/SUBSTITUIR`. A matriz só aprova **reutilização direta** do primeiro grupo. O segundo deve ser corrigido, o terceiro migrado antes de reutilizar, e o quarto substituído ou excluído. Registrar também path de `.component.yml`, schema de props, slots, arquivos story, aplicação UI Patterns (quando compatível), tokens/UI Styles, consumidores reais e gaps acessíveis.

**Regra da primeira fatia B1:** se page title/breadcrumb Core e Bootstrap já cumprem a função, eles permanecem providers, mas a **abstração visual compartilhável** deve ter SDC; não promover markup Twig isolado como componente reusable. Se `content-section` já for SDC-conforme, estendê-lo com variante/slots é preferível a novo `context-header`. Componente sem Base SDC/UI Suite não é aprovado para nova adoção.

## Resultado estático B0-R1

[Inventário de 38 SDCs, 55 stories e backlog M0–M6](sdc-ui-suite-inventory-2026-10-22.md). Evidência apenas de arquivos existentes no GitHub; todas as colunas de Runtime, UI Patterns efetivamente aplicado, acessibilidade e shell por purpose permanecem PENDENTES. Não converter este inventário em PASS de B0.
