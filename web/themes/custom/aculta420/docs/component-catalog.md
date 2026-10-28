# Catálogo visual de componentes ACULTA420

ACULTA420 mantém previews de seus SDCs na biblioteca do UI Patterns, usando
stories declarativas junto dos componentes. A interface usa o próprio Drupal e
não requer uma aplicação Storybook separada.

## Acesso

- Índice: `/admin/appearance/ui/components`
- Componentes do tema: `/admin/appearance/ui/components/aculta420`
- Cada componente apresenta seus stories no detalhe.
- A rota requer a permissão `access components page`. Ela é reservada a
  administradores; não conceder a papéis editoriais nem a usuários anônimos.

## Manutenção dos previews

- Cada SDC deve ter ao menos um arquivo `machine-name.exemplo.story.yml` no
  diretório do componente. Use variantes e estados vazios adicionais quando
  mudarem a apresentação ou ajudarem a revisão.
- Previews usam texto demonstrativo, links `#...` e placeholders CSS. Não usam
  conteúdo real de usuários, pedidos, cursos ou entidades com acesso restrito.
- Slots de mídia recebem placeholders de apresentação, nunca um `src` para
  contornar o catálogo/pipeline `aculta_image`.
- Componentes de formulário são demonstrados estaticamente: não devem submeter,
  enviar mensagem, alterar carrinho ou iniciar contribuição.
- Componentes com integração Core/contrib mantêm os nomes dos seus contratos;
  stories não alteram o presenter nem simulam autorização ou cache.
- Ao criar um SDC, atualizar o índice de componentes e seu story no mesmo
  commit.

## Inventário estático atual — 2026-10-28

Na árvore deste commit há **52 arquivos `.component.yml`, 52 Twig, 43 CSS e 91
stories** em `components/`. A contagem inclui componentes experimentais e
candidatos abaixo; não demonstra validação do schema no Runtime, renderização na
UI Patterns Library nem aprovação visual.

## UI Styles

O catálogo está em `aculta420.ui_styles.yml`; `css/components/ui-styles.css`
implementa suas classes e usa tokens semânticos. As categorias atuais são
superfície, texto, borda e elevação. A elevação elevada tem um único nível
proposital, com valor adaptado pelo token ao modo claro/escuro.

Só adicionar um estilo quando existir uma necessidade editorial recorrente,
um nome compreensível para quem monta páginas e um token ACULTA420 que cubra a
função em todos os modos. Não expor valores livres, primitivas de paleta,
classes Bootstrap genéricas, espaçamentos ad hoc ou combinações que ocultem
contraste inadequado. Não disponibilizar texto inverso sem superfície compatível.

## Variações de cartão (I-10, 2026-10-28)

`patterns/card` é o componente SDC reutilizável para cartões de conteúdo. Suas variantes são
`default` (vertical), `compact` (lista), `horizontal` e `overlay`. A variante `compact` tem story própria
e é usada pela View de álbuns. `teaser`, `destaque` e `busca` permanecem itens de planejamento até que
contratos e consumidores reais autorizem sua implementação; não são componentes disponíveis.

## Candidatos OpenCulturas avaliados — 2026-10-28

| Padrão | Situação | Consumidor / pendência |
| --- | --- | --- |
| `feedback/stat-counter` | Candidato experimental, com schema e story | Nenhum consumidor real. O provider deve omitir valores abaixo de cinco; o tema não aplica essa política. |
| `content/accessibility-badges` | Candidato experimental, com rótulos textuais | Nenhum consumidor real de local/evento; `patterns/figure` atende mídia com legenda, não a lista de selos. |
| Menção na imprensa | Story de uso de `content/editorial-card`; nenhum SDC novo | A View/consumer de menção real ainda não existe. |
| `content/sponsor-logos` | Candidato experimental, sem marcas reais | Nenhum consumidor; marca só entra após autorização registrada e mídia pelo pipeline `aculta_image`. |
| `patterns/bookmark-toggle` | Candidato experimental, com estados em stories | Consumido pelo tópico completo do Fórum através da ação nativa `flag_salvar_topico`; validação visual no Runtime pendente. A Flag mantém comportamento e acesso. |
| `layouts/event-page-five-regions` | Layout UI Patterns experimental | UI Patterns Layouts está no código e em Config Sync. Sem consumidor real: o módulo `aculta_events` ainda não tem implementação funcional. |
| `layouts/page-columns` (I-22) | Layout UI Patterns experimental com variações 1, 2 e 3 colunas e ordem DOM preservada no mobile | Sem consumidor real; permanece candidato. `rail` é horizontal e `content-section` não compõe regiões em colunas. |

As quatro cores fechadas de seção têm tokens, pares de contraste e stories no
`content-section`. O bloco básico expõe `field_section_color` e preserva
`field_section_variant` como dimensão visual separada; a hook `aculta_section`
passa a cor escolhida ao SDC com fallback `default`.

## Gates do tema — 2026-10-28

O tema tem `npm run lint:css` e `npm run lint:js`. Ambos passaram na microfase
B5. O inventário estático acima não substitui revisão WCAG 2.2 AA, testes de
consumidores, modos claro/escuro e renderização no Firefox ESR.

## Revisão visual B7 — 2026-10-28

- `patterns/card` compact: View pública de álbuns fotografada em 1280 px, 320 px
  e modo escuro; em 320 px não houve overflow e os dois cartões permaneceram
  legíveis.
- A captura inicial encontrou dois anchors sem nome em `/%5Bpath%5D`. A
  configuração da View em Config Sync foi corrigida para preservar somente o
  link de entidade do título e aplicada por import parcial apenas de
  `views.view.aculta_albuns`, após snapshot do Runtime. Firefox ESR confirmou
  zero links sem nome/placeholders em desktop, 320 px e dark; ambos os cartões
  compactos permaneceram legíveis e sem rolagem horizontal. No Runtime 11.4.10
  (`aculta_runtime`, `http://default`), `updatedb:status` não tinha pendências;
  `drush cr` concluiu com sucesso; a View não aparece entre as 160 diferenças
  restantes; os hosts de teste principal e loja responderam HTTP 200.
- `bookmark-toggle`: o tópico de homologação mostrou “Salvar tópico” e “Salvo”;
  foco visível com outline de 3 px, alvo de 44 px e sem overflow em 320 px. A
  Flag da conta de teste foi restaurada ao estado inicial.
- Após corrigir descrições YAML inválidas nas stories de cinco regiões e grade
  mista, as rotas UI Patterns abriram no Runtime. As quatro faixas foram
  capturadas em desktop, 320 px e dark; pares de texto/fundo têm contraste
  calculado de 4,69:1, 5,26:1, 7,84:1 e 12,40:1. Stories de cinco regiões,
  contador, selos e marcas também foram capturadas; seguem candidatas sem
  consumidor real. Drawers offcanvas ocultos elevam `scrollWidth` em algumas
  stories, mas o tema os recorta fora da viewport e o teste mediu `maxScrollX=0`;
  não há rolagem horizontal real em 320 px.

## B8 — composições de página em colunas

`patterns/rail` oferece rolagem horizontal, `content-section` é um frame de
conteúdo único e `ui_patterns_layouts` adapta SDCs para Layout Builder sem
fornecer por si só um arranjo 1/2/3 colunas. O candidato
`layouts/page-columns` fornece regiões principal, secundária e complementar;
tem stories de 1, 2 e 3 colunas e empilha as regiões pela ordem do DOM abaixo de
768 px. As três variações foram capturadas no Runtime em 320 px e dark. Drawers
ocultos elevam `scrollWidth`, mas não permitem rolagem horizontal (`maxScrollX=0`).
Ainda não há consumidor funcional.

## B9 — grade de itens mistos

Antes da alteração, foram localizados os consumidores `aculta_studio` em
`MarleyController` (ações), `LearnerDashboardComposer` (cursos e certificados)
e `aculta_wiki` em `WikiContributionIntentController` (intenções) e
`WikiContributorWorkspace::listSection` (listagem). `ComponentFallbackHooks`
registra fallback, mas não fornece itens. O contrato existente já aceita
qualquer render array no slot único `items`; props e slots estruturais foram
mantidos. A story nova mistura artigo, termo taxonômico e link externo. A grade
de 3 colunas empilha no breakpoint mobile. A story mista foi capturada no
Runtime em desktop, 320 px e dark; a revisão corrigiu uma regra CSS que impedia
o empilhamento em 320 px.

## Backlog do catálogo — padrões transversais ainda não implementados

`context-header`, `context-tabs` e `empty-state` já existem na árvore e não são
itens pendentes de criação. Permanecem no backlog `content-reader`,
`resource-list`, `status-timeline` e `next-action`; antes de criar qualquer um,
verificar os SDCs existentes e exigir um consumidor real. Preservar o shell
global e priorizar variantes dos SDC atuais.

## Cobertura de stories — fotografia de 2026-10-22

O inventário do GitHub encontrou 38 SDCs e 55 stories antes da adequação M1. Foram adicionadas stories demonstrativas para `forum-category`, `qr-preview` e `qr-download`, totalizando **58 arquivos de stories** e nenhum SDC sem story. A verificação de renderização no UI Patterns Library permanece PENDENTE e a contagem histórica de catálogo deve ser distinguida desta fotografia da branch. Ver [inventário](sdc-ui-suite-inventory-2026-10-22.md).

## Novo candidato do catálogo — Context Header

O SDC experimental `aculta420:context-header` possui duas stories no repositório: `context-header.default.story.yml` e `context-header.editorial.story.yml`. São **fixtures estáticas**, não evidência de renderização no UI Patterns Library; verificar schema, HTML e tokens no Runtime antes de tornar o componente disponível a consumidores. Com a criação desse componente, a fotografia da árvore passa de 38 para **39** SDCs e de 58 para **60** arquivos stories, sujeito à verificação do HEAD.

## Context Tabs — novo componente experimental

`aculta420:context-tabs` acrescenta um SDC e duas stories estáticas para navegação contextual de GanjaLab e Wiki. **Inventário nominal esperado:** 40 SDCs, 62 arquivos story, sujeito à confirmação da árvore do HEAD. Renderização UI Patterns Library, UI Styles e consumidores reais permanecem pendentes. [Plano B2](theme-ux-0.8-b2-context-tabs.md).

## Quatro SDCs de Meu Perfil — pré-S-R4 (0.7.0-dev.1)

O tema ganhou `profile-editor`, `profile-identity`, `profile-bio`
e `profile-social` (todos experimentais), com **8 stories estáticas**:
`profile-editor.full/empty`, `profile-identity.avatar/fallback`,
`profile-bio.filled/empty`, `profile-social.filled/empty`, todas
no padrão `<nome>.<variante>.story.yml` do catálogo. Há teste Unit
estático de schema/slots/assets/fixtures e ausência de lógica
de dados no Twig, escrito mas não executado.

As contagens históricas anteriores permanecem vinculadas à data de
inventário e **não** são totais verificados da branch atual. Os
SDCs novos ainda não possuem consumidor, nem validação no UI Patterns
Library ou Firefox. O catálogo jamais deve usar dados privados reais.

## Integração dos SDCs privados (S-R4, Studio dev.21)

Os quatro SDCs experimentais `profile-editor`, `profile-identity`,
`profile-bio` e `profile-social` passaram a ser consumidos em
`/studio/perfil` pelo render element Core `#type: component`.
O Studio entrega os slots autorizados dos serviços ACULTA User e
`aculta_image`. O componente não faz consulta a entidades nem decide
política de dados. O perfil público mantém sua filtragem original;
a foto própria exibida no editor é privada à sessão titular.

A mudança foi no consumidor Studio; as APIs do tema (Twig, CSS,
props e slots) não foram alteradas e a versão fica `0.7.0-dev.1`.
Sinais de código não equivalem a render validado: Unit, Kernel,
UI Patterns Library e Firefox ESR/X11 seguem pendentes.
