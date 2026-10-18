# Arquitetura

## Camadas

```text
Core + contrib
  User / Profile / Node / Views / Commerce / LMS / Group / Domain
                           |
                           v
                    aculta_portal
          access / cache / presenters / purpose
                           |
                           v
                      ACULTA420
 Foundations / Components / Patterns / Shell
                           |
                           v
                      Bootstrap 5
```

## Responsabilidades

### Drupal Core + contrib

São fonte de verdade de dados, permissões, entidades e regras funcionais.

### aculta_portal

Pode:

- integrar módulos;
- resolver Domain purpose;
- preparar presenters/view-models;
- preservar access/cache metadata;
- construir URLs de domínio;
- coordenar UX de Conta/Cursos/Apoio/Wiki.

Não deve empurrar storage ou regra de negócio para o tema.

### ACULTA420

É dono de:

- tokens e identidade visual;
- layout/shell público;
- Twig de apresentação;
- SDCs;
- CSS/JS de apresentação;
- responsividade;
- acessibilidade visual/comportamental complementar;
- progressive enhancement.

Não é dono de autenticação, pagamento, matrícula, progresso, Domain access,
persistência ou autorização.

### Authentication and anti-bot boundary

ACULTA420 owns presentation only: layout, typography, spacing, focus, responsive
behavior, reduced motion, generic form controls, and neutral classes such as
`aculta-login__divider` and `aculta-auth-provider`. `css/components/auth.css`
styles Drupal account forms and neutral Portal markup; it does not know which
provider or anti-bot mechanism is active.

`aculta_portal` owns authentication integration and policy: provider
availability, login render structures, destinations, Domain purpose, account
linking/connections, disconnect policy, and anonymous/authenticated CAPTCHA
policy. It supplies neutral render markup for ACULTA420 to style.

Drupal Core and contrib own protocol and provider implementations, including
Social Auth, CAPTCHA, and Turnstile. Infrastructure owns credentials, exposed
to Drupal through Key and the environment contract. ACULTA420 must not depend on
those modules, inspect their configuration, construct OAuth routes, or decide
which CAPTCHA engine is enabled.

### Hooks e configuração

Hooks do tema vivem em `src/Hook/` e usam `#[Hook]`; a implementação e os
métodos atuais estão no [inventário de classes e hooks](classes-hooks.md).
`ThemeHooks` recebe dependências por construtor. O tema não usa arquivo `.theme`
procedural nem service locator `\\Drupal::` em `src/`.

`aculta420.settings` contém somente apresentação/integração com Bootstrap e
assets padrão do tema. Referências funcionais institucionais pertencem ao
`aculta_portal.settings`, que prepara URLs/renderables antes de Twig.

### Bootstrap5

É infraestrutura. ACULTA420 não embarca outra cópia do Bootstrap e não
reimplementa Collapse, Dropdown, Offcanvas, Modal ou outras engines existentes.

## Presenter -> SDC

```text
Node -----------+
View -----------+
Commerce -------+--> presenter/render array --> SDC --> tokens + Bootstrap
LMS/Group ------+
Block ----------+
```

Presenter conhece Drupal. SDC conhece seu contrato de apresentação.

Presenter deve preservar, quando aplicável:

- `attributes`;
- `title_prefix` / `title_suffix`;
- access;
- cache tags/contexts/max-age;
- render arrays seguros.

SDC não recebe entidade inteira como atalho e não usa service locator.

## Namespace

O provider do tema é `aculta420`.

Exemplo:

```twig
{{ include('aculta420:editorial-card', {...}, with_context = false) }}
```

O ID do provider faz parte da API SDC. O rename 0.1.0 é deliberadamente o
ponto de ruptura entre o tema histórico e a nova fundação.

## IDs preservados

Nem todo identificador `aculta_*` foi renomeado.

Permanecem estáveis quando representam:

- marca institucional;
- Domain IDs;
- content types;
- menus;
- Views;
- webforms;
- block placement IDs existentes;
- CSS classes/tokens do design language.

Isso evita quebrar configuração/conteúdo sem benefício. O machine name do tema,
por outro lado, é sempre `aculta420`.

## Shell multidomínio

O shell é composto por Institution Bar, Domain Header e área de conteúdo. Ele recebe dados de
apresentação já resolvidos pelo Portal e não resolve purpose nem hostname. Sticky e navegação
móvel usam Bootstrap Collapse. Contrato completo em [shell.md](shell.md).

### Contrato de color mode

> Light e dark são o mesmo ACULTA420; muda a luz, não a arquitetura.

> Color mode no ACULTA420 é uma variação de tokens, não uma variação de layout.

Os modos compartilham DOM, markup, hierarquia, componentes, tipografia,
espaçamento, dimensões, grid, breakpoints, posicionamento, shell, navegação e
comportamento. Somente cores, bordas e sombras tokenizadas variam. Componentes
consomem semantic tokens e não consultam light/dark ou `prefers-color-scheme`.
Variantes de asset de logo podem ser adotadas futuramente somente se necessárias
para legibilidade, mantendo o mesmo espaço, dimensões e layout.

Hierarquia:

```text
Plataforma/instituição
ACULTA
       ↓
Domain purpose/produto
Wiki420 / Coletivo420 / Cursos / Loja / Institucional / ...
       ↓
Conteúdo da página
```

Fluxo obrigatório de resolução:

```text
Domain
  ↓
DomainPurposeManager
  ↓
aculta_portal
  ↓
contexto de apresentação
  ↓
ACULTA420
```

O tema não lê hostname para escolher identidade. `aculta_portal` resolve o
purpose e prepara dados simples/renderables. Um contrato futuro pode expor:

```text
domain_presentation
├── purpose
├── title
├── short_title
├── home_url
├── logo
├── logo_alt
├── navigation
└── optional accent
```

Isso não autoriza passar entidade `Domain` para Twig/SDC. Presenter/render array
transforma tudo antes e preserva cache/access.

O branding segue fallback:

```text
logo específico do purpose
        ↓ se inexistente
branding ACULTA padrão
        ↓
título textual
```

Um novo purpose não depende de logo próprio.

`page.html.twig` continua compositor do shell. A composição alvo é Institution Bar
+ Domain Header; não criar agora um SDC monolítico da página. Quando o contrato
amadurecer, `domain-header` pode virar SDC recebendo apenas props simples e slots.

Detalhes: [shell.md](shell.md).

## SDC

- components podem viver em subdiretórios;
- schemas são obrigatórios no tema;
- assets com o mesmo nome do component são auto-descobertos;
- variants nativos devem ser usados quando a mesma família visual compartilha
  semântica/estrutura;
- componentização não autoriza duplicar markup Bootstrap nem mover regra de
  negócio.

Referências:
- https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/creating-a-single-directory-component
- https://www.drupal.org/docs/develop/theming-drupal/using-single-directory-components/using-your-new-single-directory-component
- https://www.drupal.org/node/3517062
