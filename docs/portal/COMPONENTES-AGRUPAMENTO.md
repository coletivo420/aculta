# Agrupamento de estilos repetidos dos componentes

Status: **escaneamento de 2026-10-16**. Primeira etapa aplicada (gatilhos da barra). O restante está listado com
contagens para as próximas etapas. Mesmo princípio dos contadores (`CONTADORES.md`): uma fonte de verdade por padrão visual.

## 1. Escaneamento feito

Busca nos arquivos de CSS do tema (`css/` e `components/`) por declarações repetidas entre componentes.

| Padrão | Ocorrências | Situação |
|---|---|---|
| Gatilho de ícone da barra (tamanho, layout, fonte, foco) | 3 (carrinho, avisos, mensagens) | **Agrupado** na classe `aculta-utility-trigger` |
| Contador de não lidos (pílula, cor de alerta) | 4 arquivos | **Agrupado** em `aculta-unread-count` (ver `CONTADORES.md`) |
| Foco visível: `outline: 3px solid var(--aculta-focus-ring)` | 23 arquivos | Pendente (ver seção 3) |
| Pílula `border-radius: 999px` | 4 arquivos (contador, perfil público, leitura de mensagem, gaveta) | Pendente (ver seção 3) |
| Painel lateral (offcanvas) de avisos e mensagens | regras já agrupadas em uma lista de seletores (`portal-cart.css`) | Já agrupado; sem ação |

## 2. Aplicado nesta etapa

- O gatilho base (`aculta-utility-trigger`, em `portal-cart.css`) já era a fonte. Os arquivos de avisos e de mensagens
  repetiam o mesmo tamanho, layout, fonte e foco. Esses duplicados saíram. Cada gatilho mantém só a posição do seu
  contador (`.aculta-alerts-trigger .aculta-unread-count` e `.aculta-messenger-trigger .aculta-unread-count`).
- Verificado: gate de design do tema passa; HTML da página pública mantém os três gatilhos com a mesma marcação.

## 3. Pendente (próximas etapas, em ordem)

1. **Foco visível (23 arquivos)**: criar um único token/regra de foco no tema (`css/base.css` ou um componente de
   foco) e trocar as declarações repetidas por uma referência a ele. Impacto: acessibilidade. Exige teste de teclado
   em cada componente.
2. **Pílulas (4 arquivos)**: `public-profile`, `messenger-drawer`, `messenger-read-status` e `unread-count`. Só o
   contador é um indicador numérico; os outros são rótulos. Antes de agrupar, decidir se rótulo e contador são o
   mesmo componente.
3. **Revisar o carrinho**: `portal-cart.css` mistura estilo de gatilho, contador, caixa de status e cabeçalho.
   Separar cada parte no componente certo, sem mudar a aparência.

## 4. Regras

- Agrupar só quando o padrão visual é o mesmo. Se a aparência diverge, o padrão continua separado.
- Não alterar a marcação do módulo: o agrupamento é de CSS e de classe no tema.
- Cada etapa com teste visual e de teclado no navegador (não feito daqui).

## 5. Segunda varredura: painel da conta e demais áreas (2026-10-16)

Escopo: `css/components/portal-account*.css`, `account.css` e os componentes que usam a caixa de superfície.
Critério de agrupamento: só quando a aparência é igual; divergência de valor exige decisão visual antes.

| Padrão | Onde | Achado | Decisão |
|---|---|---|---|
| Caixa de superfície (`surface-raised` + `border-default`) | 15 arquivos (painel, cursos, conteúdo, perfil, wiki, avisos, mensagens, autenticação) | Raio diverge: 0,25 rem (painel), 0,1875 rem, 0,5 rem | Não agrupar sem decisão visual. Candidato: um componente de caixa com o raio de 0,25 rem, após conferir cada uso |
| Botão de altura mínima 2,75 rem (toque) | 16 ocorrências | Mesma altura de toque (requisito de acessibilidade) | Agrupar em um utilitário de "alvo de toque" com a mesma regra |
| Link de aba da conta (`account-data-link`) | Meus dados, Atividade, central Avisos/Mensagens | Já usa o mesmo token e a mesma classe no painel | Já agrupado; sem ação |
| Foco visível | 14 ocorrências no painel, 23 no tema | Mesma declaração | Agrupar junto com a seção 3 (foco único) |
| Layout de lista de itens (`display: flex` + `align-items: center`) | muitos | Usos variados | Sem ação: são layouts locais |

Próximas etapas recomendadas:

1. Foco visível em uma única regra (seção 3, item 1). Afeta o painel e o tema inteiro.
2. Alvo de toque: utilitário único de altura mínima para botões e links de ação.
3. Caixa de superfície: escolher o raio canônico com o responsável e agrupar os 15 arquivos.
