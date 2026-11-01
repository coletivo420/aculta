# Decisões arquiteturais ACULTA420

## D-012 — Mobile First

O tema evolui em fases para uma base mobile-first compartilhada, com Bootstrap,
SDC, tokens e fronteiras existentes. O ACULTA Studio é o primeiro consumidor
piloto. A decisão global, critérios e preferência do seletor nativo de área
estão em [`ADR-012`](../../../../../docs/decisions/ADR-012-aculta420-mobile-first.md);
evidências iniciais em [`mobile-first-audit.md`](mobile-first-audit.md).

## D-001 — novo provider em 0.1.0

O antigo tema `aculta` é base histórica. A fundação atual usa:

- nome: **ACULTA420**;
- machine name: `aculta420`;
- diretório: `web/themes/custom/aculta420`.

Não manter alias, shim ou provider runtime de compatibilidade. A Foundation 0.1.0 suporta apenas `aculta420` como provider do tema.

## D-002 — prefixo visual ACULTA permanece

Classes `.aculta-*` e custom properties `--aculta-*` representam o design
language/brand e não o machine name do Drupal.

Renomeá-las para `aculta420-*` adicionaria churn e acoplamento sem benefício.

## D-003 — Bootstrap é infraestrutura

Bootstrap5 fornece grid, utilities e behaviors. ACULTA420 fornece identidade,
contratos e composição.

Não instalar segundo Bootstrap nem reimplementar componentes só para declarar
ownership.

## D-004 — SDC é seletivo

Nem todo primitive vira SDC.

Exemplo: button continua primitive CSS/Bootstrap porque Form API/contrib geram
markup que deve receber o mesmo design sem migração manual.

## D-005 — schemas são obrigatórios

`enforce_prop_schemas: true` faz parte da fundação 0.1.0.

Novo SDC sem contrato de schema é regressão.

## D-006 — config IDs históricos podem permanecer

Block placement IDs e outros config IDs `aculta_*` não são renomeados apenas
por estética quando isso amplia o risco de config import.

O campo/dependency `theme` deve apontar para `aculta420`.

## D-007 — VVJB continua engine de carousel

O design system pode criar um pattern visual comum, mas VVJB não será removido
enquanto cumprir bem seu papel.

## D-008 — ecossistema entra por capacidade

Planejamento atual:

- UI Icons: sistema de ícones;
- UI Patterns 2: exposição seletiva de nossos SDCs;
- UI Examples/UI Patterns Library: catálogo;
- Canvas: autoria visual pós-estabilização;
- Display Builder: pesquisa enquanto beta.

UI Suite Bootstrap e Canvas Bootstrap são referência, não design system
concorrente.

## D-009 — sem build tooling por padrão

CSS nativo + JS nativo + Drupal libraries + behaviors + once().

Node/Vite/Sass/PostCSS/Storybook exigem ganho demonstrável.

## D-010 — versionamento é do tema

ACULTA420 usa SemVer próprio. Tags são namespaced:

`aculta420-theme-v0.1.0`.

O versionamento do tema não substitui o versionamento do site ou do Portal.

## D-011 — sem camada de compatibilidade de provider

ACULTA420 0.1.0 não mantém provider alternativo, alias ou shim de tema.

O estado suportado contém apenas `web/themes/custom/aculta420/` como provider
custom público. Migrações de ambientes históricos são responsabilidade
operacional anterior ao deploy do estado final da Foundation e não justificam
código legado no runtime atual.

## D-012 — shell institucional global e identidade visual por Domain purpose

ACULTA420 possui um shell público em duas camadas. A camada institucional
representa a Associação Cultural Antiproibicionista e permanece comum e
visualmente discreta em todos os domains. A camada de domínio representa a
identidade primária do purpose corrente e pode possuir logo, título e navegação
específicos.

A identidade do domínio é preparada pelo `aculta_portal` a partir de Domain
purpose; ACULTA420 não resolve hostnames nem Domain access.

Na ausência de branding específico, deve ser utilizado o branding ACULTA como
fallback e, se necessário, título textual.

Todos os purposes compartilham a mesma arquitetura de shell. O que varia são os
dados de apresentação, não um header paralelo por subdomínio.

Na Foundation 0.1.0 esta decisão é arquitetural/documental. O redesign visual
completo fica fora do escopo e entra nas fases posteriores de foundations/shell.
