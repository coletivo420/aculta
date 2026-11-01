# THEME-UX 0.8-B5-02 — Gates de interação das gavetas e integração com o shell

**Data:** 2026-10-22 · **Estado:** planejamento baseado no código e na documentação da branch; testes no Runtime PENDENTES.
**Antecedentes:** [Catálogo B5](theme-ux-0.8-b5-overlay-catalog.md), [B4 utility panels](theme-ux-0.8-b4-utility-panels.md), [B4 race AJAX](theme-ux-0.8-b4-02-ajax-drawer-race.md).

## Contexto confirmado
A branch já contém `context-header` e `context-tabs` como SDCs, além dos componentes de acionamento de avisos, mensagens e carrinho. Portanto não repetir criação de componentes de B1/B2 e não reiniciar fases concluídas por outros agentes. O cabeçalho global Institution Bar + Domain Header é obrigatório em todas as áreas. Drawer é comportamento Bootstrap, não uma segunda barra global.

## Fronteiras arquiteturais
- `aculta420:alerts-trigger`, `aculta420:messenger-trigger`, `aculta420:cart-offcanvas`: apresentação em SDC; Bootstrap controla offcanvas, foco, Escape e backdrop.
- `messenger-drawers.js`: carregamento AJAX sob contrato do Messenger; não duplicar gerenciador de estados.
- Commerce: remoção real de item exige mutação no backend; nunca só remover DOM.
- Conta: menu/accordion e AJAX do Portal, não tratar como gaveta Bootstrap.
- UI Patterns Library para histórias determinísticas, UI Styles somente tokens semânticos do tema, Core SDC para markup de componentes; não migrar lógica de acesso/cache para Twig.

## Matriz de homologação por propósito
| Cenário | Esperado | Evidência a registrar | Estado |
| --- | --- | --- | --- |
| Global — shell Main/Revista/Wiki/Fórum/Cursos/Loja | Institution Bar + Domain Header íntegros | URLs reais, DOM, captura por viewport | PENDENTE |
| Avisos — aberto/fechado | `aria-expanded`, `aria-controls`, foco e Escape coerentes | DOM + fluxo teclado e screen reader | PENDENTE |
| Messenger — carregamento lento/fechar/reabrir | nenhum resultado AJAX obsoleto substitui conteúdo atual | log de rede, captura e estado pós-abort | PENDENTE |
| Carrinho — item, quantidade e checkout | DOM espelha Commerce, sem dismiss falso | sessão com produto de teste e request de backend | PENDENTE |
| Gavetas — abrir uma após outra | um backdrop/uma gaveta ativa, foco restituído | teste sequencial por clique e teclado | PENDENTE |
| Minha Conta — AJAX + histórico + no-JS | rotas reais e formulários preservados | percurso com URLs/histórico e reflow | PENDENTE |
| CSS 320/390/768/1280; claro/escuro | sem overflow, modo de cor mantém geometria | capturas comparáveis | PENDENTE |
| UI Patterns Library/Styles | histórias SDC existentes renderizam sem dados privados | catálogo protegido e verificação de estilos | PENDENTE |

## Roteiro por microfases
- **B5-02A — levantamento:** capturar módulos habilitados/rotas existentes; confirmar ownership, DOM, aria e biblioteca ativa, com Drush `--uri` explícito.
- **B5-02B — interação:** testar teclado, Escape, foco, Bootstrap state events, aberturas concorrentes, rede lenta, sessões de usuário diferentes.
- **B5-02C — compatibilidade:** conferir viewport/zoom, dark/light, reduced-motion, leitores de tela, sem JS, cache e isolamento.
- **B5-02D — correções mínimas:** somente bugs reproduzíveis, arquivos escopados, stories e documentação no mesmo commit, sem criar SDC paralelo.
- **B5-02E — gates:** evidências na PR, status PASS/FAIL/BLOQUEADO por cenário e regressões do cabeçalho global antes de RC.

## Critérios bloqueantes
Nenhum botão indica `aria-expanded=true` quando a gaveta está fechada; nunca manter foco em elemento oculto; requests antigas não podem substituir conteúdo após fechar/reabrir; nenhuma interação remove item do carrinho apenas da UI. Sem sobrepor cabeçalhos, sem quebrar link real, sem exibir conteúdos de outro usuário/Group.

## Próxima fase após B5-02
**THEME-UX 0.8-B6 — Consolidação final de componentes:** revisar contratos e stories de context-header, context-tabs, feedback, overlays e readers, decidir correções pendentes sem duplicação, e auditar uso real de UI Patterns/UI Styles nos displays. Apenas abrir após evidências B5-02 suficientes ou registrar bloqueios.
