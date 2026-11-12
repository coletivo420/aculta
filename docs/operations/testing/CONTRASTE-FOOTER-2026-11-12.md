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

## Encaminhamento

Corrigir em frente própria de contraste/UI Skins, com medição antes/depois
nos cinco domínios afetados. Não alterar tokens de marca sem decisão do
responsável. Não é regressão desta refatoração.
