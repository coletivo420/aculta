# ACULTA Maps — visual smoke

`visual.py` captures the public `/mapa` page in Firefox ESR at 1280×1000 and
320×900, then captures a 1280px dark-theme state. WebDriver BiDi sets the CSS
viewport so the 320px capture is a real mobile-width viewport. It records map dimensions,
horizontal overflow, marker count, the privacy link, route-link attributes, and
theme state in a JSON artifact.

## Environment and execution

- Test server: `https://eventos.aculta.toca.net.br/mapa`, served from this checkout.
- Firefox ESR visible on X11 (`DISPLAY=:0`) via geckodriver; no headless mode.
- Reuses `tests/browser/aculta-cpf-checkout/wdlib.py`.
- `MAP_URL` can override the test route. `SUITE_OUT` points to the external
  artifact directory; default is `/tmp/aculta-maps-mp7`.
- The page loads OpenStreetMap tiles, so this smoke makes ordinary read-only
  tile requests to the test-selected provider. It performs no Drupal writes.

```sh
DISPLAY=:0 SUITE_OUT=/home/piradopirata/aculta-suite-out/mp7-maps \
  python3 tests/browser/aculta-maps/visual.py
```

## Limitations

The dark screenshot sets the theme attribute for visual comparison; it does not
change the saved site theme. Marker coverage depends on the test catalogue.
