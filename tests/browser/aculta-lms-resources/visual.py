#!/usr/bin/env python3
"""Firefox ESR smoke for the lms_course remote-video reference widget."""

import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
import uuid

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

RUNTIME = Path("/home/piradopirata/projetos/aculta")
RUNTIME_DRUSH = RUNTIME / "vendor/drush/drush/drush.php"
LOGIN_URI = "https://conta.aculta.toca.net.br"
COURSES_BASE = "https://cursos.aculta.toca.net.br"
EXPECTED_DB = "aculta_runtime"
EXPECTED_TABLES = 607


class Viewport:
    """Controls the CSS viewport through Firefox WebDriver BiDi."""

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

    def set(self, width, height):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": height},
        })
        time.sleep(0.5)

    def close(self):
        self.socket.close()


def run_drush(arguments, *, timeout=60):
    result = subprocess.run(
        ["php", str(RUNTIME_DRUSH), *arguments],
        cwd=RUNTIME,
        check=False,
        capture_output=True,
        text=True,
        timeout=timeout,
    )
    if result.returncode != 0:
        # Never include stdout: `uli` returns a bearer login URL.
        detail = result.stderr.strip().splitlines()[-1:] or ["Drush falhou."]
        raise RuntimeError(detail[0])
    return result.stdout.strip()


def verify_runtime():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Execute com Firefox ESR visível e DISPLAY=:0.")
    if os.uname().nodename != "toca":
        raise RuntimeError("A criação de fixture só é autorizada no servidor de desenvolvimento toca.")
    status = run_drush(["status", "--fields=db-name,root,uri"])
    expected_root = str(RUNTIME / "web")
    for value in (EXPECTED_DB, expected_root, "http://default"):
        if value not in status:
            raise RuntimeError("Drush não confirmou Runtime, root e URI esperados.")
    table_count = int(run_drush(["sqlq", "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"]))
    if table_count != EXPECTED_TABLES:
        raise RuntimeError(f"Contagem de tabelas inesperada: {table_count}; esperada {EXPECTED_TABLES}.")
    return table_count


def create_fixture(marker):
    php = f'''$marker = "{marker}";
$media = NULL;
$group = NULL;
try {{
  $media = \\Drupal\\media\\Entity\\Media::create([
    "bundle" => "remote_video",
    "name" => $marker . " media",
    "uid" => 1,
    "status" => 1,
    "field_media_oembed_video" => ["value" => "https://www.youtube.com/watch?v=ACULTA_QA_ONLY"],
  ]);
  $media->save();
  $group = \\Drupal\\group\\Entity\\Group::create([
    "type" => "lms_course",
    "label" => $marker . " course",
    "uid" => 1,
  ]);
  $group->set("field_lms_videos", [["target_id" => $media->id()]]);
  $group->save();
  echo $group->id() . " " . $media->id();
}}
catch (\\Throwable $exception) {{
  if ($group && $group->id()) {{ $group->delete(); }}
  if ($media && $media->id()) {{ $media->delete(); }}
  throw $exception;
}}'''
    output = run_drush(["php:eval", php])
    ids = output.split()
    if len(ids) != 2 or not all(value.isdigit() for value in ids):
        raise RuntimeError("Drush não retornou os IDs da fixture temporária.")
    return int(ids[0]), int(ids[1])


def cleanup_fixture(group_id, media_id, marker):
    php = f'''$group = \\Drupal\\group\\Entity\\Group::load({group_id});
if ($group) {{
  if (!str_starts_with((string) $group->label(), "{marker}")) {{ throw new \\RuntimeException("Group mudou; não removido."); }}
  $group->delete();
}}
$media = \\Drupal\\media\\Entity\\Media::load({media_id});
if ($media) {{
  if (!str_starts_with((string) $media->label(), "{marker}")) {{ throw new \\RuntimeException("Media mudou; não removido."); }}
  $media->delete();
}}
echo "removed";'''
    if run_drush(["php:eval", php]) != "removed":
        raise RuntimeError("A limpeza da fixture não foi confirmada.")


def one_time_login():
    output = run_drush(["uli", "--uid=1", f"--uri={LOGIN_URI}"])
    login_url = output.splitlines()[-1] if output else ""
    if not login_url.startswith(LOGIN_URI + "/"):
        raise RuntimeError("Drush não retornou um link único para a Conta de teste.")
    return login_url


def main():
    output = Path(os.environ.get("SUITE_OUT", "/tmp/aculta-lms-resources"))
    output.mkdir(mode=0o700, parents=True, exist_ok=True)
    table_count = verify_runtime()
    marker = "ACULTA_QA_COURSE_VIDEO_" + uuid.uuid4().hex[:12]
    fixture_path = output / "fixture-ids.json"
    group_id = None
    media_id = None
    driver = None
    viewport = None
    result_rows = []
    artifacts = {"runtime_tables_before": table_count, "checks": []}
    failure = None
    try:
        group_id, media_id = create_fixture(marker)
        artifacts.update({"temporary_group_id": group_id, "temporary_media_id": media_id})
        fixture_path.write_text(json.dumps(artifacts, indent=2), encoding="utf-8")

        driver = Driver(bidi=True)
        viewport = Viewport(driver)
        driver.get(one_time_login())
        time.sleep(2)
        editor_url = f"{COURSES_BASE}/group/{group_id}/edit"
        driver.get(editor_url)
        deadline = time.monotonic() + 25
        form_ready = False
        while time.monotonic() < deadline:
            form_ready = bool(driver.js("return !!document.querySelector('input[name^=\"field_lms_videos\"]')"))
            if form_ready:
                break
            time.sleep(0.5)

        body = driver.js("return document.body.innerText.replace(/\\s+/g, ' ')")
        result_rows.append(("formulário de turma autenticado", form_ready, driver.url()))
        result_rows.append(("rótulo Vídeos do curso", "Vídeos do curso" in body, ""))
        result_rows.append(("referência de vídeo temporária aparece no widget", marker in body, ""))
        if not form_ready:
            raise RuntimeError("Campo field_lms_videos não apareceu; acesso negado ou formulário sem widget.")

        for width, height in ((1280, 900), (390, 844), (320, 844)):
            viewport.set(width, height)
            dimensions = driver.js("return {viewport: innerWidth, scroll: document.documentElement.scrollWidth}")
            no_overflow = dimensions["viewport"] == width and dimensions["scroll"] <= width
            result_rows.append((f"sem overflow em {width}px", no_overflow, json.dumps(dimensions)))
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (output / f"course-video-{width}.png").write_bytes(base64.b64decode(screenshot))
            if not no_overflow:
                raise RuntimeError(f"Overflow em {width}px: {dimensions}")
    except Exception as exception:
        failure = str(exception)
        result_rows.append(("execução da revisão visual", False, failure))
    finally:
        if viewport:
            viewport.close()
        if driver:
            driver.close()
        if group_id and media_id:
            try:
                cleanup_fixture(group_id, media_id, marker)
                fixture_path.unlink(missing_ok=True)
            except Exception as exception:
                failure = f"Limpeza da fixture falhou: {exception}"

    artifacts["checks"] = [{"name": name, "ok": ok, "detail": detail} for name, ok, detail in result_rows]
    artifacts["runtime_tables_after"] = int(run_drush([
        "sqlq",
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()",
    ]))
    if artifacts["runtime_tables_after"] != table_count:
        raise RuntimeError("A contagem de tabelas mudou durante o teste visual.")
    artifacts["fixture_removed"] = True
    (output / "results.json").write_text(json.dumps(artifacts, ensure_ascii=False, indent=2), encoding="utf-8")
    failed = [row for row in result_rows if not row[1]]
    cleanup_state = "fixture removida" if not fixture_path.exists() else "fixture pendente de limpeza; IDs em fixture-ids.json"
    print(f"{len(result_rows) - len(failed)}/{len(result_rows)} verificações passaram; {cleanup_state}.")
    if failure:
        print(f"Revisão interrompida: {failure}")
    if failed or failure:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
