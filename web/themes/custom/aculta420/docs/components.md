# Componentes

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
o SDC mantém a marcação interna, com as mesmas classes do teaser anterior (DOM idêntico
medido). Sem props, JavaScript ou consulta de dados.

### Course card (LMS, skin do tema)

Não há SDC `aculta420:course-card`. O componente estável `lms:course_card` pertence ao
módulo LMS (dados, marcação e assets). O tema apresenta esse componente pela
`css/components/course-card.css`: mapeia as variáveis `--color-*` do LMS, o fundo, o overlay,
as sombras de texto e as cores do botão "começar" para tokens semânticos. Um SDC paralelo
duplicaria um componente estável de outro módulo e foi evitado.

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
