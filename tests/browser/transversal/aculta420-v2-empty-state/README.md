# ACULTA420 V2 — Empty state

`visual.py` valida o SDC `empty-state` e seus tokens reais no Firefox ESR
visível. A matriz cobre 320, 390, 768, 959, 960 e 1440 CSS px em claro/escuro,
mensagem, ação fornecida pelo consumer, variante compacta, foco por Tab e zoom
real de 200%. As mensagens e a URL são sintéticas; o teste não consulta o
Runtime nem altera dados Drupal.

Configure `SUITE_OUT` para diretório persistente fora do repositório. PNGs e
`results.json` são salvos nesse diretório.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-empty-state" \
  python3 tests/browser/transversal/aculta420-v2-empty-state/visual.py
```

## Regressão visual (Playwright)

`routes.json` declara a rota pública `/` desta frente. A suíte
`tests/browser/playwright.visual.spec.mjs` compara essa rota com as referências
em `tests/browser/__snapshots__/playwright.visual.spec.mjs/aculta420-v2-empty-state-*.png`:
claro e escuro, em 320, 390, 768, 1024 e 1440 CSS px, Firefox headless.

```sh
cd tests/browser
SUITE_OUT="$SUITE_OUT" ACULTA_SITE_ORIGIN="https://aculta.toca.net.br/" \
ACULTA_BROWSER_FRONTS=aculta420-v2-empty-state \
npx playwright test --config playwright.visual.config.mjs
```

Estado em 2026-11-12: referências geradas do servidor de desenvolvimento e
aprovadas após revisão humana. A verificação de estabilidade (segunda execução
contra o mesmo servidor) ainda não foi feita para esta frente. Não usar
`--update-snapshots`; novas candidatas exigem `ACULTA_VISUAL_BASELINE=1` com
`SUITE_OUT` novo e revisão humana antes de copiar.
