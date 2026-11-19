# UI Styles Block — bloco de Documentos institucionais

Teste visual anônimo do bloco `aculta420_v2_aculta_documents` na rota pública
`/transparencia`. Verifica classes produzidas pelo UI Styles Block, fundo claro e
escuro, espaçamento responsivo, quantidade de linhas, landmarks, foco por teclado
e overflow. Execute com Firefox ESR visível e `DISPLAY=:0`:

```sh
SUITE_OUT=/var/tmp/ui-styles-block-2026-11-11 python3 tests/browser/transversal/ui-styles-block/visual.py
```

As capturas geradas pelo runner ficam fora do Git. O manifesto versionado fica
em `tests/browser/capturas/ui-styles-block/<data>/manifest.json`.
