# THEME-UX 0.8-B2-05 — Revisão estática de integração e gates Runtime

**Data:** 2026-10-22 · **Status:** revisão de código, **não homologado**.
**Bases:** [B2 Context Tabs](theme-ux-0.8-b2-context-tabs.md) · [shell](shell.md) · [UI Suite](ui-suite-migration-plan.md).

## Evidências inspecionadas no GitHub
- `context-tabs.twig`: navegação por `<nav aria-label>`, slot `items`; não usa `role=tab` nem data-bs-toggle.
- `context-tabs.css`: regras para links com `aria-current=page`, foco e lista Drupal `item_list`; verificar computed style e compatibilidade com CSS de Studio no navegador.
- `StudioController::linkList()`: mantém URLs `Url::fromRoute`, links `PortalLinkElement`, estado ativo e ganchos `data-aculta-studio`; somente `tab` e `subtab` envoltos no SDC.
- `WikiController::contextNavigation()`: dois destinos Drupal reais `aculta_portal.wiki_home` e `aculta_portal.wiki_search`, com estado ativo e links renderizáveis.
- Shell: nenhuma alteração de `page.html.twig` nesta fase.

## Pendências verificáveis somente no Runtime
| Gate | Estado |
| --- | --- |
| Descoberta do SDC pelo Core e stories UI Patterns Library | PENDENTE |
| Drush CR, lint PHP/Twig, validação do schema SDC | PENDENTE |
| Studio AJAX, history e fallback sem JS | PENDENTE |
| Wiki início/busca (URLs, query strings e foco) | PENDENTE |
| ARIA, list markup e navegação teclado | PENDENTE |
| 320/390/768/1280/1440px e 200–400% zoom | PENDENTE |
| Claro/escuro com mesma geometria, CSS cross-module | PENDENTE |
| Access/cache e ausência de vazamento por usuário/domínio | PENDENTE |
| Institution Bar + Domain Header em ambas as áreas | PENDENTE |

**Gate de promoção:** não marcar B2 como homologado até todas as verificações realizadas e evidenciadas. Em falha, correção mínima com story/documentação no tema e nova inspeção.

## Transição para B3 — Estados e feedback
Planejar padrão transversal `feedback-state` via Core SDC, UI Patterns Library e UI Styles; antes de criar, inspecionar `feedback/messenger-state`, `content/institutional-notice`, mensagens Drupal Core e estados de LMS, Wiki e Commerce. Não misturar state de transporte AJAX com confirmação de operação de backend. Criar apenas se contrato neutro e dois consumidores reais forem comprovados.

**Regra mantida:** reutilização direta apenas de SDCs alinhados ao UI Suite; Institution Bar + Domain Header permanentes.
