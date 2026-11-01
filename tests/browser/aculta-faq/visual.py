#!/usr/bin/env python3
"""Read-only Firefox ESR review of a disposable published FAQ question."""

import base64
import json
import os
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3]))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("ACULTA_TEST_BASE_URL", "https://aculta.toca.net.br")
ITEMS = json.loads(os.environ["SUITE_FAQ_ITEMS"])
OUT = Path(os.environ["SUITE_OUT"])
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
results = []


def check(name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if not OUT.is_absolute():
    raise SystemExit("SUITE_OUT deve ser absoluto e fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)

driver = None
bidi = None
bidi_id = 0


def set_width(width):
    global bidi_id
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({"id": command_id, "method": "browsingContext.getTree", "params": {}}))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    context = response["result"]["contexts"][0]["context"]
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({
        "id": command_id,
        "method": "browsingContext.setViewport",
        "params": {"context": context, "viewport": {"width": width, "height": 900}},
    }))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    time.sleep(0.3)
    return int(driver.js("return window.innerWidth"))


def screenshot(name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{name}.png").write_bytes(base64.b64decode(data))


def press_tab():
    key = "\ue004"
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "faq-keyboard",
            "actions": [
                {"type": "keyDown", "value": key},
                {"type": "keyUp", "value": key},
            ],
        }],
    })


try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)
    driver.get(BASE + "/perguntas-frequentes")
    for _ in range(80):
        if driver.js("return document.readyState === 'complete'"):
            break
        time.sleep(0.25)

    page = driver.js("""const main = document.querySelector('main');
      const items = arguments[0].map(item => ({
        question: [...(main?.querySelectorAll('*') || [])].some(element =>
          element.children.length === 0 && element.textContent.trim() === item.title),
        answer: main?.innerText.includes(item.body) || false,
      }));
      return {
      path: location.pathname,
      h1: document.querySelector('main h1')?.innerText || '',
      items,
    };""", [ITEMS])
    check("página pública da FAQ abriu", page["path"] == "/perguntas-frequentes" and page["h1"] == "Perguntas frequentes")
    check("todas as perguntas e respostas esperadas estão publicadas",
          all(item["question"] and item["answer"] for item in page["items"]),
          str(page["items"]))

    expected_links = driver.js("""return [...new Map(
      [...document.querySelectorAll('main a[href]')].map(link => {
        const key = link.innerText.trim() + '|' + link.href;
        return [key, {key, text: link.innerText.trim()}];
      })
    ).values()];""")
    focused_links = set()
    focus_visible = False
    for _ in range(max(60, len(expected_links) * 4)):
        press_tab()
        active = driver.js("""const element = document.activeElement;
          if (!element || !document.querySelector('main')?.contains(element)) return null;
          return {
            key: element.innerText.trim() + '|' + element.href,
            isLink: element.matches('a[href]'),
            focusVisible: element.matches(':focus-visible'),
          };""")
        if active and active["isLink"]:
            focused_links.add(active["key"])
            focus_visible = focus_visible or active["focusVisible"]
        if len(focused_links.intersection(link["key"] for link in expected_links)) == len(expected_links):
            break
    visited_expected = focused_links.intersection(link["key"] for link in expected_links)
    missing_links = [link["text"] for link in expected_links if link["key"] not in visited_expected]
    check("links da FAQ alcançáveis por Tab com indicador de foco",
          bool(expected_links) and len(visited_expected) == len(expected_links) and focus_visible,
          f"foco {len(visited_expected)}/{len(expected_links)}, focus-visible={focus_visible}, ausentes={missing_links}")
    driver.js("if (document.activeElement?.blur) document.activeElement.blur();")

    for width, label in WIDTHS:
        actual = set_width(width)
        metrics = driver.js("""const main = document.querySelector('main');
          const questionsVisible = arguments[0].map(item => {
            const question = [...(main?.querySelectorAll('*') || [])].find(element =>
              element.children.length === 0 && element.textContent.trim() === item.title);
            const rect = question?.getBoundingClientRect();
            return !!rect && rect.left >= 0 && rect.right <= window.innerWidth;
          });
          return {
            viewport: window.innerWidth,
            document: document.documentElement.scrollWidth,
            h1Visible: (() => {
              const title = document.querySelector('main h1');
              if (!title) return false;
              const bounds = title.getBoundingClientRect();
              return bounds.left >= 0 && bounds.right <= window.innerWidth;
            })(),
            questionsVisible: questionsVisible.length === arguments[0].length && questionsVisible.every(Boolean),
          };""", [ITEMS])
        screenshot(f"faq-{label}")
        check(
            f"FAQ sem overflow e todas as perguntas visíveis em {width}px",
            actual == width and metrics["document"] <= width and metrics["h1Visible"] and metrics["questionsVisible"],
            str(metrics),
        )
finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
