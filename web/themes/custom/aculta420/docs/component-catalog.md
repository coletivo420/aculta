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

## Inventário estático atual — 2026-10-30

Na árvore atual há **55 arquivos `.component.yml`, 55 Twig, 45 CSS,
3 JS e 100 stories** em `components/`. A contagem inclui componentes experimentais e
candidatos abaixo; não demonstra validação do schema no Runtime, renderização na
UI Patterns Library nem aprovação visual.

### OCS-F0 — reuso antes de criar (2026-10-29)

O inventário OpenCulturas Next confirma equivalentes existentes em
`patterns/card`, `context-header`, `context-tabs`, `content-section`,
`content-grid`, `empty-state` e nos componentes de Perfil. Esta fase não cria
nem promove SDCs. Componentes experimentais (`stat-counter`,
`accessibility-badges`, `sponsor-logos`) só entram em uma tela após validar
schema, story, consumer real e status no catálogo.

### OCS-F1 — tokens herdados no Studio (2026-10-30)

Os SDCs do Studio herdam tipografia Montserrat e tokens de cor, contraste,
espaçamento, largura e superfície por meio dos wrappers `.studio` e
`.aculta-profile-editor`. Props, slots, render arrays e schemas de componentes
não mudaram; tokens não são definidos no `:root`, evitando efeito nos demais
purposes. A licença da fonte está junto dos WOFF2 em
`assets/fonts/montserrat/OFL.txt`. Em Firefox ESR visível, o consumer Marley
recebeu a folha candidata injetada: 63/63 checks executáveis passaram, a fonte
carregou, o gutter global ficou idêntico antes/depois e a tipografia computada do
Studio foi Montserrat. As capturas privadas ficam em
`/home/piradopirata/aculta-suite-out/ocs-f1-candidate-20261030-after-500-fix/`.
Isto não homologa cada consumer, não substitui o stylesheet publicado e não
aprova OCS-F1 integral/F7.

A conferência dos nomes usados na matriz de paridade encontrou estes SDCs em
`components/`; a existência do arquivo não confirma consumer da rota nem
renderização autenticada:

| Contrato citado na matriz | Schema encontrado | Uso candidato |
|---|---|---|
| `card` | `patterns/card/card.component.yml` | Entrada da Conta e Hub via `PortalUiHooks`; listagens (inclui `node--revista--teaser`) e produtos com consumers próprios. Variante `studio_fidelity` restrita à raiz Studio. |
| `context-header`, `context-tabs` | `patterns/context-header/context-header.component.yml`, `patterns/context-tabs/context-tabs.component.yml` | Cabeçalho e navegação contextual Studio/Conta; rota/consumer ainda requer validação. |
| `dropdown` | `patterns/dropdown/dropdown.component.yml` | Substitui `bootstrap:dropdown` via SDC `replaces`; preserva props/slots e evita passar render array do slot ao nome de bloco Twig. Consumer real: UI Patterns Library. |
| `content-section`, `content-grid`, `empty-state` | `patterns/content-section/content-section.component.yml`, `patterns/content-grid/content-grid.component.yml`, `feedback/empty-state/empty-state.component.yml` | Composição e estados vazios de telas/listas; validar owner dos dados e estado real. |
| `wiki-entry` | `patterns/wiki-entry/wiki-entry.component.yml` | Lista/entrada Wiki; consumer e estado de revisão/moderação pendentes de homologação. |
| `profile-editor`, `profile-identity`, `profile-bio`, `profile-social` | `patterns/profile-editor/profile-editor.component.yml`, `content/profile-identity/profile-identity.component.yml`, `content/profile-bio/profile-bio.component.yml`, `content/profile-social/profile-social.component.yml` | Reuso separado para edição privada e perfil público; grants, campos opt-in e cache precisam passar S-R4/S-R5. |

Institution Bar e Domain Header são elementos do shell ACULTA, não substitutos
para SDC de conteúdo Studio. Seus contratos e consumers continuam independentes
desta lista.

Meus Salvos (`/studio/salvos`) já emite tokens neutros `studio-saved-*` e usa
a listagem/paginação do controller proprietário. Antes de criar ou declarar um
SDC consumidor nessa tela, confirmar a composição renderizada e testar cache
privado A/B; `card`, `context-tabs` e `empty-state` são apenas candidatos de
reuso até essa verificação. Não apresentar módulos opcionais ou ações sem
consumer autorizado como funcionais.

O rodapé global também é composição do tema, não SDC; sua grade móvel começa em
uma coluna e amplia progressivamente sem alterar blocos ou ordem DOM. A auditoria
de cobertura por purpose e as evidências ficam em `mobile-first-audit.md`.

O SDC existente `context-tabs` mantém links e semântica em layouts mobile-first:
gap compacto é a base até 368px e a separação ampla retorna em 369px. A regra
cobre stories com links estáticos e o `<ul>` gerado por `item_list`;
`context-tabs.provider-list.story.yml` registra essa estrutura.
O `item_list` também pode receber classes Bootstrap `list-group` e
`list-group-item`; o SDC neutraliza esses estilos apenas dentro da navegação e
define o eixo horizontal explicitamente, mantendo quebra de linha e alvos de
link com pelo menos 44px. Firefox ESR visível confirmou quatro filtros em
WE SATIVA em 320–1440px, sem overflow, com wrapping natural em telas estreitas.

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
As abas usam margem inferior compacta como base e restauram o espaçamento amplo
em `48rem`, validado no mesmo consumer público.

O índice do Fórum usa Twig/Views e classes de apresentação já existentes; linhas
de tópico empilham seus metadados até `36rem`. Não houve SDC novo nem alteração
dos dados/handlers da View. O teste visual sintético está documentado em
`tests/browser/aculta-forum/README.md`. As ações do cabeçalho da listagem usam
dimensão compacta em `36rem` e restauram o tamanho em `62rem`, com progressão
mobile-first e medição sintética nas fronteiras 575/576 e 991/992px.

A fila de denúncias do Fórum não cria SDC: `ReportQueueController` emite os
tokens neutros `forum-report-queue` e `forum-report-pager`; `PortalUiHooks`
traduz para `aculta-forum-report-queue` e `aculta-pager`. Conteúdo, pager,
permissões e cache continuam no Portal/UGC.

`studio.css` compõe navegação e painel internos com os elementos neutros do
Studio. O mobile usa coluna única como base e recupera a sidebar lateral em
`48rem`; nenhum SDC paralelo ou novo contrato foi criado.

O ponto de entrada autenticado do Studio é composto por `AccountExperienceProvider`
no purpose `account` (`/` e `/conta`), conforme ADR-038; não há Hub público
`/studio`. Os links reais da lista `account-experiences` reutilizam o SDC
`patterns/card`. `PortalUiHooks` mantém o render array do link dentro do slot
`content` e propaga `#access`, cache e attachments ao cartão; nenhum destino ou
experiência é inventado. A lista usa uma coluna na base mobile e duas a partir
de `48rem`. A folha upstream citada na matriz descreve listas de perfil/salvos,
não uma home autenticada; a paridade do conteúdo do Hub não é mensurável.

O cartão do ponto de entrada usa agora a variante `studio_fidelity`. Ela mantém
o contrato de slots/render array e aproxima margem, padding, raio e sombra dos
teasers OpenCulturas 3.1.5, escopada à raiz `.studio`. A story é declarativa;
não cria links funcionais. Firefox visível passou 45/45 medições na fixture
local em nove larguras; não é captura do Runtime.

Na navegação interna, `.studio__tabs` e `.studio__subtabs` preservam os links e
estados autorizados existentes. A apresentação distingue áreas primárias de
abas secundárias no padrão OpenCulturas Next; ambas quebram linha na base
mobile e neutralizam as molduras Bootstrap dos links e itens de lista, sem criar
navegação ou destinos adicionais. Cada link tem alvo mínimo de 44×44 CSS px.
Firefox ESR visível verificou alvos e reflow numa fixture em 27 larguras, sem
overflow; depois, a CSS candidata também foi verificada no Firefox ESR visível
sobre as rotas reais servidas de Marley e Conta. A regra inclui âncoras dentro de
`.studio__subtabs`, mesmo se o markup contextual não tiver `.studio__link`.
Alvos foram medidos em 44px; a validação não prova implantação do CSS nem cobre
todos os consumers. Comparação pareada e demais rotas permanecem pendentes
(OCS-F2 parcial).

`content/editorial-card` mantém os slots e o consumer atuais; altura mínima e
padding compactos são a base, com composição ampla em `48.0625rem`. A validação
visual do consumer real `node--editorial-highlight` segue pendente.

O carrossel editorial da home MAIN reutiliza os controles do VVJB; anterior/próximo, pausa e pontos têm alvo de 44×44 CSS px, mantendo o ponto visual em 10 px. CSS escopada à View e sem novo SDC. Fixture Firefox passou 36/36 medições em 12 larguras; consulte `mobile-first-audit.md` para evidência e limites. A captura no Runtime com esta folha instalada permanece pendente.

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

O modal de confirmação Webform preserva a largura dinâmica do consumidor em
desktop e adapta o `minWidth: 600` definido pelo jQuery UI ao gutter disponível
em telas estreitas. O teste reproduz essa configuração em
`tests/browser/aculta-theme-mobile-first/webform-layout.py`; não há SDC novo nem
mudança no Form API.

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
| `content/event_card` | Experimental com consumidor real | `PortalUiHooks::preprocessEventCatalogue()` adapta os render arrays neutros de `/eventos`; dados, filtros, acesso e cache continuam no `aculta_events`. A prop `proxima` recebe apenas a data; o SDC fornece o rótulo visual. CSS candidata validada com moldura única e alvos de título de 44px em dez viewports; runtime após rebuild pendente. |
| `layouts/event-page-five-regions` | Experimental com consumidor real | O override de tema `aculta-event-page.html.twig` compõe `/eventos/{slug}` em cinco regiões; o título de página é suprimido apenas nessa rota para manter um H1. |
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

`profile-identity` não cria fallback visual quando o slot está vazio. O
`AvatarResolver` central do Portal fornece a mesma imagem canônica em Conta,
Studio, perfil público e cartões de autoria; uma story sem slot representa
somente o estado defensivo de teste.

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
No visual responsivo, `profile-identity` inicia com avatar de `3.5rem` e
alinhamento superior; em 353px restaura o avatar de `4.5rem` e o alinhamento
central, mantendo a composição lateral de desktop em 768px.
Sinais de código não equivalem a render validado: Unit, Kernel,
UI Patterns Library e Firefox ESR/X11 seguem pendentes.

## OC315 - verificação de consumidores (2026-10-29)

Novo inventário estático no HEAD da PR #299: **54 arquivos `.component.yml`** no tema; os 52 do histórico acima eram fotografia anterior. Contagem de arquivos não equivale a componentes aprovados, consumidores reais ou testes visuais.

- `content/event_card` e `layouts/event-page-five-regions`: homologar páginas de lista e detalhe de Eventos em Firefox ESR, incluindo dados reais, reflow, estados, teclado e claro/escuro; não criar Layout Builder paralelo.
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

### OCS-F1/F5–F7/F9 — estilos de Form API no tema

Os formulários existentes continuam sendo renderizados pelos módulos e pelo
Form API; o tema aplica a aparência Next somente sob `.studio__main form` e
`.aculta-profile-editor form`. Não foi criado SDC de formulário paralelo. A
fixture reutilizável `tests/browser/aculta-studio/studio-form-fidelity.py`
passou 50/50 verificações em claro/escuro e 320/390/768/1440px. Campos e ações
medem pelo menos 44px de altura, o foco de teclado permanece visível e um
formulário externo ao Studio não recebe esses estilos. A fixture não substitui
a homologação dos consumers reais ou dos estados AJAX/no-JS dos owners. Evidência
em `$SUITE_OUT/studio-form-touch-target-20261030/`.

### OCS-F8 — área comercial integrada

Green Stash usa o token neutro `green-stash-entry`; o tema o mapeia para a raiz
visual `.studio.aculta-green-stash`. Assim a área comercial integrada reutiliza
a fundação de tipografia, superfície e layout do Studio, preservando os
componentes específicos de Green Stash. Não foi criado SDC, shell, menu global
ou fluxo de Commerce paralelo. A fixture local valida a folha candidata; a
renderização integrada ainda requer Firefox visível no Runtime.

`studio.css` compõe a navegação autorizada emitida pelo
`GreenStashNavigation`, o formulário GET de busca e as listagens de
produto/remessa emitidas pelo controller. O CSS é escopado pela raiz
`.studio.aculta-green-stash`, mantém os nove links expostos, permite quebra sem
rolagem horizontal e dá acabamento de cartão às linhas com H3. Nenhum SDC novo
foi necessário: os dados operacionais atuais são wrappers simples de render
arrays e continuam pertencendo ao módulo Commerce. A fixture
`green-stash-layout.py` passou 74/74 checks no Firefox ESR visível em nove
larguras e claro/escuro; capturas da fixture estão em
`$SUITE_OUT/green-stash-layout-continuation-20261030/`. Esta evidência valida
somente a folha e a marcação simulada. Operador autorizado, grants por loja,
rotas reais e fluxos de escrita seguem pendentes.

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

Links sociais do cartão agora usam alvos de 44×44px em todos os tamanhos. A
story Editorial cobre essa variação com dados públicos autorizados. Firefox ESR
passou 43/43 checks em 320/360/390/575/576/600/768/1024/1280/1440px: a folha
candidata e dois links sintéticos do SDC mediram 44×44px; o consumer real do
Fórum manteve cinco cartões e não apresentou overflow. Os links sociais da
listagem real não foram tratados como disponíveis nem usados para alegar teste
de dados de perfil. Evidência em
`$SUITE_OUT/author-card-social-targets-20261030/`.

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

Na base mobile atual, a marca da Institution Bar permanece visível desde
320px e a barra pode quebrar em linhas, sem presumir Domain Header em todas as
rotas. A CSS candidata passou 147/147 verificações nos dez purposes e sete
larguras, incluindo Magazine sem Domain Header; evidências e limites em
[`mobile-first-audit.md`](mobile-first-audit.md).

Após publicar e reconstruir cache, o consumer integrado passou 140/140 checks
em todos os dez purposes, agora incluindo Revista, Cursos, Links e Eventos. Os
hosts vêm do manifesto de transição; LINKS não exige menu utilitário porque o
Portal o omite deliberadamente. Domain Header apareceu somente em MAIN e Wiki;
seguem pendentes Pede-A e validação MF3.

Repetição Firefox ESR visível em 2026-10-30 contra o docroot no HEAD remoto
`3c1691fc5` registrou 90/90 capturas (dez purposes × nove larguras), sem
overflow horizontal medido: Institution Bar 90/90 e Domain Header 18/90
(somente MAIN e Wiki). A Revista continuou sem H1 nem corpo editorial visível.
Esse baseline não valida os nove commits locais ainda não publicados, páginas
internas, usuários autenticados ou THEME-MF3; detalhes, Pede-A e evidências em
[`mobile-first-audit.md`](mobile-first-audit.md).

Como complemento, a rota pública Magazine `/noticias` foi capturada em nove
larguras no Firefox visível: 9/9 completas, H1/texto editorial presentes e sem
overflow. A rota é uma página comum, não uma publicação `revista`; a View de
notícias não retornou cards. A raiz segue vazia. Capturas em
`/home/piradopirata/aculta-suite-out/aculta-revista-noticias-20261030/`.

### THEME-MF2 — Revista e Estatísticas

As figuras editoriais da Revista e os filtros do painel de Estatísticas usam
composição compacta como base e ampliam progressivamente com `min-width`.
São estilos de consumers existentes; não criam SDC nem alteram props, slots,
Form API ou contratos dos módulos. Evidências e limites estão em
[`mobile-first-audit.md`](mobile-first-audit.md).

No artigo da Revista, texto, resumo e metadados também usam composição legível
desde 320px: alinhamento à esquerda e padding reduzido como base, com
justificação editorial e espaçamento amplo a partir de 576px. O conteúdo do
SDC `revista-publication` mantém a mesma ordem e os mesmos slots.

Firefox ESR visível confirmou a fixture editorial em 22/22 checks nas onze
larguras de 320 a 1440px, incluindo a transição 575/576px e ausência de
overflow. As capturas de 320/1440 foram inspecionadas. A folha CSS atual e a
referência remota têm o mesmo blob; não há mudança a atribuir a esta comparação,
que não substitui consumer/artigo real, acessibilidade ou THEME-MF3. Evidências
em `/home/piradopirata/aculta-suite-out/revista-article-current-20261030/` e
limites em [`mobile-first-audit.md`](mobile-first-audit.md).

O SDC `patterns/card` mantém as variantes atuais e aceita também
`data-card-variation="compact"` emitido pelo contrato neutro do Portal; nenhuma
prop ou slot foi alterado.


### THEME-MF2 — Institution Bar e Navigation top-bar

Reutilizados os elementos de shell existentes; nenhum SDC novo. A biblioteca
global do tema corrige o deslocamento Drupal quando a top-bar está no fluxo
móvel, mantendo o cálculo nativo quando a top-bar fica fixa no desktop. O
fallback sem JavaScript usa o token de altura existente. A Navigation top-bar
empilha título, contexto e ações como base mobile-first e restaura a grade de
três regiões em 768px. Comportamento, testes e limites estão em
[`mobile-first-audit.md`](mobile-first-audit.md).


### THEME-MF2 — alvos dos teasers da Revista

Links de título e ação no consumer `revista_sessoes` têm altura mínima de 44px,
com a mesma composição do SDC `card`. Não foi criado componente nem alterado
contrato. Teste Firefox Runtime com CSS candidata injetada passou em nove
larguras; ver evidência e limitações em `mobile-first-audit.md`.

### Consumer real — capa da Revista (2026-10-30)

A página da View `revista_sessoes` reutiliza `patterns/hero` para o título
contextual e o `patterns/card` existente para os teasers editoriais, sem novo
SDC nem alteração dos contratos de conteúdo. A capa principal muda para
composição horizontal em desktop por CSS da View; em telas menores empilha
imagem e conteúdo. Stories e schema dos SDCs permanecem inalterados porque
não houve mudança de contrato. O consumer foi capturado no Firefox ESR visível
no Runtime em 320–1440px; a evidência e os limites estão em
`mobile-first-audit.md`.

### THEME-MF2 — organizações de Eventos e conteúdo relacionado

A apresentação dos formulários de organização, cartões de organização e bloco
de conteúdo relacionado usa os contratos `data-portal-ui` existentes. O tema
centraliza CSS responsivo em `css/components/event-organization.css` e
`content.css`; a biblioteca CSS desativada do módulo Índice foi removida. Não
houve SDC novo nem mudança de props/slots: formulários e bloco preservam seus
consumidores e contratos Drupal. A fixture Firefox reutiliza Bootstrap local e
passa 74/74 checks; Kernel dos módulos está DEFERRED sem `SIMPLETEST_DB`. Veja
`mobile-first-audit.md` para detalhes e limites.

### OCS-F7 — consumer ACULTA STUDIO WE SATIVA (2026-10-30)

A composição visual do consumer Wiki reutiliza os SDCs existentes
`context-header`, `content-section`, `context-tabs`, `content-grid`, `card` e
`empty-state`; não foi criado SDC paralelo nem story fictícia. O wrapper real
`we-sativa__workspace`, mapeado pelo `PortalUiHooks`, é a raiz de escopo dos
tokens e estilos Fidelity. O catálogo não declara schema novo nem consumidor
extra: os render arrays são construídos por `WikiContributorWorkspace`.
Firefox ESR visível confirmou no consumer real servido (path legado `/wesativa`)
14 invariantes por viewport em 320/1440px: fonte, wrapper, headings, seções,
estados, quatro filtros, filtro atual único, wrapping, links de pelo menos 44px,
overflow e isolamento do shell. No modo escuro, 15/15 invariantes passaram nos
mesmos dois viewports. O path canônico `/studio/we-sativa` ainda não é servido
pelo Runtime HEAD `2493d7535`; F7 permanece parcial, sem aprovação.

### OCS-F3 — reexecução da fixture do Hub Fidelity

A combinação existente de `studio__tabs`, `studio__subtabs` e SDC `card` foi
revalidada em 27 larguras (243 checks Firefox ESR), com rótulos longos quebrando
sem overflow e cards em duas colunas a partir de 768px. Não cria SDC nem altera
contrato. Capturas e limitações em `studio-visual/visual-parity-matrix.md` e
`mobile-first-audit.md`.
