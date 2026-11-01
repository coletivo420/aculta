# Contadores de não lidos

Status: **padronizado em 2026-10-16**. Fonte de verdade da aparência: `web/themes/custom/aculta420/css/components/unread-count.css`.
Decisão de origem: a pedido do responsável, todos os contadores passam pelo mesmo token do tema, com a aparência dos
contadores da barra multidomínio.

## 1. Contrato em uma linha

Um número de itens não lidos é exibido com **uma única classe de tema**, `aculta-unread-count`. Nenhum contador tem
estilo próprio. A marcação que o módulo entrega usa o token neutro `data-portal-ui`; o tema traduz esse token nessa classe.

## 2. Quem faz o quê

| Camada | Responsabilidade | Onde |
|---|---|---|
| Módulo (dado) | Calcula o número de não lidos de cada pessoa e entrega o valor e o token | ex.: `aculta_messenger` (`AvisoService`, `MessengerBarHooks`) |
| Tema (apresentação) | Mapeia o token para `aculta-unread-count` e define a aparência | `src/Hook/PortalUiHooks.php`, `css/components/unread-count.css` |
| Gatilho (posição) | Posiciona o contador sobre o ícone, quando ele fica sobre um ícone | `components/patterns/*-trigger/*.css` |
| Script (atualização) | Atualiza o número sem recarregar, pelos atributos `data-aculta-*` | `js/counters.js`, `js/messenger-drawers.js` |

O módulo nunca emite classe de design (regra da fronteira módulo/tema, `validate-module-theme-boundary.php`).

## 3. Aparência

- Pílula com a cor semântica de alerta do tema (`--aculta-status-error-border`) e texto inverso
  (`--aculta-text-inverse`). Mínimo 1,25 rem, fonte 0,65 rem, peso 700.
- Zero não lidos: o contador some (`data-count="0"`). Acima de 99: mostra `99+`.
- A posição não faz parte do contador: sobre o ícone do gatilho, o gatilho define a posição; no meio do texto, o
  contador fica na linha.

## 4. Onde está hoje

| Contador | Dado | Marcação | Situação |
|---|---|---|---|
| Carrinho (barra) | Itens no carrinho (`cart-offcanvas`) | `aculta-unread-count`, `data-aculta-cart-count` | Padronizado |
| Avisos (barra) | Alertas + comunicados + avisos da pessoa (`MessengerBarHooks`) | `aculta-unread-count`, `data-aculta-alerts-count` | Padronizado |
| Mensagens (barra) | Conversas não lidas (`alerts-trigger` de mensagens) | `aculta-unread-count`, `data-aculta-messenger-count` | Padronizado |
| Badge de conta (menu) | Itens não lidos por seção da conta (`unread-badge`) | `aculta-unread-count` | Padronizado |
| Fórum (tópico seguido) | Avisos não lidos por tópico, via `AvisoService` | `forum-topic-unread` → `aculta-unread-count` | **Planejado**: o produtor `preprocess_forums` foi removido com o Forum contrib; a nova View ACULTA ainda não fornece essa contagem por linha. |
| LMS (aulas, cursos) | — | — | **Pendente**: ainda não há dado de não lidos do LMS. Quando houver, usa o mesmo token. |

## 5. Regras de integração (para novos contadores)

1. O módulo calcula o número e o entrega com `data-portal-ui` (token neutro) ou com `data-count` no gatilho.
2. Não criar classe de contador nova nem estilo próprio. Se a posição for diferente, ajustar só a posição no gatilho.
3. Atributos `data-aculta-*-count` e `data-count` são o contrato com os scripts. Não renomear sem atualizar `counters.js`.
4. Zero não lidos some. Não exibir "0" para o usuário.
5. Contador de avisos e de mensagens são sistemas separados (ver `AVISOS-E-MENSAGENS.md`): cada um tem seu dado,
   seu contador e seu link, mesmo que usem a mesma aparência.

## 6. Histórico desta padronização

- Antes: três classes diferentes (carrinho, avisos, mensagens) e um componente com outra cor. A regra de aparência
  do badge estava escopada ao gatilho da barra, e o contador de fórum não tinha estilo.
- Depois: uma classe no tema, a mesma aparência em toda parte, e o token `forum-topic-unread` mapeado para ela.
- Verificado: HTML da página pública usa só `aculta-unread-count` e nenhuma das classes antigas; gate de design do
  tema passa; gate de fronteira módulo/tema passa.
- Pendente: teste visual no navegador da barra e da conta (não feito daqui).
