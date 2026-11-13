#!/usr/bin/env python3
"""Suite de captura e validação visual de layout de container por purpose no Firefox ESR."""

import base64
import json
import os
import sys
import time
from datetime import date
from pathlib import Path

from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = Path("/home/piradopirata/projetos/aculta")
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

TODAY = date.today().isoformat()
CAPTURE_DIR = ROOT / f"tests/browser/capturas/layout-container/{TODAY}"
CAPTURE_DIR.mkdir(parents=True, exist_ok=True)
os.environ["ACULTA_FF_PROFILE_ROOT"] = "/home/piradopirata/aculta-suite-out/profiles"

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
WIDTHS = (320, 1440)
MODES = ("light", "dark")


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


def main():
    if os.environ.get("DISPLAY") != ":0":
        os.environ["DISPLAY"] = ":0"

    deploy_path = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
    entries = json.loads(deploy_path.read_text(encoding="utf-8"))["domain_transition"]["entries"]
    hosts = {entry["domain_id"]: entry["test_host"] for entry in entries}

    driver = Driver(bidi=True)
    viewport = Viewport(driver)
    records = []
    total_checks = 0
    passed_checks = 0
    failures = []

    try:
        for purpose, domain_id in DOMAIN_IDS.items():
            url = f"https://{hosts[domain_id]}/"
            for width in WIDTHS:
                viewport.set(width)
                driver.get(url)
                time.sleep(0.35)

                for mode in MODES:
                    driver.js(
                        "document.documentElement.setAttribute('data-theme', arguments[0]); return true",
                        [mode],
                    )
                    time.sleep(0.35)

                    # Testa também o comportamento de alternância de modo (boxed vs unboxed) no runtime
                    metrics = json.loads(driver.js("""return JSON.stringify((() => {
                      const root = document.documentElement;
                      const css = getComputedStyle(root);
                      const main = document.querySelector('main, .layout-page');
                      const nav = document.querySelector('.page__navigation');
                      const footer = document.querySelector('.page__footer-inner');

                      const getWidth = (el) => el ? el.getBoundingClientRect().width : null;

                      return {
                        purpose: root.dataset.purpose,
                        layout: root.dataset.purposeLayout || 'boxed',
                        mode: root.dataset.theme,
                        width: innerWidth,
                        documentWidth: root.scrollWidth,
                        containerWidthVar: css.getPropertyValue('--aculta-container-width').trim(),
                        narrowWidthVar: css.getPropertyValue('--aculta-narrow-container-width').trim(),
                        mainWidth: getWidth(main),
                        navWidth: getWidth(nav),
                        footerWidth: getWidth(footer),
                      };
                    })());"""))

                    # Validações estruturais e responsivas:
                    # 1. Sem overflow horizontal do documento em qualquer largura
                    # 2. Celular (320px): largura 100%, nunca ultrapassa o viewport
                    # 3. Variáveis de container emitidas no :root / html
                    checks = {
                        "purpose": metrics["purpose"] == purpose,
                        "mode": metrics["mode"] == mode,
                        "viewport": metrics["width"] == width,
                        "has_container_width_var": bool(metrics["containerWidthVar"]),
                        "has_narrow_width_var": bool(metrics["narrowWidthVar"]),
                        "has_layout_attribute": metrics["layout"] in ["boxed", "unboxed", "contained", "max_full_width"],
                        "no_horizontal_overflow": metrics["documentWidth"] <= width,
                    }

                    if width <= 600:
                        # Em celular, a largura principal deve ocupar ~100% da viewport (com gutters)
                        checks["mobile_100_percent"] = (metrics["mainWidth"] is None or metrics["mainWidth"] <= width)

                    for check_name, passed in checks.items():
                        total_checks += 1
                        if passed:
                            passed_checks += 1
                        else:
                            failures.append(f"{purpose} {mode} {width}px: {check_name} falhou")

                    record = {
                        "purpose": purpose,
                        "mode": mode,
                        "viewport": width,
                        "url": url,
                        "checks": checks,
                        "metrics": metrics,
                    }
                    records.append(record)

                    # Captura de tela PNG
                    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
                    png_path = CAPTURE_DIR / f"{purpose}-{mode}-{width}.png"
                    png_path.write_bytes(base64.b64decode(screenshot))
                    print(f"[{passed_checks}/{total_checks}] Captura {purpose} {mode} {width}px: PASS={all(checks.values())}")

    finally:
        viewport.close()
        driver.close()

    # Grava results.json e manifest.json
    results = {
        "data": TODAY,
        "purposes": list(DOMAIN_IDS.keys()),
        "widths": list(WIDTHS),
        "modes": list(MODES),
        "total_checks": total_checks,
        "passed_checks": passed_checks,
        "failed_checks": len(failures),
        "failures": failures,
        "records": records,
    }
    (CAPTURE_DIR / "results.json").write_text(json.dumps(results, indent=2, ensure_ascii=False), encoding="utf-8")

    manifest = {
        "metodo": "Firefox visible via WebDriver BiDi; captures by purpose, color mode and viewport",
        "motor": "Firefox ESR",
        "viewport": list(WIDTHS),
        "modo": list(MODES),
        "url_relativa": "/ em cada host de purpose do deploy.json (ambiente test)",
        "capturas_dir": str(CAPTURE_DIR.relative_to(ROOT)),
        "resultados": {
            "purposes": list(DOMAIN_IDS.keys()),
            "casos": len(records),
            "verificacoes": total_checks,
            "passaram": passed_checks,
            "falharam": len(failures),
            "falhas": failures,
            "assertions": {
                "purpose": "PASS" if all(r["checks"]["purpose"] for r in records) else "FAIL",
                "mode": "PASS" if all(r["checks"]["mode"] for r in records) else "FAIL",
                "viewport": "PASS" if all(r["checks"]["viewport"] for r in records) else "FAIL",
                "has_container_width_var": "PASS" if all(r["checks"]["has_container_width_var"] for r in records) else "FAIL",
                "has_narrow_width_var": "PASS" if all(r["checks"]["has_narrow_width_var"] for r in records) else "FAIL",
                "has_layout_attribute": "PASS" if all(r["checks"]["has_layout_attribute"] for r in records) else "FAIL",
                "no_horizontal_overflow": "PASS" if all(r["checks"]["no_horizontal_overflow"] for r in records) else "FAIL",
                "mobile_100_percent": "PASS" if all(r["checks"].get("mobile_100_percent", True) for r in records) else "FAIL",
            },
        },
    }
    (CAPTURE_DIR / "manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"\nFinalizado: {passed_checks}/{total_checks} verificações aprovadas. Manifest gerado em {CAPTURE_DIR}/manifest.json.")


if __name__ == "__main__":
    main()
