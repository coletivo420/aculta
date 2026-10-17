# ACULTA420 0.2-B.1 — Inventário da fronteira Domain Presentation

Status: **0.2-B.1 concluída; 0.2-B.2 validada no Runtime Homelab; 0.2-B.3 e 0.2-B.4 implementadas na PR #88 e aguardando Runtime final**.

Este documento delimita a fronteira entre Drupal Domain, `aculta_portal` e o
tema `aculta420` antes da implementação do shell multidomínio 0.2-C/0.2-D.

## Objetivo

Transformar uma fronteira hoje implícita em um contrato explícito:

```text
Core/contrib + Domain
        ↓
resolução funcional
        ↓
aculta_portal
        ↓
view-model de apresentação
        ↓
ACULTA420
        ↓
Twig / SDC / Bootstrap
```

O tema não conhece Domain, hostname, storage, entidades de negócio ou serviços
do Portal. O Portal não define geometria, CSS, tokens ou markup do shell.

## Inventário atual

### Resolução funcional já existente no Portal

`DomainPurposeManager` é a fonte operacional para:

- purpose corrente;
- associação purpose → Domain ID;
- carregamento da entidade Domain quando necessário no Portal;
- URLs por purpose respeitando aliases do ambiente;
- URLs canônicas de produção;
- paths cross-domain.

Purposes existentes:

| Purpose | Domain ID | Papel funcional |
| --- | --- | --- |
| `main` | `aculta_org` | institucional/principal |
| `account` | `conta_aculta_org` | conta |
| `support` | `apoio_aculta_org` | apoio |
| `magazine` | `revista420_aculta_org` | Observatório/Coletivo 420 |
| `wiki` | `wiki420_aculta_org` | Wiki420 |
| `shop` | `loja_aculta_org` | loja |
| `courses` | `cursos_aculta_org` | cursos |

`ContentPurposeResolver` resolve ownership funcional de rotas e conteúdo.
`DomainPurposeRequestSubscriber` aplica fail-closed/wrong-host e fluxos de
destino. Nenhuma dessas responsabilidades deve migrar para o tema.

### Apresentação já preparada pelo Portal

O Portal já injeta dados de apresentação em pontos específicos:

- `preprocess_breadcrumb`: título corrente via `AcultaBreadcrumbBuilder`;
- `preprocess_block`: URL de transparência para bloco institucional;
- `preprocess_menu`: reescrita de URLs cross-domain;
- `preprocess_links`: links editoriais no purpose correto;
- `preprocess_page`: shell da Conta, aviso de edição e URL de Segurança.

Esses casos provam o padrão correto: o Portal resolve significado/URL e entrega
ao sistema de tema um valor simples ou renderable.

### Apresentação atualmente pertencente ao tema

`ACULTA420` atualmente:

- compõe o shell em `page.html.twig`;
- move Page Title, breadcrumb, menu de conta e help para posições visuais no
  `ThemeHooks::preprocessPage()`;
- apresenta o block `system_branding_block`;
- apresenta o menu `main` na região `primary_menu`;
- apresenta o menu `account` como faixa utilitária;
- possui tokens, CSS, responsividade, Bootstrap Collapse e JS de navegação.

Isso é responsabilidade correta do tema enquanto os dados já chegam resolvidos.

### Acoplamentos que ainda são implícitos

Hoje não existe um serviço/view-model único que diga ao tema:

- qual é o purpose de apresentação;
- qual título curto deve representar esse purpose;
- qual URL representa sua home;
- qual branding opcional deve ser usado;
- qual navegação primária foi preparada;
- quais ações pertencem à camada institucional;
- quais cache contexts/tags acompanham esses dados.

O shell depende de block placements globais e de `system_branding_block`,
portanto ainda não existe um contrato explícito purpose → apresentação.

## Fronteira normativa

A partir de 0.2-B, a fronteira é dividida em três níveis.

### 1. Functional Domain Context — Portal only

Pode conhecer:

- `DomainPurposeManager`;
- `DomainInterface`;
- Domain Negotiator;
- Domain IDs;
- aliases/hostname;
- Request;
- route ownership;
- access;
- entities e configuração funcional.

Não pode ser consumido diretamente por Twig/SDC.

### 2. Domain Presentation View-model — Portal → tema

É a única estrutura específica de purpose autorizada a atravessar a fronteira.

A pesquisa de Drupal Core reforça duas regras:

1. **dados tipados e renderables são coisas diferentes** — SDC usa props para
   dados estruturados e slots para conteúdo renderizável;
2. **cacheability pertence ao render tree**, não a um campo arbitrário do
   view-model.

Por isso, o contrato alvo passa a separar identidade escalar de regiões
renderizáveis:

```text
domain_presentation
├── identity
│   ├── purpose          string estável
│   ├── title            string
│   ├── short_title      string|null
│   ├── home_url         string seguro
│   └── logo_alt         string|null
├── regions
│   ├── brand_media      render array|null  (array só no purpose wiki, desde a PR #112; consumido pelo header)
│   ├── navigation       render array|null
│   ├── actions          render array|null
│   └── wiki_search      render array|null  (só Wiki; formulário GET do endpoint existente)
└── layout               flags booleanas de visibilidade, decididas pelo Portal por purpose
    ├── institution_bar_account  bool  (variante "conta" da barra institucional)
    ├── show_utility_bar         bool  (busca, carrinho, Messenger, avisos e Minha conta; falso em LINKS)
    ├── links_page               bool  (shell da página pública LINKS)
    └── show_site_header         bool  (cabeçalho de domínio: Wiki sempre; main só na página inicial)
```

`layout` existe para que o tema não decida por purpose. O Portal calcula cada flag
a partir do purpose e, no caso da página inicial, do `path.matcher` da requisição (cache
`url.path.is_front`). O tema só desenha o que as flags permitem. O gate
`validate-aculta420-shell-contract.php` proíbe comparar `identity.purpose` no tema. Os valores
ausentes do `layout` valem como falso, exceto `show_utility_bar`, que vale verdadeiro para
manter a barra visível quando não há apresentação de domínio.

`identity` é **props-ready**: somente escalares/arrays simples que podem ser
validados por schema quando o shell virar SDC.

`regions` é **slots-ready**: render arrays construídos por APIs Drupal, sem
pré-renderizar HTML em strings.

Cache contexts, tags e max-age **não fazem parte de
`domain_presentation`**. Eles são acumulados no Portal e aplicados ao render
array da página com `CacheableMetadata`, para que a Render API faça o bubbling
normal.

Esse formato é deliberadamente pequeno. Campos só entram quando existe
consumidor real.

O view-model que chega ao tema:

- não contém entidade `Domain`;
- não contém hostname;
- não contém service IDs;
- não contém objetos de storage;
- não contém objetos de acesso ou decisão de permissão;
- não contém `CacheableMetadata` ou outro objeto interno do Portal;
- não contém cores literais ou classes CSS específicas de purpose;
- não contém decisão light/dark;
- não contém markup estrutural do header.

O Portal pode usar internamente um value object imutável para montar esse
resultado e carregar cacheability, mas esse objeto **não atravessa para Twig**.
A travessia final é sempre array neutro + render arrays.

### 3. Shell presentation — ACULTA420 only

Pode decidir:

- DOM e landmarks;
- hierarquia visual;
- Institution Bar / Domain Header;
- Bootstrap primitives;
- slots/props SDC;
- tokens;
- spacing;
- tipografia;
- responsividade;
- sticky/mobile;
- comportamento visual e acessibilidade complementar.

Não pode:

- chamar `DomainPurposeManager`;
- carregar Domain;
- consultar hostname;
- selecionar menu por hostname;
- montar URL cross-domain;
- buscar config funcional do Portal;
- decidir access;
- inventar fallback funcional.

## Ownership por dado

| Dado/decisão | Owner | Cruza a fronteira como |
| --- | --- | --- |
| purpose corrente | Portal | string |
| Domain ID/hostname/alias | Portal | **não cruza** |
| URL home do purpose | Portal | URL preparada |
| título do purpose | Portal | string |
| branding específico disponível | Portal | valor/renderable opcional |
| fallback de branding | Portal prepara disponibilidade; tema apresenta | props simples |
| menu correto para o purpose | Portal | render array |
| busca da Wiki420 | Portal | render array com rota GET e cache context da query; sem classes de tema |
| reescrita cross-domain | Portal | URL já correta |
| account/global actions | Portal | render array |
| access/permissões | Core/Portal | somente resultado autorizado |
| cache contexts/tags | Portal/Core | metadata preservada |
| cores/tokens | ACULTA420 | não vem do Portal |
| layout/header/spacing | ACULTA420 | não vem do Portal; visibilidade por purpose (`layout`) vem do Portal |
| dark/light | tokens/etapa futura de preferência | nunca purpose |
| Collapse/Offcanvas | Bootstrap/ACULTA420 | comportamento visual |

## Fallback de branding

O fallback deve ser resolvível sem tornar branding próprio obrigatório:

```text
branding específico preparado para o purpose
        ↓ ausente
branding ACULTA padrão
        ↓ indisponível
título textual
```

O Portal informa os valores disponíveis. O tema executa apenas a apresentação
do fallback já contratado; ele não consulta Domain/config para descobrir outra
fonte.

## Navegação

Estado atual:

- `system.menu.main` continua na região `primary_menu` e aparece nos cabeçalhos
  compartilhados dos purposes não enciclopédicos;
- `system.menu.account` é global e aparece na faixa utilitária;
- `preprocess_menu` do Portal já corrige destinos cross-domain.
- Para Wiki, o Portal entrega `regions.wiki_search` como formulário renderizável.
  O ACULTA420 usa a presença dessa região como capacidade do cabeçalho de busca,
  omite `system.menu.main` somente nesse cabeçalho e mantém a busca junto da
  marca. Categorias, verbetes recentes e links de colaboração continuam na
  página da Wiki. Isso não altera a Institution Bar multidomínio.

0.2-B não deve criar menus duplicados por hostname. Se a 0.2-D exigir
navegação distinta por purpose, a seleção deve ser feita no Portal e entregue
como render array em `domain_presentation.navigation`.

## Cache e access

O contrato não pode transformar dados privados/variáveis em strings e depois
tentar reconstruir cacheability no tema.

O padrão de Core é acumular `CacheableMetadata` durante as decisões e aplicá-la
ao render array final. `MenuLinkTree::build()`, por exemplo, agrega a
cacheability dos access results e links antes de produzir o menu renderizável.

Regras:

- access é resolvido antes da apresentação;
- render arrays mantêm `#cache`;
- o builder acumula dependências com `CacheableMetadata`;
- a ponte de `preprocess_page` mescla essa metadata com a árvore existente,
  sem sobrescrever contexts/tags/max-age já presentes;
- no mínimo revisar contextos `domain`, `route`, `url.path`,
  `user`/`user.permissions` quando realmente afetarem a saída;
- entidades/configs usados pelo Portal entram como cacheable dependencies no
  Portal;
- menu deve preferir Menu API/MenuLinkTree em vez de arrays manuais, preservando
  access e o cache tag `config:system.menu.*`;
- vazio por access continua carregando a cacheability que explica por que está
  vazio;
- o tema não compensa metadata perdida e não adiciona contexts funcionais por
  adivinhação.

## Pontos de integração alvo

Drupal executa preprocess de módulos antes do preprocess do tema. Isso cria uma
ponte natural: o Portal prepara a variável; o ACULTA420 a consome depois.

A implementação 0.2-B.2 deve usar um serviço dedicado no Portal:

```text
DomainPurposeManager + Menu API + config/entidades autorizadas
        ↓
DomainPresentationBuilder
        ↓
DomainPresentation (objeto interno do Portal + cacheability)
        ↓
PortalHooks::preprocessPage()
        ├── $variables['domain_presentation'] = array neutro
        └── merge CacheableMetadata na render tree da página
        ↓
ThemeHooks::preprocessPage()
        ↓
page.html.twig / futuros SDCs ACULTA420
```

O objeto interno pode implementar `CacheableDependencyInterface` ou expor
`CacheableMetadata`, mas **nunca é passado para Twig**.

O nome concreto será decidido na implementação, mas deve existir **um único
builder/presenter autoritativo** para o shell.

### Regra de dependência

O Portal **não deve criar** `#type: component` / `#component:
aculta420:...`. Embora Core permita renderizar SDCs via render arrays, fazer
isso aqui inverteria a dependência e acoplaria o módulo funcional ao provider do
tema.

A divisão correta é:

```text
Portal:
  dados + URLs + access + cache + renderables neutros

Tema:
  escolhe SDC/Bootstrap + mapeia identity → props + regions → slots
```

Assim o Portal continua semanticamente independente do mecanismo visual atual.

Não espalhar `match ($purpose)` por hooks, controllers e templates.

## Modelos Drupal usados como referência

### Theme API / preprocess

Drupal permite que módulos adicionem/altere variáveis de templates por
`hook_preprocess_HOOK`; em Drupal 11.2 esses preprocess hooks suportam
implementação OOP com `#[Hook('preprocess_HOOK')]`. Em Drupal 11.3 temas também
suportam hooks OOP. Isso valida a direção já adotada por
`PortalHooks::preprocessPage()` e `ThemeHooks::preprocessPage()`.

Para compatibilidade futura, não introduzir novas funções mágicas
`template_preprocess_*`: Core 11.2/11.3 move preprocess inicial para callbacks
registrados no theme hook e remove caminhos legados rumo ao Drupal 12.

### Core Navigation / Toolbar

O módulo Navigation do Core separa construção funcional da apresentação:
renderer/services montam render arrays, acumulam cacheability e templates
recebem regiões renderizáveis simples. A top bar recebe `tools`, `context` e
`actions`, em vez de entidades/serviços.

Esse é o modelo mais próximo do nosso shell:

```text
Core Navigation                 ACULTA
renderer/service                DomainPresentationBuilder
      ↓                                ↓
render arrays + cacheability     identity + regions + cacheability
      ↓                                ↓
theme variables                 domain_presentation
      ↓                                ↓
template                        ACULTA420
```

Não copiar a implementação administrativa do Navigation; copiar a **separação de
responsabilidades**.

### MenuLinkTree

`MenuLinkTree::build()` agrega cacheability de access e links e produz um
render array com o cache tag da configuração do menu. Portanto a navegação do
shell não deve ser reduzida a uma lista de URLs/títulos manualmente construída
quando a Menu API já consegue preservar access/cache.

### SDC / UI Patterns

Core SDC recomenda:

- props para dados estritamente estruturados;
- slots para renderables;
- render array `#type: component` quando PHP é o consumidor do componente;
- `#cache` e `#attached` continuam disponíveis.

UI Patterns reforça usar renderables em slots em vez de decompor conteúdo
Drupal em strings/URLs quando isso faria perder capacidades do Render API.

No ACULTA, quem consome o SDC do shell será **o tema**, não o Portal. O Portal
entrega a matéria-prima neutra.

### Domain integrations

Integrações modernas com Domain tendem a manter Domain como contexto da camada
funcional e filtrar/decidir antes da apresentação. Isso reforça nossa regra de
que Domain Negotiator/entidade/hostname não chegam ao tema.

## Revisão de compatibilidade Drupal 11 → 12 → 13

Revisado em 2026-10-08 contra Core atual:

- hooks OOP em módulos são suportados desde Drupal 11.2 e hooks OOP em temas desde Drupal 11.3; manter `#[Hook]` em `src/Hook/` é o caminho corrente;
- `template_preprocess()` e `template_preprocess_HOOK()` legados estão deprecated na linha 11.x e removidos no Drupal 12; não reintroduzir callbacks mágicos legados;
- a ordem documentada do Theme API continua módulo preprocess → theme preprocess, sustentando a ponte Portal → ACULTA420 sem chamada direta entre providers;
- Render API continua exigindo que cache contexts/tags/max-age permaneçam no render tree e façam bubbling;
- desde Drupal 11.3, passar a `Renderer::addCacheableDependency()` um objeto que não implemente `CacheableDependencyInterface` é deprecated e o Core anuncia type-hint obrigatório no Drupal 13; `DomainPresentation` implementa explicitamente essa interface;
- `Element::children()` identifica filhos estruturais do render array, não garante que um filho represente branding visual efetivo. B.3/B.4 portanto preserva `page.header` e detecta explicitamente `system_branding_block` para decidir apenas o fallback de marca;
- SDC continua reservado para a fase em que existir componente estável: props para dados tipados e slots para renderables. O Portal não instancia provider SDC do tema.

Essas regras são deliberadamente mais estreitas que “funciona no Drupal 11”: evitam APIs já deprecated e preservam o caminho de atualização para Drupal 12/13.

## Compatibilidade Drupal 11+

Baseline recomendado para esta fronteira:

- módulos: OOP preprocess com `#[Hook]` (Drupal 11.2+);
- temas: OOP hooks com `#[Hook]` (Drupal 11.3+);
- não criar novas dependências em módulos auxiliares de preprocess;
- Render API + `CacheableMetadata` como contrato de cache;
- Menu API para menus;
- SDC Core para componentes estáveis;
- evitar APIs legadas `template_preprocess_*` que seguem rumo à remoção no
  Drupal 12.

## O que não deve entrar em 0.2-B

- redesign visual da Institution Bar;
- novo Domain Header;
- sticky/mobile;
- UI light/dark/auto;
- persistência de preferência;
- novo sistema de menus;
- SDC monolítico de página;
- branding hardcoded por hostname;
- mudança de access/wrong-host;
- migração de dados Domain.

Esses itens pertencem às fases posteriores ou a outras linhas do roadmap.

## Gate da 0.2-B

A fase estará pronta para avançar à 0.2-C quando:

1. existir um único builder/presenter de `domain_presentation`;
2. todos os purposes conhecidos tiverem fallback seguro;
3. o tema receber somente valores simples/render arrays;
4. nenhuma entidade Domain chegar a Twig/SDC;
5. nenhum arquivo do tema consultar hostname, Domain ou serviços do Portal;
6. URLs cross-domain forem construídas pelo Portal;
7. cache/access forem preservados por `CacheableMetadata` e render arrays,
   sem campo fake `cacheability` no view-model;
8. menu/actions permanecerem renderables e não HTML pré-renderizado;
9. o Portal não referenciar provider SDC `aculta420:*`;
10. fixtures cobrirem purpose conhecido, purpose sem branding, fallback,
    cache contexts/tags e saída vazia por access;
11. documentação e gate anti-regressão refletirem o contrato;
12. nenhuma mudança visual estrutural tiver sido introduzida na 0.2-B.

## Estado da implementação

Builder, identity no shell e analyzer compartilhado estão implementados. O histórico de cada
fase, com resultados de Runtime, está no `CHANGELOG.md` do tema e no Git.
