#!/usr/bin/env python3
"""Firefox ESR visível: gatilho hambúrguer e painel do account-menu (ACULTA420 V2).

PHASE=before registra o estado atual, com avatar no gatilho, sem julgamento.
PHASE=after confere o comportamento novo: gatilho, Escape, X, foco, Tab preso,
Enter nos links, colunas, sem rolagem horizontal e contraste de texto.

Variáveis: DISPLAY=:0, SUITE_OUT (saída externa), PHASE, ACULTA_BASE_URL,
ACULTA_TEST_UID, ACULTA_PROJECT_ROOT (checkout com drush). Nenhum dado é gravado.
"""

import base64
import json
import os
import re
import subprocess
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parents[1] / "shop/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

PHASE = os.environ.get("PHASE", "after")
if PHASE not in ("before", "after"):
    raise SystemExit("PHASE deve ser before ou after.")
BASE = os.environ.get("ACULTA_BASE_URL", "https://aculta.toca.net.br").rstrip("/")
UID = os.environ.get("ACULTA_TEST_UID", "5")
PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", "/home/piradopirata/projetos/aculta"))
OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT para guardar PNG e JSON fora do repositório.")
OUT = Path(OUT_VALUE) / PHASE
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(Path(OUT_VALUE) / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

WIDTHS = [320, 1440]
KEY_TAB, KEY_ENTER, KEY_ESC = "", "", ""
results = []


def check(name, ok, detail=""):
    if PHASE == "before":
        ok = None
    results.append({"name": name, "ok": ok, "detail": str(detail)})
    label = "OBS " if PHASE == "before" else ("PASS" if ok else "FAIL")
    print(f"{label}  {name}" + (f" — {detail}" if detail else ""))


def login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    proc = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", f"--uid={UID}", f"--uri={BASE}"],
        cwd=PROJECT, check=True, capture_output=True, text=True, timeout=60, env=env,
    )
    return proc.stdout.strip().splitlines()[-1]


def luminance(rgb):
    def chan(c):
        c = c / 255
        return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
    r, g, b = rgb
    return 0.2126 * chan(r) + 0.7152 * chan(g) + 0.0722 * chan(b)


def ratio(css_a, css_b):
    a = [int(x) for x in re.findall(r"[\d.]+", css_a)[:3]]
    b = [int(x) for x in re.findall(r"[\d.]+", css_b)[:3]]
    la, lb = sorted([luminance(a), luminance(b)], reverse=True)
    return (la + 0.05) / (lb + 0.05)


driver = Driver()


def set_width(width):
    driver.req("POST", f"/session/{driver.session}/window/rect", {"width": width, "height": 900})
    time.sleep(0.3)


def press(key):
    driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
        "type": "key", "id": "kbd",
        "actions": [{"type": "keyDown", "value": key}, {"type": "keyUp", "value": key}],
    }]})
    time.sleep(0.25)


def shot(name):
    image = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"{name}.png").write_bytes(base64.b64decode(image))


def set_theme(mode):
    driver.js("document.documentElement.setAttribute('data-theme', arguments[0]); return true", [mode])
    time.sleep(0.3)


FACTS_JS = """
const nav = document.querySelector('.aculta-account-menu');
const sum = nav && nav.querySelector('.aculta-account-menu__trigger');
const r = sum ? sum.getBoundingClientRect() : null;
const hid = sum ? sum.querySelector('.visually-hidden') : null;
return {
  hasNav: !!nav,
  navCount: document.querySelectorAll('.aculta-account-menu').length,
  summaryAvatar: !!(sum && sum.querySelector('.avatar, img, [class*="avatar"]')),
  summaryIcon: !!(sum && sum.querySelector('.aculta-account-menu__icon')),
  summaryLabel: hid ? hid.textContent.trim() : null,
  summaryW: r ? Math.round(r.width) : 0,
  summaryH: r ? Math.round(r.height) : 0,
  ariaExpanded: sum ? sum.getAttribute('aria-expanded') : null,
  overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  institutionBars: document.querySelectorAll('.aculta-institution-bar').length,
};
"""

PANEL_JS = """
const d = document.querySelector('.aculta-account-dialog');
const p = d;
const close = d.querySelector('[data-aculta-dialog-close]');
const cols = Array.from(d.querySelectorAll('.aculta-account-menu__col'));
const links = Array.from(p.querySelectorAll('a[href]'));
const pr = p.getBoundingClientRect();
const cr = close ? close.getBoundingClientRect() : null;
const cs = getComputedStyle(p);
const ls = links[0] ? getComputedStyle(links[0]) : null;
return {
  open: d.open,
  panelW: Math.round(pr.width), panelH: Math.round(pr.height),
  innerW: window.innerWidth, innerH: window.innerHeight,
  panelBg: cs.backgroundColor, panelFg: cs.color,
  linkColor: ls ? ls.color : null,
  closeExists: !!close,
  closeTopRight: cr ? (cr.right >= window.innerWidth - 24 && cr.top <= 64) : false,
  closeSize: cr ? [Math.round(cr.width), Math.round(cr.height)] : [0, 0],
  activeInPanel: p.contains(document.activeElement),
  cols: cols.length,
  colTops: cols.map(c => Math.round(c.getBoundingClientRect().top)),
  colLefts: cols.map(c => Math.round(c.getBoundingClientRect().left)),
  links: links.length,
  minLinkH: links.length ? Math.min(...links.map(a => Math.round(a.getBoundingClientRect().height))) : 0,
  overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
  linkLabels: links.map(a => a.textContent.trim()),
  linkHrefs: links.map(a => a.getAttribute('href')),
  sectionLabels: Array.from(d.querySelectorAll('.aculta-account-menu__col')).map(c => c.getAttribute('aria-label')),
  identity: !!d.querySelector('.aculta-account-menu__identity'),
  institutionBars: document.querySelectorAll('.aculta-institution-bar').length,
};
"""


def open_panel():
    driver.js("const t = document.querySelector('.aculta-account-menu__trigger'); t.focus(); t.click(); return true")
    time.sleep(0.4)


def close_is_closed():
    return driver.js("return !document.querySelector('.aculta-account-dialog').open")


def state_pass(state, theme, width):
    tag = f"{state}-{theme}-{width}"
    driver.js("document.querySelector('.aculta-account-menu__trigger') && 0; return true")
    facts = driver.js(FACTS_JS)
    print(f"  facts[{tag}] {json.dumps(facts, ensure_ascii=False)}")
    if PHASE == "before":
        check(f"{tag}: gatilho observado (avatar?)", True,
              f"avatar={facts['summaryAvatar']}, ícone={facts['summaryIcon']}, rótulo={facts['summaryLabel']}")
    else:
        check(f"{tag}: gatilho sem avatar", not facts["summaryAvatar"], f"avatar={facts['summaryAvatar']}")
        check(f"{tag}: gatilho com três linhas e rótulo 'Abrir menu'",
              facts["summaryIcon"] and facts["summaryLabel"] == "Abrir menu", facts["summaryLabel"])
        check(f"{tag}: alvo de toque >= 44 px", facts["summaryW"] >= 44 and facts["summaryH"] >= 44,
              f"{facts['summaryW']}x{facts['summaryH']}")
        check(f"{tag}: aria-expanded=false antes de abrir", facts["ariaExpanded"] == "false", facts["ariaExpanded"])
        check(f"{tag}: sem rolagem horizontal na página", facts["overflowX"] <= 0, facts["overflowX"])
        check(f"{tag}: barra institucional única", facts["institutionBars"] == 1, facts["institutionBars"])
    shot(f"{tag}-fechado")

    if PHASE == "before":
        return
    open_panel()
    p = driver.js(PANEL_JS)
    print(f"  panel[{tag}] {json.dumps(p, ensure_ascii=False)}")
    check(f"{tag}: abre ao clicar no gatilho", p["open"], "")
    check(f"{tag}: painel ocupa a tela", p["panelW"] == p["innerW"] and p["panelH"] >= p["innerH"] - 1,
          f"{p['panelW']}x{p['panelH']} em {p['innerW']}x{p['innerH']}")
    check(f"{tag}: X no canto superior direito com 44 px", p["closeExists"] and p["closeTopRight"]
          and p["closeSize"][0] >= 44 and p["closeSize"][1] >= 44, p["closeSize"])
    check(f"{tag}: foco entra no painel ao abrir", p["activeInPanel"], "")
    check(f"{tag}: sem logotipo no painel", p["institutionBars"] == 1 and not driver.js(
        "return !!document.querySelector('.aculta-account-dialog img[src*=logo]')"),
        "")
    if width == 320:
        check(f"{tag}: uma coluna na ordem 1, 2, 3",
              p["cols"] == 3 and len(set(p["colLefts"])) == 1 and p["colTops"] == sorted(p["colTops"]),
              f"tops={p['colTops']}")
    else:
        check(f"{tag}: três colunas no desktop", p["cols"] == 3 and len(set(p["colTops"])) == 1,
              f"tops={p['colTops']}")
    check(f"{tag}: alvos dos links >= 44 px", p["minLinkH"] >= 44, p["minLinkH"])
    check(f"{tag}: sem rolagem horizontal com painel aberto", p["overflowX"] <= 0, p["overflowX"])
    if p["cols"] == 3:
        check(f"{tag}: seções na ordem navegação, conta, sites", p["sectionLabels"][0] == "Navegação principal"
              and p["sectionLabels"][2] == "Sites e informações legais", p["sectionLabels"])
    ratio_text = ratio(p["linkColor"], p["panelBg"])
    check(f"{tag}: contraste do texto dos links >= 4,5:1", ratio_text >= 4.5, f"{ratio_text:.2f}:1")
    shot(f"{tag}-aberto")

    # Escape fecha e devolve o foco ao gatilho.
    press(KEY_ESC)
    check(f"{tag}: Escape fecha", close_is_closed(), "")
    check(f"{tag}: foco volta ao gatilho após Escape",
          driver.js("return document.activeElement.classList.contains('aculta-account-menu__trigger')"), "")

    # X fecha e devolve o foco ao gatilho.
    open_panel()
    driver.js("document.querySelector('.aculta-account-dialog [data-aculta-dialog-close]').click(); return true")
    time.sleep(0.3)
    check(f"{tag}: X fecha", close_is_closed(), "")
    check(f"{tag}: foco volta ao gatilho após X",
          driver.js("return document.activeElement.classList.contains('aculta-account-menu__trigger')"), "")

    # Foco preso: Tab além do número de itens não sai do painel.
    open_panel()
    driver.js("document.querySelector('.aculta-account-dialog [data-aculta-dialog-close]').focus(); return true")
    escaped = 0
    for _ in range(30):
        press(KEY_TAB)
        if not driver.js("return document.querySelector('.aculta-account-dialog').contains(document.activeElement)"):
            escaped += 1
    check(f"{tag}: foco preso no painel em 30 Tabs", escaped == 0, f"escapou {escaped}x")
    press(KEY_ESC)

    # Enter em cada link: só links do mesmo host, para não sair do site.
    if width == 320 and theme == "light":
        hrefs = [h for h in p["linkHrefs"] if h and (h.startswith("/") or h.startswith(BASE))]
        for href in hrefs:
            open_panel()
            driver.js("""const a = Array.from(document.querySelectorAll('.aculta-account-dialog a[href]'))
              .find(x => x.getAttribute('href') === arguments[0]); a.focus(); return true""", [href])
            press(KEY_ENTER)
            time.sleep(1.0)
            landed = driver.js("return location.pathname")
            expected = href if href.startswith("/") else href.replace(BASE, "") or "/"
            check(f"{tag}: Enter em {href} leva a {expected}", landed.rstrip("/") == expected.rstrip("/")
                  or (expected.startswith("/") and landed.startswith(expected.rstrip("/"))), landed)
            driver.get(f"{BASE}/")
            time.sleep(0.8)
            set_theme(theme)
    driver.get(f"{BASE}/")
    time.sleep(0.8)
    set_theme(theme)


try:
    driver.get(f"{BASE}/")
    time.sleep(1.0)
    for state in ("anon", "auth"):
        if state == "auth":
            driver.get(login_url())
            driver.get(f"{BASE}/")
            time.sleep(1.0)
        for theme in ("light", "dark"):
            for width in WIDTHS:
                set_width(width)
                driver.get(f"{BASE}/")
                time.sleep(1.0)
                set_theme(theme)
                state_pass(state, theme, width)
    (OUT / "results.json").write_text(json.dumps({"phase": PHASE, "base": BASE, "results": results},
                                                  ensure_ascii=False, indent=2) + "\n")
finally:
    driver.close()

failed = [r for r in results if r["ok"] is False]
print(f"\n{len(results) - len(failed)}/{len(results)} itens sem falha. Evidências: {OUT}")
sys.exit(1 if failed else 0)
