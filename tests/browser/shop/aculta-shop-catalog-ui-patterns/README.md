# Catálogo da Loja — UI Patterns Views

Suíte de navegador focal para `/produtos` no host de desenvolvimento da Loja.
Usa Firefox ESR headless via WebDriver e `wdlib.py` compartilhado com a suíte
de checkout. Mede grid e uma row de produto, H1/main, ausência de overflow,
link do título no SDC `product-card`, formulário/submit de adicionar ao carrinho e foco por teclado
em 320/390/768/1024/1440 CSS px, claro/escuro. `summary.json` fica em
`SUITE_OUT`; PNGs são gravados em `tests/browser/capturas/` (ignorados pelo Git)
e o manifesto versionado contém somente hashes e resultados resumidos.

```sh
SUITE_OUT="${SUITE_OUT:?diretório externo persistente}" \
ACULTA_BASE="${ACULTA_BASE:?host de desenvolvimento da Loja}" \
python3 -I tests/browser/shop/aculta-shop-catalog-ui-patterns/visual.py
```

Esta suíte não envia o formulário nem altera carrinho/conteúdo. Não valida
leitor de tela, zoom 200%, cache-hit ou aceite visual humano. O host precisa
responder ao ambiente de desenvolvimento documentado em `docs/architecture/multidomain.md`.
