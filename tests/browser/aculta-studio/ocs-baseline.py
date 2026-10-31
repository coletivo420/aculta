#!/usr/bin/env python3
"""Capture a public OpenCulturas 3.1.5 reference and the read-only Studio baseline."""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlparse

ROOT = Path(os.environ.get("ACULTA_ROOT", Path(__file__).resolve().parents[3])).resolve()
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

URI = os.environ.get("STUDIO_LOGIN_URI", "https://conta.aculta.toca.net.br")
PAGE = os.environ.get("STUDIO_PAGE", URI + "/marley")
USER = os.environ.get("STUDIO_USER", "teste.comum")
DEFAULT_WIDTHS = [(1440, 1000), (390, 844)]
GATE_WIDTHS = (320, 360, 390, 599, 600, 768, 1024, 1280, 1440)
WIDTHS = [
    (width, 1000 if width >= 1000 else 844)
    for width in map(int, os.environ.get("OCS_WIDTHS", "").split(",") if os.environ.get("OCS_WIDTHS") else [])
] or DEFAULT_WIDTHS
CAPTURES = []
DEFAULT_STUDIO_PAGES = [
    ("account-entry", "account", "/"),
    ("marley", "account", "/marley"),
    ("ganjalab", "courses", "/ganjalab"),
    ("we-sativa", "wiki", "/studio/we-sativa"),
    ("conta", "account", "/conta"),
    ("perfil", "account", "/perfil"),
    ("green-stash", "shop", "/studio/green-stash"),
    ("culturas", "eventos", "/culturas"),
    ("planos", "account", "/conta/mercado-pago/planos"),
]


def studio_pages():
    if "STUDIO_ROUTES" not in os.environ:
        if "STUDIO_PAGE" in os.environ:
            return [("custom", PAGE)]
        return [(name, environment_url(purpose, path)) for name, purpose, path in DEFAULT_STUDIO_PAGES]
    pages = []
    for path in os.environ["STUDIO_ROUTES"].split(","):
        path = path.strip()
        if not path:
            continue
        url = path if path.startswith(("http://", "https://")) else URI.rstrip("/") + "/" + path.lstrip("/")
        name = urlparse(url).path.strip("/").replace("/", "-") or "home"
        pages.append((name, url))
    return pages


def environment_url(purpose, path):
    php = (
        "$url = \\Drupal::service('aculta_portal.domain_purpose')"
        f"->environmentPathUrl('{purpose}', '{path}');"
        "print($url ? $url->toString() : '');"
    )
    url = drush("php:eval", php)
    if not url.startswith(("http://", "https://")):
        raise RuntimeError(f"DomainPurposeManager não resolveu {purpose}:{path} neste ambiente.")
    return url


def drush(*args):
    return subprocess.check_output(
        ["php", "vendor/drush/drush/drush.php", *args], cwd=ROOT, text=True
    ).strip()


def verify_runtime():
    host = subprocess.check_output(["hostname"], text=True).strip()
    status = drush("status", "--fields=uri,root,db-name")
    if host != "toca" or "aculta_runtime" not in status or str(ROOT / "web") not in status:
        raise RuntimeError("Baseline autenticado permitido apenas no toca/aculta_runtime do checkout atual.")


def capture(driver, url, prefix, width, height):
    node = r"""
const socket = new WebSocket(process.argv[1]);
socket.onopen = () => socket.send(JSON.stringify({id: 1, method: 'browsingContext.getTree', params: {}}));
socket.onmessage = (event) => {
  const message = JSON.parse(event.data);
  if (message.id === 1) {
    const context = message.result.contexts[0].context;
    socket.send(JSON.stringify({id: 2, method: 'browsingContext.setViewport', params: {context, viewport: {width: Number(process.argv[2]), height: Number(process.argv[3])}}}));
  } else if (message.id === 2) {
    if (message.error) { console.error(message.error.message); process.exitCode = 1; }
    socket.close();
  }
};
"""
    subprocess.run(["node", "-e", node, driver.websocket_url, str(width), str(height)], check=True)
    driver.get(url)
    for _ in range(100):
        if driver.js("return document.readyState === 'complete'"):
            break
        time.sleep(0.2)
    time.sleep(1)
    css_override = os.environ.get("OCS_CONTEXT_TABS_CSS_OVERRIDE") == "1"
    if css_override:
        css_root = Path(os.environ.get("OCS_CONTEXT_TABS_CSS_ROOT", ROOT)).resolve()
        css_path = css_root / "web/themes/custom/aculta420/components/patterns/context-tabs/context-tabs.css"
        css = css_path.read_text()
        driver.js("const style=document.createElement('style');style.dataset.ocsContextTabsCandidate='true';style.textContent=arguments[0];document.head.append(style);return true", [css])
    detail = driver.js("const text=document.body.innerText;const title=document.title;const elementCount=document.documentElement.querySelectorAll('*').length;const tab=document.querySelector('.aculta-context-tabs__items');const list=tab?.querySelector('ul');const tabItems=Array.from(list?.querySelectorAll('li')||[]);return JSON.stringify({title,url:location.href,width:innerWidth,height:innerHeight,documentWidth:document.documentElement.clientWidth,documentScrollWidth:document.documentElement.scrollWidth,bodyScrollWidth:document.body.scrollWidth,documentScrollHeight:document.documentElement.scrollHeight,documentElementCount:elementCount,htmlLength:document.documentElement.innerHTML.length,contentType:document.contentType,readyState:document.readyState,body:text.slice(0,120),bodyClasses:Array.from(document.body.classList),studioMarkers:Array.from(document.querySelectorAll('[data-aculta-studio]'),element=>element.getAttribute('data-aculta-studio')).slice(0,60),institutionBar:!!document.querySelector('.aculta-institution-bar'),domainHeader:!!document.querySelector('.aculta-header'),contextTabs:tab?{count:tabItems.length,listTag:list.tagName,listClass:list.className,parentTag:list.parentElement.tagName,parentClass:list.parentElement.className,flexDirection:getComputedStyle(list).flexDirection,flexWrap:getComputedStyle(list).flexWrap,candidateRules:document.querySelector('style[data-ocs-context-tabs-candidate]')?.sheet?.cssRules.length||0,matchingSelector:!!list.matches('.aculta-context-tabs__items > ul, .aculta-context-tabs__items > .item-list > ul'),itemClasses:tabItems.map(item=>item.className),itemBorders:tabItems.map(item=>getComputedStyle(item).borderTopWidth+' '+getComputedStyle(item).borderTopStyle),linkMinHeights:tabItems.map(item=>Math.round(item.querySelector('a')?.getBoundingClientRect().height||0))}:null,emptyDocumentHint:elementCount<=2&&!text.trim(),accessDeniedHint:/^(?:acesso negado|access denied|forbidden)(?:\\s*\\|.*)?$/im.test(title)||/Você não está autorizado a acessar esta página\\./i.test(text)})")
    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
    record = json.loads(detail)
    record["cssOverride"] = css_override
    target = OUT / f"{prefix}-{width}.png"
    target.write_bytes(base64.b64decode(screenshot))
    record["screenshot"] = target.name
    CAPTURES.append(record)
    print(f"CAPTURE {target.name}: {detail}")


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use Firefox ESR visível com DISPLAY=:0.")
    driver = Driver(bidi=True)
    try:
        if os.environ.get("OCS_SKIP_OPENCULTURAS") != "1":
            for width, height in WIDTHS:
                capture(driver, "https://www.openculturas.org/en", "openculturas", width, height)

        try:
            verify_runtime()
            login = drush("uli", "--no-browser", f"--name={USER}", f"--uri={URI}").splitlines()[-1]
        except (subprocess.CalledProcessError, RuntimeError) as error:
            metadata = {
                "source": "OpenCulturas public Drupal site; theme stylesheet SHA matches the 3.1.5 tarball",
                "runtime_status": "DEFERRED",
                "reason": f"Runtime/ULI unavailable ({type(error).__name__}); no login URL or Studio screenshot was created.",
                "screenshots": [capture["screenshot"] for capture in CAPTURES if "screenshot" in capture],
                "captures": CAPTURES,
            }
            (OUT / "baseline-deferred.json").write_text(json.dumps(metadata, indent=2) + "\n")
            print("DEFERRED: no authenticated Studio capture; public source screenshots were saved.")
            return 3
        driver.get(login)
        time.sleep(2)
        for page_name, page_url in studio_pages():
            for width, height in WIDTHS:
                try:
                    capture(driver, page_url, f"studio-{page_name}", width, height)
                except Exception as error:
                    CAPTURES.append({
                        "url": page_url,
                        "width": width,
                        "height": height,
                        "status": "DEFERRED",
                        "reason": f"{type(error).__name__}: {error}",
                    })
                    print(f"DEFERRED {page_url} at {width}px: {type(error).__name__}")
    finally:
        driver.close()

    metadata = {
        "source": "OpenCulturas public Drupal site; theme stylesheet SHA matches the 3.1.5 tarball",
        "runtime_status": "CAPTURED",
        "studio_capture_status": "PARTIAL" if any(capture.get("status") == "DEFERRED" or capture.get("accessDeniedHint") or capture.get("emptyDocumentHint") for capture in CAPTURES) else "CAPTURED",
        "studio_user": USER,
        "studio_pages": [url for _, url in studio_pages()],
        "widths": [width for width, _ in WIDTHS],
        "screenshots": [capture["screenshot"] for capture in CAPTURES if "screenshot" in capture],
        "captures": CAPTURES,
        "limitations": [
            "The public OpenCulturas homepage is not an authenticated dashboard screen.",
            "The Studio capture uses one synthetic Runtime account and does not submit forms.",
        ],
    }
    (OUT / "baseline.json").write_text(json.dumps(metadata, indent=2) + "\n")


if __name__ == "__main__":
    sys.exit(main() or 0)
