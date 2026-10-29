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

## Inventário estático atual — 2026-10-29

Na árvore em `f2708e60f` há **54 arquivos `.component.yml`, 54 Twig, 45 CSS,
3 JS e 97 stories** em `components/`. A contagem inclui componentes experimentais e
candidatos abaixo; não demonstra validação do schema no Runtime, renderização na
UI Patterns Library nem aprovação visual.

### OCS-F0 — reuso antes de criar (2026-10-29)

O inventário OpenCulturas Next confirma equivalentes existentes em
`patterns/card`, `context-header`, `context-tabs`, `content-section`,
`content-grid`, `empty-state` e nos componentes de Perfil. Esta fase não cria
nem promove SDCs. Componentes experimentais (`stat-counter`,
`accessibility-badges`, `sponsor-logos`) só entram em uma tela após validar
schema, story, consumer real e status no catálogo.

A conferência dos nomes usados na matriz de paridade encontrou estes SDCs em
`components/`; a existência do arquivo não confirma consumer da rota nem
renderização autenticada:

| Contrato citado na matriz | Schema encontrado | Uso candidato |
|---|---|---|
| `card` | `patterns/card/card.component.yml` | Hub, listagens (inclui `node--revista--teaser`) e produtos com consumer real já existente; revisar variantes disponíveis. |
| `context-header`, `context-tabs` | `patterns/context-header/context-header.component.yml`, `patterns/context-tabs/context-tabs.component.yml` | Cabeçalho e navegação contextual Studio/Conta; rota/consumer ainda requer validação. |
| `content-section`, `content-grid`, `empty-state` | `patterns/content-section/content-section.component.yml`, `patterns/content-grid/content-grid.component.yml`, `feedback/empty-state/empty-state.component.yml` | Composição e estados vazios de telas/listas; validar owner dos dados e estado real. |
| `wiki-entry` | `patterns/wiki-entry/wiki-entry.component.yml` | Lista/entrada Wiki; consumer e estado de revisão/moderação pendentes de homologação. |
| `profile-editor`, `profile-identity`, `profile-bio`, `profile-social` | `patterns/profile-editor/profile-editor.component.yml`, `content/profile-identity/profile-identity.component.yml`, `content/profile-bio/profile-bio.component.yml`, `content/profile-social/profile-social.component.yml` | Reuso separado para edição privada e perfil público; grants, campos opt-in e cache precisam passar S-R4/S-R5. |

Institution Bar e Domain Header são elementos do shell ACULTA, não substitutos
para SDC de conteúdo Studio. Seus contratos e consumers continuam independentes
desta lista.

O rodapé global também é composição do tema, não SDC; sua grade móvel começa em
uma coluna e amplia progressivamente sem alterar blocos ou ordem DOM. A auditoria
de cobertura por purpose e as evidências ficam em `mobile-first-audit.md`.

A Institution Bar multidomínio também pertence ao shell, não ao catálogo SDC; em
viewports abaixo de 375px a marca é ocultada visualmente como base, preservando
o link acessível, e reaparece progressivamente em `min-width: 23.4375rem`.

A barra de progresso de comentários é CSS compartilhada de Fórum/Wiki/Revista,
com markup e ações emitidos pelo Portal; a grade começa compacta e cresce em
`22.5rem` e `36rem`. O harness e os limites por consumer estão documentados em
`tests/browser/aculta-forum/README.md` e `mobile-first-audit.md`.

A composição pública de Wiki (header, formulários e grids de Views) permanece
Twig/CSS de apresentação, sem SDC novo; inicia em coluna única e aprimora em
`48rem`. O inventário de rotas e o smoke candidato estão no roadmap transversal.
O SDC `patterns/wiki-entry` também inicia o verbete em coluna única com o aside
antes do texto e retorna a duas colunas em `48rem`; o smoke mede esse consumer.

O índice do Fórum usa Twig/Views e classes de apresentação já existentes; linhas
de tópico empilham seus metadados até `36rem`. Não houve SDC novo nem alteração
dos dados/handlers da View. O teste visual sintético está documentado em
`tests/browser/aculta-forum/README.md`.

`studio.css` compõe navegação e painel internos com os elementos neutros do
Studio. O mobile usa coluna única como base e recupera a sidebar lateral em
`48rem`; nenhum SDC paralelo ou novo contrato foi criado.

`content/editorial-card` mantém os slots e o consumer atuais; altura mínima e
padding compactos são a base, com composição ampla em `48.0625rem`. A validação
visual do consumer real `node--editorial-highlight` segue pendente.

A galeria de álbuns usa markup e estado do módulo `aculta_gallery`; o tema faz
o palco de uma coluna e miniaturas recolhidas desde a base, restaurando a grade
lateral em `30rem`. O smoke reutiliza fixtures públicas dos domains principais
e do Fórum; não cria SDC nem duplicação de componente.
Sem JavaScript, as miniaturas continuam visíveis e navegáveis; o botão de
recolhimento só aparece quando o behavior insere o controle.

O leitor DOCX/planilhas mantém seu wrapper compacto e iframes de 50vh como base;
a partir de `36rem` restaura padding de 1rem e altura mínima de 60vh. O harness
`tests/browser/aculta-document-viewer/office-options.py` cobre 575/576px nos
temas ACULTA420 e Olivero.

A barra de progresso de comentários é CSS compartilhada de Fórum/Wiki/Revista,
com markup e ações emitidos pelo Portal; a grade começa compacta e cresce em
`22.5rem` e `36rem`. O harness e os limites por consumer estão documentados em
`tests/browser/aculta-forum/README.md` e `mobile-first-audit.md`.


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
| `layouts/event-page-five-regions` | Layout UI Patterns experimental | UI Patterns Layouts está no código e em Config Sync. Sem consumidor real: `aculta_events` já tem controller e Twig públicos, mas ainda não consome este SDC. |
| `layouts/page-columns` (I-22) | Layout UI Patterns experimental com variações 1, 2 e 3 colunas e ordem DOM preservada no mobile | Sem consumidor real; permanece candidato. `rail` é horizontal e `content-section` não compõe regiões em colunas. |

`layouts/page-columns` agora empilha todas as variantes como base; duas/três
colunas só entram em `48rem`. `tests/browser/aculta-theme-mobile-first/page-columns.py`
compara a fixture do SDC em 320–1440px; continua sem consumer de produto.

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

## OC315 - verificação de consumidores (2026-10-29)

Novo inventário estático no HEAD da PR #299: **54 arquivos `.component.yml`** no tema; os 52 do histórico acima eram fotografia anterior. Contagem de arquivos não equivale a componentes aprovados, consumidores reais ou testes visuais.

- `layouts/event-page-five-regions`: integrar ao controller/tema de Eventos sem H1 duplicado; não criar Layout Builder paralelo.
- `content/accessibility-badges`: criar integração de campo estruturado em local/evento, não apenas carregar rótulos fictícios da story.
- `content/sponsor-logos`: integrar dados autorizados e mídia pela cadeia de `aculta_image`; evitar URL arbitrária.
- `patterns/bookmark-toggle`: o consumidor do Fórum existe; ampliar sem duplicar Flag e com controle de cache por usuário.
- `editorial-card` (imprensa) e `patterns/card` (teasers): preferir reuso com dados editoriais consistentes; não criar SDC alternativo sem gate.
- Acessibilidade: modo escuro/claro, 320 px, zoom 200%, teclado, contraste WCAG 2.2 AA, no-JS quando aplicável e Olivero. Não marcar "concluído" sem evidência no Runtime.

Fonte: [auditoria OpenCulturas 3.1.5](../../../../../docs/architecture/AUDITORIA-OPENCULTURAS-3.1.5-2026-10-29.md).

## OCS-F1 — reutilização de navegação

O acabamento inicial de fidelidade Studio reutiliza o SDC
`patterns/context-tabs` e os contratos de `studio__tabs`/`studio__subtabs` já
fornecidos pelo consumidor. Nenhum SDC, prop, slot ou story novo foi necessário
nesta camada; a matriz de paridade e o inventário upstream registram os estados
pendentes de captura.

## THEME-MF2 — `patterns/content-grid`

O SDC experimental tem consumidores de duas colunas em Marley, LMS e Wiki; os
consumidores LMS/Wiki não foram exercitados nesta captura. A base responsiva
empilha itens no mobile, passa a duas colunas em 576px e a três em 992px; as
stories declaram esse reflow. Firefox confirmou visualmente Marley 1→2 colunas
e zero overflow em nove larguras. A variante 3 colunas ainda não tem consumer
Runtime real confirmado, somente story.

## THEME-MF2 — `patterns/content-section`

Consumers reais em formulários e workspace da Wiki usam o SDC experimental.
Foi removido um override estreito de gap redundante: o `clamp()` base já limita
o espaço mínimo a 0.75rem. As cinco stories registram a escala fluida; não há
mudança de markup, props ou ordem DOM. A validação Firefox da Wiki após a
alteração segue pendente.
# Retificação de escopo visual (2026-10-29)

O inventário deste catálogo é uma referência histórica de componentes, não uma
lista de aceites responsivos. THEME-MF audita e valida todos os SDCs e todos os
purposes do ACULTA420, incluindo consumers de módulos/Views que não estejam
listados aqui. Studio reutiliza estes contratos durante OCS-F; OCS-A só pode
alterar tokens/acabamento após THEME-MF3 e OCS-F11 aprovados. A auditoria
estática inicial e suas limitações estão em
[`mobile-first-audit.md`](mobile-first-audit.md).

## THEME-MF2 — `content/author-card`

SDC existente reutilizado pelos consumidores de autoria do Fórum, LMS e Wiki.
A base mobile usa avatar 2.5rem, alinhamento inicial e gap 0.5rem; o breakpoint
de 36rem restaura dimensões/alinhamento da variante inline. O cartão empilhado,
editorial e os tamanhos `sm`/`lg` mantêm suas regras. Stories Fórum, LMS e Wiki
documentam os estados. No consumer do Fórum, a data de última atividade agora
pode quebrar em colunas estreitas. Firefox ESR passou 32/32 checks, incluindo
320/360/390/575/576/600/768/1024/1280/1440 sem overflow; a quebra entre
575/576px foi confirmada. Capturas em
`/home/piradopirata/suite-out/aculta-theme-author-card-892f803d5/`.

## THEME-MF2 — navegação do shell `portal-account`

A sanfona já usada em mobile virou base; o breakpoint de 48rem restaura o menu
desktop sticky e o layout de duas colunas. Sem alteração no template do módulo,
DOM ou atributos AJAX. A folha candidata passou 33/33 checks Firefox autenticados
em dez larguras e sem overflow. A repetição integrada sem CSS override passou
33/33 após commit e `drush cr`; a agregação ativa foi verificada. Evidência em
`/home/piradopirata/suite-out/aculta-account-shell-8768825cf/`.

## THEME-MF0 — `donation-quick` no purpose SUPPORT

O purpose público SUPPORT consome o SDC existente `donation-quick`, que compõe
cinco instâncias de `donation-card`; nenhum componente novo foi criado. Firefox
ESR registrou baseline em onze larguras entre 320 e 1440px, com grade dentro do
viewport e sem overflow. A captura também observou ausência do Domain Header,
registrada no Pede-A de composição global; este baseline não homologa a
presença do shell completo. Ver detalhes e limitações em
[`mobile-first-audit.md`](mobile-first-audit.md).

## THEME-MF2 — navegação do shell global

O menu do `page.html.twig` conserva o mesmo markup e progressive enhancement.
Regras mobile de colapso, rolagem interna e alvo de toque agora são a base;
`min-width: 1100px` restaura o menu horizontal. Firefox MAIN manteve os mesmos
valores computados e passou 28/28 checks, inclusive fallback sem JavaScript.
Este padrão do shell não cria SDC nem altera os headers institucionais. A
validação se limita à home MAIN; a auditoria detalha os limites.

## THEME-MF2 — Institution Bar global

As regras compactas do shell passaram a ser a base mobile; `min-width: 768px`
restaura as dimensões e o espaçamento desktop. A regra contempla `.navbar-nav`
e o consumer real de conta, `<ul class="nav flex-row">`, sem alterar Twig ou
contrato do shell. Firefox ESR candidato passou 84/84 checks nas páginas MAIN,
Conta, SUPPORT, Wiki, Loja e Fórum, em 320/374/375/600/767/768/1440px, sem
overflow. O baseline também passou 84/84. Isso não aprova MF3 nem cobre Domain
Header nos purposes que hoje não o renderizam; ver `mobile-first-audit.md`.

Após publicar e reconstruir cache, o consumer integrado passou 140/140 checks
em todos os dez purposes, agora incluindo Revista, Cursos, Links e Eventos. Os
hosts vêm do manifesto de transição; LINKS não exige menu utilitário porque o
Portal o omite deliberadamente. Domain Header apareceu somente em MAIN e Wiki;
seguem pendentes Pede-A e validação MF3.

### THEME-MF2 — Revista e Estatísticas

As figuras editoriais da Revista e os filtros do painel de Estatísticas usam
composição compacta como base e ampliam progressivamente com `min-width`.
São estilos de consumers existentes; não criam SDC nem alteram props, slots,
Form API ou contratos dos módulos. Evidências e limites estão em
[`mobile-first-audit.md`](mobile-first-audit.md).

O SDC `patterns/card` mantém as variantes atuais e aceita também
`data-card-variation="compact"` emitido pelo contrato neutro do Portal; nenhuma
prop ou slot foi alterado.


### THEME-MF2 — Institution Bar e Navigation top-bar

Reutilizados os elementos de shell existentes; nenhum SDC novo. A biblioteca
global do tema corrige o deslocamento Drupal quando a top-bar está no fluxo
móvel, mantendo o cálculo nativo quando a top-bar fica fixa no desktop. O
fallback sem JavaScript usa o token de altura existente. Comportamento, testes
e limites estão em [`mobile-first-audit.md`](mobile-first-audit.md).
