# ACULTA420 V2 - Homologação do canvas branco e superfícies verdes

Data: 2026-11-12. Branch: dev. Escopo: tema aculta420_v2.

## Contrato implementado

- Claro: canvas branco (#fff), estrutura verde suave (#e7eedc) e borda #6b7d70.
- Escuro: canvas grafite (#171513), estrutura verde #2a3a2f, borda #8fa397.
- Raised branco no claro e grafite no escuro fica preservado para cartões internos e leitura.
- Omitir tone nos SDCs surface, surface-panel e surface-accent escolhe green-soft.
- Tons explícitos preservados: canvas, raised, subtle, institution.
- Cabeçalho institucional do panel e faixas tricolores mantidos.
- UI Styles recebeu variante green-soft; UI Skins e mapeamento de Domain IDs não foram alterados.

## Matriz de homologação

| Gate | Evidência necessária | Estado |
| --- | --- | --- |
| Estático | Gate novo, foundations, schemas, UI Styles, Stylelint | EXECUÇÃO PENDENTE NO CHECKOUT |
| SDC no Runtime | Renderizar as 3 surfaces e nested na UI Library | DEFERRED |
| CSS computado | body, cards, border, preview light/dark e raised interno | DEFERRED |
| UI Styles | Disponibilidade, persistência por editor/admin, CKEditor5 | DEFERRED |
| Skins | 10 Domain IDs reais, claro/escuro, sem vazamento | DEFERRED |
| Layout | 320, 390, 768, 1440 e 1920 CSS px, zoom 200% | DEFERRED |
| Acessibilidade | Texto AA 4,5:1, focos e bordas essenciais 3:1, Tab, axe | DEFERRED |
| Aplicação | Formulários, cards, artigos, sidebars, header, modal, tabelas | DEFERRED |
| Perfis/cache | Anônimo/editor/admin e cache sem vazamento | DEFERRED |
| Aceite | Capturas antes/depois e aprovação humana | DEFERRED |

## Procedimento para agente no servidor de desenvolvimento

1. Conferir status, HEAD/origin/dev, arquivos locais e AGENTS.md; não tocar em trabalho alheio.
2. Rodar o novo gate e os gates de design foundations, V2 foundations, schema SDC, UI Styles, CSS lint e render SDC.
3. Usar aculta-runtime-sync cotidiano somente com baseline seguro; sem config:import ou updatedb.
4. Validar UI Library dos três SDCs, todos os tons, modos e nested raised.
5. Em todos os dez Domain IDs configurados, verificar páginas públicas e os blocos SDC.
6. Validar foco, teclado, 200% zoom, 320/390/768/1440/1920, axe e contraste computado.
7. Gravar tests/browser/capturas/surface-inversion/2026-11-12/manifest.json; PNGs fora do Git.
8. Registrar falhas PASS/FAIL/DEFERRED por cenário; corrigir somente regressões comprovadas.
9. Atualizar TODO/status/roadmap e este relatório; novo commit/push dev sem força.

## Controle de mudanças e riscos

A mudança global de --color-canvas afeta qualquer CSS que use o canvas.
Card raised e fundo branco não podem perder contorno/foco; comparar computed styles.
Não substituir --color-surface-raised globalmente nem reaplicar purpose palette.
Rollback por commit novo e isolado, sem reset ou reescrita de histórico.
Esta sessão via GitHub não executou testes Drupal/Runtime e não declara aceite visual.
