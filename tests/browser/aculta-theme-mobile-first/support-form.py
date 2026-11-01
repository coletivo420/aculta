#!/usr/bin/env python3
"""Read-only responsive smoke for the public support form in Firefox ESR."""

import base64
import json
import os
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("SUPPORT_BASE", "https://apoio.aculta.toca.net.br").rstrip("/")
OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
WIDTHS = [320, 360, 390, 480, 481, 599, 600, 768, 1024, 1280, 1440]
checks = []


def check(name, ok, detail=""):
    checks.append({"name": name, "ok": bool(ok), "detail": str(detail)})
    print(f"{'PASS' if ok else 'FAIL'} {name}" + (f" — {detail}" if detail else ""), flush=True)


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 1000},
        })
        time.sleep(0.25)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute em Firefox ESR visível com DISPLAY=:0.")

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get(BASE + "/")
    time.sleep(1)
    page = driver.js("""return {
      url: location.href,
      title: document.title,
      form: !!document.querySelector('form[data-portal-ui="support-form"]'),
      actionCount: document.querySelectorAll('form[data-portal-ui="support-form"] .form-actions :is(button, input[type="submit"], input[type="button"])').length,
      fieldsetCount: document.querySelectorAll('form[data-portal-ui="support-form"] fieldset').length,
      visibleControlCount: [...document.querySelectorAll('form[data-portal-ui="support-form"] input:not([type="hidden"]), form[data-portal-ui="support-form"] select, form[data-portal-ui="support-form"] textarea, form[data-portal-ui="support-form"] button')].filter(element => element.getClientRects().length > 0).length,
      donationCards: document.querySelectorAll('.aculta-donation-quick .aculta-donation-card').length,
      institutionBar: document.querySelectorAll('.aculta-institution-bar').length,
      domainHeader: document.querySelectorAll('.aculta-header').length
    };""")
    check("formulário público de apoio carregado", page["form"], json.dumps(page, ensure_ascii=False))
    check("SDC de valores rápidos renderizado", page["donationCards"] > 0, str(page["donationCards"]))
    check("Institution Bar preservada", page["institutionBar"] == 1, str(page["institutionBar"]))
    check("nenhum campo de cobrança ou submit não implementado é exposto",
          page["fieldsetCount"] == 0 and page["visibleControlCount"] == 0 and page["actionCount"] == 0,
          json.dumps({"fieldsets": page["fieldsetCount"], "visibleControls": page["visibleControlCount"],
                      "submitActions": page["actionCount"]}))

    for width in WIDTHS:
        actual = viewport.set(width)
        driver.get(f"{BASE}/?aculta_support_viewport={width}")
        time.sleep(0.45)
        metrics = driver.js("""return (() => {
          const form = document.querySelector('form[data-portal-ui="support-form"]');
          const intro = form?.querySelector('[data-portal-ui="support-intro"], .aculta-support-intro');
          const otherSupport = form?.querySelector('[data-portal-ui="support-other"], .aculta-support-other');
          const contact = otherSupport?.querySelector('a[href]');
          const grid = document.querySelector('.aculta-donation-quick');
          const tracks = grid ? getComputedStyle(grid).gridTemplateColumns.trim().split(/\\s+/).filter(Boolean) : [];
          const cards = grid?.querySelectorAll('.aculta-donation-card') ?? [];
          return {
            width: innerWidth,
            documentWidth: document.documentElement.scrollWidth,
            form: !!form,
            formWidth: form ? Math.round(form.getBoundingClientRect().width) : 0,
            introVisible: !!intro && intro.getClientRects().length > 0,
            otherSupportVisible: !!otherSupport && otherSupport.getClientRects().length > 0,
            contactVisible: !!contact && contact.getClientRects().length > 0,
            donationCards: grid?.querySelectorAll('.aculta-donation-card').length ?? 0,
            gridColumns: tracks.length,
            gridWidth: grid ? Math.round(grid.getBoundingClientRect().width) : 0,
            lastCardRight: cards.length ? Math.round(cards[cards.length - 1].getBoundingClientRect().right) : 0
          };
        })();""")
        check(f"{width}px: viewport configurado", actual == width, f"medido={actual}")
        check(f"{width}px: sem overflow", metrics["documentWidth"] <= width, json.dumps(metrics))
        check(f"{width}px: grade do SDC dentro do viewport", metrics["form"]
              and metrics["donationCards"] == page["donationCards"]
              and metrics["gridColumns"] >= 1
              and metrics["gridWidth"] > 0
              and metrics["lastCardRight"] <= width
              and metrics["formWidth"] <= width
              and metrics["introVisible"] and metrics["otherSupportVisible"] and metrics["contactVisible"],
              json.dumps(metrics, ensure_ascii=False))
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"support-form-{width}.png").write_bytes(base64.b64decode(screenshot))

    (OUT / "results.json").write_text(json.dumps({
        "base": BASE, "page": page, "checks": checks,
    }, ensure_ascii=False, indent=2) + "\n")
finally:
    viewport.close()
    driver.close()

failed = [result for result in checks if not result["ok"]]
print(f"\n{len(checks) - len(failed)}/{len(checks)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
