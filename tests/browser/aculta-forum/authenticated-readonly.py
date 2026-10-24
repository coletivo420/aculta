"""Leitura autenticada do painel do Fórum no ambiente local (127.0.0.1).

Faz login com um link único gerado por `drush uli` para um UID de teste e navega
somente por GET no painel, na fila de revisões, no formulário de mover tópico e
nas configurações. Não envia formulário, não move conteúdo e não altera
configuração.

O Apache local responde pelos hostnames do servidor de testes; o perfil do
Firefox resolve esses nomes para 127.0.0.1 via network.dns.localDomains, então
o servidor de testes não é tocado. O link único nunca é impresso nem gravado.

Variáveis: DISPLAY, SUITE_OUT (absoluto, fora do checkout), SUITE_UID (padrão
15), SUITE_TOPIC_NID (tópico público elegível; o padrão é descoberto por drush
quando vazio). Artefatos ficam em SUITE_OUT com modo 0600.
"""

import base64
import json
import os
import subprocess
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", "..", ".."))
sys.path.insert(0, os.path.join(os.path.dirname(HERE), "aculta-cpf-checkout"))
from wdlib import Driver, WDError  # noqa: E402

OUT = os.environ.get("SUITE_OUT")
DISPLAY = os.environ.get("DISPLAY")
UID = os.environ.get("SUITE_UID", "1")
NID = os.environ.get("SUITE_TOPIC_NID", "").strip()
if not OUT or not os.path.isabs(OUT) or not DISPLAY:
    raise SystemExit("Defina DISPLAY da sessão X11 e SUITE_OUT absoluto fora do checkout.")
os.umask(0o077)
os.makedirs(OUT, exist_ok=True)

HOSTS = ",".join([
    "aculta.toca.net.br",
    "conta.aculta.toca.net.br",
    "growroom420.aculta.toca.net.br",
])
PREFS = {"network.dns.localDomains": HOSTS}
MAIN = "https://aculta.toca.net.br"
ACCOUNT = "https://conta.aculta.toca.net.br"
FORUM = "https://growroom420.aculta.toca.net.br"
checks = []


def check(name, passed, detail=""):
    checks.append({"name": name, "pass": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


def drush(*args):
    return subprocess.run(
        [os.path.join(ROOT, "vendor/bin/drush"), *args],
        cwd=ROOT, capture_output=True, text=True, timeout=120, check=True,
    ).stdout.strip()


def screenshot(driver, name):
    png = driver.req("GET", f"/session/{driver.session}/screenshot")
    path = os.path.join(OUT, name)
    with open(path, "wb") as handle:
        handle.write(base64.b64decode(png))
    os.chmod(path, 0o600)


def visible_errors(driver):
    return driver.js("""return [...document.querySelectorAll('.messages--error, [role=alert]')]
      .map(e=>e.innerText.trim()).filter(Boolean).slice(0,5)""")


if not NID:
    NID = drush(
        "php:eval",
        '$nids = \\Drupal::entityQuery("node")->accessCheck(FALSE)'
        '->condition("type","aculta_forum_topic")->condition("status",1)->sort("nid")->range(0,1)->execute();'
        'echo $nids === [] ? "" : reset($nids);',
    )

uli = drush("uli", f"--uid={UID}", "--no-browser", f"--uri={ACCOUNT}")
driver = Driver(prefs=PREFS)
try:
    driver.get(uli)
    logged = False
    for _ in range(15):
        logged = driver.js(
            "return !!document.querySelector('a[href*=\"/user/logout\"], a[href*=\"/sair\"]')"
        )
        if logged:
            break
        if driver.js("return !!document.querySelector('form[action*=\"entrar\"], input[name=\"pass-reset-token\"]')"):
            try:
                driver.js("var b=document.querySelector('input[type=submit], button[type=submit]'); if (b) b.click();")
            except WDError:
                pass
        time.sleep(1)
    check("Sessão autenticada iniciada no host local simulado", logged, f"uid={UID}")
    check("Link único não aparece na URL final", "/user/reset/" not in driver.url(), driver.url().split("?")[0])

    pages = [
        ("painel", MAIN + "/painel-administrativo/forum"),
        ("fila", FORUM + "/painel-administrativo/forum/publicacoes-revisionaveis"),
        ("mover", FORUM + f"/painel-administrativo/forum/{NID}/mover-categoria"),
        ("configuracoes", MAIN + "/painel-administrativo/forum/configuracoes"),
    ]
    results = {}
    for key, url in pages:
        try:
            driver.get(url)
        except WDError as error:
            check(f"{key}: página abre", False, str(error))
            continue
        state = driver.js("""return {
          path: location.pathname,
          title: document.title,
          h1: [...document.querySelectorAll('h1')].map(e => e.innerText.trim()).filter(Boolean),
          errors: [...document.querySelectorAll('.messages--error, [role=alert]')]
            .map(e => e.innerText.trim())
            .filter(t => t && !t.includes('atualizações de segurança')).slice(0, 5),
          overflow: document.documentElement.scrollWidth - innerWidth,
        }""")
        if key == "painel":
            state["links"] = driver.js("""return [...document.querySelectorAll('a[href*="painel-administrativo/forum"]')]
              .map(a => a.getAttribute('href')).slice(0, 20)""")
        if key == "mover":
            state["form"] = driver.js("""return {
              select: !!document.querySelector('select[name="destination"]'),
              options: document.querySelectorAll('select[name="destination"] option').length,
              confirm: !!document.querySelector('input[name="confirm_move"]'),
              submit: !!document.querySelector('input[type="submit"]'),
            }""")
        if key == "configuracoes":
            state["form"] = driver.js("""return {
              move: !!document.querySelector('input[name="comment_move_enabled"]'),
              window: !!document.querySelector('input[name="edit_window_minutes"]'),
            }""")
        results[key] = state
        check(f"{key}: abre sem erro visível", not state["errors"], json.dumps(state, ensure_ascii=False))
        screenshot(driver, f"forum-autenticado-{key}.png")

    panel = results.get("painel", {})
    links = panel.get("links", [])
    check(
        "painel: liga Conteúdo, Comentários e Configurações",
        bool(panel) and any("/forum/conteudo" in link for link in links)
        and any("/forum/comentarios" in link for link in links)
        and any("/configuracoes" in link for link in links),
        json.dumps(links),
    )

    queue = results.get("fila", {})
    check("fila: título ou estado vazio reconhecível", bool(queue) and "publica" in queue.get("title", "").lower(), queue.get("title", ""))

    move = results.get("mover", {})
    form = move.get("form", {})
    check(
        "mover: formulário de confirmação para tópico elegível",
        bool(move) and form.get("select") and form.get("options", 0) > 0
        and form.get("confirm") and form.get("submit") and not move.get("errors"),
        json.dumps({"nid": NID, **form}, ensure_ascii=False),
    )

    settings = results.get("configuracoes", {})
    settings_form = settings.get("form", {})
    check(
        "configurações: opções do Fórum presentes",
        bool(settings) and settings_form.get("move") and settings_form.get("window"),
        json.dumps(settings_form),
    )

    driver.get(MAIN + "/painel-administrativo/forum")
    for width, label in ((1024, "1024px"), (390, "estreito")):
        driver.req("POST", f"/session/{driver.session}/window/rect", {"width": width, "height": 844})
        metrics = driver.js("return {innerWidth, document: document.documentElement.scrollWidth}")
        check(
            f"painel em {label} sem overflow horizontal",
            metrics["document"] <= metrics["innerWidth"] + 1,
            json.dumps(metrics),
        )
        screenshot(driver, f"forum-autenticado-painel-{label}.png")
finally:
    driver.close()
    passed = sum(1 for item in checks if item["pass"])
    with open(os.path.join(OUT, "results.json"), "w", encoding="utf-8") as handle:
        json.dump({"uid": UID, "topic": NID, "checks": checks}, handle, ensure_ascii=False, indent=2)
    print(f"Resultado: {passed}/{len(checks)} verificações; capturas em {OUT}")
    if passed != len(checks):
        sys.exit(1)
