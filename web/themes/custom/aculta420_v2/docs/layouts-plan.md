# Plano: sistema de layouts de página do tema V2

- **Status:** decisões principais aprovadas em 2026-11-04 (opção A, variante escolhida pelo tema, mapeamento da seção 5.5). Breakpoint e área da conta em proposta. Nenhum código foi alterado por este plano.
- **Data:** 2026-11-04 (atualizado no mesmo dia).
- **Relaciona-se com:** ADR-046 (shell, fundações e Fase 4), ADR-047 (menu de conta), [architecture.md](architecture.md), [components.md](components.md), [roadmap.md](roadmap.md).
- **Fica para:** ADR-048, a ser redigido com as decisões aprovadas (seção 9).

## 1. Objetivo

Definir como as páginas montam o layout (cabeçalho, destaque, ajuda, breadcrumb, conteúdo, barra lateral e rodapé), de modo que:

- o tema tenha um conjunto pequeno e fechado de layouts;
- cada layout seja um componente SDC com contrato de props e slots;
- a escolha do layout seja feita pelo tema, a partir do tipo de conteúdo, sem regra de domínio;
- o mobile-first, a acessibilidade e a cacheabilidade sejam garantidos pela estrutura, não por CSS específico de página.

## 2. Diagnóstico do código atual (`1.2.0-dev.17`)

- `templates/layout/page.html.twig` tem uma única estrutura: barra institucional (`multidomain-bar`), `page__header`, `page__highlighted`, `page__help`, `page__breadcrumb`, `main` com `page__content` e uma `aside` opcional (`sidebar_first`), e `page__footer`.
- Não existe componente de layout. As classes `page__main--with-sidebar` e `page__sidebar` são CSS de página, não contrato de componente.
- Os templates de conteúdo novos (revista, wiki, fórum) não escolhem layout: usam a mesma estrutura da página.
- O Portal envia `domain_presentation.layout.institution_bar_account` e `domain_presentation.layout.show_utility_bar`.
- **Layout Builder e Layout Discovery estão instalados** (`config/sync/core.extension.yml`). Nenhuma exibição de entidade usa Layout Builder: as exibições de mensagem que o mencionam trazem `layout_builder: enabled: false`, e não há exibição de nó com seção ativa.
- **UI Styles está instalado** no site: `ui_styles` e os submódulos `ui_styles_block`, `ui_styles_layout_builder`, `ui_styles_ui_patterns` e `ui_styles_views` aparecem em `core.extension.yml`. O tema V2 ainda não define nenhum estilo de UI Styles.

## 3. O que a pesquisa mostrou

- **SDC, slots e props.** Slots são declarados em `component.yml` com `id` e `title`. Para preencher um slot com bloco Twig, usa-se `embed`; para um slot que só imprime uma variável, usa-se `include()`. O drupal.org recomenda `only` no `embed`, e nesse caso o conteúdo do bloco recebe as variáveis como props. As props são validadas contra o schema quando o componente é renderizado a partir do PHP. Fonte: [Using your new single-directory component](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/using-your-new-single-directory-component).
- **Layout Discovery (core).** Os plugins de layout do core incluem `layout_onecol`, `layout_twocol`, `layout_twocol_section`, `layout_twocol_bricks`, `layout_threecol_section`, `layout_threecol_25_50_25`, `layout_threecol_33_34_33` e `layout_fourcol_section`. **Fonte secundária** ([camoa dev-guides](https://camoa.github.io/dev-guides/drupal/layout-builder/core-layout-plugins)); a lista de regiões não foi confirmada. Antes de usar, confirmar em [api.drupal.org](https://api.drupal.org/api/drupal/core%21modules%21layout_discovery%21layout_discovery.info.yml/main).
- **Layout Discovery e Layout Builder são mecanismos de seção por entidade**, para o editor montar a página com blocos. Não são template de tema. O ADR-046 proíbe page builders pesados como base de apresentação.
- **Layout Builder Styles** (contrib) estilizaria seções do Layout Builder. Não é necessário para páginas de layout fixo.

Conclusão: o Drupal oferece dois caminhos. A seção por entidade (Layout Builder) dá controle ao editor, com custo de configuração, cache e manutenção. O template de tema com SDC dá controle ao desenvolvedor, com contrato explícito. Para o ACULTA, o segundo casa com o ADR-046.

### 3.1 Breakpoints: o que a pesquisa encontrou

- **Wikipedia (Vector 2022).** Segundo a discussão de design publicada no Phabricator, a skin "pode ser redimensionada com conforto até 500 px", abaixo disso o cabeçalho não se adapta e surge rolagem horizontal; a equipe quer suportar 320 px. A mesma fonte indica que o Vector nasceu desktop-first, e que o Minerva, skin móvel, foi feito mobile-first. Fonte: [Should Vector be responsive?](https://phabricator.wikimedia.org/phame/post/view/286/should_vector_be_responsive/). Nota: é uma discussão de design, não a especificação.
- **GOV.UK Design System.** A página de layout que consegui ler não traz valores em pixels. Ela recomenda começar por uma coluna, para telas pequenas, e expandir. Não usei números dessa fonte.
- **Sites de notícias.** As buscas não retornaram valores verificáveis. Não usei esse dado.

Como não há uma referência externa confirmada para o valor exato, o breakpoint proposto (seção 5.3) é calculado a partir das colunas do próprio layout.

## 4. Opções

| Opção | Como funciona | Prós | Contras |
| --- | --- | --- | --- |
| **A. Template de tema com SDC (aprovada)** | `page.html.twig` escolhe uma variante de `layout-page` (`stack`, `aside`, `full`), pelo tipo de conteúdo. As regiões continuam as mesmas. | Contrato explícito; mobile-first no CSS do componente; sem configuração no admin; cache simples; alinhado ao ADR-046 | Editor não monta layout; a variação depende de código |
| B. Layout Builder por entidade | O editor escolhe a seção e arrasta blocos | Flexibilidade para o editor | Configuração por exibição; cache e permissões mais complexos; conflita com a Fase 4 |
| C. Só templates por tipo de conteúdo | Cada `node--*.html.twig` monta a própria estrutura | Simples de começar | Markup duplicado; sem contrato reutilizável |

**Decisão: A.** Layout Builder fica disponível para uma eventual vitrine editável, com decisão própria (fase P5).

## 5. Sistema (opção A)

### 5.1 Um componente, três variantes

Um único `layout-page` com a prop `variant` em enum fechado. O schema estrito impede variante inválida.

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
| `stack` | página simples, texto, FAQ, aviso, formulário, tabela de gestão | uma coluna com largura de leitura; `aside` ignorada se vazia |
| `aside` | wiki, aula do LMS, tópico do fórum, conta, painéis de gestão | conteúdo e barra lateral lado a lado a partir de `60rem`; abaixo disso, a barra lateral vem depois do conteúdo |
| `full` | mapa de eventos, home, revista (edição), catálogo da loja | sem limite de largura; faixas de destaque e grades ocupam a largura toda |

### 5.2 Regras de contrato

- O componente recebe **só** regiões renderizadas (slots) e a variante. Não recebe entidade, usuário, nó, purpose ou URL de domínio.
- A variante é definida **pelo tema**, a partir do tipo de conteúdo e da rota de área do tema, em `preprocess_page`. O tema não lê chave do Portal para isso, e não escolhe layout por hostname.
- Ordem de precedência: (1) mapeamento por tipo de conteúdo (seção 5.5); (2) `stack`, quando não há mapeamento.
- Variante inválida ou ausente cai em `stack`. O PHP normaliza o valor antes de renderizar.
- A barra institucional e o cabeçalho de domínio ficam fora do componente: são contratos do shell (ADR-046).

### 5.3 Mobile-first e breakpoints

- Base: uma coluna, a partir de 320 CSS px.
- **`40rem` (640 px):** grades de cartões passam a duas colunas (ex.: cards de experiências).
- **`60rem` (960 px):** a variante `aside` passa a duas colunas. Proposta com base no cálculo das colunas: leitura de 36 rem, barra lateral de 16 rem, espaço entre elas de 1,5 rem e margens de 2 rem somam cerca de 56 rem. `60rem` dá folga para a barra fixa e para o zoom.
- `full` não cria breakpoint próprio; só remove o limite de leitura.
- Limite de leitura (`--layout-measure`, proposta `68ch`): exceção justificada à regra de `max-width` do ADR-046.
- Compensação da barra fixa: `scroll-padding-top` no `html` com `--aculta-institution-bar-height`, na base.
- Espaçamento e gutter por tokens (`--layout-gutter`).
- Nenhum layout usa JavaScript.
- **Validação dos breakpoints:** testar em 959, 960 e 961 px, e em zoom de 200% (320 CSS px efetivos) para confirmar que a barra lateral não aparece nem corta conteúdo.

### 5.4 Acessibilidade

- Landmarks: um `banner` (cabeçalho), um `main`, uma `complementary` (só quando a barra lateral tem conteúdo) e um `contentinfo` (rodapé).
- A ordem do DOM coloca o conteúdo antes da barra lateral, para leitura linear e para o mobile.
- O link "Pular para o conteúdo" aponta para `main`.
- Sem rolagem horizontal da página em 200% e 400% de zoom; tabelas rolam dentro do próprio container.
- Páginas de conta e dados privados: `noindex` por rota, conforme a regra do Portal.

### 5.5 Mapeamento por tipo de conteúdo e área (aprovado)

| Tipo de conteúdo / área | Variante | Observação |
| --- | --- | --- |
| página básica, FAQ, aviso | `stack` | padrão |
| wiki (artigo) | `aside` | índice na barra lateral |
| tópico do fórum | `aside` | categorias na barra lateral |
| revista (edição) | `full` | faixas de destaque |
| evento (lista e mapa) | `full` | mapa ocupa a largura toda |
| loja (catálogo) | `full` | grade de cartões |
| loja (produto) | `stack` no mobile, `aside` a partir de `60rem` | imagem e ações em duas colunas |

### 5.6 Área do Studio e da conta (proposta a confirmar)

Mapeamento das telas apresentadas no material visual de referência:

| Tela | Variante | Como se comporta |
| --- | --- | --- |
| Studio dashboard, desktop | `aside` | navegação vertical do estúdio à esquerda; cards das experiências em grade (1, 2 ou 4 colunas) |
| Studio dashboard, mobile | `stack` | lista de cards; a navegação vira menu horizontal |
| Minha conta, dados cadastrais | `aside` | sub-navegação vertical (dados, endereço, documentos, e-mail e segurança, senha, contas conectadas, pedidos, apoio, preferências) à esquerda; formulário à direita |
| Minha conta, mobile | `stack` | sub-navegação em lista horizontal com rolagem, item atual com `aria-current`; formulário abaixo |
| Meu Perfil (edição pública) | `stack` | avatar e formulário; as duas colunas do formulário são do componente de formulário, não do layout |
| Meu trabalho (Marley) | `stack` | abas da suíte, abas de situação e tabela; no mobile, a tabela vira cartões (`data-table`) |
| Ganjalab (estudante e educador) | `aside` | painel do educador à direita; no mobile, empilha |
| WE Sativa (contribuições) | `aside` | editor da contribuição na lateral; no mobile, empilha |
| Culturas (eventos) | `aside` | painel "Selecionar local" na lateral; no mobile, vira diálogo (decisão do componente) |
| Green Stash (produtos) | `stack` | abas da visão geral e tabela de produtos |

Observações sobre o material:

- Há dois padrões de navegação: **vertical à esquerda** (conta e dashboard) e **abas horizontais** (suítes). A proposta usa a vertical só onde há muitas seções pessoais (conta e dashboard) e as abas nas suítes. Precisa de confirmação (seção 9).
- O material chama a área de "ACULTA Drupal Studio", mas o ADR-038 e as suítes usam "ACULTA STUDIO". Nome a confirmar antes de qualquer rótulo visível.
- As telas de conta mostram dados privados. A página deve ser `noindex` e não entrar em cache público (ver 5.4).

## 6. Integração com o que existe

- `page.html.twig` passa a renderizar `layout-page` no lugar do `<main>` atual. As regiões do Drupal (`page.header`, `page.highlighted`, `page.help`, `page.breadcrumb`, `page.content`, `page.sidebar_first`, `page.footer`) continuam as mesmas: nenhum bloco é remapeado.
- `page__main--with-sidebar` e `page__sidebar` deixam de existir; a regra vira a variante `aside`.
- A barra institucional, o menu de conta e as utilidades não mudam.
- Os templates `node--wiki-entry--*`, `node--revista--*` e `node--aculta-forum-topic` continuam com seus SDCs. Recebem apenas a variante definida no `preprocess_page`.
- Layout Builder continua instalado e **desabilitado** nas exibições. Este plano não o ativa.

## 7. Fases

| Fase | Entrega | Critério de aceite |
| --- | --- | --- |
| P0 | Decisões da seção 9 registradas em ADR-048 | ADR aprovado pelo responsável |
| P1 | `layout-page` SDC com as três variantes, stories e refatoração do `page.html.twig` sem mudar as regiões | gates do tema; capturas em 320, 390, 768, 959, 960, 1440 px, claro e escuro, zoom 200%; landmarks conferidos |
| P2 | Mapeamento tipo → variante no `preprocess_page` (seção 5.5) | wiki, revista, fórum e loja renderizam com a variante definida; nenhuma regra por domínio |
| P3 | Área da conta e do Studio (seção 5.6) | cada tela com captura em 320 e 1440 px; sub-navegação com `aria-current`; `noindex` conferido |
| P4 | Adoção nas áreas restantes da Fase 4 (evento, LMS, FAQ) | cada área com captura e story próprias |
| P5 | Revisão de Layout Builder, só se o responsável pedir uma vitrine editável | decisão registrada no ADR; nenhuma exibição ativada sem ele |

A fase P3 do plano anterior (chave do Portal) foi removida, porque a variante passa a ser escolhida pelo tema.

## 8. Riscos

- **Barra lateral no mobile:** conteúdo lateral grande deixa a página longa. Mitigação: o aside tem título e, quando for índice, pode ser recolhido com `details`.
- **Compensação da barra fixa:** `scroll-padding` errado esconde o foco. Mitigação: teste de teclado em cada variante.
- **Breakpoint de 60rem:** o valor é uma proposta calculada, não uma referência externa. Mitigação: validar com as capturas de P1 antes de fixar no ADR.
- **Gin e toolbar:** a faixa mede a coluna do Gin no `1.2.0-dev.17`. O toolbar Core, com sidebar recolhida, ainda passa de 320 e 390 px (`DEFERRED`). Layouts não são validados em área administrativa até essa pendência fechar.
- **Cache:** a variante depende do tipo de conteúdo; o cache de página já varia por tags do nó. A validação precisa confirmar isso.
- **Telas de conta com dados privados:** risco de cache público e indexação. Mitigação: `noindex` e cache por usuário, verificados em P3.

## 9. Decisões

### 9.1 Aprovadas

1. **Opção A:** componente SDC de layout com variantes.
2. **Quem escolhe a variante:** o tema, pelo tipo de conteúdo. Sem chave do Portal para layout.
3. **Mapeamento da seção 5.5**, incluindo wiki como `aside` e revista como `full`.

### 9.2 Propostas que precisam de confirmação

4. **Breakpoint da variante `aside`:** `60rem` (960 px), com `40rem` para grades de dois cards. A pesquisa não trouxe valor externo verificável; a proposta vem do cálculo de colunas (seção 5.3).
5. **Navegação do Studio:** vertical à esquerda só em conta e dashboard, e abas horizontais nas suítes (seção 5.6). O material de referência mostra os dois padrões no dashboard; qual deles fica?
6. **Área da conta:** `aside` com sub-navegação vertical no desktop e lista horizontal com rolagem no mobile (seção 5.6).
7. **Nome da área:** "ACULTA Studio" (ADR-038) ou "ACULTA Drupal Studio" (material visual)?

## 10. Não-decisões

- Não define o conteúdo da wiki nem a lista final de itens da conta; só a estrutura de layout.
- Não define campos, preço e ações da página de produto; só o layout.
- Não ativa Layout Builder nem Layout Discovery em nenhuma exibição.
- Não altera o contrato do Portal.

## 11. Fontes

- [Using your new single-directory component](https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/using-your-new-single-directory-component) (drupal.org).
- [Should Vector be responsive?](https://phabricator.wikimedia.org/phame/post/view/286/should_vector_be_responsive/) (Wikimedia Phabricator, discussão de design).
- [Core layout plugins](https://camoa.github.io/dev-guides/drupal/layout-builder/core-layout-plugins) (fonte secundária, a confirmar em api.drupal.org).
