# Plano: sistema de layouts de página do tema V2

- **Status:** proposta para decisão. Nenhum código foi alterado por este plano.
- **Data:** 2026-11-04.
- **Relaciona-se com:** ADR-046 (shell, fundações e Fase 4), ADR-047 (menu de conta), [architecture.md](architecture.md), [components.md](components.md), [roadmap.md](roadmap.md).
- **Fica para:** ADR-048, depois da aprovação das decisões da seção 9.

## 1. Objetivo

Definir como as páginas do site escolhem e montam o layout (cabeçalho, destaque, ajuda, navegação estrutural, conteúdo, barra lateral e rodapé), de modo que:

- o tema tenha um conjunto pequeno e fechado de layouts;
- cada layout seja um componente SDC com contrato de props e slots;
- o Portal entregue só uma chave neutra, sem regra de domínio;
- o mobile-first, a acessibilidade e a cacheabilidade sejam garantidos pela estrutura, não por CSS específico de página.

## 2. Diagnóstico do código atual (`1.2.0-dev.17`)

- `templates/layout/page.html.twig` tem uma única estrutura: barra institucional (`multidomain-bar`), `page__header`, `page__highlighted`, `page__help`, `page__breadcrumb`, `main` com `page__content` e uma `aside` opcional (`sidebar_first`), e `page__footer`.
- Não existe componente de layout. As classes `page__main--with-sidebar` e `page__sidebar` são CSS de página, não contrato de componente.
- Os templates de conteúdo novos (revista, wiki, fórum) não escolhem layout: usam a mesma estrutura da página.
- O Portal envia `domain_presentation.layout.institution_bar_account` e `domain_presentation.layout.show_utility_bar`. Não há chave de layout no contrato.
- **Layout Builder e Layout Discovery estão instalados** (`config/sync/core.extension.yml`). Nenhuma exibição de entidade usa Layout Builder: as exibições de mensagem que o mencionam trazem `layout_builder: enabled: false`, e não há exibição de nó com seção ativa. Ou seja, o mecanismo existe, mas não é usado.
- **UI Styles está instalado** no site: `ui_styles` e os submódulos `ui_styles_block`, `ui_styles_layout_builder`, `ui_styles_ui_patterns` e `ui_styles_views` aparecem em `core.extension.yml`. O tema V2 ainda não define nenhum estilo de UI Styles. (Isso corrige a leitura anterior de que UI Styles não estava adotado: está instalado, mas o tema não o usa.)

## 3. O que a pesquisa mostrou

Fontes consultadas em 2026-11-04. Onde a fonte é secundária, está marcado.

- **SDC, slots e props.** Slots são declarados em `component.yml` com `id` e `title`. Para preencher um slot com bloco Twig, usa-se `embed`; para um slot que só imprime uma variável, usa-se `include()`. O drupal.org recomenda `only` no `embed` para não vazar variáveis, e nesse caso o conteúdo do bloco precisa receber as variáveis como props. As props são validadas contra o schema quando o componente é renderizado a partir do PHP. Fonte: [Using your new single-directory component](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/using-your-new-single-directory-component).
- **Layout Discovery (core).** Os plugins de layout do core incluem `layout_onecol`, `layout_twocol`, `layout_twocol_section`, `layout_twocol_bricks`, `layout_threecol_section`, `layout_threecol_25_50_25`, `layout_threecol_33_34_33` e `layout_fourcol_section`. **Fonte secundária** ([camoa dev-guides](https://camoa.github.io/dev-guides/drupal/layout-builder/core-layout-plugins)); a lista de regiões não foi confirmada. Antes de usar qualquer um deles, confirmar em [api.drupal.org](https://api.drupal.org/api/drupal/core%21modules%21layout_discovery%21layout_discovery.info.yml/main).
- **Layout Discovery e Layout Builder são mecanismos de seção por entidade**, não de template de página do tema. Eles servem para o editor montar a página com blocos. O ADR-046 e as regras do projeto proíbem page builders pesados como base de apresentação.
- **Layout Builder Styles** (contrib) adiciona estilos a seções do Layout Builder. Como `ui_styles_layout_builder` já está instalado, qualquer estilo de seção passaria por ele. Não é necessário para o plano de página fixa.

Conclusão da pesquisa: o Drupal oferece dois caminhos. O de seção por entidade (Layout Builder) dá controle ao editor, com custo de configuração, cache e manutenção. O de template de tema com SDC dá controle ao desenvolvedor, com contrato explícito. Para o ACULTA, o segundo é o que casa com o ADR-046.

## 4. Opções

| Opção | Como funciona | Prós | Contras |
| --- | --- | --- | --- |
| **A. Template de tema com SDC (recomendada)** | `page.html.twig` escolhe uma variante de um componente `layout-page` (`stack`, `aside`, `full`). Os blocos e as regiões continuam como estão. | Contrato explícito; mobile-first pelo CSS do componente; zero configuração no admin; cache simples; alinhado ao ADR-046 | Editor não monta layout; a variação depende de código |
| B. Layout Builder por entidade | O editor escolhe a seção (Layout Discovery) e arrasta blocos em cada página | Flexibilidade total para editor | Configuração por exibição; cache e permissões mais complexos; contrário a page builder pesado; conflita com a Fase 4, que já define templates por tipo de conteúdo |
| C. Só templates por tipo de conteúdo | Cada `node--*.html.twig` monta a própria estrutura | Simples de começar | Markup duplicado; sem contrato reutilizável; cada template vira um layout próprio |

**Recomendação: A.** Se, no futuro, o editor precisar montar uma página de vitrine, a opção B pode ser avaliada só para essa exibição, com decisão própria.

## 5. Sistema proposto (opção A)

### 5.1 Um componente, três variantes

Em vez de três componentes, um único `layout-page` com a prop `variant` em enum fechado. O schema estrito impede variante inválida.

```yaml
# components/layout-page/layout-page.component.yml (proposta, não criada)
props:
  variant:
    type: string
    enum: [stack, aside, full]
    default: stack
slots:
  header:      { title: Cabeçalho }
  highlighted: { title: Destaque }
  help:        { title: Ajuda }
  breadcrumb:  { title: Breadcrumb }
  content:     { title: Conteúdo principal }
  aside:       { title: Barra lateral }
  footer:      { title: Rodapé }
```

| Variante | Quando | Comportamento |
| --- | --- | --- |
| `stack` | página simples, texto, FAQ, aviso, formulário | uma coluna com largura de leitura; `aside` ignorada se vazia |
| `aside` | wiki, aula do LMS, tópico do fórum, Conta com navegação lateral | conteúdo e barra lateral lado a lado a partir de tablet; no mobile, a barra lateral vem depois do conteúdo |
| `full` | mapa de eventos, home, revista (edição) | sem limite de largura; faixas de destaque ocupam a largura toda |

### 5.2 Regras de contrato

- O componente recebe **só** regiões renderizadas (slots) e a variante. Não recebe entidade, usuário, nó, purpose ou URL de domínio.
- Variante inválida ou ausente cai em `stack`. O PHP do tema é quem normaliza o valor antes de renderizar.
- A variante vem de uma única fonte, na ordem: (1) chave `aculta_page_layout` enviada pelo Portal, quando existir; (2) mapeamento de tipo de conteúdo definido no tema; (3) `stack`. O tema nunca escolhe layout por hostname.
- A barra institucional e o cabeçalho de domínio continuam fora do componente: são contratos do shell (ADR-046).

### 5.3 Mobile-first e CSS

- A base de cada variante é uma coluna, em 320 CSS px. `aside` ganha duas colunas por `min-width` (proposta: a partir de `48rem`). `full` não adiciona colunas; só remove o limite de leitura.
- Limite de leitura (`--layout-measure`, proposta: `68ch`) é um limite justificado, documentado como exceção à regra de `max-width` do ADR-046.
- Compensação da barra fixa: `scroll-padding-top` no `html` usa `--aculta-institution-bar-height`, uma só vez, na base, não em cada variante.
- Espaçamento e gutter por tokens (`--layout-gutter`), nunca valor fixo.
- Nenhuma variante usa JavaScript.

### 5.4 Acessibilidade

- Landmarks: um `banner` (cabeçalho), um `main`, uma `complementary` (aside, só quando tem conteúdo) e um `contentinfo` (rodapé).
- A ordem do DOM é o conteúdo antes da barra lateral, para leitura linear e para o mobile.
- Link "Pular para o conteúdo" continua apontando para `main`.
- Zoom de 200% e 400% sem rolagem horizontal da página; tabelas e blocos largos rolam dentro do próprio container.

### 5.5 Mapeamento inicial (proposta a aprovar)

| Tipo de conteúdo / área | Variante | Observação |
| --- | --- | --- |
| página básica, FAQ, aviso | `stack` | padrão |
| wiki (artigo) | `aside` | barra lateral com índice (a validar com a wiki) |
| tópico do fórum | `aside` | barra com categorias |
| revista (edição) | `full` | faixas de destaque |
| evento (lista e mapa) | `full` | mapa ocupa a largura toda |
| conta / painel | `aside` ou `stack` | a definir com a conta (abas podem bastar) |
| loja (catálogo) | `full` | grade de cartões |
| loja (produto) | `stack` no mobile, `aside` a partir de tablet | duas colunas: imagem e ações |

## 6. Integração com o que existe

- `page.html.twig` passa a renderizar `layout-page` no lugar do `<main>` atual. As regiões do Drupal (`page.header`, `page.highlighted`, `page.help`, `page.breadcrumb`, `page.content`, `page.sidebar_first`, `page.footer`) continuam as mesmas: nenhum bloco precisa ser remapeado.
- `page__main--with-sidebar` e `page__sidebar` deixam de existir; a regra vira a variante `aside`.
- A barra institucional, o menu de conta e as utilidades não mudam.
- Os templates `node--wiki-entry--*`, `node--revista--*` e `node--aculta-forum-topic` continuam com seus SDCs. Só recebem a variante escolhida pelo `preprocess_page`.
- Layout Builder continua instalado e **desabilitado** nas exibições. Este plano não o ativa.

## 7. Fases

| Fase | Entrega | Critério de aceite |
| --- | --- | --- |
| P0 | Decisões da seção 9 registradas em ADR-048 | ADR aprovado pelo responsável |
| P1 | `layout-page` SDC com as três variantes, stories e refatoração do `page.html.twig` sem mudar as regiões | gates do tema; capturas em 320, 390, 768 e 1440 px, claro e escuro, zoom 200%; landmarks conferidos |
| P2 | Mapeamento tipo → variante no `preprocess_page` | wiki, revista e fórum renderizam com a variante definida; nenhuma regra por domínio |
| P3 | Chave `aculta_page_layout` no contrato do Portal (PR própria, se aprovada) | tema lê a chave e cai em `stack` quando ela não existe |
| P4 | Adoção nas áreas da Fase 4 (evento, loja, LMS, FAQ) | cada área com captura e story próprias |
| P5 | Revisão de Layout Builder, só se o responsável pedir uma vitrine editável | decisão registrada no ADR; nenhuma exibição ativada sem ele |

## 8. Riscos

- **Barra lateral no mobile:** se o conteúdo lateral for grande, a página fica longa. Mitigação: o aside tem título e, quando for índice, pode ser recolhido com `details`.
- **Compensação da barra fixa:** `scroll-padding` errado faz o foco ficar escondido. Mitigação: teste de teclado em cada variante.
- **Gin e toolbar:** a faixa já mede a coluna do Gin no `1.2.0-dev.17`. O toolbar Core, com sidebar recolhida, ainda passa de 320 e 390 px (`DEFERRED`). Layouts não devem ser validados em área administrativa até essa pendência fechar.
- **Cache:** a variante depende do tipo de conteúdo. O cache de página já varia por tags do nó; não precisa de contexto novo, mas a validação precisa confirmar isso.
- **Divergência entre variante do tema e variante do Portal:** mitigação: uma única ordem de precedência (seção 5.2) e teste para cada caminho.

## 9. Decisões que preciso do responsável

1. **Opção A** (componente SDC de layout com variantes) ou **B** (Layout Builder por entidade)? Recomendo A.
2. **Quem escolhe a variante:** o Portal envia `aculta_page_layout` (recomendado, para o tema não conhecer regra de domínio) ou o tema decide por tipo de conteúdo? Pode ser os dois, com a ordem da seção 5.2.
3. **Mapeamento da seção 5.5:** aprova? Em especial wiki como `aside` e revista como `full`.
4. **Breakpoint da coluna lateral:** `48rem` (proposta) ou outro?
5. **Conta:** `aside` ou `stack` com abas?

## 10. Não-decisões

- Não define o conteúdo da barra lateral da wiki nem da conta.
- Não define detalhes da página de produto da loja (campos, preço, ações); só o layout.
- Não ativa Layout Builder nem Layout Discovery em nenhuma exibição.
- Não altera o contrato do Portal sem PR própria.
