# Firefox checks for ACULTA Studio Culturas organization layouts

`organization-forms.py` renders a local fixture with the Bootstrap CSS actually
used by the theme plus the theme's current CSS, and verifies the mobile-first organization search, location inputs, selection
cards, co-organizer grid, admin filters, related-content cards, internal table
scrolling, focus and light/dark surfaces. It does not access Runtime, submit
forms, or prove module ACL, cache, AJAX, or no-JS behavior.

Run from the repository root with Firefox ESR visible on X11:

```sh
export DISPLAY=:0
export SUITE_OUT=/home/piradopirata/aculta-suite-out/aculta-events-organization-layout
export ACULTA_FF_PROFILE_ROOT="$SUITE_OUT/profiles"
DISPLAY=:0 python3 -I tests/browser/aculta-events/organization-forms.py
```

PNG and JSON evidence are written only to `$SUITE_OUT`. The test reuses the
project's `wdlib.py`; it uses WebDriver BiDi viewport sizing. This fixture
reads the local Bootstrap stylesheet from
`web/libraries/bootstrap/dist/css/bootstrap.min.css`; it is not copied or
fetched from a CDN. It checks candidate theme CSS, not Drupal's rendered markup
or the separate Firefox/Runtime gate for THEME-MF3.
