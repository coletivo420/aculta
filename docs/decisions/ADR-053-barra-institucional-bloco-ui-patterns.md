# ADR-053 — Barra institucional como bloco UI Patterns/SDC

- **Status:** aceita pelo responsável (2026-11-09).
- **Data:** 2026-11-09.
- **Substitui parcialmente:** ADR-046 (trecho "Shell preservado" e item do shell sobre a barra institucional) e ADR-048 (trechos "nenhum bloco é remapeado" e "a barra institucional, o menu de conta e as utilidades não mudam").
- **Relaciona-se com:** ADR-047 (menu de conta dentro da barra, continua válido), ADR-050 (htmx no popup de login).

## 1. Contexto

A Institution Bar era incluída fixa em `templates/layout/page.html.twig`, com dados dinâmicos calculados pela página (conta, carrinho, mensagens, alertas, busca e apresentação do domínio). Isso impedia configurar a barra no layout de blocos e deixava a barra fora da Biblioteca de Componentes como uso de layout.

## 2. Decisão

1. **A barra institucional é o bloco `aculta_institution_bar`** (plugin em `aculta_portal`), colocado na região `institution` do tema `aculta420_v2`. A região fica no topo da página, antes do cabeçalho, e aparece em todos os domínios.
2. **O bloco renderiza o SDC `aculta420_v2:multidomain-bar`.** A barra segue sendo um componente SDC com schema estrito.
3. **Os dados dinâmicos continuam vindo da página.** O bloco executa os hooks de pré-processamento que preenchem essas variáveis (portal, mensagens e apresentação do domínio) e entrega ao SDC só o contrato neutro de propriedades.
4. **O `page.html.twig` não inclui mais a barra.** A região `institution` é impressa como qualquer outra região.
5. **As partes que continuam válidas** são: a barra fica no shell e em todos os purposes; o menu de conta é item da barra (ADR-047); o popup de login usa htmx (ADR-050).

## 3. Consequências

- A barra pode ser reposicionada, desativada ou substituída pela interface de blocos, sem editar o tema.
- A colocação do bloco está no banco de cada ambiente. Para versionar, é preciso exportar a configuração do bloco.
- O bloco usa `max-age: 0`, porque os dados são por usuário. No domínio de conta, a barra chega por BigPipe.
- Qualquer nova região do shell deve seguir a mesma regra: o layout é configurado por blocos, e o tema só declara as regiões.

## 4. Itens não alterados

Os demais itens de ADR-046 e de ADR-048 continuam vigentes, inclusive a estrutura `layout-page`, as variantes de largura e as regiões do Drupal.
