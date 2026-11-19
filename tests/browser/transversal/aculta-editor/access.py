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
from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[4]
sys.path.insert(0, str(ROOT / "tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

DEPLOY_CONFIG = ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json"
DOMAIN_TEST_HOSTS = {
    entry["domain_id"]: entry["test_host"]
    for entry in json.loads(DEPLOY_CONFIG.read_text(encoding="utf-8"))["domain_transition"]["entries"]
}
BASE = os.environ.get(
    "EDITOR_BASE_URL", f"https://{DOMAIN_TEST_HOSTS['aculta_org']}"
).rstrip("/") + "/"
ACCOUNT_BASE = os.environ.get(
    "EDITOR_ACCOUNT_URL", f"https://{DOMAIN_TEST_HOSTS['conta_aculta_org']}"
)
SERVED_ROOT = Path(os.environ.get("EDITOR_SERVED_ROOT", str(ROOT)))
OUT = Path(os.environ["SUITE_OUT"]).resolve()
ADMIN_ONLY_ROUTES = {
    "full_html_format": "painel-administrativo/config/content/formats/manage/full_html",
    "aculta_adm_create": "node/add/aculta_adm",
}
VIEWPORTS = [(1280, 900), (390, 844), (320, 720)]


class Viewport:
    """Sets the exact CSS viewport through WebDriver BiDi."""

    def __init__(self, driver: Driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method: str, params: dict | None = None) -> dict:
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

    def set(self, width: int, height: int) -> None:
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": height},
        })

    def close(self) -> None:
        self.socket.close()


def drush_uli(uid: int) -> str:
    cmd = [
        "php",
        str(SERVED_ROOT / "vendor/drush/drush/drush.php"),
        f"--root={SERVED_ROOT / 'web'}",
        "ev",
        f"echo user_pass_reset_url(\\Drupal\\user\\Entity\\User::load({uid})) . '/entrar' . PHP_EOL;",
    ]
    raw = subprocess.check_output(cmd, text=True, timeout=30).strip().splitlines()[-1].strip()
    account_base = os.environ.get(
        "SUITE_LOGIN_URI",
        ACCOUNT_BASE,
    ).rstrip("/") + "/"
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


def check_viewports(driver: Driver, viewport: Viewport, prefix: str) -> dict:
    vp_results = {}
    for w, h in VIEWPORTS:
        viewport.set(w, h)
        # Gin and CKEditor resize observers can need a full layout turn after
        # BiDi changes the CSS viewport; measure only after widths settle.
        time.sleep(1.0)
        previous = None
        stable = 0
        metrics = {}
        for _ in range(10):
            metrics = driver.js("""return {
              innerWidth: window.innerWidth,
              scrollWidth: document.documentElement.scrollWidth,
              overflowPx: Math.max(0, document.documentElement.scrollWidth - window.innerWidth)
            };""")
            signature = (metrics["innerWidth"], metrics["scrollWidth"])
            stable = stable + 1 if signature == previous else 0
            if stable >= 2:
                break
            previous = signature
            time.sleep(0.2)
        png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
        (OUT / f"{prefix}-{w}.png").write_bytes(png)
        if metrics["innerWidth"] != w:
            raise RuntimeError(f"Viewport CSS incorreto em {prefix}: esperado {w}px, medido {metrics['innerWidth']}px")
        if metrics["overflowPx"] > 2:
            raise RuntimeError(f"Overflow horizontal em {prefix} ({w}px): {metrics}")
        vp_results[str(w)] = metrics
    return vp_results


def wait_for_ckeditor(driver: Driver, timeout: float = 12.0) -> dict:
    deadline = time.time() + timeout
    last = {}
    while time.time() < deadline:
        last = driver.js("""return {
          url: location.href,
          responseStatus: performance.getEntriesByType('navigation')[0]?.responseStatus ?? null,
          bodyTextLength: document.body?.innerText.length ?? 0,
          formCount: document.forms.length,
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
    driver = Driver(bidi=True)
    viewport = Viewport(driver)
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
        sanitized["viewports"] = check_viewports(driver, viewport, "anon-rendered-node6")
        results["anonymous"]["rendered_sanitization"] = sanitized
        print("PASS anonymous.rendered_sanitization: conteúdo renderizado sem tags/atributos executáveis e sem overflow")
    finally:
        viewport.close()
        driver.close()

    # 2. Content Editor (uid=15): denied on full_html and aculta_adm; CKEditor 5
    # loads on the Revista host with revista_editorial and without full_html.
    driver = Driver(bidi=True)
    viewport = Viewport(driver)
    try:
        driver.get(drush_uli(15))
        time.sleep(1.5)
        for name, path in ADMIN_ONLY_ROUTES.items():
            results["content_editor"][name] = check_denied(driver, f"editor_{name}", path)
            print(f"PASS content_editor.{name}: negado para content_editor")

        # Node creation/edit routes are canonical on the main host. The
        # Magazine host returns 404 for this administrative route by design.
        driver.get(urljoin(BASE, "node/add/revista"))
        time.sleep(1.5)
        ck_editor = wait_for_ckeditor(driver)
        if ck_editor.get("responseStatus") != 200:
            screenshot = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
            (OUT / "content-editor-revista-route-unavailable.png").write_bytes(screenshot)
            raise RuntimeError(
                "Rota de criação da Revista indisponível para avaliar o editor: "
                f"HTTP {ck_editor.get('responseStatus')}, formulário(s)={ck_editor.get('formCount')}, "
                f"texto={ck_editor.get('bodyTextLength')} caracteres. CKEditor não foi renderizado; "
                "não é possível validar seletores nem atribuir falha ao editor."
            )
        if not ck_editor.get("ckEditor") or "full_html" in ck_editor.get("formatOptions", []):
            raise RuntimeError(f"CKEditor inválido ou full_html exposto para content_editor: {ck_editor}")
        ck_editor["viewports"] = check_viewports(driver, viewport, "editor-node-add-revista")
        results["content_editor"]["revista_ckeditor"] = ck_editor
        print("PASS content_editor.revista_ckeditor: CKEditor 5 ativo sem full_html e sem overflow (1280/390/320)")
    finally:
        viewport.close()
        driver.close()

    # 3. Administrator (uid=1): allowed on aculta_adm; CKEditor 5 is verified on page.
    driver = Driver(bidi=True)
    viewport = Viewport(driver)
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
        adm_obs["viewports"] = check_viewports(driver, viewport, "admin-node-add-aculta-adm")
        results["administrator"]["aculta_adm_create"] = adm_obs
        print("PASS administrator.aculta_adm_create: formulário aculta_adm disponível sem overflow (1280/390/320)")

        driver.get(urljoin(BASE, "node/add/page"))
        time.sleep(1.5)
        page_ck = wait_for_ckeditor(driver)
        if not page_ck.get("ckEditor") or page_ck.get("toolbarButtons", 0) < 5:
            raise RuntimeError(f"CKEditor 5 não inicializou em node/add/page para admin: {page_ck}")
        page_ck["viewports"] = check_viewports(driver, viewport, "admin-node-add-page")
        results["administrator"]["page_ckeditor"] = page_ck
        print("PASS administrator.page_ckeditor: CKEditor 5 e barra de ferramentas ativos sem overflow (1280/390/320)")
    finally:
        viewport.close()
        driver.close()

    (OUT / "results.json").write_text(
        json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print("ALL PASS: aculta-editor browser checks (anonymous, content_editor, administrator)")


if __name__ == "__main__":
    main()
