# Contraste do rodapé e breadcrumb do Fórum — achado de 2026-11-12

Origem: execução de `playwright.a11y.spec.mjs` durante a homologação da
auditoria de CSS do `aculta420_v2`.

## Achados

| Regra | Impacto | Alvo | Cores | Razão | Mínimo | Hosts |
| --- | --- | --- | --- | ---: | ---: | --- |
| `color-contrast` | serious | `.site-footer` e link do rodapé | texto `#0c3c29` sobre fundo `#689427` | 3,45:1 | 4,5:1 | main, growroom420, apoio, wiki420 |
| `color-contrast` | serious | `body` e `.aculta-breadcrumb__current > span` | texto `#689427` sobre fundo `#ffffff` | — | 4,5:1 | growroom420 |

## Classificação

**Pré-existente.** `components/site-footer/site-footer.css`,
`components/breadcrumb/breadcrumb.css`, `src/styles/tokens.css` e
`src/styles/base.css` não foram alterados entre `e37f3d8` e a entrega
(`git diff --stat` vazio para esses caminhos). O CSS removido nesta
refatoração não incluía os seletores desses componentes.

## Evidência

- `SUITE_OUT=/home/piradopirata/aculta-suite-out/css-audit-a11y-20261112`
- Manifesto versionado: `tests/browser/capturas/css-audit-20261112/manifest.json`.
- 16 verificações axe; 7 falhas de contraste; 4 reflow a 320 px PASS.

## Resolução (autorizada pelo responsável em 2026-11-12)

Correção em frente própria de contraste/UI Skins, sem alterar tokens de marca:

| Arquivo | Mudança |
| --- | --- |
| `components/site-footer/site-footer.css` | links do rodapé passam a declarar `:is(:link, :visited)`, elevando a especificidade sobre `.page a` e aplicando `--color-footer-text` (#171513 → 5,08:1) |
| `components/breadcrumb/breadcrumb.css` | nível atual usa `--color-text-primary` e o link usa `--color-link-editorial`; o verde da marca fica na faixa tricolor decorativa |
| `components/revista-teaser/revista-teaser.css` | categoria deixa de usar `--color-brand-secondary` como texto e adota `--color-text-secondary` |

Commit `e3a7f7140`. Depois de `drush cr` no Runtime de desenvolvimento, a
frente `tests/browser/transversal/aculta-contraste/axe-nodes.mjs` mediu
**22/22 PASS** (onze URLs × claro/escuro), zero violações. Manifesto:
`tests/browser/capturas/contraste-20261112/manifest.json`.

## Classificação original

Não é regressão da refatoração de CSS: os arquivos envolvidos não foram
alterados entre `e37f3d8` e a entrega.
