# ACULTA420

**ACULTA420** é o tema público e o Bootstrap Component Design System da
plataforma Drupal da Associação Cultural Antiproibicionista.

- machine name: `aculta420`;
- versão no código: **0.6.0-dev.80** (`aculta420.info.yml`; linha 0.6.x em desenvolvimento); sem tag Git (tags só por pedido). Histórico em `CHANGELOG.md` e `docs/roadmap.md`;
- Drupal: `^11`;
- base theme: `bootstrap` (`drupal/bootstrap` 5.0.2);
- Bootstrap Framework: `5.3.8`, instalado pelo Composer em `web/libraries/bootstrap`;
- Bootstrap Icons: `1.11.0`, distribuído pelo ACULTA420 com licença MIT;
- Display Suite: `3.37.0`, com layouts ACULTA420 responsivos de duas e três colunas;
- SDC: Drupal Core;
- documentação visual: Storybook 10 com renderer Drupal Server, stories Twig do módulo `drupal/storybook` e páginas MDX; dependências JS isoladas em `storybook/`.

## Arquitetura

```text
Drupal Core + contrib
        |
        v
   aculta_portal
        |
        v
     ACULTA420
        |
        v
 Drupal Bootstrap base theme
        |
        v
Bootstrap 5.3.8 + Bootstrap Icons
```

Display Suite administra os layouts e modos de visualização das entidades; os
layouts adicionados pelo tema usam a grade Bootstrap sem substituir o controle
editorial do módulo.

Os arquivos CSS/JS públicos do Bootstrap são sincronizados de `vendor/twbs/bootstrap`
para `web/libraries/bootstrap` pelos scripts Composer `post-install-cmd` e
`post-update-cmd`, conforme o caminho esperado pela base Drupal Bootstrap.

RUNTIME HOMELAB: DEFERRED; a configuração exportada ainda precisa ser importada e
o tema precisa de smoke test visual no ambiente de testes.

Drupal/contrib são donos de dados e regras. `aculta_portal` integra e prepara
contexto. ACULTA420 apresenta. Bootstrap fornece infraestrutura de layout,
utilities e behaviors.

## O que já existe em 0.1.0

- tokens ACULTA mapeados para custom properties Bootstrap;
- tipografia Inter + Oswald;
- shell responsivo;
- navegação com Bootstrap Collapse e progressive enhancement;
- breadcrumb público integrado ao presenter do Portal;
- formulários/auth com herança Bootstrap5;
- primeiro SDC stable: `aculta420:editorial-card`;
- CSS do editorial card auto-carregado pelo SDC;
- carrossel editorial VVJB com JS contextual;
- cards/prosa/projetos/institucional/footer;
- foco visível, teclado e reduced-motion;
- schema obrigatório para SDCs via `enforce_prop_schemas: true`;
- hooks do tema em OOP/DI (`src/Hook/ThemeHooks.php`), sem arquivo `.theme` procedural;
- versionamento SemVer próprio.

## Limite da Foundation 0.1.0

A 0.1.0 estabelece provider, namespace, configuração, documentação e fronteiras
arquiteturais. Ela **não** incorpora o redesign completo do shell multidomínio.

A direção já está congelada para evitar contratos incompatíveis na próxima fase:

```text
instituição/plataforma ACULTA
            ↓
Domain purpose/produto
            ↓
conteúdo da página
```

O shell futuro terá uma Institution Bar global e discreta sobre um Domain Header
visualmente dominante. Logo, título e navegação podem variar por purpose, mas são
preparados pelo `aculta_portal`; o tema nunca escolhe identidade por hostname.

O contrato detalhado e a direção visual estão em [docs/shell.md](docs/shell.md).
A implementação visual pertence às fases posteriores de foundations/shell, junto
dos semantic tokens.

## Estrutura

```text
web/themes/custom/aculta420/
├── aculta420.info.yml
├── aculta420.libraries.yml
├── src/
│   └── Hook/
│       └── ThemeHooks.php
├── assets/
├── components/
│   └── content/
│       └── editorial-card/
├── config/
├── css/
├── docs/
├── js/
└── templates/
```

Os componentes podem ser agrupados em subdiretórios de `components/`; o Core
SDC suporta essa organização.

## Storybook

O [Storybook ACULTA420](../../../../storybook/README.md) cataloga os SDCs e apresenta a documentação navegável do tema para desenvolvedores e agentes. Todo SDC possui `*.stories.twig` que inclui o componente real; a saída JSON é gerada localmente por Drush e não é versionada.

O módulo Drupal Storybook é dependência de desenvolvimento (`require-dev`) e suas stories só devem ser renderizadas para administradores. `storybook.development` permanece `false`; o endpoint não é aberto a anônimos. Consulte o README do Storybook para credenciais/cookies, CORS local restrito e comandos de compilação.

Gate obrigatório: `php scripts/validate-aculta420-storybook.php`.

## Documentação

Comece por [docs/README.md](docs/README.md).

Documentos normativos:

- [Arquitetura](docs/architecture.md)
- [Design system](docs/design-system.md)
- [Shell multidomínio](docs/shell.md)
- [Componentes](docs/components.md)
- [Desenvolvimento](docs/development.md)
- [Acessibilidade](docs/accessibility.md)
- [Decisões](docs/decisions.md)
- [Roadmap](docs/roadmap.md)
- [Versionamento](docs/versioning.md)
- [Branding Wiki420](docs/branding-wiki420.md)
- [Branding Podplant420](docs/branding-podplant420.md)
- [Branding Baque Sativa](docs/branding-baque-sativa.md)
- [Branding Bloco Sativa 420](docs/branding-bloco-sativa420.md)
- [CHANGELOG](CHANGELOG.md)

## Regra principal

> O tema não vira fonte de verdade de autenticação, Commerce, LMS, Domain,
> Wiki, fórum, pagamentos ou persistência.

SDCs recebem contratos de apresentação. Não consultam storage, entidades ou
serviços de domínio.

## Histórico

ACULTA420 0.1.0 nasce da base técnica do antigo tema `aculta`. O histórico de
refatoração foi consolidado no CHANGELOG e nas decisões; a documentação corrente
descreve apenas o estado suportado da versão atual.
