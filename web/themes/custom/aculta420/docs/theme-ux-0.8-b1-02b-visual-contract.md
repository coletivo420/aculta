# THEME-UX 0.8 — B1-02b: Contrato visual do Context Header

**Data:** 2026-10-22 · **Estado:** projeto visual e critérios de implementação; nenhum SDC novo nesta etapa.
**Dependências:** [mapa B1-02a](theme-ux-0.8-b1-02a-consumer-code-map.md) · [ownership H1](theme-ux-0.8-b1-02-ownership-h1.md) · [shell](shell.md) · [UI Suite](ui-suite-migration-plan.md).

## Regra permanente
O cabeçalho multidomínio existente, **Institution Bar + Domain Header**, será renderizado em todas as páginas. O Context Header entra **dentro do main, abaixo do shell**. Não há alternativa de shell para Studio, Wiki, LMS ou Revista.

## Primeiro par de casos de referência — confirmado no código, não no Runtime
- **GanjaLab420:** `aculta_lms.student_dashboard`, rota `/ganjalab420`, controller `LearningDashboardController::student`. Há `_title` do Drupal e H2 interno de listagem.
- **Wiki:** `node--wiki-entry--full.html.twig` renderiza `aculta420:wiki-entry`; variante article do SDC tem **H1 já existente**. O design de Context Header exige migrar esse H1 sem perder eyebrow, ações e lead, não criar título duplicado.

## Hierarquia visual conceitual
```text
┌───────────────────────────────────────────────────────────────┐
│ Institution Bar — manter shell global atual                   │
├───────────────────────────────────────────────────────────────┤
│ Domain Header — manter shell multidomínio atual               │
└───────────────────────────────────────────────────────────────┘
┌────────────────── Main content ───────────────────────────────┐
│ Breadcrumb do Drupal (reutilizar; não duplicar markup)        │
│ [Voltar: link nativo opcional]                [Ações]         │
│ EYEBROW  contexto/purpose da página, não novo header global   │
│ H1      título único, emitido pelo SDC contextual             │
│ Texto de apoio opcional                                       │
├───────────────────────────────────────────────────────────────┤
│ Corpo LMS / corpo Wiki (sem segundo H1)                       │
└───────────────────────────────────────────────────────────────┘
```

## Composição e variantes documentais (sem criar SDC prematuro)
| Variante | Composição visual | Caso inicial |
| --- | --- | --- |
| `standard` | breadcrumb + eyebrow + H1 + subtítulo + ações opcionais | GanjaLab420 dashboard |
| `editorial` | eyebrow editorial + H1 + lead + ações da entidade | Wiki verbete |
| `compact` | retorno + H1 + informação curta; ações abaixo em mobile | sala/aula futura |

Todas partilham **o mesmo contrato SDC**; variantes não alteram ACL, render arrays nem regras de título. Não pressupor que todas serão implementadas: preferir uma única variante viável e introduzir outras após consumidores efetivos.

## Tokens, UI Suite e CSS
- Core SDC para HTML/slots/schema; UI Patterns para consumidores de display onde suportado; UI Patterns Library para stories exemplares de LMS e Wiki; UI Styles para superfícies, texto, bordas e elevação semânticas existentes.
- Não criar paleta local de cores nem copiar CSS do LMS ou Wiki para o tema. Light/dark mantêm posição, medidas e hierarquia, alterando apenas tokens.
- Mobile first: largura 320/390 coloca ações após H1, reduz densidade sem suprimir breadcrumbs necessários; 768/1280 organiza ações ao lado do título quando houver espaço. Preservar ordem de leitura no DOM.
- Nenhum sticky/fixed adicional competirá com Institution Bar ou Domain Header; links de retorno reais continuam funcionais sem JavaScript.
- Contraste e foco WCAG 2.2 AA, zoom/reflow, nomes acessíveis e reduced motion.

## Ownership de dados e H1
- Provider Drupal prepara título, breadcrumb, ações, URLs e render arrays com cache/access.
- `context-header` pode emitir o **único H1**. O page title nativo redundante é removido **somente no consumer migrado**, sem CSS para escondê-lo.
- Na Wiki, o H1 já existente em `aculta420:wiki-entry` deve ser removido/movido de forma coordenada, preservando os outros elementos e sem mudar a variante teaser H2.
- O componente não carrega DomainPurposeManager, Group, User ou LMS.

## Critério para iniciar implementação
1. Obter DOM real da rota LMS e do verbete Wiki, localizar owner do H1, breadcrumb, shell e cache.
2. Confirmar necessidade de um SDC transversal ao invés de combinação de SDCs existentes.
3. Criar schema tipado e story(s) sem dados reais; conferir biblioteca UI Patterns.
4. Migrar **um consumidor por commit** e validar H1 e shell; seguir para o segundo somente após ausência de regressão.
5. Fechar com testes em 320/390/768/1280, claro/escuro, teclado, no-JS, acesso/cache e documentação do tema.

## Backlog da fase posterior
- **B1-02c:** SDC mínimo conforme, stories e tokens; integração seletiva GanjaLab420.
- **B1-02d:** migração Wiki para H1 contextual sem quebrar artigo/teaser.
- **B1-02e:** regressão shell, catálogos, responsividade e acessibilidade.
- **B2:** tabs contextuais SDC/UI Suite com links reais, AJAX progressivo controlado pelo módulo.

**Status de verificação:** somente code review estático e documentação. Nenhuma screenshot, prova visual Runtime ou teste funcional foi executado.

## Atualização B1-02c — SDC criado, sem integração (2026-10-22)

Foi criado o componente experimental `aculta420:context-header` em `components/patterns/context-header/`, com `.component.yml`, Twig, CSS com tokens semânticos e duas stories (educacional e editorial). O SDC emite H1 a partir de `title` simples fornecido pelo consumidor; contém slots renderizáveis para `breadcrumbs`, `back_link` e `actions`. **Ainda não foi aplicado a rotas LMS/Wiki**, portanto o H1 nativo e o H1 de `wiki-entry` continuam intactos. Nunca renderizar o novo SDC em páginas sem remover previamente a saída H1 concorrente pelo provider. Verificar schema, Library, CSS e compatibilidade UI Patterns/Styles no Runtime antes de aprovar reuso. O shell multidomínio não foi modificado.
