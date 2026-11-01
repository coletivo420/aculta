# THEME-UX 0.8-B6 — Consolidação SDC/UI Suite transversal

**Data:** 2026-10-22 · **Estado:** auditoria estática parcial e plano de consolidação; Runtime/homelab pendente.
**Branch compartilhada:** `docs/aculta-forum-0.1.0-dev.1`; manter PR #297 e escopo isolado.

## Fotografia verificada na árvore da branch
- **41 arquivos de metadados SDC** `.component.yml` e **65 stories** `.story.yml` em `web/themes/custom/aculta420/components/`. São contagens estáticas; os registros anteriores de 38/58 representam uma fotografia anterior, não o inventário atual.
- Presentes **context-header** (duas stories), **context-tabs** (duas stories), **empty-state** (duas stories) e **messenger-state** (três stories).
- `empty-state` é SDC experimental de apresentação, com slots `message` e `actions`, prop `compact`; não calcula permissões nem resultados.
- A fase B5-02 possui plano documentado de Runtime para overlays e AJAX; **não foi homologada nesta execução**, portanto B6 não representa aprovação retroativa de B5.

## Regra global inegociável
Preservar **Institution Bar + Domain Header multidomínio** em todos os purposes. Componentes contextuais pertencem ao conteúdo, não substituem o shell. Não reimplementar lógica Drupal, Group, LMS, Commerce, Messenger, AJAX e acessos no tema. Reutilização transversal apenas de apresentação com contrato Core SDC, ferramentas UI Patterns / UI Styles compatíveis e gates adequados.

## Matriz de consolidação — primeiro lote
| Padrão existente | Story estática | Consumidor / gate de homologação | Decisão |
| --- | --- | --- | --- |
| `context-header` | default + editorial | detectar H1 Core duplicado, breadcrumbs, dois consumidores reais e cache | preservar SDC experimental, NÃO criar duplicata |
| `context-tabs` | studio + wiki | links com `aria-current`, back/forward, AJAX com fallback no-JS, acesso | preservar SDC, não reutilizar abas de Commerce |
| `empty-state` | lms + wiki | estado oficial do provider, slots renderizáveis, mensagens acessíveis | candidato preferencial para vazio genérico; não duplicar messenger-state |
| `messenger-state` | empty/error/loading | UX de Messenger e estados específicos, AJAX e permissão | manter especializado; só generalizar após evidência |
| `alerts-trigger`, `messenger-trigger`, `cart-offcanvas` | consultar catálogo/Runtime | Escape, foco, aria, race AJAX, Commerce | manter Bootstrap como engine e SDC como apresentação |
| `card`, `content-grid`, `figure`, `quote` | presentes em inventário anterior | LMS, Revista, Wiki, Gallery — consumidores e displays reais | reutilizar após prova UI Patterns/Styles e schema |

## B6-S1 — auditoria estática de contratos
1. Fixar SHA da branch e produzir lista versionada de todos os metadados, arquivos Twig e stories, sem confundir arquivo com componente homologado.
2. Para **cada um dos 41** inspecionar: schema Core SDC, tipos/required/defaults, slots, variants, Twig, tokens CSS, stories válidas, duplicação com componentes existentes e owner.
3. Inspecionar compatibilidade real UI Patterns (Views, fields, blocks, layouts) e UI Styles (catálogo semântico) onde o código/configuração permitir; identificar dependências exclusivamente Runtime.
4. Classificar `SDC-CONFORME` só com evidência; `SDC-PARCIAL`, `LEGADO-MIGRAR`, `DESCARTAR/SUBSTITUIR` com arquivo e motivo. Não promover a conformidade integral apenas pela presença de YAML.

## B6-S2 — contratos e biblioteca visual
1. Resolver duplicação de apresentação `empty-state` vs `messenger-state`: genericidade é permitida apenas na superfície; estado de domínio permanece no módulo.
2. Consolidar como **SDC** componentes reutilizados pelo LMS, Wiki, Revista, Fórum, Conta e GanjaLab; nunca reutilizar diretamente Twig avulso.
3. Atualizar UI Patterns Library com stories determinísticas de desktop/mobile, empty/loading/error quando semânticos; deixar storybook externo e UI Suite Bootstrap fora do escopo.
4. Conferir UI Styles semânticos e tokens light/dark mantendo geometria idêntica; evitar hardcodes do CSS.

## B6-R — gates exclusivos do homelab
| Gate | Evidência requerida | Estado |
| --- | --- | --- |
| Core/SDC Devel | schema e renderização das stories | PENDENTE |
| UI Patterns / UI Styles | uso em displays e variantes reais | PENDENTE |
| Shell multidomínio | Institution + Domain Header em todas as áreas | PENDENTE |
| H1, breadcrumb, tabs e no-JS | DOM real, fluxo, foco e histórico | PENDENTE |
| Overlays B5-02 | foco, Escape, AJAX lento, Commerce | PENDENTE |
| A11y/mobile | WCAG 2.2 AA, 320/390/768/1280, zoom, dark/light | PENDENTE |
| Access/cache | usuários/Group/Commerce sem vazamentos | PENDENTE |

## Ordem para próxima execução
**B6-S1 primeiro:** comparação integral dos 41 SDCs, inventário de consumidores por código e classificação com evidência. **B6-S2 depois:** corrigir apenas contratos objetivamente defeituosos com commits pequenos e stories. **B6-R por último:** homologação no homelab. Não marcar fases B5 ou B6 como PASS sem evidência.
