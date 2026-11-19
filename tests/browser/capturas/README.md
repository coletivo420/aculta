# Capturas de teste (não são frentes)

Esta pasta guarda as **evidências** visuais das rodadas, não scripts. Os scripts ficam em `tests/browser/<purpose>/<frente>/`.

- Caminho: `tests/browser/capturas/<frente>/<AAAA-MM-DD>/`, com caminho relativo.
- Os PNG e JPG ficam fora do Git pelo `.gitignore` desta pasta.
- Cada pasta de data tem um `manifest.json` versionado, com método, motor, viewport, modo de cor, URL relativa, resultado das asserções e hash do commit testado.
- Comparações usam `before/` e `after/`.
- Regra completa: `docs/operations/NAVEGADOR-TESTES.md`.

Para encontrar um script, use o índice: `tests/browser/INDICE.md`.
