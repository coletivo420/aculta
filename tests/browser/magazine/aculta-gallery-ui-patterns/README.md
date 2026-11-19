# Galeria editorial — UI Patterns Views

Compara o álbum público de 63 fotos antes/depois da migração de `views_vvjs` para o SDC `layout-grid`. Mantém a ordenação e os render arrays PhotoSwipe com as imagens, que continuam lazy-loaded e acessíveis pelas regras de acesso do módulo.

```sh
DISPLAY=:0 PHASE=before ACULTA_BASE="${ACULTA_BASE:?origem Revista de desenvolvimento}" GALLERY_PATH="${GALLERY_PATH:?alias de álbum publicado}" SUITE_OUT="${SUITE_OUT:?diretório externo}" python3 tests/browser/magazine/aculta-gallery-ui-patterns/visual.py
DISPLAY=:0 PHASE=after ACULTA_BASE="${ACULTA_BASE:?origem Revista de desenvolvimento}" GALLERY_PATH="${GALLERY_PATH:?alias de álbum publicado}" SUITE_OUT="${SUITE_OUT:?diretório externo}" python3 tests/browser/magazine/aculta-gallery-ui-patterns/visual.py
```

Firefox ESR visível via WebDriver BiDi. Valida 63 links/fotos PhotoSwipe, rótulos alternativos, um H1/main, foco, ausência de overflow em 320/390/768/1024/1440 px e claro/escuro. Depois do corte também abre e fecha o lightbox pelo teclado. Capturas PNG ficam fora do Git; o manifesto versionado contém hashes, rollback e SHA testado.
