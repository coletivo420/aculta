# ADR-040 — Rolagem horizontal visível em tabelas

- **Status:** Aceita; implementação em `dev` em 2026-11-01.
- **Data:** 2026-11-01.
- **Decisores:** responsável pelo produto.
- **Contexto:** tabelas Drupal e listagens do ACULTA Studio podem ter mais colunas do que o viewport móvel comporta. Em alguns consumidores o conteúdo era comprimido (`table-layout: fixed`, quebra forçada) ou a rolagem interna não tinha barra visível, dando a impressão de tabela cortada ou quebrada. O responsável determinou que, em todo o site, o comportamento esperado é uma barra de rolagem horizontal.
- **Decisão:** toda tabela que exceder o contêiner usa rolagem horizontal **visível**, confinada ao wrapper da tabela. A página não ganha rolagem horizontal. Aplicam-se as classes e marcadores já existentes: `.table-responsive`, `.webform-multiple-table-responsive`, `.studio__listing-wrap`, `[data-aculta-studio="listing-wrap"]` e `[data-portal-ui="studio-listing-wrap"]`.
- **Apresentação:** a barra usa `scrollbar-width: auto` e `scrollbar-color` no Firefox e `::-webkit-scrollbar` com altura de `0.75rem` no demais motores, sempre com os tokens semânticos do tema (`--aculta-border-strong`, `--aculta-surface-muted`, `--aculta-text-secondary`). Não se usam cores literais nem `!important`.
- **Acessibilidade:** o wrapper rolável recebe `tabindex="0"` e `aria-label="Tabela rolável"` somente quando `scrollWidth` excede `clientWidth`; o foco visível usa `--aculta-focus-ring`. A rolagem bidimensional fica confinada à tabela, conforme o critério 1.4.10 (Reflow) da WCAG 2.2.
- **Sem compressão artificial:** `table-layout: fixed` e a quebra forçada que escondiam o overflow em listagens do Studio foram removidos; células mantêm a largura natural e o wrapper rola.
- **Ownership:** a decisão é de apresentação do tema `aculta420`. Não altera markup de tabelas, Views, Form API, dados, ACL, rotas ou cache dos módulos donos.
- **Implementação:** `css/drupal-bootstrap.css` (regras globais da barra e foco), `css/components/studio.css` (remove a compressão de listagens) e `js/studio-table-a11y.js` (marca wrappers roláveis em todo o site, não só no Studio).

## Consequências

- Tabelas largas permanecem legíveis em 320px sem alargar a página; a rolagem é local e explícita.
- O mesmo comportamento vale para Revista, Wiki, Fórum, Loja, Events, LMS e Studio, sem CSS por purpose.
- Tabelas que cabem no contêiner não exibem barra nem recebem tab stop extra.
- Testes de regressão devem verificar ausência de overflow documental, presença de `scrollWidth > clientWidth` e foco visível no wrapper.
