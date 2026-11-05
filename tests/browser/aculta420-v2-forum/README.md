# Firefox ESR visual smoke — Fórum V2

Read-only, anonymous Runtime check for the public topic listing, category
listing and one linked topic. Requires the Homelab HTTPS root in
`FORUM_BASE_URL`, visible Firefox ESR (`DISPLAY=:0`), geckodriver, Python
websockets, and a writable external `SUITE_OUT` directory.

The script checks 320, 360, 390, 599, 600, 768, 1024, 1280 and 1440 CSS px in
both explicit color modes; document overflow, H1 count, presence and computed
box styles for topic/category components; Tab and Shift+Tab; physical Firefox
zoom at 200% and 400%. PNGs and JSON results are saved only under `SUITE_OUT`.

Run from the repository root:

```sh
FORUM_BASE_URL="$FORUM_BASE_URL" SUITE_OUT="$SUITE_OUT" DISPLAY=:0 \
  python3 -I tests/browser/aculta420-v2-forum/visual.py
```

The runner doesn't create content, authenticate, submit forms, or change the
saved color preference. Authenticated state, assistive technology, and other
purposes require separate evidence.
