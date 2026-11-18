# Baseline laboratorial de performance do ACULTA420 V2

Runner exploratório para medir recursos, LCP e CLS em páginas públicas do
Runtime de desenvolvimento. Não calcula CrUX/RUM nem trata TBT como INP.

## Execução

```bash
SUITE_OUT=/diretorio/fora/do/repositorio \
ACULTA_SITE_ORIGIN=https://aculta.toca.net.br/ \
FORUM_BASE=https://growroom420.aculta.toca.net.br/ \
SHOP_HOST=https://loja.aculta.toca.net.br/ \
node tests/browser/transversal/aculta420-v2-performance/measure.mjs
```

O runner usa Chromium pelo Playwright e exige as três origens de `routes.json`.
`SUITE_OUT` precisa ser absoluto e externo ao repositório; o arquivo
`baseline.json` contém somente identificadores de rota, status e métricas, sem
HTML, capturas, cookies ou conteúdo de conta. Faltas de ambiente retornam 2;
falhas de navegação ou status diferente de 200 retornam 1.

## Matriz

- MAIN `/`, Fórum `/` e catálogo SHOP `/produtos`;
- 390 e 1440 CSS px;
- temas claro e escuro, com `data-theme="dark"` injetado antes da navegação;
- três repetições independentes por combinação, cada uma com uma visita sem
  cache prévio e uma navegação de cache quente na mesma sessão do browser.

O navegador roda sem throttling de CPU/rede. LCP e CLS são métricas de
laboratório da página carregada; INP fica nulo porque o runner não simula uma
interação real. Fontes em origem cruzada podem não expor `transferSize` ou
`encodedBodySize`; compare esses campos com cautela. Esta amostra não permite
aprovar Core Web Vitals, orçamentos de produção ou self-host de fontes.

## Evidência inicial

Resultados coletados em 2026-11-17 e limites conhecidos ficam em
[`docs/operations/testing/ADR-057-F4-2026-11-17.md`](../../../../docs/operations/testing/ADR-057-F4-2026-11-17.md).
