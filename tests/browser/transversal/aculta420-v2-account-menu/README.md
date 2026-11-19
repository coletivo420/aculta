# ACULTA420 V2 — account menu

`account-menu-visual.py` verifica em Firefox ESR visível o gatilho hambúrguer
e o painel do menu de conta do ACULTA420 V2.

- `PHASE=before` registra o estado atual sem julgamento.
- `PHASE=after` confere gatilho, Escape, fechamento pelo X, foco preso por Tab,
  Enter nos links, colunas, ausência de rolagem horizontal e contraste de texto.

Variáveis: `DISPLAY=:0`, `SUITE_OUT` (saída externa ao repositório), `PHASE`,
`ACULTA_BASE_URL`, `ACULTA_TEST_UID` e `ACULTA_PROJECT_ROOT` (checkout com
Drush). Nenhum dado é gravado.

```sh
DISPLAY=:0 SUITE_OUT="$SUITE_OUT/aculta420-v2-account-menu" PHASE=after \
  python3 tests/browser/transversal/aculta420-v2-account-menu/account-menu-visual.py
```
