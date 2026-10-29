#!/usr/bin/env python3
"""Read-only Firefox check for anonymous access to administrator editor routes."""

import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import urljoin

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("EDITOR_BASE_URL", "https://aculta.toca.net.br").rstrip("/") + "/"
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-editor-access"))
ROUTES = {
    "full_html_format": "admin/config/content/formats/manage/full_html",
    "aculta_adm_create": "node/add/aculta_adm",
}


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("DISPLAY=:0 é obrigatório para Firefox ESR visível.")
    OUT.mkdir(parents=True, exist_ok=True)
    os.environ["ACULTA_FF_PROFILE_ROOT"] = str(OUT / "profiles")
    driver = Driver()
    results = {}
    try:
        for name, path in ROUTES.items():
            driver.get(urljoin(BASE, path))
            time.sleep(1)
            observation = driver.js("""return {
              url: location.pathname,
              heading: document.querySelector('main h1, h1')?.innerText || '',
              loginForm: !!document.querySelector('#user-login-form'),
              nodeForm: !!document.querySelector('form.node-aculta-adm-form'),
              formatForm: !!document.querySelector('form#filter-format-form') ||
                !!document.querySelector('form#filter-format-edit-form')
            };""")
            missing = any(term in observation["heading"].lower() for term in
                          ("not found", "não encontrada", "não encontrado", "404"))
            observation["denied"] = (
                bool(observation["heading"])
                and not missing
                and not observation["nodeForm"]
                and not observation["formatForm"]
            )
            if not observation["denied"]:
                raise RuntimeError(f"Rota administrativa exposta anonimamente: {name}")
            results[name] = observation
            print(f"PASS {name}: acesso anônimo negado")
    finally:
        driver.close()
    (OUT / "results.json").write_text(
        json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print(json.dumps({k: {"denied": v["denied"], "loginForm": v["loginForm"]}
                      for k, v in results.items()}, ensure_ascii=False))


if __name__ == "__main__":
    main()
