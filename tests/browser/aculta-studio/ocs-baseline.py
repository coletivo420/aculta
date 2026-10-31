#!/usr/bin/env python3
"""Capture a public OpenCulturas 3.1.5 reference and the read-only Studio baseline."""
import base64
import json
import os
import re
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
CAPTURE_FAILURES = []
STUDIO_CSS_OVERRIDE = os.environ.get("OCS_STUDIO_FIDELITY_CSS_OVERRIDE") == "1"
STUDIO_CSS_ROOT = Path(os.environ.get("OCS_STUDIO_FIDELITY_CSS_ROOT", Path(__file__).resolve().parents[3])).resolve()
STUDIO_COLOR_MODE = os.environ.get("OCS_STUDIO_COLOR_MODE", "system")


def fidelity_rule(css, selector):
    start = css.find(selector)
    if start < 0:
        raise RuntimeError(f"Regra Fidelity ausente em tokens.css: {selector}")
    opening = css.find("{", start)
    depth = 0
    for index in range(opening, len(css)):
        if css[index] == "{":
            depth += 1
        elif css[index] == "}":
            depth -= 1
            if depth == 0:
                return css[start:index + 1]
    raise RuntimeError(f"Regra Fidelity sem fechamento em tokens.css: {selector}")


def studio_fidelity_css():
    theme = STUDIO_CSS_ROOT / "web/themes/custom/aculta420"
    tokens = (theme / "css/tokens.css").read_text()
    start = tokens.index("/* OpenCulturas Next 3.1.5 tokens are scoped to Studio-owned visual roots. */")
    end = tokens.index('\n[data-bs-theme="dark"] {', start)
    dark = fidelity_rule(tokens, '[data-bs-theme="dark"] .studio,')
    automatic = fidelity_rule(tokens, ':root:not([data-bs-theme="light"]) .studio,')
    blocks = tokens[start:end] + "\n" + dark + "\n@media (prefers-color-scheme: dark) {\n" + automatic + "\n}"
    components = (theme / "css/components/studio.css").read_text()
    fonts = theme / "assets/fonts/montserrat"
    for name in ("Montserrat-latin.woff2", "Montserrat-latin-ext.woff2"):
        encoded = base64.b64encode((fonts / name).read_bytes()).decode("ascii")
        components = components.replace(
            f"../../assets/fonts/montserrat/{name}",
            f"data:font/woff2;base64,{encoded}",
        )
    return blocks + "\n" + components


STUDIO_CANDIDATE_CSS = studio_fidelity_css() if STUDIO_CSS_OVERRIDE else ""


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


def institution_link_contrast(driver):
    """Measure visible Institution Bar utility-link contrast without text values."""
    links = json.loads(driver.js("""return JSON.stringify(
      [...document.querySelectorAll('.aculta-institution-bar .aculta-utility .nav-link')]
        .filter(link=>link.innerText.trim()).map(link=>({
          accountHome:link.classList.contains('aculta-account-home'),
          foreground:getComputedStyle(link).color,
          linkBackground:getComputedStyle(link).backgroundColor,
          shellBackground:getComputedStyle(link.closest('.aculta-institution-bar')).backgroundColor
        })));
    """))

    def color(value):
        parts = [float(part) for part in re.findall(r"[\d.]+", value)]
        if len(parts) < 3:
            return None
        return parts[:3], parts[3] if len(parts) > 3 else 1.0

    def luminance(rgb):
        channels = [channel / 255 for channel in rgb]
        linear = [channel / 12.92 if channel <= 0.04045 else ((channel + 0.055) / 1.055) ** 2.4 for channel in channels]
        return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]

    result = []
    for link in links:
        foreground = color(link["foreground"])
        background = color(link["linkBackground"])
        shell = color(link["shellBackground"])
        if not foreground or not background or not shell:
            contrast = None
        else:
            background_rgb = [
                background[0][index] * background[1] + shell[0][index] * (1 - background[1])
                for index in range(3)
            ]
            foreground_rgb = [
                foreground[0][index] * foreground[1] + background_rgb[index] * (1 - foreground[1])
                for index in range(3)
            ]
            foreground_luminance = luminance(foreground_rgb)
            background_luminance = luminance(background_rgb)
            contrast = round((max(foreground_luminance, background_luminance) + 0.05)
                             / (min(foreground_luminance, background_luminance) + 0.05), 2)
        result.append({**link, "contrast": contrast})
    return result


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
    if STUDIO_CSS_OVERRIDE:
        driver.js("const style=document.createElement('style');style.dataset.ocsStudioFidelityCandidate='true';style.textContent=arguments[0];document.head.append(style);return true", [STUDIO_CANDIDATE_CSS])
        driver.js("document.fonts.load('400 16px Montserrat', 'Meus cursos');return true")
        for _ in range(30):
            if driver.js("return document.fonts.check('400 16px Montserrat', 'Meus cursos')"):
                break
            time.sleep(0.1)
        if STUDIO_COLOR_MODE in ("light", "dark"):
            driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0]);return true", [STUDIO_COLOR_MODE])
            time.sleep(0.35)
    detail = driver.js("const text=document.body.innerText;const title=document.title;const elementCount=document.documentElement.querySelectorAll('*').length;const tab=document.querySelector('.aculta-context-tabs__items');const list=tab?.querySelector('ul');const tabItems=Array.from(list?.querySelectorAll('li')||[]);return JSON.stringify({title,url:location.href,width:innerWidth,height:innerHeight,documentWidth:document.documentElement.clientWidth,documentScrollWidth:document.documentElement.scrollWidth,bodyScrollWidth:document.body.scrollWidth,documentScrollHeight:document.documentElement.scrollHeight,documentElementCount:elementCount,htmlLength:document.documentElement.innerHTML.length,contentType:document.contentType,readyState:document.readyState,body:text.slice(0,120),bodyClasses:Array.from(document.body.classList),studioMarkers:Array.from(document.querySelectorAll('[data-aculta-studio]'),element=>element.getAttribute('data-aculta-studio')).slice(0,60),mainStructure:(()=>{const main=document.querySelector('main#main-content');return main?{className:String(main.className),children:[...main.children].slice(0,16).map(element=>({tag:element.tagName,className:String(element.className),children:[...element.children].slice(0,12).map(child=>({tag:child.tagName,className:String(child.className)}))}))}:null})(),studioComponents:(()=>{const main=document.querySelector('main#main-content');return main?[...main.querySelectorAll('.aculta-context-header,.aculta-card,.aculta-empty-state,.aculta-content-grid')].map(element=>({className:String(element.className),parentClass:String(element.parentElement?.className||''),childClasses:[...element.children].slice(0,8).map(child=>String(child.className))})).slice(0,40):[]})(),fidelityStyles:(()=>{const main=document.querySelector('main#main-content');const heading=main?.querySelector('h1');const empty=main?.querySelector('.aculta-empty-state');const card=main?.querySelector('.aculta-card');const institution=document.querySelector('.aculta-institution-bar');const domainHeader=document.querySelector('.aculta-header');const box=(element)=>{if(!element)return null;const style=getComputedStyle(element);const rect=element.getBoundingClientRect();return {fontFamily:style.fontFamily,color:style.color,background:style.backgroundColor,borderRadius:style.borderRadius,width:rect.width,height:rect.height,right:rect.right}};return {fontLoaded:document.fonts.check('400 16px Montserrat', 'Meus cursos'),fontFaces:Array.from(document.fonts).filter(face=>face.family.replaceAll('\"','')==='Montserrat').map(face=>({status:face.status,weight:face.weight,style:face.style})),main:box(main),heading:box(heading),empty:box(empty),emptyStates:Array.from(main?.querySelectorAll('.aculta-empty-state')||[]).map(box),card:box(card),wikiRoot:box(main?.querySelector('.we-sativa__workspace')),wikiHeading:box(main?.querySelector('.we-sativa__workspace h1')),wikiEmptyStates:Array.from(main?.querySelectorAll('.we-sativa__workspace .aculta-empty-state')||[]).map(box),wikiCards:Array.from(main?.querySelectorAll('.we-sativa__workspace .aculta-card')||[]).map(box),wikiSectionCount:main?.querySelectorAll('.we-sativa__workspace .aculta-editorial-section').length||0,wikiContextHeaderPresent:!!main?.querySelector('.we-sativa__workspace .aculta-context-header'),shellFidelityTokens:[institution,domainHeader].map(element=>element?getComputedStyle(element).getPropertyValue('--aculta-studio-fidelity-font-family').trim():null)}})(),institutionBar:!!document.querySelector('.aculta-institution-bar'),domainHeader:!!document.querySelector('.aculta-header'),contextTabs:tab?{count:tabItems.length,currentCount:tabItems.filter(item=>item.querySelector('a[aria-current=\"page\"]')).length,listTag:list.tagName,listClass:list.className,parentTag:list.parentElement.tagName,parentClass:list.parentElement.className,flexDirection:getComputedStyle(list).flexDirection,flexWrap:getComputedStyle(list).flexWrap,candidateRules:document.querySelector('style[data-ocs-context-tabs-candidate]')?.sheet?.cssRules.length||0,matchingSelector:!!list.matches('.aculta-context-tabs__items > ul, .aculta-context-tabs__items > .item-list > ul'),itemClasses:tabItems.map(item=>item.className),itemBorders:tabItems.map(item=>getComputedStyle(item).borderTopWidth+' '+getComputedStyle(item).borderTopStyle),linkMinHeights:tabItems.map(item=>Math.round(item.querySelector('a')?.getBoundingClientRect().height||0))}:null,emptyDocumentHint:elementCount<=2&&!text.trim(),accessDeniedHint:/^(?:acesso negado|access denied|forbidden)(?:\\s*\\|.*)?$/im.test(title)||/Você não está autorizado a acessar esta página\\./i.test(text)})")
    screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
    record = json.loads(detail)
    record["institutionBarUtilityLinkContrast"] = institution_link_contrast(driver)
    record["cssOverride"] = css_override
    record["studioFidelityCssOverride"] = STUDIO_CSS_OVERRIDE
    record["studioColorMode"] = STUDIO_COLOR_MODE
    styles = record.get("fidelityStyles", {})
    if "path-ganjalab" in record.get("bodyClasses", []) and STUDIO_CSS_OVERRIDE:
        components = [component.get("className", "") for component in record.get("studioComponents", [])]
        main_font = (styles.get("main") or {}).get("fontFamily", "")
        heading_font = (styles.get("heading") or {}).get("fontFamily", "")
        empty_fonts = [
            component.get("fontFamily", "")
            for component in styles.get("emptyStates", [])
        ]
        expected_text = "rgb(239, 239, 239)" if STUDIO_COLOR_MODE == "dark" else None
        checks = {
            "fontLoaded": styles.get("fontLoaded") is True,
            "mainMontserrat": "Montserrat" in main_font,
            "headingMontserrat": "Montserrat" in heading_font,
            "emptyStateUsesMontserrat": bool(empty_fonts) and all("Montserrat" in font for font in empty_fonts),
            "contextHeaderPresent": any("aculta-context-header" in component for component in components),
            "twoEmptyStatesPresent": sum("aculta-empty-state" in component for component in components) == 2,
            "noDocumentOverflow": record.get("documentScrollWidth") == record.get("documentWidth"),
            "institutionBarPresent": record.get("institutionBar") is True,
            "domainHeaderPresent": record.get("domainHeader") is True,
            "institutionBarUtilityLinksContrastAA": bool(record["institutionBarUtilityLinkContrast"])
            and all(link["contrast"] is not None and link["contrast"] >= 4.5
                    for link in record["institutionBarUtilityLinkContrast"]),
            "fidelityTokensStayOffShell": styles.get("shellFidelityTokens") == ["", ""],
        }
        if expected_text:
            checks["darkTextTokenApplied"] = (styles.get("main") or {}).get("color") == expected_text
        record["ganjalabFidelityChecks"] = checks
        record["ganjalabFidelityStatus"] = "PASS" if all(checks.values()) else "FAIL"
        if record["ganjalabFidelityStatus"] == "FAIL":
            CAPTURE_FAILURES.append({"url": url, "width": width, "checks": checks})
    wiki_root = styles.get("wikiRoot")
    if wiki_root and STUDIO_CSS_OVERRIDE:
        wiki_heading = styles.get("wikiHeading") or {}
        wiki_empty_fonts = [item.get("fontFamily", "") for item in styles.get("wikiEmptyStates", [])]
        wiki_checks = {
            "fontLoaded": styles.get("fontLoaded") is True,
            "workspaceMontserrat": "Montserrat" in wiki_root.get("fontFamily", ""),
            "headingMontserrat": "Montserrat" in wiki_heading.get("fontFamily", ""),
            "contextHeaderPresent": styles.get("wikiContextHeaderPresent") is True,
            "contentSectionPresent": styles.get("wikiSectionCount", 0) >= 1,
            "emptyStatesUseMontserrat": all("Montserrat" in font for font in wiki_empty_fonts),
            "noDocumentOverflow": record.get("documentScrollWidth") == record.get("documentWidth"),
            "institutionBarPresent": record.get("institutionBar") is True,
            "institutionBarUtilityLinksContrastAA": bool(record["institutionBarUtilityLinkContrast"])
            and all(link["contrast"] is not None and link["contrast"] >= 4.5
                    for link in record["institutionBarUtilityLinkContrast"]),
            "domainHeaderPresent": record.get("domainHeader") is True,
            "fidelityTokensStayOffShell": styles.get("shellFidelityTokens") == ["", ""],
            "fourContextFiltersPresent": (record.get("contextTabs") or {}).get("count") == 4,
            "singleCurrentFilter": (record.get("contextTabs") or {}).get("currentCount") == 1,
            "contextFilterLinksAtLeast44px": all(height >= 44 for height in (record.get("contextTabs") or {}).get("linkMinHeights", [])),
            "contextFiltersWrap": (record.get("contextTabs") or {}).get("flexWrap") == "wrap",
        }
        if STUDIO_COLOR_MODE == "dark":
            wiki_checks["darkTextTokenApplied"] = wiki_root.get("color") == "rgb(239, 239, 239)"
        record["weSativaFidelityChecks"] = wiki_checks
        record["weSativaFidelityStatus"] = "PASS" if all(wiki_checks.values()) else "FAIL"
        if record["weSativaFidelityStatus"] == "FAIL":
            CAPTURE_FAILURES.append({"url": url, "width": width, "checks": wiki_checks})
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
        "studio_capture_status": "FAIL" if CAPTURE_FAILURES else ("PARTIAL" if any(capture.get("status") == "DEFERRED" or capture.get("accessDeniedHint") or capture.get("emptyDocumentHint") for capture in CAPTURES) else "CAPTURED"),
        "studio_user": USER,
        "studio_pages": [url for _, url in studio_pages()],
        "widths": [width for width, _ in WIDTHS],
        "screenshots": [capture["screenshot"] for capture in CAPTURES if "screenshot" in capture],
        "captures": CAPTURES,
        "fidelity_failures": CAPTURE_FAILURES,
        "limitations": [
            "The public OpenCulturas homepage is not an authenticated dashboard screen.",
            "The Studio capture uses one synthetic Runtime account and does not submit forms.",
        ],
    }
    (OUT / "baseline.json").write_text(json.dumps(metadata, indent=2) + "\n")
    if CAPTURE_FAILURES:
        print(f"FAIL: {len(CAPTURE_FAILURES)} Fidelity capture(s) did not meet visual invariants.")
        return 1


if __name__ == "__main__":
    sys.exit(main() or 0)
