#!/usr/bin/env python3
"""Firefox runtime regression suite for migrated purpose buttons."""

import base64
import json
import os
import re
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))

DOMAIN_IDS = {
    "main": "aculta_org",
    "account": "conta_aculta_org",
    "support": "apoio_aculta_org",
    "magazine": "revista420_aculta_org",
    "wiki": "wiki420_aculta_org",
    "shop": "loja_aculta_org",
    "courses": "cursos_aculta_org",
    "forum": "forum_aculta_org",
    "links": "links_aculta_org",
    "eventos": "eventos_aculta_org",
}
EXPECTED_SCHEMES = {
    "main": "verde-skunk",
    "account": "verde-afghan",
    "support": "amarelo-acapulco-gold",
    "magazine": "vermelho-red-kush",
    "wiki": "verde-sativa",
    "shop": "amarelo-lemon-haze",
    "courses": "vermelho-panama-red",
    "forum": "vermelho-red-congolese",
    "links": "verde-ruderalis",
    "eventos": "amarelo-golden-goat",
}
WIDTHS = (320, 1440)
MODES = ("light", "dark")
BUTTON_STYLES = (
    ROOT / "web/themes/custom/aculta420_v2/src/styles/buttons.css",
    ROOT / "web/themes/custom/aculta420_v2/components/button/button.css",
)


class Viewport:
    """Controls CSS viewport dimensions through WebDriver BiDi."""

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
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })

    def close(self):
        self.socket.close()


def static_button_css_checks():
    styles = "\n".join(path.read_text(encoding="utf-8") for path in BUTTON_STYLES)
    return {
        "button_tokens_present": "var(--button-" in styles,
        "no_purpose_tokens": re.search(r"var\(\s*--purpose-", styles) is None,
        "no_brand_highlight": "--color-brand-highlight" not in styles,
        "no_brightness_filter": re.search(r"filter\s*:\s*brightness\s*\(", styles, re.I) is None,
    }


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

    deploy_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
    entries = json.loads(deploy_path.read_text(encoding="utf-8"))["domain_transition"]["entries"]
    hosts = {entry["domain_id"]: entry["test_host"] for entry in entries}
    missing = set(DOMAIN_IDS.values()) - set(hosts)
    if missing:
        raise SystemExit(f"Domínios ausentes em deploy.json: {', '.join(sorted(missing))}")

    static_checks = static_button_css_checks()
    if not all(static_checks.values()):
        raise SystemExit(f"Contrato estático de botões falhou: {static_checks}")

    driver = Driver(bidi=True)
    viewport = Viewport(driver)
    records = []
    try:
        for purpose, domain_id in DOMAIN_IDS.items():
            url = f"https://{hosts[domain_id]}/"
            for width in WIDTHS:
                viewport.set(width)
                driver.get(url)
                time.sleep(0.25)
                driver.js("""const fixture = document.createElement('section');
                  fixture.id = 'purpose-button-test-fixture';
                  fixture.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;max-width:100%;padding:8px';
                  for (const variant of ['primary', 'outline']) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.portalUi = `aculta-btn-${variant}`;
                    button.textContent = variant;
                    fixture.append(button);
                  }
                  document.querySelector('main')?.prepend(fixture) || document.body.prepend(fixture);
                  return true;""")

                for mode in MODES:
                    driver.js(
                        "document.documentElement.setAttribute('data-theme', arguments[0]); return true",
                        [mode],
                    )
                    # Allow the declared color transitions to settle before measuring.
                    time.sleep(0.35)
                    metrics = json.loads(driver.js("""return JSON.stringify((() => {
                      const root = document.documentElement;
                      const css = getComputedStyle(root);
                      const resolve = (value) => {
                        const probe = document.createElement('i');
                        probe.style.backgroundColor = value;
                        document.body.append(probe);
                        const result = getComputedStyle(probe).backgroundColor;
                        probe.remove();
                        return result;
                      };
                      const buttons = [...document.querySelectorAll('#purpose-button-test-fixture button')]
                        .map((button) => {
                          const style = getComputedStyle(button);
                          return {variant: button.dataset.portalUi, background: style.backgroundColor,
                            foreground: style.color, border: style.borderTopColor, filter: style.filter};
                        });
                      return {purpose: root.dataset.purpose, scheme: root.dataset.purposeScheme,
                        mode: root.dataset.theme, width: innerWidth, documentWidth: root.scrollWidth,
                        accent: css.getPropertyValue('--purpose-accent').trim(),
                        primaryBackground: resolve('var(--button-primary-bg)'),
                        primaryForeground: resolve('var(--button-primary-fg)'),
                        secondaryBackground: resolve('var(--button-secondary-bg)'),
                        secondaryForeground: resolve('var(--button-secondary-fg)'),
                        secondaryBorder: resolve('var(--button-secondary-border)'), buttons};
                    })());"""))
                    primary, outline = metrics["buttons"]
                    checks = {
                        "purpose": metrics["purpose"] == purpose,
                        "configured_scheme": metrics["scheme"] == EXPECTED_SCHEMES[purpose],
                        "mode": metrics["mode"] == mode,
                        "viewport": metrics["width"] == width,
                        "palette_accent_resolved": bool(metrics["accent"]),
                        "primary_uses_button_tokens": (
                            primary["background"] == metrics["primaryBackground"]
                            and primary["foreground"] == metrics["primaryForeground"]
                        ),
                        "outline_uses_button_tokens": (
                            outline["background"] == metrics["secondaryBackground"]
                            and outline["foreground"] == metrics["secondaryForeground"]
                            and outline["border"] == metrics["secondaryBorder"]
                        ),
                        "no_filter": all(button["filter"] == "none" for button in metrics["buttons"]),
                        "no_overflow": metrics["documentWidth"] <= width,
                    }
                    record = {
                        "purpose": purpose,
                        "mode": mode,
                        "viewport": width,
                        "url_relativa": "/",
                        "checks": checks,
                        "metrics": metrics,
                    }
                    records.append(record)
                    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    (OUT / f"buttons-{purpose}-{mode}-{width}.png").write_bytes(
                        base64.b64decode(screenshot)
                    )
    finally:
        viewport.close()
        driver.close()

    report = {
        "engine": "Firefox ESR",
        "method": "Firefox visible via WebDriver BiDi; inert primary/outline button fixtures",
        "static_checks": static_checks,
        "records": records,
    }
    (OUT / "results.json").write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n")
    failures = [
        f"{record['purpose']} {record['mode']} {record['viewport']}px {name}"
        for record in records
        for name, passed in record["checks"].items()
        if not passed
    ]
    passed = sum(sum(record["checks"].values()) for record in records)
    total = sum(len(record["checks"]) for record in records)
    print(json.dumps({
        "scenarios": len(records),
        "passed": passed,
        "total": total,
        "failures": failures,
        "evidence": str(OUT),
    }, ensure_ascii=False))
    raise SystemExit(bool(failures))


if __name__ == "__main__":
    main()
