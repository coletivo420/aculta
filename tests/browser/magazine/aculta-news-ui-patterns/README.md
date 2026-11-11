# UI Patterns Views — listagem da Revista

Compara a View `aculta_news:block_1` antes/depois do SDC `revista-listing` na rota pública `/noticias`. Usa somente GET e conteúdo já publicado no Runtime de desenvolvimento.

```sh
DISPLAY=:0 PHASE=before ACULTA_BASE="${ACULTA_BASE:?}" SUITE_OUT="${SUITE_OUT:?}" python3 tests/browser/magazine/aculta-news-ui-patterns/visual.py
DISPLAY=:0 PHASE=after ACULTA_BASE="${ACULTA_BASE:?}" SUITE_OUT="${SUITE_OUT:?}" python3 tests/browser/magazine/aculta-news-ui-patterns/visual.py
```

Firefox ESR visível via WebDriver BiDi. A suíte valida quatro nodes teaser, uma H1 visível e um `main`, foco por teclado, contraste, overflow e reflow em 320/390/768/1024/1440 px claro/escuro. Capturas são guardadas em `$SUITE_OUT`; o manifesto da comparação fica em `tests/browser/capturas/ui-patterns-revista-news/2026-11-11/manifest.json`.
