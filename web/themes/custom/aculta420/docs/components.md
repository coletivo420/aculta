# Componentes

## Discussões ACULTA — contrato futuro

O tema mantém os templates legados do Drupal Forum durante a migração e passa a
apresentar comentários compartilhados via `comment--aculta-discussion-post.html.twig`.
O `aculta_user` prepara o cartão `author_comment`; ACULTA420 controla markup,
layout responsivo, acessibilidade e tokens light/dark em `discussion.css`. Não há
consulta de usuário no tema. A lista de seis presets e capacidades está em
`aculta_forum/docs/ADVANCED-FORUM-PORT.md`.

O catálogo visual e suas regras estão em
[`component-catalog.md`](component-catalog.md). Os stories ficam junto dos
SDCs e aparecem na biblioteca protegida do UI Patterns.

## Organização

Drupal Core permite SDCs aninhados. A organização alvo é:

```text
components/
├── primitives/
├── content/
├── navigation/
├── sections/
└── feedback/
```

0.1.0 possui:

```text
components/
├── content/
│   ├── editorial-card/       (stable)
│   ├── course_card/          (experimental, replaces LMS card)
│   └── (project-card removido com o tipo projeto)
└── patterns/
    └── social-links-page/    (experimental, 0.6.0)
```

Não criar diretórios vazios apenas para parecer completo.

## Regras SDC

Todo SDC ACULTA420:

- possui schema;
- recebe props/slots explícitos;
- não consulta storage/services;
- não recebe entidade inteira por conveniência;
- documenta ownership de CSS/JS;
- usa status `experimental` até provar contrato;
- só vira `stable` após uso real e validação.

O tema usa `enforce_prop_schemas: true`.

## Props e slots

Slots:

- renderables;
- markup;
- regiões de conteúdo.

Props:

- strings/números/booleans/enums e dados estruturados simples;
- sempre validados pelo schema.

Preferir render element `#type: component` em PHP quando Drupal precisar
conhecer attachment/cache/render contract; Twig inclui componentes em presenters
quando esse boundary for mais simples.

## Padrões 0.4 (experimental)

Estado em 0.4.0. Todos experimentais por política, até consumo estável e validação.

| Pattern | Local | Consumidor | Dados |
| --- | --- | --- | --- |
| `aculta420:content-section` | `components/patterns/content-section/` | `block--block-content--type--basic.html.twig` | campos `field_section_title`, `field_section_heading`, `field_section_variant` e o corpo |
| `aculta420:hero` | `components/patterns/hero/` | `ThemeHooks::preprocessNode()` (página `page` na visualização full) | campos `field_hero_*` do nó |
| `aculta420:content-grid` | `components/patterns/content-grid/` | (view de projetos removida com o tipo projeto) | linhas de uma view (cada uma com seu presenter) |
| `aculta420:carousel` | `components/patterns/carousel/` | `views-view-vvjb.html.twig` | engine VVJB, sem alteração |
| `aculta420:rail` | `components/patterns/rail/` | `views-view-unformatted--courses-catalog.html.twig` | linhas do catálogo de cursos (card do LMS) |

Ao migrar, o HTML antigo das seções (`section.aculta-editorial-section`) passou a ser
estrutura de campos. O texto rico interno permaneceu no corpo, e o texto de cada seção
foi conferido contra o original antes da gravação.

Pendências da 0.4:

- **Seções editoriais** agora são renderizadas pela hook de tema `aculta_section`, declarada pelo `aculta_portal` (`PortalHooks::theme()`). O `EditorialHooks::entityViewAlter()` entrega título, subtítulo, variante e corpo para blocos `basic`. O template `aculta-section.html.twig` do tema monta o `content-section`; o template do módulo é só um fallback neutro.
- **Cabeçalho de projetos** foi removido com o tipo projeto (2026-10-21). Nenhum ramo por purpose existe no tema.
- **Cabeçalho por domínio** segue a prática do projeto: o Domain Header é único para todos os domínios. Cada seção de subdomínio é um bloco com visibilidade `aculta_domain_purpose`, configurado por placement; o tema apenas apresenta o bloco. Um novo subdomínio recebe seu próprio placement, sem mudança de código.
- **Carrossel**: o wrapper foi migrado, mas a view da home está desativada no Runtime, então não foi visto em navegador.
- **Estruturas internas** (`aculta-areas`, `aculta-callout`, `aculta-page-intro`, `aculta-editorial-link`, `aculta-institutional-note`): **decisão registrada (T1): permanecem como rich text no corpo.** São texto editorial sem schema fixo. Virar SDC exigiria um modelo de conteúdo por bloco, o que é decisão de produto e não entra no saneamento. Revisar só se o responsável pedir.
- **Rail de cursos**: não criado, porque exigiria mudar a view `courses_catalog`.

## Componentes atuais

### `aculta420:forum-category`

Status: experimental. Local: `components/content/forum-category/`.

No bloco `block_landing`, os slots `parent`, `title` e `description` recebem os
campos renderizados pela View Core. Na página `/categorias`, a View agrupa as
linhas pela relação Parent e o slot `parent` fica vazio; `title` inclui o link
filtrado preparado pela View. O grupo usa H2 e cada link de categoria usa H3.
Os presenters preservam linhas e attributes, e o SDC apresenta os cartões numa
grade responsiva. Não carrega entidades, constrói URLs ou decide filtros/acesso;
isso continua com Drupal Views e o Portal.

### `aculta420:project-card` (removido)

O SDC foi removido em 2026-10-21, quando o tipo de conteúdo projeto foi retirado do Runtime e do código. Não há consumidor restante.

### `aculta420:course_card`

Status: experimental. Substitui `lms:course_card` via `replaces`, com props e slots
compatíveis. O Drupal LMS segue dono dos dados, URL canônica, botão de entrada e cacheability;
ACULTA420 compõe `aculta420:card` na variante vertical e apresenta o cartão e seus
estados com tokens semânticos no CSS do próprio SDC. O template mantém os nomes de
classe usados pelo row do LMS, sem alterar o plugin nem a View.
O foco é visível por teclado, o cartão respeita movimento reduzido e não exige JavaScript.

### `aculta420:editorial-card`

Status: stable.

Local:

`components/content/editorial-card/`

Slots:

- category;
- title;
- summary;
- complement;
- cta.

Presenter:

`templates/node--editorial-highlight.html.twig`

O presenter preserva Theme API e encaminha renderables. O SDC não conhece Node.

## Button

Button é primitive do design system, mas **não** SDC obrigatório.

`css/components/buttons.css` precisa cobrir markup vindo de Bootstrap,
Form API e contrib:

- `.btn`;
- `.button`;
- submits;
- variants Bootstrap.

Criar `aculta420:button` agora criaria uma segunda API parcial.

## Cards

Roadmap de cards:

- editorial-card;
- course-card;
- product-card somente quando Commerce possuir catálogo real.

Compartilhar contratos onde houver semântica comum, sem criar “base component”
abstrato que force domínios diferentes.

Usar variants nativos para layouts da mesma família quando apropriado.

## Pattern experimental da Wiki420

aculta420:wiki-entry vive em components/patterns/wiki-entry/ e apresenta os
modos artigo e teaser. Os presenters node--wiki-entry--full.html.twig e
node--wiki-entry--teaser.html.twig mantêm atributos e render arrays nativos;
não exibem autoria. Campos e ações continuam sob access do Drupal. O CSS usa
tokens semânticos e não cria modo ou layout alternativo para Dark.

## Componentes experimentais do ACULTA Messenger (0.6.0-dev.23)

| Componente | Consumidor | Contrato |
| --- | --- | --- |
| `messenger-list` | rotas de central e inbox nativa | slot para render arrays autorizados; sem reconstruir Views |
| `messenger-item` | entidade Private Message | avatar autorizado e conteúdo nativo em slots; prop booleana `outgoing`; CSS organiza balões e alinhamento |
| `messenger-compose` | criação e resposta | slot contém Form API nativa, CSRF, AJAX e fallback |
| `messenger-notification` | Message na lista própria | slot com entidade Message autorizada pelo Portal |
| `institutional-notice` | Site Alert nativo | severity `low`, `medium`, `high` e slot da mensagem |
| `unread-badge` | navegação de Minha Conta | prop inteira da contagem de não lidas; omitido em zero |
| `messenger-state` | avisos vazios e estados futuros | `empty`, `error` ou `loading`; sem lógica de carregamento no tema |
| `messenger-trigger` | ação de mensagens na barra multidomínio | gaveta Bootstrap compartilhando shell, largura, cabeçalho, fechamento, tokens e badge do carrinho; conteúdo e contador são atualizados por AJAX a cada abertura |
| `alerts-trigger` | ação de avisos na barra multidomínio | gaveta Bootstrap compartilhando shell do carrinho; Site Alerts, comunicados e contador são atualizados por AJAX a cada abertura |
| `messenger-read-status` | thread ou aviso renderizado | rótulo lido/não lido fornecido pelo Portal/Flag |

O Portal escolhe access, dados, URL e cache; o ACULTA420 mapeia para esses SDCs.
Todos permanecem `experimental` até validação real. Estilos usam tokens
semânticos compartilhados entre claro e escuro, HTML semântico e foco visível.
Na prévia da barra, as conversas chegam agrupadas em caixas por remetente e
ordenadas pela atualização mais recente; o cabeçalho abre a conversa mais nova
daquela pessoa. O badge da navegação de Minha Conta usa vermelho dentro do
shell `.portal-account`, mantendo a cor semântica original fora dele. Cada
linha tem menu contextual para limpar o histórico pessoal pelo formulário
nativo do Private Message.

## Cartão de autoria compartilhado (0.6.0-dev.91)

`content/author-card` apresenta variantes de autoria sem consultar entidades ou
decidir acesso. O `aculta_user` prepara apelido, link canônico, avatar autorizado
e cache no theme hook neutro; a imagem passa por `aculta_image`. `author_forum`
e `author_comment` compartilham o layout inline, enquanto
`author_forum_stacked` altera apenas a disposição. `author_growroom_historico` é somente
identificação de acervo, sem link individual ou ação de conta. `author_editorial`
é consumido pela caixa de autoria da Revista, com designação Autor/Coautor,
biografia e redes entregues pelo Portal somente quando autorizadas pelo perfil.
`author_lms` e `author_wiki` aguardam seus consumidores contextuais. Inspeção
visual integrada e Runtime: DEFERRED.

A home/lista da Wiki usa css/components/wiki.css, arquivo temático de
responsabilidade própria na biblioteca global. O markup continua preparado pelo
Portal por tokens data-portal-ui; Views/VVJB continuam fonte dos itens.

Status: experimental, até validação em navegador no servidor de testes.

### Tópico público ACULTA Forum (0.6.0-dev.101)

`node--aculta-forum-topic.html.twig` deriva do template Node Core 11.4.10:
mantém attributes, title prefix/suffix, título linkado no teaser e conteúdo
renderizado. O byline padrão (`author_picture`/`author_name`) é omitido porque a
autoria vem do cartão de `aculta_user`, que aplica a política de privacidade. A
data segue visível. `forum-index.css` apresenta teasers, categorias e corpo com
tokens semânticos. O presenter declara `aculta-forum-topic--{view_mode}` porque
o template override não recebe automaticamente as classes bundle/mode do
wrapper padrão do Node. O gate da Foundation alinha esses modificadores aos
seletores teaser/full. Firefox no Runtime de teste confirmou os cartões com
borda/superfície e categoria em etiqueta; o cartão de autoria continua vindo do
`aculta_user`. Nenhum conteúdo foi alterado.

O template `views-view-fields--aculta-forum-public-topics.html.twig` preserva
o teaser renderizado pelo Drupal e apresenta contagem e última atividade. A
View do Portal prepara os valores com os handlers de estatística do Core; o
Twig escolhe o rótulo singular/plural. A ordenação usa atividade descendente
após tópicos fixados. Na lista, `forum-index.css` omite apenas o resumo do corpo
e a data de publicação redundante com última atividade; reduz avatar e
metadados autorais dentro do cartão compartilhado. O detalhe completo mantém
corpo, data e identidade sem alterações. Firefox/X11 verificou a listagem em
1024 e 500 px e o detalhe em 500 px; capturas privadas e limites restantes
estão no changelog `0.6.0-dev.109`.

O Portal pode anexar ao campo compartilhado a navegação de progresso das
mensagens. O tema apresenta os controles neutros `[data-aculta-discussion-progress]`
em `discussion.css`; não consulta dados nem decide quais comentários são
visíveis. Em telas estreitas, o status textual fica disponível a leitores de
tela e os botões mantêm alvos confortáveis. O Firefox/X11 verificou claro e
escuro em 1250/500 px e teclado do slider com DOM sintético não persistido;
leitor de tela segue pendente. A barra sticky respeita `--drupal-displace-offset-top`
e o scroll-padding do cabeçalho; em mobile ela fica no fluxo normal para não
cobrir a mensagem ativa. Em 320 px, as ações ocupam duas colunas; de 360 px em
diante, ficam em uma linha. Em mobile, slider e contador têm linhas próprias
para evitar alvos estreitos. Firefox ESR/X11 e BiDi confirmaram 320/360/390/500/
1250 px, modos claro/escuro, slider e botão sem overflow. Evidências nos
changelogs `0.6.0-dev.110` a `0.6.0-dev.115`. Quando History marca um Comment
renderizado com `.new`, o Portal acrescenta `[data-aculta-discussion-unread]`;
o tema o apresenta como ação secundária em uma linha separada no mobile e numa
linha compacta no desktop. O botão continua oculto sem marcador e o tema não
consulta History. Evidência visual em `0.6.0-dev.116`.

## Patterns

Patterns não são engines de dados.

Exemplos futuros:

- content-section;
- content-grid;
- hero;
- carousel/rail.

Carousel pode apresentar qualquer card, enquanto VVJB/Bootstrap continuam
fornecendo engine conforme o contexto.

## Maturidade

### experimental

- API ainda pode mudar;
- deve estar documentada;
- não pode ser usada como dependência silenciosa por dezenas de telas.

### stable

- schema claro;
- ownership de assets claro;
- acessibilidade validada;
- dois ou mais consumidores ou fronteira visual muito estável;
- mudança incompatível exige release MINOR antes de 1.0 e nota de migração.

## Ecossistema

Planejado:

- UI Patterns 2 para expor SDCs maduros na UI Drupal;
- UI Icons para Icon API;
- UI Patterns Library/UI Examples para catálogo.

Não usar UI Suite Bootstrap para substituir ACULTA420.

## Publicação da Revista (`aculta420:revista-publication`, 0.6.0-dev.21)

Modelo padrão de publicações da Revista. Os templates `node--revista--full`, `node--article--full` e `node--blog--full` chamam
o mesmo componente; o conteúdo vem do Drupal e a apresentação é deste componente.

Classes (documentadas aqui porque são de apresentação, não do módulo):

| Classe | Onde | Função |
| --- | --- | --- |
| `node`, `node--type-revista`, `node--type-article` ou `node--type-blog`, `node--view-mode-full` | raiz do nó | Classes padrão do Drupal, mantidas para as regras que dependem delas. |
| `aculta-revista-article` | raiz do nó | Marca a publicação da Revista no tema. |
| `aculta-revista-publication` | raiz do componente | Coluna de leitura (68ch), centralizada. |
| `aculta-revista-publication__meta` | cabeçalho (autor, data, compartilhamento) | Faixa de metadados. |
| `aculta-revista-publication__cover` | capa | Moldura 4:3 única, recorte pelo centro, largura máxima 40rem. |
| `aculta-revista-publication__subtitle` | chamada | Texto de abertura. |
| `aculta-revista-publication__summary` | resumo | Caixa de resumo (superfície muted). |
| `aculta-revista-publication__body` | corpo | Texto da publicação. Intertítulos alinhados à esquerda; imagens do corpo centralizadas (`media--type-image`). |
| `aculta-revista-publication__footer` | rodapé | Sessão, tags, referências e créditos. |
| `aculta-revista-publication__authors` | caixa de autoria | Recebe `data-portal-ui="article-authors"` do módulo. |
| `aculta-revista-publication__discussion` | discussão | Recebe o render array Core de `field_aculta_discussion` no slot `discussion`; access e estado são preparados pelo Drupal/Portal. |

Regras de apresentação que valem para todas as publicações (`css/components/revista-post.css`): não há ajuste por
publicação; capa, resumo e corpo ficam na mesma coluna de leitura.

## Contador de não lidos (`aculta-unread-count`)

Classe única para todos os contadores (carrinho, avisos, mensagens, badge de conta e contadores de conteúdo, como o
do fórum). Arquivo: `css/components/unread-count.css`. O Portal poderá entregar o token `data-portal-ui` (ex.: `forum-topic-unread`) na futura contagem da View ACULTA; não há produtor ativo hoje,
que `PortalUiHooks` traduz nessa classe. Zero não lidos oculta o contador. A posição fica no gatilho. Contrato completo
em `docs/portal/CONTADORES.md` (raiz do repositório).

## Auditoria de reutilização GanjaLab420 — 2026-10-22

O planejamento [GanjaLab420 × ACULTA420](../../../../../docs/portal/GANJALAB420-CROSS-VISUAL-AUDIT.md) prioriza componentes existentes e variantes. Criar novos SDCs apenas com contrato de dados/slots, primeiro consumidor real, segundo consumidor mapeado, story UI Patterns, acessibilidade, cache/access nos providers e documentação. Padrões transversais candidatos: context-header, context-tabs, empty-state, content-reader, resource-list e status-timeline. Nenhum foi implementado nesta auditoria.

## Planejamento dos componentes transversais — 0.8-B

[Contratos de Context Header, Context Tabs e Feedback](../../../../../docs/portal/GANJALAB420-SHARED-UI-PLAN.md) orientam o reuso em LMS, Revista, Wiki, Fórum e Conta. Não criar componentes antes de identificar consumidores reais e avaliar Core/Bootstrap/SDCs existentes; cada novo SDC necessita story, contrato de props/slots e testes mobile/light-dark.

## Critério obrigatório de reuso (revisão 2026-10-22)

O ACULTA420 só compartilha novos padrões de apresentação como **Drupal Core SDC**, preparados para **UI Patterns, UI Patterns Library e UI Styles**. Componentes preexistentes com Twig/Bootstrap puro não são elegíveis à reutilização transversal até sua migração para SDC e testes. Componentes SDC incompletos precisam de alinhamento antes de consumo novo. Conservar interfaces funcionais de terceiros (H5P, PDF.js, Form API) nos donos originais; aplicar SDC à composição visual, sem recodificar players/formulários.

Consultar [roadmap visual transversal](visual-cross-product-roadmap.md) e [matriz operacional B0](theme-ux-0.8-b0-execution-matrix.md) para estados de conformidade e gates. UI Patterns/Styles são usados onde a integração é apropriada; não se requer converter indiscriminadamente todos os displays existentes.

## Novo componente experimental — `aculta420:context-header`

Diretório: `components/patterns/context-header/`. Apresentação contextual abaixo do shell multidomínio; `title` texto simples obrigatório, `eyebrow` e `subtitle` opcionais, slots `breadcrumbs`, `back_link`, `actions`. O Twig renderiza H1 e **exige que o consumer evite emitir outro H1 na mesma página**. Tem stories estáticas educacional/editorial e CSS semântico. Não depende de LMS, Wiki, Group ou Domain. **Não usar em rota de produção antes da revisão B1-02d, prova de consumidores e testes Runtime**. [Contrato visual](theme-ux-0.8-b1-02b-visual-contract.md).

## Context Header — primeiro consumidor Wiki

`node--wiki-entry--full.html.twig` usa `aculta420:context-header` com `title` do label da entidade e `eyebrow` editorial. `aculta420:wiki-entry` tem a prop booleana `show_heading`, default `true`, configurada como `false` apenas na página completa para transferir o H1 sem alterar teasers e outros consumidores. O CSS existente de `.aculta-wiki-article__header` pode exigir adaptação após inspeção visual do Runtime. O component Context Header permanece `experimental` até validações em Drupal e segundo consumidor real.

## Segundo consumidor — GanjaLab420

`LearningDashboardController::student()` passou a renderizar `aculta420:context-header` com título `Meus cursos`, etiqueta GanjaLab420 e subtítulo. `ThemeHooks::preprocessPage()` suprime seletivamente o page-title block nessa rota para evitar H1 concorrente, mantendo o `_title` do Drupal. O tema preserva o shell multidomínio e o LMS mantém a propriedade de acesso/progresso/cache. Ver [homologação pendente](theme-ux-0.8-b1-02e-runtime-validation.md).

## `aculta420:context-tabs` — navegação contextual por rotas

SDC experimental em `components/patterns/context-tabs/`: prop `label` e slot `items` de âncoras access-checked, incluindo `aria-current=page` preparado pelo provider. Twig possui `<nav>`, CSS usa tokens e flex wrap mobile; stories estáticas GanjaLab e Wiki. **Não** confundir com `product-tabs`, que controla painéis Bootstrap internos. Ainda sem consumidores funcionais; ver [plano B2](theme-ux-0.8-b2-context-tabs.md).

## Context Tabs — primeiro consumidor real no código

`StudioController::linkList()` entrega `#theme: item_list` ao slot `items` do SDC `aculta420:context-tabs` somente para áreas e subtabs. Seções mantêm sua sidebar. O componente é apresentação: não modifica navegação Drupal Ajax, URLs, ACL nem Domain Header. Ver [fases e validação pendente](theme-ux-0.8-b2-context-tabs.md).

## Context Tabs — segundo consumidor Wiki

`WikiController::contextNavigation()` prepara links Início/Busca com Url::fromRoute, render arrays e `aria-current=page` e entrega `item_list` ao slot do SDC. Agora Studio e Wiki compartilham a apresentação Core SDC/UI Suite, sem atribuir ao tema URLs, ACL ou AJAX. [Gates B2-05 pendentes](theme-ux-0.8-b2-context-tabs.md).
