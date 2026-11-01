#!/usr/bin/env python3
"""Review Image processing settings in visible Firefox on the dev Runtime."""

import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
import uuid

from websockets.sync.client import connect

PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3]))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

RUNTIME = Path("/home/piradopirata/projetos/aculta")
DRUSH = RUNTIME / "vendor/drush/drush/drush.php"
HOST = os.environ.get("ACULTA_IMAGE_HOST", "https://aculta.toca.net.br")
ACCOUNT_HOST = os.environ.get("ACULTA_ACCOUNT_HOST", "https://conta.aculta.toca.net.br")
OUT = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-suite-image-admin"))
WIDTHS = (1280, 390, 320)
ROUTE = "/painel-administrativo/imagens/configuracoes"
MARKER = "qa_image_" + uuid.uuid4().hex[:12]
ROLE_ID = MARKER
USERNAME = MARKER
results = []


def check(name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


def run_drush(arguments, *, timeout=60):
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    result = subprocess.run(
        ["php", str(DRUSH), *arguments],
        cwd=RUNTIME,
        env=env,
        capture_output=True,
        text=True,
        timeout=timeout,
        check=False,
    )
    if result.returncode:
        detail = result.stderr.strip().splitlines()[-1:] or ["Drush falhou."]
        raise RuntimeError(detail[0])
    return result.stdout.strip()


def verify_runtime():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Execute com Firefox ESR visível e DISPLAY=:0.")
    if os.uname().nodename != "toca":
        raise RuntimeError("Fixtures deste teste só podem ser criadas no servidor toca.")
    status = run_drush(["status", "--fields=db-name,root,uri"])
    for expected in ("aculta_runtime", str(RUNTIME / "web"), "http://default"):
        if expected not in status:
            raise RuntimeError("Drush não confirmou o Runtime, root e URI esperados.")


def create_fixture():
    php = rf'''$role_id = "{ROLE_ID}";
$username = "{USERNAME}";
if (\Drupal\user\Entity\Role::load($role_id) || \Drupal::entityTypeManager()->getStorage("user")->loadByProperties(["name" => $username])) {{
  throw new \RuntimeException("Identificador descartável já existe.");
}}
$role = \Drupal\user\Entity\Role::create([
  "id" => $role_id,
  "label" => "QA Image settings temporary",
  "permissions" => ["administer aculta images"],
]);
$role->save();
$user = \Drupal\user\Entity\User::create([
  "name" => $username,
  "mail" => $username . "@example.invalid",
  "status" => 1,
]);
$user->addRole($role_id);
$user->setPassword(bin2hex(random_bytes(32)));
$user->save();
echo (int) $user->id();'''
    uid = run_drush(["php:eval", php])
    if not uid.isdigit():
        raise RuntimeError("Drush não retornou UID válido para a fixture.")
    return int(uid)


def cleanup_fixture():
    php = rf'''$storage = \Drupal::entityTypeManager()->getStorage("user");
$users = $storage->loadByProperties(["name" => "{USERNAME}"]);
foreach ($users as $user) {{ $user->delete(); }}
$role = \Drupal\user\Entity\Role::load("{ROLE_ID}");
if ($role) {{ $role->delete(); }}
echo "fixture removed";'''
    run_drush(["php:eval", php])


def make_login_url(uid):
    lines = run_drush(["uli", f"--uid={uid}", f"--uri={ACCOUNT_HOST}"]).splitlines()
    if not lines or not lines[-1].startswith(ACCOUNT_HOST):
        raise RuntimeError("Drush não retornou link ULI esperado.")
    return lines[-1]


class Viewport:
    """Sets a real CSS viewport through Firefox WebDriver BiDi."""

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
                    raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.4)

    def close(self):
        self.socket.close()


def screenshot(driver, filename):
    encoded = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / filename).write_bytes(base64.b64decode(encoded))


def main():
    if not OUT.is_absolute() or PROJECT != RUNTIME:
        raise RuntimeError("Use o checkout público do servidor e SUITE_OUT absoluto.")
    OUT.mkdir(mode=0o700, parents=True, exist_ok=True)
    verify_runtime()
    uid = None
    driver = None
    viewport = None
    fixture_started = False
    try:
        fixture_started = True
        uid = create_fixture()
        login_url = make_login_url(uid)
        driver = Driver(bidi=True)
        viewport = Viewport(driver)
        driver.get(login_url)
        deadline = time.monotonic() + 30
        while time.monotonic() < deadline:
            if driver.js("return document.body.classList.contains('user-logged-in')"):
                break
            time.sleep(0.25)
        check("ULI autenticou a fixture", bool(driver.js(
            "return document.body.classList.contains('user-logged-in')"
        )))

        driver.get(HOST + ROUTE)
        time.sleep(1)
        page = driver.js("""return {
          path: location.pathname,
          title: document.querySelector('main h1')?.innerText || '',
          body: document.querySelector('main')?.innerText || '',
          fields: [...document.querySelectorAll('main label, main legend')].map(x=>x.innerText.trim()),
          status: performance.getEntriesByType('navigation')[0]?.responseStatus || 0
        }""")
        check("painel carregou", page["path"] == ROUTE and page["status"] == 200,
              f"path={page['path']} status={page['status']}")
        expected_labels = (
            "Biblioteca de processamento",
            "Ativar compressão e redimensionamento de imagens inline",
            "Tipos processados em imagens inline",
            "Converter e substituir imagens compatíveis por WebP",
            "Qualidade WebP",
            "Formato de saída para os demais tipos",
            "Qualidade JPEG",
            "Nível de compressão PNG",
        )
        for label in expected_labels:
            check(f"controle visível: {label}", label in page["fields"])
        check("detecção de bibliotecas visível",
              "Bibliotecas e formatos detectados neste servidor" in page["body"])

        for width in WIDTHS:
            viewport.set(width)
            metrics = driver.js("""return {
              viewport: document.documentElement.clientWidth,
              document: document.documentElement.scrollWidth,
              body: document.body.scrollWidth,
              form: document.querySelector('main form')?.getBoundingClientRect().width || 0
            }""")
            passed = metrics["viewport"] == width and metrics["document"] <= width
            check(f"reflow em {width}px", passed,
                  f"viewport={metrics['viewport']} document={metrics['document']} form={metrics['form']}")
            screenshot(driver, f"image-settings-{width}px.png")

        (OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2))
        if not all(item["passed"] for item in results):
            raise RuntimeError("Uma ou mais verificações visuais falharam.")
    finally:
        if viewport:
            viewport.close()
        if driver:
            driver.close()
        if fixture_started:
            cleanup_fixture()


if __name__ == "__main__":
    main()
