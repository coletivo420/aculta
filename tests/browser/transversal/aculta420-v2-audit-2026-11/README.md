# Homologação visual do aculta420_v2 — auditoria 2026-11-11

Frente transversal criada para a auditoria de CSS e layout do tema
`web/themes/custom/aculta420_v2`. Mede os domínios públicos nos dois modos de
cor e nas larguras pedidas pelo plano.

## Como rodar

```bash
SUITE_OUT=/caminho/externo \
ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ \
node tests/browser/transversal/aculta420-v2-audit-2026-11/audit.mjs
```

- `SUITE_OUT` é obrigatório, absoluto e fora do repositório; guarda capturas
  PNG e `audit-summary.json`.
- O manifest versionado fica em
  `tests/browser/capturas/aculta420-v2-audit/2026-11-11/manifest.json`.
- Motor: Firefox do Playwright, headless (método 1 de
  `docs/operations/NAVEGADOR-TESTES.md`).

## Cobertura

- Domínios: home, Fórum (`growroom420.aculta.*`), Links, Wiki, Revista,
  Eventos, Loja, Apoio, Cursos e login da Conta.
- Larguras: 320, 390, 768, 959, 960, 1024 e 1440 CSS px.
- Modos: claro (como o servidor entrega) e escuro (forçado com
  `data-theme="dark"` no `<html>`; o CSS é o mesmo que a preferência da pessoa
  usuária aciona no servidor).

Cada combinação verifica:

1. canvas esperado (`#fff` no claro e `#171513` no escuro, conforme os tokens V2);
2. ausência de overflow horizontal (`scrollWidth <= innerWidth + 1`);
3. exatamente um `main` e no máximo um `h1`;
4. contraste do cabeçalho institucional (mínimo 4,5:1);
5. `aside` visualmente à esquerda do conteúdo a partir de 960 px.

## Limitações conhecidas

- A UI Patterns Library exige autenticação e não é coberta por este laço.
- O modo escuro é forçado por `data-theme`; a preferência salva no servidor é
  coberta pelos testes do Portal, não por esta varredura.
- Contraste de todos os pares de tokens é coberto pelo gate
  `scripts/validate-aculta420-v2-foundations.php`; aqui só o cabeçalho
  institucional é medido no navegador.

## Baseline de performance (P07)

`performance.mjs` mede, sem autenticação, as origens declaradas em
`ACULTA_PERF_HOSTS` como pares `site_key=https://host` separados por `;`.
Use os dez hosts de desenvolvimento de `docs/architecture/multidomain.md`.
Larguras e modos podem ser limitados para uma amostra rápida com
`ACULTA_PERF_WIDTHS` e `ACULTA_PERF_MODES`; o padrão é 390/1440 px e claro/
escuro. Cada combinação tem uma navegação fria em contexto novo e uma recarga
quente no mesmo contexto.

```sh
SUITE_OUT="${SUITE_OUT:?diretório absoluto fora do Git}" \
ACULTA_PERF_HOSTS="${ACULTA_PERF_HOSTS:?declare site_key=https://host pairs from multidomain.md}" \
ACULTA_BROWSER_ENGINE=firefox ACULTA_BROWSER_ENV=desenvolvimento \
node tests/browser/transversal/aculta420-v2-audit-2026-11/performance.mjs
```

O JSON `performance-summary.json` registra status, TTFB/DOM Ready/load, paint,
LCP, CLS, long tasks, requests e tamanhos informados por Resource Timing,
agrupados por CSS/JS/fontes/imagens e outros recursos. Em viewport móvel o
runner tenta abrir o menu e registra a duração Event Timing como proxy de uma
interação de laboratório. Esse valor não é INP; INP de campo p75, prioridade
real do LCP e bytes de recursos cross-origin sem `Timing-Allow-Origin` ficam
explicitamente fora da medição. O segundo carregamento é cache do navegador no
mesmo contexto, não uma medição do cache CDN.

Resultados e limitações das matrizes iniciais estão em
[`ADR-057-P07-2026-11-17.md`](../../../../docs/operations/testing/ADR-057-P07-2026-11-17.md).
