# Home editorial — UI Patterns Views

Compara `home_editorial_highlights:block_1` antes/depois do SDC `layout-grid` na home MAIN de desenvolvimento. A View mantém os três destaques publicados, sua ordenação e o Layout Builder de cada conteúdo; a apresentação em carrossel é substituída pela grade responsiva.

```sh
DISPLAY=:0 PHASE=before ACULTA_BASE="${ACULTA_BASE:?origem MAIN de desenvolvimento}" SUITE_OUT="${SUITE_OUT:?diretório externo}" python3 tests/browser/transversal/aculta-home-editorial-ui-patterns/visual.py
DISPLAY=:0 PHASE=after ACULTA_BASE="${ACULTA_BASE:?origem MAIN de desenvolvimento}" SUITE_OUT="${SUITE_OUT:?diretório externo}" python3 tests/browser/transversal/aculta-home-editorial-ui-patterns/visual.py
```

Firefox ESR visível via WebDriver BiDi. Valida os nodes 14, 15 e 16, uma H1 e um `main`, foco visível, contraste mínimo 4,5:1, ausência de overflow em 320/390/768/1024/1440 px e os modos claro/escuro. Capturas finais em `tests/browser/capturas/ui-patterns-home-editorial/2026-11-11/`; PNGs ficam fora do Git e o manifesto versionado registra o hash testado e o rollback.
