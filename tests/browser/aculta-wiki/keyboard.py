#!/usr/bin/env python3
"""Read-only keyboard smoke test for public ACULTA Wiki pages."""

import base64
import json
import os
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
PROJECT = HERE.parents[2]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

SEARCH_PREVIEW_JS = (PROJECT / "web/modules/custom/aculta_portal/js/search-preview.js").read_text(encoding="utf-8")
BASE = os.environ.get("WIKI_BASE_URL", "https://wiki420.aculta.toca.net.br").rstrip("/")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-wiki-keyboard"))
OUT.mkdir(parents=True, exist_ok=True)
SIZES = [(1280, "desktop"), (320, "mobile-320")]
results = []


def check(name, ok, detail=""):
    results.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f" — {detail}" if detail else ""))


driver = Driver(bidi=True)
session = f"/session/{driver.session}"
bidi = connect(driver.websocket_url)
bidi_id = 0
context = None


def bidi_command(method, params=None):
    global bidi_id
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") != command_id:
            continue
        if "error" in response:
            raise RuntimeError(f"BiDi {method}: {response['error']}: {response.get('message', '')}")
        return response.get("result", {})


def press_key(key):
    driver.req("POST", session + "/actions", {
        "actions": [{
            "type": "key",
            "id": "wiki-keyboard",
            "actions": [
                {"type": "keyDown", "value": key},
                {"type": "keyUp", "value": key},
            ],
        }],
    })


def press_tab():
    press_key("\ue004")


def computed_accessible_name(selector):
    element = driver.find(selector)["element-6066-11e4-a52e-4f735466cecf"]
    return driver.req("GET", f"{session}/element/{element}/computedlabel")


def computed_accessible_role(selector):
    element = driver.find(selector)["element-6066-11e4-a52e-4f735466cecf"]
    return driver.req("GET", f"{session}/element/{element}/computedrole")


def focus_state():
    return driver.js("""return (() => {
      const el = document.activeElement;
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      const style = getComputedStyle(el);
      const labels = [...(el.labels || [])].map((label) => label.innerText.trim()).filter(Boolean);
      const labelledBy = (el.getAttribute('aria-labelledby') || '').split(/\\s+/).filter(Boolean)
        .map((id) => document.getElementById(id)?.innerText.trim() || '').filter(Boolean);
      const normal = el.cloneNode(false);
      normal.removeAttribute('id');
      normal.setAttribute('aria-hidden', 'true');
      normal.style.setProperty('position', 'fixed', 'important');
      normal.style.setProperty('left', '-10000px', 'important');
      normal.style.setProperty('top', '0', 'important');
      normal.style.setProperty('visibility', 'hidden', 'important');
      document.body.appendChild(normal);
      const normalStyle = getComputedStyle(normal);
      const outlineVisible = style.outlineStyle !== 'none'
        && parseFloat(style.outlineWidth) > 0
        && (style.outlineStyle !== normalStyle.outlineStyle
          || style.outlineWidth !== normalStyle.outlineWidth
          || style.outlineColor !== normalStyle.outlineColor
          || style.outlineOffset !== normalStyle.outlineOffset);
      const shadowVisible = style.boxShadow !== 'none' && style.boxShadow !== normalStyle.boxShadow;
      normal.remove();
      return {
        tag: el.tagName,
        type: el.getAttribute('type') || '',
        name: el.getAttribute('name') || '',
        text: (el.innerText || el.value || '').trim().slice(0, 100),
        accessibleName: el.getAttribute('aria-label') || labelledBy.join(' ') || labels.join(' ') || el.getAttribute('title') || el.getAttribute('placeholder') || ((el.tagName === 'INPUT' && ['button', 'submit', 'reset'].includes(el.type)) ? el.value : '') || '',
        focusVisible: el.matches(':focus-visible'),
        indicator: outlineVisible || shadowVisible,
        rect: {left: Math.round(rect.left), top: Math.round(rect.top), right: Math.round(rect.right), bottom: Math.round(rect.bottom)},
        viewport: {width: window.innerWidth, height: window.innerHeight},
        visible: rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden',
        withinViewport: rect.left >= 0 && rect.right <= window.innerWidth,
      };
    })();""")


def load_candidate_fixture(context, width):
    """Runs the local Portal behavior against a synthetic search preview."""
    driver.get("about:blank")
    fixture = """<!doctype html><html lang="pt-BR"><head><title>Prévia de busca</title></head><body>
      <main><form role="search">
        <label for="wiki-search" class="visually-hidden">Pesquisar na Wiki420</label>
        <input id="wiki-search" class="aculta-search-preview-input" type="search" name="q"
          data-preview-url="/preview" data-preview-target="aculta-wiki-search-preview" data-min-chars="3">
        <section id="aculta-wiki-search-preview" role="region" aria-label="Prévia dos resultados da busca" aria-live="polite" hidden></section>
        <button type="submit">Buscar</button>
      </form></main>
    </body></html>"""
    preview_html = """<ul><li><a href="/verbete/um">Verbete um</a></li>
      <li><a href="/verbete/dois">Verbete dois</a></li></ul>"""
    driver.js("document.open(); document.write(arguments[0]); document.close();", [fixture])
    driver.js("""const previewHtml = arguments[0];
      window.Drupal = {behaviors: {}, t: (text) => text};
      window.once = (id, selector, context) => Array.from((context || document).querySelectorAll(selector));
      window.fetch = () => Promise.resolve({ok: true, text: () => Promise.resolve(previewHtml)});""", [preview_html])
    driver.js("""const script = document.createElement('script');
      script.textContent = arguments[0];
      document.head.appendChild(script);
      Drupal.behaviors.acultaSearchPreview.attach(document);""", [SEARCH_PREVIEW_JS])
    bidi_command("browsingContext.setViewport", {
        "context": context,
        "viewport": {"width": width, "height": 920},
    })

    role = computed_accessible_role("#wiki-search")
    check(f"candidate-{width}: mantém role nativo searchbox", role == "searchbox", role)
    driver.type("#wiki-search", "maconha")
    preview = None
    for _ in range(20):
        preview = driver.js("""const input = document.querySelector('#wiki-search');
          const box = document.querySelector('#aculta-wiki-search-preview');
          return {focused: document.activeElement === input, hidden: box.hidden, links: box.querySelectorAll('a').length};""")
        if preview["links"]:
            break
        time.sleep(0.1)
    check(f"candidate-{width}: preview atualiza sem mover foco", preview["focused"] and not preview["hidden"] and preview["links"] == 2, preview)
    check(
        f"candidate-{width}: região de prévia tem nome e live polite",
        computed_accessible_role("#aculta-wiki-search-preview") == "region"
        and computed_accessible_name("#aculta-wiki-search-preview") == "Prévia dos resultados da busca"
        and driver.js("return document.querySelector('#aculta-wiki-search-preview').getAttribute('aria-live')") == "polite",
        {
            "role": computed_accessible_role("#aculta-wiki-search-preview"),
            "name": computed_accessible_name("#aculta-wiki-search-preview"),
        },
    )

    press_key("\ue015")
    check(
        f"candidate-{width}: seta para baixo não rouba foco do searchbox",
        driver.js("return document.activeElement.id") == "wiki-search",
        driver.js("return document.activeElement.tagName + '#' + document.activeElement.id"),
    )
    press_tab()
    first = focus_state()
    if first and first["tag"] == "A":
        first["accessibleName"] = computed_accessible_name("#aculta-wiki-search-preview a[href]")
    check(f"candidate-{width}: Tab alcança primeiro link", first is not None and first["tag"] == "A" and first["accessibleName"] == "Verbete um" and first["focusVisible"] and first["indicator"], first)
    press_tab()
    second = focus_state()
    check(f"candidate-{width}: Tab alcança próximo link", second is not None and second["tag"] == "A" and second["text"] == "Verbete dois", second)
    press_tab()
    submit = focus_state()
    if submit is not None:
        submit["accessibleName"] = computed_accessible_name("button[type='submit']")
    check(f"candidate-{width}: Tab segue dos links ao submit sem enviá-lo", submit is not None and submit["tag"] == "BUTTON" and submit["accessibleName"] == "Buscar", submit)


try:
    context = bidi_command("browsingContext.getTree")["contexts"][0]["context"]
    summary = {"base": BASE, "tested_url": BASE + "/wiki/busca", "candidate_sizes": [], "sizes": {}}
    for width, name in SIZES:
        load_candidate_fixture(context, width)
        summary["candidate_sizes"].append(width)
    live_sizes = [] if os.environ.get("WIKI_CANDIDATE_ONLY") == "1" else SIZES
    for width, name in live_sizes:
        driver.get(BASE + "/wiki/busca")
        time.sleep(1)
        if os.environ.get("WIKI_CSS_OVERRIDE") == "1":
            loaded = driver.req("POST", session + "/execute/async", {
                "script": "const done = arguments[arguments.length - 1]; const link = document.createElement('link'); link.rel = 'stylesheet'; link.href = '/themes/custom/aculta420/css/components/wiki.css?keyboard=' + Date.now(); link.onload = () => done(true); link.onerror = () => done(false); document.head.appendChild(link);",
                "args": [],
            })
            check(f"{name}: CSS candidato carregado", loaded is True)
        bidi_command("browsingContext.setViewport", {
            "context": context,
            "viewport": {"width": width, "height": 920},
        })
        time.sleep(0.3)
        check(f"{name}: largura efetiva", driver.js("return window.innerWidth") == width)

        stops = []
        search = None
        submit = None
        for step in range(1, 45):
            press_tab()
            state = focus_state()
            if not state or state["tag"] == "BODY":
                check(f"{name}: sequência de foco não se perde", False, f"tab={step} state={state}")
                break
            stops.append(state)
            check(
                f"{name}: foco visível no tab {step}",
                state["visible"] and state["focusVisible"] and state["indicator"],
                f"{state['tag']} {state['accessibleName'][:70]}",
            )
            if state["tag"] == "INPUT" and (state["name"] == "q" or state["type"] == "search"):
                search = state
                break

        if search is not None:
            search["accessibleName"] = computed_accessible_name('input[name="q"], input[type="search"]')
        check(
            f"{name}: campo de busca alcançável por Tab",
            search is not None and search["accessibleName"] == "Pesquisar na Wiki420" and search["withinViewport"],
            search or "não encontrado em 44 tabs",
        )
        if search is not None:
            driver.type('input[name="q"], input[type="search"]', "maconha")
            preview = None
            for _ in range(30):
                preview = driver.js("""return (() => {
                  const input = document.querySelector('input[name="q"], input[type="search"]');
                  const box = document.getElementById(input?.dataset.previewTarget || 'aculta-wiki-search-preview');
                  if (!input || !box) return null;
                  return {
                    focusPreserved: document.activeElement === input,
                    inputRole: input.getAttribute('role'),
                    autocomplete: input.getAttribute('aria-autocomplete'),
                    expanded: input.hasAttribute('aria-expanded'),
                    controls: input.getAttribute('aria-controls'),
                    boxId: box.id,
                    boxRole: box.getAttribute('role'),
                    boxLive: box.getAttribute('aria-live'),
                    hidden: box.hidden,
                    links: box.querySelectorAll('a[href]').length,
                  };
                })();""")
                if preview and preview["links"]:
                    break
                time.sleep(0.2)

            input_role = computed_accessible_role('input[name="q"], input[type="search"]')
            preview_name = computed_accessible_name("#aculta-wiki-search-preview")
            check(
                f"{name}: campo mantém semântica nativa de busca",
                preview is not None and input_role == "searchbox" and preview["inputRole"] is None and preview["autocomplete"] is None and not preview["expanded"],
                {"computed_role": input_role, **(preview or {})},
            )
            check(
                f"{name}: prévia é região viva nomeada",
                preview is not None and preview["boxRole"] == "region" and preview_name == "Prévia dos resultados da busca" and preview["boxLive"] == "polite" and preview["controls"] == preview["boxId"],
                {"computed_role": computed_accessible_role("#aculta-wiki-search-preview"), "computed_name": preview_name, **(preview or {})},
            )
            check(
                f"{name}: resultados carregam sem roubar foco",
                preview is not None and preview["focusPreserved"] and not preview["hidden"] and preview["links"] > 0,
                preview or "campo ou região de prévia ausente",
            )

            # Search suggestions are ordinary links. Tab reaches each result,
            # then continues to the form submit without triggering navigation.
            press_tab()
            first_stop = focus_state()
            if first_stop is not None and first_stop["tag"] != "A":
                first_stop["accessibleName"] = computed_accessible_name("#aculta-wiki-search-preview")
                check(
                    f"{name}: região rolável tem foco nomeado",
                    first_stop["tag"] == "DIV" and computed_accessible_role("#aculta-wiki-search-preview") == "region" and first_stop["accessibleName"] == "Prévia dos resultados da busca" and first_stop["focusVisible"] and first_stop["indicator"],
                    first_stop,
                )
                press_tab()
            first_link = focus_state()
            if first_link is not None and first_link["tag"] == "A":
                first_link["accessibleName"] = computed_accessible_name("#aculta-wiki-search-preview a[href]")
            first_link_inside = driver.js("return document.getElementById('aculta-wiki-search-preview').contains(document.activeElement)")
            check(
                f"{name}: Tab alcança o primeiro link da prévia",
                first_link is not None and first_link["tag"] == "A" and first_link["accessibleName"] and first_link_inside and first_link["visible"] and first_link["focusVisible"] and first_link["indicator"],
                first_link or "nenhum link recebeu foco",
            )
            for link_index in range(max(0, (preview or {}).get("links", 0) - 1)):
                press_tab()
                result_link = focus_state()
                check(
                    f"{name}: Tab alcança link {link_index + 2} da prévia",
                    result_link is not None and result_link["tag"] == "A" and result_link["visible"] and result_link["focusVisible"] and result_link["indicator"] and driver.js("return document.getElementById('aculta-wiki-search-preview').contains(document.activeElement)"),
                    result_link or "link da prévia não recebeu foco",
                )
            press_tab()
            submit = focus_state()
            if submit is not None:
                submit["accessibleName"] = computed_accessible_name('input[type="submit"], button[type="submit"]')
            is_submit = submit is not None and (
                submit["tag"] == "BUTTON"
                or (submit["tag"] == "INPUT" and submit["type"] == "submit")
            )
            check(
                f"{name}: submit da busca alcançável por Tab",
                is_submit and submit["accessibleName"] == "Buscar" and submit["focusVisible"] and submit["indicator"] and submit["visible"] and submit["withinViewport"],
                submit or "submit não recebeu foco",
            )
            screenshot = driver.req("GET", session + "/screenshot")
            (OUT / f"keyboard-search-submit-{name}.png").write_bytes(base64.b64decode(screenshot))
        summary["sizes"][name] = {"width": width, "focus_stops": stops, "search": search, "submit": submit}

    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
finally:
    if context:
        try:
            driver.req("DELETE", session + "/actions")
        except Exception:
            pass
    bidi.close()
    driver.close()

failed = [result for result in results if not result["ok"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
