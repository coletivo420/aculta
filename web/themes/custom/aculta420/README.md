# ACULTA420 (`aculta420`)

Tema público e sistema de componentes da plataforma Drupal da ACULTA. Implementa
a apresentação; não é fonte de verdade de identidade, autenticação, Commerce,
LMS, Domains, conteúdo ou persistência.

## Meu Perfil — preparação visual 0.7.0-dev.1

Quatro SDCs experimentais para o editor privado (`profile-editor`,
`profile-identity`, `profile-bio`, `profile-social`) foram criados
com schema, Twig, CSS e duas stories por componente. Nenhum foi ligado
aos formulários ou render arrays do Studio; sua integração funcional
é a etapa posterior S-R4, com gates de acesso, cache e navegador
ainda pendentes.

## Fronteira de arquitetura

```text
Drupal Core + contrib → aculta_portal → contrato neutro → ACULTA420 → Bootstrap
```

O Portal prepara URLs, dados, acesso e cache. O ACULTA420 escolhe a apresentação
com Twig, Bootstrap e Single Directory Components (SDC) do Drupal Core. SDCs
recebem props e slots de apresentação e não resolvem regras de domínio.

Schema `aculta420.settings` inclui as opções Bootstrap `b5_*` preservadas nos
defaults do tema. A instalação do ACULTA420 e a renderização dos SDCs da home
Wiki foram verificadas em Kernel MariaDB; HTTP/Runtime e revisão visual seguem
pendentes.

## Documentação

- [Índice da documentação do tema](docs/README.md)
- [Design system e tokens](docs/design-system.md)
- [Auditoria Mobile First](docs/mobile-first-audit.md) — inventário e evidências de responsividade do tema e do Studio.
- [Catálogo de componentes](docs/component-catalog.md)
- [Shell multidomínio](docs/shell.md)
- [Arquitetura](docs/architecture.md)
- [Acessibilidade](docs/accessibility.md)
- [Desenvolvimento e gates](docs/development.md)
- [Decisões](docs/decisions.md)
- [Roadmap do tema](docs/roadmap.md)
- [Versionamento](docs/versioning.md)
- [CHANGELOG](CHANGELOG.md) — mudanças, versões e evidências de validação.

Os guias de identidade específicos ficam no [índice de documentação](docs/README.md).
Cada componente SDC também pode manter seu README local em `components/`.

## Estrutura

- `components/` — componentes SDC reutilizáveis.
- `css/`, `js/` — apresentação e comportamento visual do tema.
- `templates/` — templates Twig.
- `src/Hook/` — hooks OOP de apresentação.
- `assets/` — fontes e recursos visuais.

Consulte [components/README.md](components/README.md) antes de criar componentes.
Para mudanças coordenadas com funcionalidade do Portal, consulte a
[fronteira Portal–tema](../../../../docs/portal/FRONTEIRA-MODULO-TEMA.md) e o
[mapa módulo–tema](../../../../docs/portal/MAPA-MODULO-TEMA.md).

## Planejamento visual compartilhado

O tema passa a concentrar o planejamento de novos padrões visuais multiárea em [docs/visual-cross-product-roadmap.md](docs/visual-cross-product-roadmap.md). O shell multidomínio (Institution Bar + Domain Header) permanece obrigatório em todo o portal; Context Header é exclusivamente interno ao conteúdo. Antes de desenvolver componente: reaproveitar SDC/Core/Bootstrap existentes, comprovar consumidores reais, definir slots/props, documentar stories e validar mobile, WCAG e dark/light. A documentação é um plano, não componentes já implementados.

A próxima fase do tema está especificada em [docs/theme-ux-0.8-a-ui-suite-audit.md](docs/theme-ux-0.8-a-ui-suite-audit.md), com inventário obrigatório de Core SDC, UI Patterns e UI Styles antes de criar novos padrões. Preservar o cabeçalho multidomínio global.
