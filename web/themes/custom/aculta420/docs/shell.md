# Shell multidomínio — direção arquitetural

Status: **linha 0.2.0 concluída em 2026-10-09** (0.2-A a 0.2-F); 0.3.0 é a próxima.

Este documento fixa a direção do shell público. A 0.1.0 preserva a arquitetura
e o shell existente; 0.2-A prepara tokens; as etapas 0.2-B a 0.2-F entregam o
contrato e a validação visual progressivamente.

## Hierarquia

```text
Plataforma/instituição
ACULTA
       ↓
Domain purpose/produto
Wiki420 / Coletivo420 / Cursos / Loja / Institucional / ...
       ↓
Conteúdo da página
```

O shell possui duas camadas:

1. **Institution Bar** — global, baixa e discreta; representa a Associação Cultural
   Antiproibicionista/ACULTA e concentra utilitários globais como Conta, sair e
   futuros acessos ao ecossistema.
2. **Domain Header** — identidade visual principal do purpose corrente; pode variar
   logo, nome, navegação e accent sem trocar a arquitetura do shell.

## Ownership e resolução

O tema nunca escolhe identidade por hostname.

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

`aculta_portal` continua responsável por resolver o contexto funcional. O tema
apenas apresenta dados já preparados.

Contrato conceitual futuro:

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

O inventário 0.2-B.1 e a fronteira normativa estão registrados em
`docs/portal/DOMAIN-PRESENTATION-CONTRACT.md`. A 0.2-B.2 implementou o builder
Portal, a 0.2-B.3 conecta `identity` ao shell atual sem redesign e a B.4 congela
a fronteira com analyzer/fixtures compartilhados antes da Institution Bar.

Não passar entidade `Domain`, storage, services, hostname ou regra de negócio
para Twig/SDC.

Na 0.2-B.3 o shell atual mantém o `system_branding_block` como caminho primário.
`page.header` continua sempre renderizado. `ThemeHooks` detecta explicitamente o
plugin `system_branding_block`; somente quando ele está ausente, reduz a identidade
preparada pelo Portal ao fallback mínimo `{label, home_url}`, com `label =
short_title ?? title`, e `page.html.twig` o acrescenta. Não inferir branding pela
truthiness ou por “vazio visual” do render array. `purpose` não é emitido no DOM
em B.3/B.4 e `logo_alt` fica reservado para mídia de branding futura. Regiões
renderizáveis do contrato continuam deferidas, exceto `brand_media` no purpose Wiki, consumido pelo header desde 0.4.0-dev.6 (logo Wiki420, PR #112). `navigation` e `actions` seguem nulos até terem consumidor real.

## Branding e fallback

Branding próprio não é requisito para criar um purpose.

```text
logo específico do purpose
        ↓ se inexistente
branding ACULTA padrão
        ↓
título textual
```

Assets de logo light/dark podem existir no futuro somente quando necessários
para legibilidade. O espaço, as dimensões e a estrutura reservados à marca
permanecem iguais nos dois modos; a exceção não autoriza DOM ou layout distinto.

## Navegação por purpose

O shell é comum; o menu pode variar por purpose. Exemplos conceituais:

```text
MAIN:    Institucional | Projetos | Atividades | Notícias | Transparência
WIKI:    Explorar | Categorias | Recentes | Colabore
COURSES: Cursos | Trilhas | Meus cursos
```

O tema não decide esses menus por hostname. A origem/seleção é preparada pelo
Portal e apresentada pelo shell.

## Composição Twig/SDC

`page.html.twig` permanece compositor do shell. Não criar um SDC monolítico da
página.

Composição alvo:

```text
aculta-shell
├── institution-bar
└── domain-header
    ├── domain-brand
    ├── primary-navigation
    └── domain-actions
```

Quando o contrato estiver estável, `domain-header` pode virar SDC próprio com
props simples e slots de apresentação.

## Direção visual

A referência escolhida é próxima ao conceito “Design B”:

- fundo geral verde muito suave;
- superfícies principais brancas;
- Institution Bar branca, compacta e visualmente secundária;
- Domain Header branco, mais alto e dominante;
- linha inferior verde estrutural;
- item atual amarelo com texto escuro de contraste adequado;
- texto base verde escuro;
- logo ACULTA compacto na faixa institucional;
- logo principal variável no Domain Header.

Essas cores não devem ser espalhadas como literais. A implementação deve nascer
sobre semantic tokens fornecidos em 0.2-A. O fundo da página usa uma superfície
verde muito suave; conteúdo elevado usa superfície clara; a Institution Bar usa
uma superfície secundária; o Domain Header usa a superfície elevada principal.
O verde é acento estrutural. O item ativo usa fundo amarelo e texto escuro para
manter contraste legível; qualquer ênfase adicional deve passar por avaliação
de contraste.

Vocabulário alvo:

```css
--aculta-surface-page: ...;
--aculta-surface-raised: ...;
--aculta-surface-header: ...;
--aculta-text-primary: ...;
--aculta-text-secondary: ...;
--aculta-border-subtle: ...;
--aculta-shell-institution-bg: ...;
--aculta-shell-domain-bg: ...;
```

## Color modes

> Light e dark são o mesmo ACULTA420; muda a luz, não a arquitetura.

> Color mode no ACULTA420 é uma variação de tokens, não uma variação de layout.

0.2-A/0.2-A.1 fornece somente valores semânticos para
`data-bs-theme="light"` e `data-bs-theme="dark"`. Light usa página verde suave,
superfícies elevadas claras e identidade estrutural verde. Dark usa página
carvão quente, superfícies grafite quentes, texto creme/branco quente e bordas
neutras. Verde é marca/acento/interação; amarelo é ação/estado ativo; vermelho é
ênfase editorial. Dark não é uma versão verde-escura do ACULTA420.

O shell, DOM, markup, hierarquia, tipografia, dimensões, espaçamento, navegação e
comportamento são compartilhados. Somente tokens visuais variam. Componentes não
conhecem o modo nem `prefers-color-scheme`; tokens resolvem a aparência. O gate
proíbe seletores dark fora de `css/tokens.css`. 0.2-E/0.2-F valida visualmente o
shell nos dois modos. Seletor, preferência automática e persistência ficam para
uma fase posterior.

## Sequência 0.2.0

- 0.2-A — Semantic Foundations;
- 0.2-B — Domain Presentation Contract;
- 0.2-C — Institution Bar;
- 0.2-D — Domain Header (PR #93);
- 0.2-E — Mobile/Sticky Shell (PR #94);
- 0.2-E — Mobile/Sticky Shell;
- 0.2-F — Design B QA.

Estado atual: linha 0.2.0 concluída. 0.2-A e 0.2-B estão na `main`; 0.2-C a 0.2-F
estão em PRs empilhadas (#92 a #95). QA em navegador registrado no Git (commit `9c95420`, `docs/design-b-qa.md`).

### Institution Bar (0.2-C)

```text
div.aculta-institution-bar            (faixa institucional, não é landmark)
  .container > .aculta-institution-bar__inner
    p.aculta-institution-bar__brand   logo ACULTA (img 78×26; alt com o nome completo; sem texto visível)
    div.aculta-utility                menu de conta, quando existir
header.aculta-header                  Domain Header de purpose (0.2-D)
```

### Domain Header (0.2-D)

- o `<header class="aculta-header">` é o único banner da página e usa
  `--aculta-shell-domain-bg` / `--aculta-shell-domain-text`, com linha inferior
  `--aculta-border-accent`;
- a navegação herda a superfície do header, sem fundo verde próprio; o item ativo
  mantém `--aculta-interactive-active-*`;
- o template não ganhou ramificação por purpose: a variação continua vindo de dados
  preparados pelo Portal.

- o `<div>` evita um segundo landmark `banner`; o `<header>` do purpose continua
  sendo o único banner da página;
- a marca não é link: a URL de MAIN deve vir do Portal, sem derivar por hostname;
- `regions.actions` e `regions.navigation` seguem sem consumidor até que o Portal
  os prepare; quando isso ocorrer, a faixa os consumirá como render arrays.

## Sticky e mobile

Começar por `position: sticky`, não `fixed`, por conviver melhor com Drupal
Toolbar, zoom/reflow e ausência de compensação artificial no `body`.

Referência de densidade, não contrato rígido:

- Institution Bar: aproximadamente 28–34 px no desktop;
- Domain Header: aproximadamente 56–68 px no desktop.

No mobile:

```text
institution bar mínima

[logo do domain] [nome]                     [menu]
```

Bootstrap continua dono de Collapse/Offcanvas. Ações secundárias podem migrar
para Offcanvas quando necessário; não criar menu lateral custom em JavaScript.

## Anti-regressão

- nunca usar hostname para escolher logo/menu;
- nunca mover `DomainPurposeManager` para o tema;
- nunca passar entidade `Domain` diretamente a SDC/Twig;
- nunca obrigar branding próprio para um purpose;
- nunca criar um header diferente por subdomínio; o shell é único e os dados variam;
- não acoplar light/dark a markup específico;
- não criar segunda engine de navegação quando Bootstrap já oferece Collapse/Offcanvas;
- não implementar o redesign completo dentro da Foundation 0.1.0.
