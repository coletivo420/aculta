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
