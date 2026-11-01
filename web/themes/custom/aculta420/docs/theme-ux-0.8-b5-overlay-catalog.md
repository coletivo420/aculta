# THEME-UX 0.8-B5 — Catálogo de overlays e estado acessível

**Data:** 2026-10-22. **Estado:** hardening do contrato dos acionadores implementado, homologação no homelab pendente.

## Componentes e ownership
| Superfície | Trigger e conteúdo | Gerenciador | Fonte de dados |
| --- | --- | --- | --- |
| Avisos | `aculta420:alerts-trigger` | Bootstrap Offcanvas + `messenger-drawers.js` para AJAX | módulo de avisos |
| Mensagens | `aculta420:messenger-trigger` | Bootstrap Offcanvas + `messenger-drawers.js` para AJAX | Aculta Messenger |
| Carrinho | `aculta420:cart-offcanvas` | Bootstrap Offcanvas | Portal/Commerce |
| Minha Conta | CSS `portal-account.css`; navegação do módulo | `account-navigation.js` Drupal AJAX (não offcanvas) | Portal/usuário |

## Alteração desta fase
- Os três botões de utilidade trazem `aria-controls` e `aria-expanded=false` inicialmente, para representar estado fechado.
- Os três elementos Bootstrap offcanvas têm `data-aculta-utility-drawer`. O comportamento global `js/navigation.js` associa o id da gaveta ao seu botão e sincroniza `aria-expanded` nos eventos `show.bs.offcanvas` e `hidden.bs.offcanvas`.
- **Não** cria gerenciador adicional de backdrop, Escape ou foco: a biblioteca Bootstrap permanece a fonte de verdade. Sem novos eventos no Ajax ou URLs de navegação.
- Mantidas Institution Bar + Domain Header e a ordem de ícones. O desenho de Minha Conta segue o módulo com navegação progressiva e layout responsivo.

## Compatibilidade e limites
Não usar `role=tab` em botões de offcanvas. Não simular fechamento do item do Commerce. Evitar aninhar offcanvas e Drupal dialog; validar foco quando diálogos de confirmação de mensagens são acionados a partir de gavetas.

## Critérios para homologação
1. Abrir e fechar cada uma das 3 gavetas por ícone, X, backdrop e Escape; inspecionar `aria-expanded`, foco e overlays.
2. Abrir uma gaveta após outra, verificar se não existem backdrops empilhados ou controles com estado expandido incorreto.
3. Usar rede lenta nos carregamentos de avisos/mensagens, abortar e reabrir; validar correção de race B4-02.
4. Carrinho: dados atualizados, checkout e nenhum dismiss visual enganoso sem mutação do Commerce.
5. Minha Conta: menu lateral/accordion, formulários AJAX, histórico, comportamento sem JS e viewports 320/390/768/1280.
6. Modo escuro/claro, leitor de tela, WCAG 2.2 AA, Drush CR, JS lint, UI Patterns Library, ausência de regressão no shell e isolamento por conta.
7. Documentar capturas e resultados na PR antes de marcar PASS.

## Próxima microfase B5-02
Auditoria do DOM real e ajustes finos da interação de drawers/modais, incluindo estados pós-AJAX e foco. Catalogar regras visuais no tema e manter contratos de comportamento nos módulos existentes, sem inventar novo SDC para invólucro Bootstrap já funcional.

**Nenhum teste de interface no servidor foi executado nesta fase.**

## Encaminhamento B5-02 (2026-10-22)

A fase seguinte está detalhada em [B5-02 — gates Runtime para gavetas](theme-ux-0.8-b5-02-drawer-runtime-gates.md), com matriz de evidências de offcanvas, AJAX, foco, Commerce, mobile e preservação do shell. Todos os testes dependentes de homelab estão **PENDENTES**.
