# ACULTA420 V2 — Data table

`visual.py` valida o SDC `data-table` em Firefox ESR visível, usando as folhas
de tokens e CSS e o JavaScript do próprio tema. A matriz cobre 320, 390, 768,
959, 960 e 1440 CSS px em claro/escuro; verifica cartões móveis, rolagem
horizontal local nomeada e focável, ausência de overflow da página e o fallback
sem JavaScript em 320 px. As tabelas são fixtures sintéticas, não dados do Studio.

Configure `SUITE_OUT` para diretório persistente fora do repositório. Capturas,
fixture e `results.json` são gravados nesse diretório.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-data-table" \
  python3 tests/browser/transversal/aculta420-v2-data-table/visual.py
```

O script usa somente um servidor HTTP local efêmero e não acessa nem altera o
Runtime Drupal. Zoom real e teste de teclado devem ser registrados como
evidências complementares da entrega.
