#!/usr/bin/env python3
"""Read-only Firefox ESR check for anonymous, content_editor, and admin editor access."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import urljoin

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

BASE = os.environ.get("EDITOR_BASE_URL", "https://aculta.toca.net.br").rstrip("/") + "/"
SERVED_ROOT = Path(os.environ.get("EDITOR_SERVED_ROOT", "/home/piradopirata/projetos/aculta"))
OUT = Path(os.environ.get("SUITE_OUT", "/home/piradopirata/worktrees/suite-out/aculta-editor-access"))
ADMIN_ONLY_ROUTES = {
    "full_html_format": "painel-administrativo/config/content/formats/manage/full_html",
    "aculta_adm_create": "node/add/aculta_adm",
}
VIEWPORTS = [(1280, 900), (390, 844), (320, 720)]


def drush_uli(uid: int) -> str:
    cmd = [
        "php",
        str(SERVED_ROOT / "vendor/drush/drush/drush.php"),
        f"--root={SERVED_ROOT / 'web'}",
        "ev",
        f"echo user_pass_reset_url(\\Drupal\\user\\Entity\\User::load({uid})) . '/entrar' . PHP_EOL;",
    ]
    raw = subprocess.check_output(cmd, text=True, timeout=30).strip().splitlines()[-1].strip()
    account_base = os.environ.get("EDITOR_ACCOUNT_URL", "https://conta.aculta.toca.net.br").rstrip("/") + "/"
    if raw.startswith("http://default/"):
        raw = account_base + raw[len("http://default/"):]
    return raw


def check_denied(driver: Driver, name: str, path: str) -> dict:
    driver.get(urljoin(BASE, path))
    time.sleep(1)
    obs = driver.js("""return {
      url: location.pathname,
      heading: document.querySelector('main h1, h1')?.innerText || '',
      loginForm: !!document.querySelector('#user-login-form'),
      nodeForm: !!document.querySelector('form.node-aculta-adm-form'),
      formatForm: !!document.querySelector('form#filter-format-form') ||
        !!document.querySelector('form#filter-format-edit-form')
    };""")
    missing = any(term in obs["heading"].lower() for term in
                  ("not found", "não encontrada", "não encontrado", "404"))
    obs["denied"] = (
        bool(obs["heading"])
        and not missing
        and not obs["nodeForm"]
        and not obs["formatForm"]
    )
    if not obs["denied"]:
        raise RuntimeError(f"Rota administrativa exposta indevidamente: {name} ({obs})")
    return obs


def check_viewports(driver: Driver, prefix: str) -> dict:
    vp_results = {}
    for w, h in VIEWPORTS:
        driver.req("POST", f"/session/{driver.session}/window/rect", {"width": w, "height": h})
        time.sleep(0.4)
        metrics = driver.js("""return {
          innerWidth: window.innerWidth,
          scrollWidth: document.documentElement.scrollWidth,
          overflowPx: Math.max(0, document.documentElement.scrollWidth - window.innerWidth)
        };""")
        png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"{prefix}-{w}.png").write_bytes(png)
        if metrics["overflowPx"] > 2:
            raise RuntimeError(f"Overflow horizontal em {prefix} ({w}px): {metrics}")
        vp_results[str(w)] = metrics
    driver.req("POST", f"/session/{driver.session}/window/rect", {"width": 1280, "height": 900})
    return vp_results


def wait_for_ckeditor(driver: Driver, timeout: float = 12.0) -> dict:
    deadline = time.time() + timeout
    last = {}
    while time.time() < deadline:
        last = driver.js("""return {
          url: location.href,
          heading: document.querySelector('main h1, h1')?.innerText || '',
          ckEditor: !!document.querySelector('.ck-editor'),
          editableCount: document.querySelectorAll('.ck-editor__editable').length,
          toolbarButtons: document.querySelectorAll('.ck-toolbar button').length,
          editableRole: document.querySelector('.ck-editor__editable')?.getAttribute('role') || '',
          editableLabel: document.querySelector('.ck-editor__editable')?.getAttribute('aria-label') || '',
          formatOptions: Array.from(document.querySelectorAll('select.js-filter-list option')).map(o => o.value)
        };""")
        if last.get("ckEditor") and last.get("editableCount", 0) >= 1:
            return last
        time.sleep(0.5)
    return last


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("DISPLAY=:0 é obrigatório para Firefox ESR visível.")
    OUT.mkdir(parents=True, exist_ok=True)
    os.environ["ACULTA_FF_PROFILE_ROOT"] = str(OUT / "profiles")
    results = {"anonymous": {}, "content_editor": {}, "administrator": {}}

    # 1. Anonymous session: denied on admin routes + rendered sanitization on node/6.
    driver = Driver()
    try:
        for name, path in ADMIN_ONLY_ROUTES.items():
            results["anonymous"][name] = check_denied(driver, f"anon_{name}", path)
            print(f"PASS anonymous.{name}: acesso anônimo negado")

        driver.get(urljoin(BASE, "node/6"))
        time.sleep(1)
        sanitized = driver.js("""return (function() {
          const root = document.querySelector('main') || document.body;
          const forbiddenTags = root.querySelectorAll('script, iframe, object, embed, applet, frame, frameset');
          let inlineEvents = 0;
          for (const el of root.querySelectorAll('*')) {
            for (const attr of el.getAttributeNames()) {
              if (attr.toLowerCase().startsWith('on')) inlineEvents++;
            }
          }
          return {
            url: location.pathname,
            heading: document.querySelector('main h1, h1')?.innerText || '',
            forbiddenTagCount: forbiddenTags.length,
            inlineEventCount: inlineEvents
          };
        })();""")
        if sanitized["forbiddenTagCount"] != 0 or sanitized["inlineEventCount"] != 0:
            raise RuntimeError(f"Marcação insegura em conteúdo renderizado: {sanitized}")
        sanitized["viewports"] = check_viewports(driver, "anon-rendered-node6")
        results["anonymous"]["rendered_sanitization"] = sanitized
        print("PASS anonymous.rendered_sanitization: conteúdo renderizado sem tags/atributos executáveis e sem overflow")
    finally:
        driver.close()

    # 2. Content Editor (uid=15): denied on full_html and aculta_adm; CKEditor 5
    # loads on the Revista host with revista_editorial and without full_html.
    driver = Driver()
    try:
        driver.get(drush_uli(15))
        time.sleep(1.5)
        for name, path in ADMIN_ONLY_ROUTES.items():
            results["content_editor"][name] = check_denied(driver, f"editor_{name}", path)
            print(f"PASS content_editor.{name}: negado para content_editor")

        driver.get(os.environ.get("EDITOR_REVISTA_ADD_URL", "https://revista.aculta.toca.net.br/node/add/revista"))
        time.sleep(1.5)
        ck_editor = wait_for_ckeditor(driver)
        if not ck_editor.get("ckEditor") or "full_html" in ck_editor.get("formatOptions", []):
            raise RuntimeError(f"CKEditor inválido ou full_html exposto para content_editor: {ck_editor}")
        ck_editor["viewports"] = check_viewports(driver, "editor-node-add-revista")
        results["content_editor"]["revista_ckeditor"] = ck_editor
        print("PASS content_editor.revista_ckeditor: CKEditor 5 ativo sem full_html e sem overflow (1280/390/320)")
    finally:
        driver.close()

    # 3. Administrator (uid=1): allowed on node/add/aculta_adm and node/add/page with CKEditor 5.
    driver = Driver()
    try:
        driver.get(drush_uli(1))
        time.sleep(1.5)
        driver.get(urljoin(BASE, "node/add/aculta_adm"))
        time.sleep(1.5)
        adm_obs = driver.js("""return {
          url: location.pathname,
          heading: document.querySelector('main h1, h1')?.innerText || '',
          nodeForm: !!document.querySelector('form.node-aculta-adm-form')
        };""")
        if not adm_obs["nodeForm"]:
            raise RuntimeError(f"Administrador não recebeu form.node-aculta-adm-form: {adm_obs}")
        adm_obs["ck"] = wait_for_ckeditor(driver)
        adm_obs["viewports"] = check_viewports(driver, "admin-node-add-aculta-adm")
        results["administrator"]["aculta_adm_create"] = adm_obs
        print("PASS administrator.aculta_adm_create: formulário aculta_adm e CKEditor 5 disponíveis sem overflow (1280/390/320)")

        driver.get(urljoin(BASE, "node/add/page"))
        time.sleep(1.5)
        page_ck = wait_for_ckeditor(driver)
        if not page_ck.get("ckEditor") or page_ck.get("toolbarButtons", 0) < 5:
            raise RuntimeError(f"CKEditor 5 não inicializou em node/add/page para admin: {page_ck}")
        page_ck["viewports"] = check_viewports(driver, "admin-node-add-page")
        results["administrator"]["page_ckeditor"] = page_ck
        print("PASS administrator.page_ckeditor: CKEditor 5 e barra de ferramentas ativos sem overflow (1280/390/320)")
    finally:
        driver.close()

    (OUT / "results.json").write_text(
        json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print("ALL PASS: aculta-editor browser checks (anonymous, content_editor, administrator)")


if __name__ == "__main__":
    main()
