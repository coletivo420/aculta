#!/usr/bin/env python3
"""Reflow smoke for the B1 consumer and read-only UI Patterns access probe."""

import base64
import json
import os
import re
import subprocess
import sys
import time
import unicodedata
from pathlib import Path
from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-visual-b7"))
DISPLAY = os.environ.get("DISPLAY")
UID = os.environ.get("VISUAL_ADMIN_UID", "205")
ALBUM_URL = os.environ.get(
    "ALBUM_URL", "https://revista.aculta.toca.net.br/albuns"
)
STORY_URL = os.environ.get(
    "STORY_URL",
    "https://aculta.toca.net.br/painel-administrativo/aparencia/ui/components/aculta420",
)
ACCOUNT_URL = "https://conta.aculta.toca.net.br"


def save_screenshot(driver, filename):
    payload = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / filename).write_bytes(base64.b64decode(payload))


def capture_story_headings(driver, mode, viewport_label):
    headings = [item.strip() for item in os.environ.get("STORY_HEADINGS", "").split("|")
                if item.strip()]
    captured = []
    for heading in headings:
        exists = driver.js("""const target = [...document.querySelectorAll('h2, h3')]
          .find(element => element.innerText.trim() === arguments[0]);
          if (target) target.scrollIntoView({block: 'start'});
          return !!target;""", [heading])
        if not exists:
            print(f"BLOCK story heading not found: {heading}")
            continue
        time.sleep(0.2)
        normalized = unicodedata.normalize("NFKD", heading).encode("ascii", "ignore").decode()
        slug = re.sub(r"[^a-z0-9]+", "-", normalized.lower()).strip("-") or "story"
        filename = f"story-{slug}-{mode}-{viewport_label}.png"
        save_screenshot(driver, filename)
        captured.append(filename)
    return captured


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method,
                                     "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context, "viewport": {"width": width, "height": 920},
        })
        time.sleep(0.2)
        return self.command("script.evaluate", {
            "expression": "window.innerWidth",
            "target": {"context": self.context}, "awaitPromise": False,
        }).get("result", {}).get("value")

    def close(self):
        self.socket.close()


def album_metrics(driver):
    return driver.js("""return (() => {
      const cards = [...document.querySelectorAll('.aculta-card--compact')];
      const visible = element => element.getClientRects().length > 0;
      const actions = [...document.querySelectorAll(
        '.aculta-card--compact a, .aculta-card--compact button')].filter(visible);
      const smallActions = actions.map(element => {
        const r = element.getBoundingClientRect();
        return {tag: element.tagName, text: (element.innerText || '').trim().slice(0, 60),
          width: Math.round(r.width), height: Math.round(r.height)};
      }).filter(item => item.width > 0 && item.height > 0
        && (item.width < 44 || item.height < 44));
      const cardLinks = cards.flatMap(card => [...card.querySelectorAll('a')]);
      const unnamedLinks = cardLinks.filter(link => {
        const text = (link.innerText || '').trim();
        const imageText = [...link.querySelectorAll('img[alt]')]
          .map(image => image.alt.trim()).filter(Boolean).join(' ');
        const svgText = [...link.querySelectorAll('svg[aria-label], svg title')]
          .map(element => (element.getAttribute('aria-label') || element.textContent || '').trim())
          .filter(Boolean).join(' ');
        return !text && !imageText && !svgText && !link.getAttribute('aria-label')
          && !link.getAttribute('aria-labelledby');
      });
      const placeholderLinks = cardLinks.filter(link => {
        const href = (link.getAttribute('href') || '').toLowerCase();
        return href.includes('%5bpath%5d') || href.includes('[path]');
      })
        .map(link => ({href: link.getAttribute('href'),
          text: (link.innerText || '').trim(),
          ariaLabel: link.getAttribute('aria-label'),
          imageAlts: [...link.querySelectorAll('img[alt]')].map(image => image.alt)}));
      const originalScrollX = scrollX;
      window.scrollTo(10000, 0);
      const maxScrollX = scrollX;
      window.scrollTo(originalScrollX, 0);
      return {title: document.title, heading: document.querySelector('main h1, h1')?.innerText || '',
        viewport: innerWidth, documentWidth: document.documentElement.scrollWidth, maxScrollX,
        compactCards: cards.length, smallActions,
        unnamedLinks: unnamedLinks.length, placeholderLinks,
        firstCardText: cards[0]?.innerText?.trim().slice(0, 180) || ''};
    })();""")


def bookmark_focus_result(driver):
    driver.js("document.body.setAttribute('tabindex', '-1'); document.body.focus()")
    tab = "\uE004"
    for _ in range(60):
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
            "type": "key", "id": "keyboard", "actions": [
                {"type": "keyDown", "value": tab}, {"type": "keyUp", "value": tab},
            ],
        }]})
        result = driver.js("""const element = document.activeElement;
          const style = getComputedStyle(element);
          return {bookmark: !!element.closest('.aculta-bookmark-toggle'),
            tag: element.tagName, focusVisible: element.matches(':focus-visible'),
            outlineStyle: style.outlineStyle, outlineWidth: style.outlineWidth,
            outlineColor: style.outlineColor};""")
        if result["bookmark"]:
            return result
    return {"bookmark": False}


def one_time_login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    result = subprocess.run(
        ["vendor/bin/drush", "uli", "--no-browser", f"--uid={UID}",
         f"--uri={ACCOUNT_URL}"],
        cwd=ROOT, env=env, capture_output=True, text=True, timeout=30, check=True,
    )
    return result.stdout.strip().splitlines()[-1]


def inspect_album(prefs=None, dark=False):
    driver = Driver(prefs=prefs or {}, bidi=True)
    viewport = Viewport(driver)
    try:
        driver.get(ALBUM_URL)
        time.sleep(1.5)
        if dark:
            driver.js("document.documentElement.setAttribute('data-theme', 'dark'); "
                      "document.documentElement.setAttribute('data-bs-theme', 'dark')")
        snapshots = {}
        for width, label in ((1280, "desktop"), (320, "320")):
            actual = viewport.set(width)
            time.sleep(0.25)
            metrics = album_metrics(driver)
            metrics["requestedViewport"] = width
            if dark:
                metrics["prefersDark"] = driver.js(
                    "return matchMedia('(prefers-color-scheme: dark)').matches"
                )
                metrics["dataTheme"] = driver.js(
                    "return document.documentElement.getAttribute('data-theme')"
                )
                metrics["dataBsTheme"] = driver.js(
                    "return document.documentElement.getAttribute('data-bs-theme')"
                )
            snapshots[label] = metrics
            if metrics["maxScrollX"] > 0:
                print(f"BLOCK horizontal scroll album {label}: maxScrollX={metrics['maxScrollX']}")
            if metrics["unnamedLinks"] > 0:
                print(f"BLOCK unnamed links album {label}: {metrics['unnamedLinks']}")
            if metrics["placeholderLinks"]:
                print(f"BLOCK placeholder links album {label}: {metrics['placeholderLinks']}")
            suffix = "-dark" if dark else ""
            save_screenshot(driver, f"albums-{label}{suffix}.png")
            if actual != width:
                print(f"BLOCK viewport {label}: esperado {width}, recebido {actual}")
        return snapshots
    finally:
        viewport.close()
        driver.close()


def inspect_story_gate():
    driver = Driver(prefs={"ui.systemUsesDarkTheme": 1}, bidi=True)
    viewport = Viewport(driver)
    try:
        driver.get(one_time_login_url())
        time.sleep(1.0)
        # The one-time-login notice is informational. Never visit or submit the
        # account password form; navigate directly to the protected library.
        driver.get(STORY_URL)
        time.sleep(2.0)
        # On narrow viewports the Claro administrative sidebar overlays most of
        # the story. Collapse it through its UI control before measuring or
        # capturing the component.
        viewport.set(320)
        if driver.exists(".admin-toolbar__close-button"):
            driver.click(".admin-toolbar__close-button")
            time.sleep(0.3)
        result = {"url": driver.url().split("?")[0], "title": driver.title()}
        for width, label in ((1280, "desktop"), (320, "320")):
            actual = viewport.set(width)
            driver.js("window.scrollTo(0, 0)")
            time.sleep(0.2)
            result[label] = driver.js("""const originalScrollX = scrollX;
              window.scrollTo(10000, 0);
              const maxScrollX = scrollX;
              window.scrollTo(originalScrollX, 0);
              return {viewport: innerWidth,
              documentWidth: document.documentElement.scrollWidth,
              maxScrollX,
              rootOverflowX: getComputedStyle(document.documentElement).overflowX,
              overflow: [...document.querySelectorAll('body *')]
                .map(element => ({selector: element.tagName.toLowerCase() +
                  (element.id ? '#' + element.id : '') +
                  [...element.classList].slice(0, 2).map(name => '.' + name).join(''),
                  right: Math.round(element.getBoundingClientRect().right),
                  width: Math.round(element.getBoundingClientRect().width)}))
                .filter(element => element.right > innerWidth + 1)
                .slice(0, 8),
              body: (document.body?.innerText || '').slice(0, 180)};""")
            result[label]["requestedViewport"] = width
            save_screenshot(driver, f"ui-patterns-{label}.png")
            result[label]["storyCaptures"] = capture_story_headings(driver, "light", label)
            if actual != width:
                print(f"BLOCK viewport story {label}: esperado {width}, recebido {actual}")
        driver.js("document.documentElement.setAttribute('data-theme', 'dark'); "
                   "document.documentElement.setAttribute('data-bs-theme', 'dark')")
        viewport.set(1280)
        driver.js("window.scrollTo(0, 0)")
        time.sleep(0.2)
        result["dark"] = driver.js("""return {darkPreference:
          matchMedia('(prefers-color-scheme: dark)').matches,
          dataTheme: document.documentElement.getAttribute('data-theme'),
          dataBsTheme: document.documentElement.getAttribute('data-bs-theme')};""")
        save_screenshot(driver, "ui-patterns-dark.png")
        result["dark"]["storyCaptures"] = capture_story_headings(driver, "dark", "desktop")
        result["blocked"] = "O site encontrou um erro inesperado" in (
            result["320"]["body"] + result["desktop"]["body"]
        )
        if result["320"].get("maxScrollX", 0) > 0:
            print(f"BLOCK horizontal scroll at 320: maxScrollX={result['320']['maxScrollX']}")
        return result
    finally:
        viewport.close()
        driver.close()


def first_published_topic():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    result = subprocess.run(
        ["vendor/bin/drush", "sql:query",
         "SELECT nid FROM node_field_data WHERE type='aculta_forum_topic' "
         "AND status=1 ORDER BY nid LIMIT 1;"],
        cwd=ROOT, env=env, capture_output=True, text=True, timeout=30, check=True,
    )
    ids = [line.strip() for line in result.stdout.splitlines() if line.strip().isdigit()]
    if not ids:
        raise RuntimeError("Não há tópico público elegível para a verificação da Flag.")
    return ids[-1]


def bookmark_label(driver):
    return driver.js("return document.querySelector('.aculta-bookmark-toggle')?.innerText.trim() || ''")


def wait_for_bookmark_label(driver, expected):
    for _ in range(40):
        if bookmark_label(driver) == expected:
            return True
        time.sleep(0.5)
    return bookmark_label(driver) == expected


def capture_bookmark_state(driver, viewport, label):
    metrics = {"label": bookmark_label(driver)}
    for width, name in ((1280, "desktop"), (320, "320")):
        metrics[name + "Viewport"] = viewport.set(width)
        time.sleep(0.2)
        metrics[name] = driver.js("""return (() => {
          const link = document.querySelector('.aculta-bookmark-toggle a');
          const r = link?.getBoundingClientRect();
          const originalScrollX = scrollX;
          window.scrollTo(10000, 0);
          const canScrollHorizontally = scrollX > originalScrollX;
          window.scrollTo(originalScrollX, 0);
          return {label: link?.innerText.trim() || '', width: Math.round(r?.width || 0),
            height: Math.round(r?.height || 0), canScrollHorizontally};
        })();""")
        if metrics[name].get("canScrollHorizontally"):
            print(f"BLOCK horizontal scroll bookmark {label}/{name}")
        save_screenshot(driver, f"bookmark-{label}-{name}.png")
    driver.js("document.documentElement.setAttribute('data-theme', 'dark'); "
              "document.documentElement.setAttribute('data-bs-theme', 'dark')")
    metrics["darkViewport"] = viewport.set(1280)
    metrics["dark"] = driver.js("""return {label: document.querySelector('.aculta-bookmark-toggle a')?.innerText.trim() || '',
      prefersDark: matchMedia('(prefers-color-scheme: dark)').matches,
      dataTheme: document.documentElement.getAttribute('data-theme'),
      dataBsTheme: document.documentElement.getAttribute('data-bs-theme')};""")
    save_screenshot(driver, f"bookmark-{label}-dark.png")
    return metrics


def inspect_bookmark():
    nid = first_published_topic()
    login_url = one_time_login_url()
    driver = Driver(prefs={"ui.systemUsesDarkTheme": 1}, bidi=True)
    viewport = Viewport(driver)
    initial = None
    try:
        driver.get(login_url)
        time.sleep(1)
        driver.get(f"https://growroom420.aculta.toca.net.br/node/{nid}")
        time.sleep(1)
        if not driver.exists(".aculta-bookmark-toggle a"):
            raise RuntimeError("A ação nativa de salvar não aparece no tópico de teste.")
        initial = bookmark_label(driver)
        if initial not in ("Salvar tópico", "Salvo"):
            raise RuntimeError("Rótulo inesperado na ação nativa de salvar.")
        original_saved = initial == "Salvo"
        output = {"initial": initial, "focus": bookmark_focus_result(driver)}
        if original_saved:
            driver.click(".aculta-bookmark-toggle a")
            if not wait_for_bookmark_label(driver, "Salvar tópico"):
                raise RuntimeError("Não foi possível preparar o estado não salvo.")
        output["unsaved"] = capture_bookmark_state(driver, viewport, "unsaved")
        driver.click(".aculta-bookmark-toggle a")
        if not wait_for_bookmark_label(driver, "Salvo"):
            raise RuntimeError("A Flag não confirmou o estado salvo.")
        output["saved"] = capture_bookmark_state(driver, viewport, "saved")
        driver.click(".aculta-bookmark-toggle a")
        if not wait_for_bookmark_label(driver, "Salvar tópico"):
            raise RuntimeError("Não foi possível restaurar o estado inicial não salvo.")
        if original_saved:
            driver.click(".aculta-bookmark-toggle a")
            if not wait_for_bookmark_label(driver, "Salvo"):
                raise RuntimeError("Não foi possível restaurar o estado salvo inicial.")
        output["restoredInitialState"] = bookmark_label(driver) == initial
        if not output["restoredInitialState"]:
            raise RuntimeError("O estado de Flag inicial não foi restaurado.")
        return output
    finally:
        if initial is not None:
            try:
                current = bookmark_label(driver)
                if (current == "Salvo") != (initial == "Salvo"):
                    driver.click(".aculta-bookmark-toggle a")
                    wait_for_bookmark_label(driver, initial)
            except Exception:
                pass
        viewport.close()
        driver.close()


def main():
    if DISPLAY != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
    OUT.mkdir(parents=True, exist_ok=True)
    os.chmod(OUT, 0o700)
    os.umask(0o077)
    story = inspect_story_gate()
    if os.environ.get("STORY_ONLY") == "1":
        summary = {"uiPatterns": story}
    else:
        light = inspect_album()
        dark = inspect_album({"ui.systemUsesDarkTheme": 1}, dark=True)
        bookmark = inspect_bookmark()
        summary = {"album": {"light": light, "dark": dark},
                   "uiPatterns": story, "bookmark": bookmark}
    (OUT / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n")
    if os.environ.get("STORY_ONLY") != "1":
        print(f"Album: {ALBUM_URL}")
        for variant in ("light", "dark"):
            for size, data in summary["album"][variant].items():
                print(f"album {variant}/{size}: {data['compactCards']} cards compact; "
                      f"viewport={data['viewport']}; documento={data['documentWidth']}; "
                      f"alvos pequenos={len(data['smallActions'])}; "
                      f"links sem nome={data['unnamedLinks']}; "
                      f"links placeholder={len(data['placeholderLinks'])}")
    print("UI Patterns bloqueada:", story["blocked"])
    if os.environ.get("STORY_ONLY") != "1":
        print("Flag Fórum: estados salvo/não salvo capturados; estado inicial restaurado:",
              bookmark["restoredInitialState"])
    print("Evidências:", OUT)


if __name__ == "__main__":
    main()
