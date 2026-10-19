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
│   └── project-card/         (experimental, 0.3.0)
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
| `aculta420:content-grid` | `components/patterns/content-grid/` | `views-view-unformatted--aculta-projects.html.twig` | linhas da view (cada uma com seu presenter) |
| `aculta420:carousel` | `components/patterns/carousel/` | `views-view-vvjb.html.twig` | engine VVJB, sem alteração |
| `aculta420:rail` | `components/patterns/rail/` | `views-view-unformatted--courses-catalog.html.twig` | linhas do catálogo de cursos (card do LMS) |

Ao migrar, o HTML antigo das seções (`section.aculta-editorial-section`) passou a ser
estrutura de campos. O texto rico interno permaneceu no corpo, e o texto de cada seção
foi conferido contra o original antes da gravação.

Pendências da 0.4:

- **Seções editoriais** agora são renderizadas pela hook de tema `aculta_section`, declarada pelo `aculta_portal` (`PortalHooks::theme()`). O `EditorialHooks::entityViewAlter()` entrega título, subtítulo, variante e corpo para blocos `basic`. O template `aculta-section.html.twig` do tema monta o `content-section`; o template do módulo é só um fallback neutro.
- **Cabeçalho de projetos** é um bloco `basic` ("Cabeçalho — Nossos projetos") posicionado por visibilidade de purpose: `aculta_projects_header_home` em `<front>` e `aculta_projects_header_page` em `/projetos`, ambos com `aculta_domain_purpose = main`. A view de projetos não tem mais cabeçalho próprio. Nenhum ramo por purpose existe no tema.
- **Cabeçalho por domínio** segue a prática do projeto: o Domain Header é único para todos os domínios. Cada seção de subdomínio é um bloco com visibilidade `aculta_domain_purpose`, configurado por placement; o tema apenas apresenta o bloco. Um novo subdomínio recebe seu próprio placement, sem mudança de código.
- **Carrossel**: o wrapper foi migrado, mas a view da home está desativada no Runtime, então não foi visto em navegador.
- **Estruturas internas** (`aculta-areas`, `aculta-callout`, `aculta-page-intro`, `aculta-editorial-link`, `aculta-institutional-note`): **decisão registrada (T1): permanecem como rich text no corpo.** São texto editorial sem schema fixo. Virar SDC exigiria um modelo de conteúdo por bloco, o que é decisão de produto e não entra no saneamento. Revisar só se o responsável pedir.
- **Rail de cursos**: não criado, porque exigiria mudar a view `courses_catalog`.

## Componentes atuais

### `aculta420:project-card`

Status: experimental (0.3.0). Local: `components/content/project-card/`.

Slots: `media` (omitido quando vazio), `category`, `title` (link), `summary`, `cta` (link).
O presenter `node--project--teaser.html.twig` mantém `<article>`, attributes e links;
o SDC compõe o pattern `aculta420:card` e mantém os slots e classes semânticas do
projeto. A mídia continua renderizada pelo Drupal e contida sem recorte. Sem props,
JavaScript ou consulta de dados.

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
- project-card;
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
identificação de acervo, sem link individual ou ação de conta. `author_editorial`,
`author_lms` e `author_wiki` têm chaves reservadas, mas aguardam seus consumidores
contextuais. Inspeção visual e Runtime: DEFERRED.

A home/lista da Wiki usa css/components/wiki.css, arquivo temático de
responsabilidade própria na biblioteca global. O markup continua preparado pelo
Portal por tokens data-portal-ui; Views/VVJB continuam fonte dos itens.

Status: experimental, até validação em navegador no servidor de testes.

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

Modelo padrão de Notícia e blog. Experimental. Os templates `node--article--full` e `node--blog--full` chamam
o mesmo componente; o conteúdo vem do Drupal e a apresentação é deste componente.

Classes (documentadas aqui porque são de apresentação, não do módulo):

| Classe | Onde | Função |
| --- | --- | --- |
| `node`, `node--type-article` ou `node--type-blog`, `node--view-mode-full` | raiz do nó (`article`) | Classes padrão do Drupal, mantidas para as regras que dependem delas. |
| `aculta-revista-article` | raiz do nó | Marca a publicação da Revista no tema. |
| `aculta-revista-publication` | raiz do componente | Coluna de leitura (68ch), centralizada. |
| `aculta-revista-publication__meta` | cabeçalho (autor, data, compartilhamento) | Faixa de metadados. |
| `aculta-revista-publication__cover` | capa | Moldura 4:3 única, recorte pelo centro, largura máxima 40rem. |
| `aculta-revista-publication__subtitle` | chamada | Texto de abertura. |
| `aculta-revista-publication__summary` | resumo | Caixa de resumo (superfície muted). |
| `aculta-revista-publication__body` | corpo | Texto da publicação. Intertítulos alinhados à esquerda; imagens do corpo centralizadas (`media--type-image`). |
| `aculta-revista-publication__footer` | rodapé | Sessão, tags, referências e créditos. |
| `aculta-revista-publication__authors` | caixa de autoria | Recebe `data-portal-ui="article-authors"` do módulo. |
| `aculta-revista-publication__discussion` | discussão | Recebe `data-portal-ui="discussion"` do módulo. |

Regras de apresentação que valem para todas as publicações (`css/components/revista-post.css`): não há ajuste por
publicação; capa, resumo e corpo ficam na mesma coluna de leitura.

## Contador de não lidos (`aculta-unread-count`)

Classe única para todos os contadores (carrinho, avisos, mensagens, badge de conta e contadores de conteúdo, como o
do fórum). Arquivo: `css/components/unread-count.css`. O módulo entrega o token `data-portal-ui` (ex.: `forum-topic-unread`),
que `PortalUiHooks` traduz nessa classe. Zero não lidos oculta o contador. A posição fica no gatilho. Contrato completo
em `docs/portal/CONTADORES.md` (raiz do repositório).
