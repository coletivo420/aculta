#!/usr/bin/env python3
"""Firefox ESR smoke for H5P discussion links in a temporary LMS course."""

import base64
import json
import os
import socket
import subprocess
import sys
import time
from pathlib import Path

from websockets.sync.client import connect

PROJECT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

RUNTIME = Path(os.environ.get("ACULTA_TEST_ROOT", "/home/piradopirata/projetos/aculta"))
DRUSH = RUNTIME / "vendor/bin/drush"
OUT = Path(os.environ["SUITE_OUT"])
COURSES = "https://cursos.aculta.toca.net.br"
ACCOUNT = "https://conta.aculta.toca.net.br"
WIDTHS = (1280, 390, 320)


def drush(*args, env=None):
    result = subprocess.run(
        [str(DRUSH), *args], cwd=RUNTIME, env=env,
        capture_output=True, text=True, timeout=120,
    )
    if result.returncode:
        combined = result.stdout.splitlines() + result.stderr.splitlines()
        diagnostic = next((line.strip() for line in combined if "setup_error" in line), "")
        if not diagnostic:
            diagnostic = next((line.strip() for line in combined if "Error:" in line), "")
        raise RuntimeError(f"Drush falhou (exit {result.returncode}): {diagnostic[:240] or 'detalhes omitidos'}")
    return result.stdout.strip()


def check(results, name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


def run_php(code, env):
    return drush("php:eval", code, env=env)


def set_width(driver, bidi, width):
    request_id = int(time.time_ns() % 1_000_000_000)
    bidi.send(json.dumps({"id": request_id, "method": "browsingContext.getTree", "params": {}}))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == request_id:
            break
    context = response["result"]["contexts"][0]["context"]
    request_id += 1
    bidi.send(json.dumps({
        "id": request_id,
        "method": "browsingContext.setViewport",
        "params": {"context": context, "viewport": {"width": width, "height": 900}},
    }))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == request_id:
            break
    time.sleep(0.25)
    return int(driver.js("return window.innerWidth"))


def main():
    os.umask(0o077)
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("Execute no Firefox ESR visível com DISPLAY=:0.")
    if not OUT.is_absolute() or OUT.is_relative_to(PROJECT):
        raise SystemExit("SUITE_OUT deve ser absoluto e fora do repositório.")
    if socket.gethostname().split(".")[0] != "toca":
        raise SystemExit("A fixture só pode ser criada no servidor de teste toca.")
    OUT.mkdir(parents=True, exist_ok=True)
    os.chmod(OUT, 0o700)
    profile_root = OUT / "firefox-profiles"
    profile_root.mkdir(mode=0o700, exist_ok=True)
    os.environ["ACULTA_FF_PROFILE_ROOT"] = str(profile_root)

    status = drush("status", "--fields=uri,db-name,root")
    if "aculta_runtime" not in status or str(RUNTIME / "web") not in status:
        raise SystemExit("Drush não aponta ao Runtime de teste esperado; fixture não criada.")

    suffix = f"{time.time_ns()}"
    username = f"qa_forum_h5p_{suffix}"
    email = f"{username}@example.com"
    user_name = f"[TESTE H5P LMS] curso {suffix}"
    topic_title = f"[TESTE H5P LMS] discussão {suffix}"
    env = os.environ.copy()
    env.update({
        "ACULTA_H5P_TEST_USER": username,
        "ACULTA_H5P_TEST_EMAIL": email,
        "ACULTA_H5P_TEST_COURSE": user_name,
        "ACULTA_H5P_TEST_TOPIC": topic_title,
    })
    fixture = {}
    driver = None
    bidi = None
    results = []
    try:
        setup = r'''$user = NULL; $group = NULL; $topic = NULL;
try {
  \Drupal::service("aculta_lms_h5p.framework");
  $h5p_ids = \Drupal::entityQuery("aculta_h5p_content")->accessCheck(FALSE)->sort("id")->range(0, 1)->execute();
  if ($h5p_ids === []) { throw new \RuntimeException("No H5P content available for test."); }
  $h5p_id = (int) reset($h5p_ids);
  $user = \Drupal\user\Entity\User::create(["name" => getenv("ACULTA_H5P_TEST_USER"), "mail" => getenv("ACULTA_H5P_TEST_EMAIL"), "status" => 1]);
  $user->setPassword(bin2hex(random_bytes(24)));
  $user->save();
  $group = \Drupal\group\Entity\Group::create(["type" => "lms_course", "label" => getenv("ACULTA_H5P_TEST_COURSE"), "uid" => $user->id()]);
  $group->set("field_h5p_activities", [["h5p_content_id" => $h5p_id]]);
  $group->save();
  $group->addMember($user, ["group_roles" => ["lms_course-teacher"]])->save();
  $topic = \Drupal\node\Entity\Node::create(["type" => "aculta_lms_topic", "title" => getenv("ACULTA_H5P_TEST_TOPIC"), "uid" => $user->id(), "status" => 1, "body" => ["value" => "Temporary test discussion.", "format" => "plain_text"], "field_aculta_discussion" => ["status" => 2], "field_h5p_content" => ["target_id" => $h5p_id]]);
  $topic->save();
  $group->addRelationship($topic, "group_node:aculta_lms_topic")->save();
  print json_encode(["uid" => (int) $user->id(), "gid" => (int) $group->id(), "nid" => (int) $topic->id(), "h5p_id" => $h5p_id]);
}
catch (\Throwable $error) {
  if ($topic && !$topic->isNew()) { $topic->delete(); }
  if ($group && !$group->isNew()) { $group->delete(); }
  if ($user && !$user->isNew()) { $user->delete(); }
  print json_encode(["setup_error" => get_class($error), "message" => $error->getMessage()]);
  throw $error;
}'''
        fixture = json.loads(run_php(setup, env).splitlines()[-1])
        check(results, "fixtures efêmeras de curso, educador e tópico criadas", all(fixture.values()))

        login = drush("uli", f"--uid={fixture['uid']}", "--no-browser", f"--uri={ACCOUNT}")
        driver = Driver(bidi=True)
        bidi = connect(driver.websocket_url)
        driver.get(login)
        for _ in range(80):
            if driver.js("return document.readyState === 'complete'") and "/user/login" not in driver.url():
                break
            time.sleep(0.25)
        check(results, "link único autenticou o educador de teste", "/user/login" not in driver.url())

        driver.get(f"{COURSES}/group/{fixture['gid']}")
        for _ in range(80):
            if driver.js("return document.readyState === 'complete'"):
                break
            time.sleep(0.25)
        page = driver.js("""const main = document.querySelector('main') || document.body;
          const box = main.querySelector('[data-portal-ui="lms-h5p-discussions"]');
          const link = [...(box?.querySelectorAll('a[href]') || [])].find(a => a.textContent.trim() === arguments[0]);
          return {path: location.pathname, h1: main.querySelector('h1')?.innerText || '',
            title: document.title, box: !!box, link: link ? link.getAttribute('href') : '',
            text: box?.innerText || '', errors: [...document.querySelectorAll('.messages--error,[role=alert]')]
              .map(e => e.innerText.trim()).filter(Boolean)};""", [topic_title])
        expected_path = f"/group/{fixture['gid']}"
        link_ok = page["link"].rstrip("/").endswith(f"/node/{fixture['nid']}")
        check(results, "course page contém o H5P e o contêiner de discussões", page["path"] == expected_path and page["box"] and topic_title in page["text"], json.dumps({"path": page["path"], "h1": page["h1"], "box": page["box"], "errors": page["errors"]}, ensure_ascii=False))
        check(results, "link da discussão aponta ao tópico autorizado", link_ok, page["link"])

        for width in WIDTHS:
            actual = set_width(driver, bidi, width)
            metrics = driver.js("""const box = document.querySelector('[data-portal-ui="lms-h5p-discussions"]');
              const link = [...(box?.querySelectorAll('a[href]') || [])].find(a => a.textContent.trim() === arguments[0]);
              const rect = link?.getBoundingClientRect();
              return {viewport: innerWidth, document: document.documentElement.scrollWidth,
                linkVisible: !!rect && rect.left >= 0 && rect.right <= innerWidth};""", [topic_title])
            screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
            image = OUT / f"forum-lms-h5p-{width}.png"
            image.write_bytes(base64.b64decode(screenshot))
            os.chmod(image, 0o600)
            check(results, f"discussion reflow em {width}px", actual == width and metrics["document"] <= width and metrics["linkVisible"], json.dumps(metrics))

    finally:
        if bidi:
            bidi.close()
        if driver:
            driver.close()
        if fixture:
            cleanup = r'''$topic_id = (int) getenv("ACULTA_H5P_TEST_NID");
$group_id = (int) getenv("ACULTA_H5P_TEST_GID");
$uid = (int) getenv("ACULTA_H5P_TEST_UID");
$topic = \Drupal\node\Entity\Node::load($topic_id);
if ($topic && $topic->bundle() === "aculta_lms_topic" && str_starts_with($topic->label(), "[TESTE H5P LMS] discussão ")) { $topic->delete(); }
$group = \Drupal\group\Entity\Group::load($group_id);
if ($group && $group->bundle() === "lms_course" && str_starts_with($group->label(), "[TESTE H5P LMS] curso ")) { $group->delete(); }
$user = \Drupal\user\Entity\User::load($uid);
if ($user && str_starts_with($user->getAccountName(), "qa_forum_h5p_")) { $user->delete(); }
$left = ["node" => (bool) \Drupal\node\Entity\Node::load($topic_id), "group" => (bool) \Drupal\group\Entity\Group::load($group_id), "user" => (bool) \Drupal\user\Entity\User::load($uid)];
print json_encode($left);
if (in_array(TRUE, $left, TRUE)) { throw new \RuntimeException("A test fixture remains."); }'''
            env.update({
                "ACULTA_H5P_TEST_UID": str(fixture["uid"]),
                "ACULTA_H5P_TEST_GID": str(fixture["gid"]),
                "ACULTA_H5P_TEST_NID": str(fixture["nid"]),
            })
            try:
                remaining = json.loads(run_php(cleanup, env).splitlines()[-1])
                check(results, "fixtures Runtime removidas e verificadas", not any(remaining.values()), json.dumps(remaining))
            except Exception as error:
                check(results, "fixtures Runtime removidas e verificadas", False, str(error))
        os.chmod(OUT, 0o700)
        summary = OUT / "results.json"
        summary.write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        os.chmod(summary, 0o600)
    return 0 if all(item["passed"] for item in results) else 1


if __name__ == "__main__":
    raise SystemExit(main())
