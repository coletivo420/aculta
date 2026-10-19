# ACULTA420 (`aculta420`)

Tema público e sistema de componentes da plataforma Drupal da ACULTA. Implementa
a apresentação; não é fonte de verdade de identidade, autenticação, Commerce,
LMS, Domains, conteúdo ou persistência.

## Fronteira de arquitetura

```text
Drupal Core + contrib → aculta_portal → contrato neutro → ACULTA420 → Bootstrap
```

O Portal prepara URLs, dados, acesso e cache. O ACULTA420 escolhe a apresentação
com Twig, Bootstrap e Single Directory Components (SDC) do Drupal Core. SDCs
recebem props e slots de apresentação e não resolvem regras de domínio.

## Documentação

- [Índice da documentação do tema](docs/README.md)
- [Design system e tokens](docs/design-system.md)
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
