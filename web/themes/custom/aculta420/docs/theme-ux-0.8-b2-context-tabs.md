# THEME-UX 0.8-B2 — Abas contextuais base SDC/UI Suite

**Data:** 2026-10-22 · **Estado:** SDC experimental criado e stories presentes; sem integração em rotas, sem testes Runtime.

## Evidência da auditoria de código
- `aculta_studio.routing.yml` define URLs reais `/ganjalab`, `/ganjalab/{aba}`, `/ganjalab/{aba}/{secao}` e `/ganjalab/{aba}/{secao}/{sub}`, e aliases legados `/studio...`. AJAX deve ser enhancement, não fonte exclusiva de navegação.
- `aculta420:product-tabs` implementa **painéis Bootstrap por botão e data-bs-toggle** em produto. **Não** é elegível para navegação por rotas.
- `css/components/navigation.css` pertence à navbar do Domain Header. Nenhum novo componente pode substituir ou duplicar esse shell.
- Wiki tem ações de verbete em `wiki-entry.twig` com links; migração exige URLs reais, acesso e cache vindos do provider.

## Primeiro incremento B2-01 — código criado
`components/patterns/context-tabs/` contém `.component.yml` (Core SDC), Twig de `<nav>`, CSS tokenizado responsivo e duas stories UI Patterns Library (GanjaLab e Wiki). O slot `items` recebe **links renderizados pelo provider**; prop `label` define nome acessível. O estado atual vem do atributo `aria-current=page` fornecido pelo backend, sem lógica JS no SDC.

## Contrato obrigatório para integrar
1. Provider prepara somente âncoras `<a href>` válidas, access-checked, com `aria-current=page` quando ativo, cache contexts/tags corretos; não entregar botões `role=tab` destinados a painéis.
2. O slot `items` deve ser uma sequência de links ou render arrays equivalentes. Caso o provider entregue uma lista `ul/li`, adaptar HTML/CSS na fase de integração para não quebrar a semântica ou o layout.
3. No GanjaLab, cada aba tem URL e fallback sem JS; Ajax/history/foco são responsabilidade de `aculta_studio` e jamais do componente.
4. Na Wiki, não substituir as ações do verbete por rotas inventadas; identificar consumidor com links reais antes da integração.
5. Padrão sempre sob Institution Bar + Domain Header. Não usar classes shell/navegação institucional.
6. Usar UI Patterns / UI Styles semanticamente, com stories sem dados reais e sem configurar displays em massa.

## Próximas microfases
- **B2-02:** rastrear controller/render arrays de abas no Studio e a navegação contextual da Wiki/Conta com rotas reais; escolher dois consumidores efetivos.
- **B2-03:** integrar primeiro consumidor em pequena fatia e validar links, ACL, cache, no-JS, AJAX e histórico.
- **B2-04:** segundo consumidor com o mesmo contrato SDC, sem duplicar `product-tabs`.
- **B2-05:** homologar com 320/390/768/1280, dark/light, foco/teclado, WCAG 2.2 AA, shell multidomínio; evidência Runtime e story UI Patterns Library.
- **Depois:** B3 estados/feedback universais em SDC/UI Suite.

**Limitação:** este documento e o SDC não comprovam funcionalidade dos consumidores, renderização no Drupal, validade de esquema ou gates de navegador.

## Execução B2-03 — primeiro consumidor conectado (2026-10-22)

No `aculta_studio/src/Controller/StudioController.php`, o método `linkList()` agora envolve as listas de links do comportamento `tab` (áreas) e `subtab` (abas da seção) com `#type: component`, `#component: aculta420:context-tabs`. O comportamento `section`/sidebar permanece no render anterior por ter outro layout. O render array interno `#theme: item_list`, os links `PortalLinkElement`, atributos `data-aculta-studio` e `aria-current` permanecem. A rotina `studio-navigation.js` intercepta âncoras por atributos, de modo que a composição SDC não exige novo AJAX, novas URLs ou mudança de permissões.

O CSS `context-tabs.css` foi adequado a `ul/li` produzidos por `item_list`, sem remover semântica de lista. O shell multidomínio não sofreu alterações.

**Gates NÃO executados no homelab:** confirmar a estrutura real do slot e do `item_list` renderizado, uma região `nav` para cada grupo, alinhamento visual, acesso por usuário e cache, teclado, comportamento AJAX/pushState/popstate, navegação sem JS, 320/390/768/1280px e dark/light. Também verificar conflitos com CSS legado do Studio. Não marcar PASS com base no código.

**Próxima fase B2-04:** auditar e integrar segundo consumidor real (Wiki ou Conta), evitando alterar ações do verbete e evitando transformar `product-tabs` em tabs de rotas; B2-05 será a homologação.
