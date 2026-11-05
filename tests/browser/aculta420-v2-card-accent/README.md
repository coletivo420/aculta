# ACULTA420 V2 — faixa de acento do cartão (DRAFT)

`visual.py` verifica em Firefox ESR visível os três valores neutros de acento
no SDC `card-cultural`, usando tokens e CSS reais. A matriz cobre 320, 390,
768, 959, 960 e 1440 CSS px em claro/escuro, sem overflow; também verifica foco
por Tab e zoom real de 200%. As fixtures são somente visuais: não mapeiam
domínio ou tipo e não representam integração Portal. O consumer permanece
bloqueado pelo contrato D1.

Configure `SUITE_OUT` fora do repositório. PNGs e `results.json` são gravados
nesse diretório.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-card-accent" \
  python3 tests/browser/aculta420-v2-card-accent/visual.py
```
