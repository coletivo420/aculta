"""Revisão de acessibilidade pré-deploy do Fórum no ambiente local.

Abre páginas públicas e administrativas do Fórum com sessão autenticada e
verifica, sem submeter formulário: título principal único, hierarquia de
títulos, nome acessível de controles, `alt` de imagens, texto de links, IDs
únicos e indicador de foco ao percorrer com Tab.

O Apache local responde pelos hostnames do servidor de testes; o perfil do
Firefox resolve esses nomes para 127.0.0.1 via network.dns.localDomains, então
o servidor de testes não é tocado. O link único nunca é impresso.

Variáveis: DISPLAY, SUITE_OUT (absoluto, fora do checkout), SUITE_UID (padrão
1), SUITE_THEME (olivero ou aculta420; quando presente o script troca o tema
padrão local e restaura no final). Artefatos ficam em SUITE_OUT com modo 0600.
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
THEME = os.environ.get("SUITE_THEME", "").strip()
if not OUT or not os.path.isabs(OUT) or not DISPLAY:
    raise SystemExit("Defina DISPLAY da sessão X11 e SUITE_OUT absoluto fora do checkout.")
os.umask(0o077)
os.makedirs(OUT, exist_ok=True)

PREFS = {"network.dns.localDomains": ",".join([
    "aculta.toca.net.br",
    "conta.aculta.toca.net.br",
    "growroom420.aculta.toca.net.br",
])}
ACCOUNT = "https://conta.aculta.toca.net.br"
MAIN = "https://aculta.toca.net.br"
FORUM = "https://growroom420.aculta.toca.net.br"
checks = []


def check(name, passed, detail=""):
    checks.append({"name": name, "pass": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


def drush(*args):
    return subprocess.run(
        [os.path.join(ROOT, "vendor/bin/drush"), *args],
        cwd=ROOT, capture_output=True, text=True, timeout=180, check=True,
    ).stdout.strip()


def screenshot(driver, name):
    png = driver.req("GET", f"/session/{driver.session}/screenshot")
    path = os.path.join(OUT, name)
    with open(path, "wb") as handle:
        handle.write(base64.b64decode(png))
    os.chmod(path, 0o600)


def tab(driver, times=1):
    for _ in range(times):
        driver.req("POST", f"/session/{driver.session}/actions", {
            "actions": [{
                "type": "key",
                "id": "keyboard",
                "actions": [
                    {"type": "keyDown", "value": ""},
                    {"type": "keyUp", "value": ""},
                ],
            }],
        })


AUDIT_JS = r"""
const nameOf = (el) => {
  const labels = el.labels ? [...el.labels].map(l => l.innerText.trim()).join(' ') : '';
  return (el.getAttribute('aria-label') || labels ||
    (el.getAttribute('aria-labelledby') ? 'labelledby' : '') ||
    el.getAttribute('title') || '').trim();
};
const inputTypes = ['hidden', 'submit', 'button', 'reset', 'image'];
const controls = [...document.querySelectorAll('main input, main select, main textarea')]
  .filter(el => !inputTypes.includes((el.type || '').toLowerCase()) && !el.disabled)
  .filter(el => el.offsetParent !== null || el.type === 'hidden');
const unnamed = controls.filter(el => !nameOf(el))
  .map(el => ({tag: el.tagName, type: el.type, name: el.name}));
const headings = [...document.querySelectorAll('main h1, main h2, main h3, main h4, main h5, main h6')]
  .filter(el => el.offsetParent !== null)
  .map(el => ({level: Number(el.tagName[1]), text: el.innerText.trim().slice(0, 60)}));
const jumps = [];
let previous = 0;
for (const heading of headings) {
  if (previous && heading.level > previous + 1) {
    jumps.push(`${previous}->${heading.level}: ${heading.text}`);
  }
  previous = heading.level;
}
const images = [...document.querySelectorAll('main img')]
  .filter(el => el.offsetParent !== null)
  .filter(el => !el.hasAttribute('alt'))
  .map(el => el.getAttribute('src'));
const links = [...document.querySelectorAll('main a[href]')]
  .filter(el => el.offsetParent !== null)
  .filter(el => el.getAttribute('aria-hidden') !== 'true')
  .filter(el => !(el.textContent.trim() || el.getAttribute('aria-label') ||
    el.getAttribute('title') || el.querySelector('img[alt]:not([alt=""])')))
  .map(el => el.getAttribute('href'));
const ids = [...document.querySelectorAll('[id]')].map(el => el.id);
const duplicates = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
const mainFocusables = [...document.querySelectorAll(
  'main a[href], main button, main input:not([type="hidden"]), main select, main textarea, main [tabindex]:not([tabindex="-1"])'
)].filter(el => el.offsetParent !== null && !el.disabled).length;
return {
  path: location.pathname,
  mainFocusables,
  h1: headings.filter(h => h.level === 1).length,
  jumps,
  unnamed,
  images,
  links,
  duplicates: duplicates.slice(0, 5),
  theme: document.documentElement.getAttribute('data-theme') ||
    (document.querySelector('link[href*="olivero"]') ? 'olivero' : 'aculta420'),
};
"""

FOCUS_JS = r"""
const el = document.activeElement;
if (!el || el === document.body) { return null; }
// The focus ring may be drawn on an ancestor through :focus-within.
let indicator = null;
let node = el;
for (let depth = 0; node && depth < 4; depth += 1) {
  const style = getComputedStyle(node);
  const outline = style.outlineStyle !== 'none' && style.outlineWidth !== '0px';
  if (outline || style.boxShadow !== 'none') {
    indicator = {
      outline: `${style.outlineStyle} ${style.outlineWidth}`,
      shadow: style.boxShadow,
      depth,
    };
    break;
  }
  node = node.parentElement;
}
return {
  tag: el.tagName,
  name: (el.innerText || el.getAttribute('aria-label') || el.getAttribute('name') || '').trim().slice(0, 40),
  indicator,
  visible: el.offsetParent !== null,
  inMain: !!el.closest('main'),
};
"""

original_theme = None
driver = None
try:
    if THEME:
        original_theme = drush(
            "php:eval", 'echo \\Drupal::config("system.theme")->get("default");'
        )
        if THEME not in ("olivero", "aculta420"):
            raise SystemExit("SUITE_THEME deve ser olivero ou aculta420.")
        drush("config:set", "system.theme", "default", THEME, "-y")
        drush("cr")

    nid = drush(
        "php:eval",
        '$nids = \\Drupal::entityQuery("node")->accessCheck(FALSE)'
        '->condition("type","aculta_forum_topic")->condition("status",1)->sort("nid")->range(0,1)->execute();'
        'echo $nids === [] ? "" : reset($nids);',
    )
    uli = drush("uli", f"--uid={UID}", "--no-browser", f"--uri={ACCOUNT}")
    driver = Driver(prefs=PREFS)
    driver.get(uli)
    logged = False
    for _ in range(15):
        logged = driver.js(
            "return !!document.querySelector('a[href*=\"/user/logout\"], a[href*=\"/sair\"]')"
        )
        if logged:
            break
        time.sleep(1)
    check("Sessão autenticada iniciada no host local simulado", logged, f"uid={UID}")

    pages = [
        ("inicio", FORUM + "/"),
        ("categorias", FORUM + "/categorias"),
        ("pesquisar", FORUM + "/pesquisar"),
        ("painel", MAIN + "/painel-administrativo/forum"),
        ("configuracoes", MAIN + "/painel-administrativo/forum/configuracoes"),
    ]
    if nid:
        pages.append(("mover-topico", FORUM + f"/painel-administrativo/forum/{nid}/mover-categoria"))

    for key, url in pages:
        driver.get(url)
        state = driver.js(AUDIT_JS)
        prefix = f"{key}:"
        check(f"{prefix} um único título principal", state["h1"] == 1,
              json.dumps({"h1": state["h1"], "theme": state["theme"]}))
        check(f"{prefix} hierarquia de títulos sem salto", not state["jumps"],
              json.dumps(state["jumps"], ensure_ascii=False))
        check(f"{prefix} controles com nome acessível", not state["unnamed"],
              json.dumps(state["unnamed"][:5], ensure_ascii=False))
        check(f"{prefix} imagens com atributo alt", not state["images"],
              json.dumps(state["images"][:5], ensure_ascii=False))
        check(f"{prefix} links com texto discernível", not state["links"],
              json.dumps(state["links"][:5], ensure_ascii=False))
        check(f"{prefix} sem IDs duplicados", not state["duplicates"],
              json.dumps(state["duplicates"], ensure_ascii=False))

        driver.js("document.body.focus(); window.scrollTo(0, 0);")
        stops = []
        for _ in range(40):
            tab(driver)
            focus = driver.js(FOCUS_JS)
            # The global administration toolbar is theme furniture, not the
            # Forum surface; measure the focus ring inside the main content.
            if focus is None or not focus["visible"] or not focus["inMain"]:
                continue
            stops.append(focus)
        unique = {f"{f['tag']}|{f['name']}" for f in stops}
        expected = min(4, state["mainFocusables"])
        check(f"{prefix} Tab percorre elementos distintos",
              expected > 0 and len(unique) >= expected,
              json.dumps({"alcancados": sorted(unique), "focaveis_no_conteudo": state["mainFocusables"]},
                         ensure_ascii=False))
        visible = [f for f in stops if f["indicator"]]
        check(f"{prefix} foco detectável nos controles do conteúdo",
              len(visible) >= min(4, len(stops)) and bool(stops),
              json.dumps({"paradas": len(stops), "com_indicador": len(visible),
                          "sem_indicador": [f["name"] for f in stops if not f["indicator"]]},
                         ensure_ascii=False))
        screenshot(driver, f"forum-a11y-{key}.png")
finally:
    if driver is not None:
        driver.close()
    if THEME and original_theme is not None:
        drush("config:set", "system.theme", "default", original_theme, "-y")
        drush("cr")
    passed = sum(1 for item in checks if item["pass"])
    with open(os.path.join(OUT, "results.json"), "w", encoding="utf-8") as handle:
        json.dump({"uid": UID, "theme": THEME or "atual", "checks": checks},
                  handle, ensure_ascii=False, indent=2)
    print(f"Resultado: {passed}/{len(checks)} verificações; capturas em {OUT}")
    if passed != len(checks):
        sys.exit(1)
