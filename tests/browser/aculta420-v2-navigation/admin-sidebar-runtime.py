#!/usr/bin/env python3
"""Check Institution Bar and the real Gin sidebar in a test-admin session."""

import base64
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit

from websockets.sync.client import connect

ROOT = Path(__file__).resolve().parents[3]
DRUPAL_ROOT = Path(os.environ.get("ACULTA_DRUPAL_ROOT", ROOT)).resolve()
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT):
    raise SystemExit("SUITE_OUT deve ficar fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True, mode=0o700)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Use Firefox ESR visível em DISPLAY=:0.")

ADMIN_UID = os.environ.get("ACULTA_ADMIN_TEST_UID", "257")
if not ADMIN_UID.isdecimal():
    raise SystemExit("ACULTA_ADMIN_TEST_UID deve ser numérico.")
DEPLOY = json.loads(
    (ROOT / "web/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json").read_text()
)
TEST_HOSTS = {
    item["domain_id"]: item["test_host"]
    for item in DEPLOY["domain_transition"]["entries"]
}
MAIN = f"https://{TEST_HOSTS['aculta_org']}"
ACCOUNT = f"https://{TEST_HOSTS['conta_aculta_org']}"
WIDTHS = (320, 360, 390, 575, 576, 768, 991, 992, 1024, 1280, 1440)


def one_time_login_url():
    process = subprocess.run(
        [
            "php", str(DRUPAL_ROOT / "vendor/drush/drush/drush.php"), "uli", "/", "--no-browser",
            f"--uid={ADMIN_UID}", f"--uri={ACCOUNT}",
        ],
        cwd=DRUPAL_ROOT,
        check=True,
        capture_output=True,
        text=True,
        timeout=30,
    )
    generated = urlsplit(process.stdout.strip().splitlines()[-1])
    account = urlsplit(ACCOUNT)
    return urlunsplit((account.scheme, account.netloc, generated.path, generated.query, generated.fragment))


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.sequence = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.sequence += 1
        command_id = self.sequence
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") == command_id:
                if "error" in response:
                    raise RuntimeError(f"{method}: {response['error']}")
                return response.get("result", {})

    def set(self, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.2)

    def close(self):
        self.socket.close()


driver = Driver(bidi=True, browser="firefox")
viewport = Viewport(driver)
results = []


def record(width, color_mode, state, metrics):
    passed = (
        metrics["viewport"] == width
        and metrics["barLeft"] >= 0
        and metrics["barRight"] <= width + 1
        and metrics["barHeight"] > 0
        and metrics["logoLoaded"]
        and metrics["adminSidebarPresent"]
        and metrics["noHorizontalOverlap"]
        and metrics["barContentFits"]
        and metrics["accountPanelNoGinOverlap"]
        and not metrics["brokenBlockMessageVisible"]
    )
    item = {
        "width": width,
        "colorMode": color_mode,
        "sidebarState": state,
        "passed": passed,
        **metrics,
    }
    results.append(item)
    print(("PASS" if passed else "FAIL") + " " + json.dumps(item, ensure_ascii=False), flush=True)
    if width in (320, 390, 1024, 1440) and color_mode == "light" and state == "initial":
        driver.js("document.querySelector('.aculta-institution-bar')?.scrollIntoView({block:'start',behavior:'instant'})")
        time.sleep(0.1)
        screenshot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"admin-sidebar-{width}.png").write_bytes(base64.b64decode(screenshot))


try:
    driver.get(one_time_login_url())
    time.sleep(0.5)
    driver.get(MAIN + "/")
    deadline = time.monotonic() + 12
    while time.monotonic() < deadline:
        authenticated = driver.js("return !!document.querySelector('#admin-toolbar .admin-toolbar__content')")
        if authenticated:
            break
        time.sleep(0.2)
    if not authenticated:
        raise RuntimeError("A sessão da conta administrativa de teste não chegou ao MAIN.")
    broken_block = driver.js("""const walker=document.createTreeWalker(document.querySelector('#admin-toolbar'),NodeFilter.SHOW_TEXT);
      const matches=[];while(walker.nextNode()){const text=walker.currentNode.textContent.trim();if(/quebrado|ausente/i.test(text)){
        let node=walker.currentNode.parentElement,chain=[];for(let i=0;node&&i<7;i++,node=node.parentElement){
          chain.push({tag:node.tagName.toLowerCase(),id:node.id,className:typeof node.className==='string'?node.className:'',
            selector:node.getAttribute('data-drupal-selector'),plugin:node.getAttribute('data-plugin-id')});}
        matches.push({text,chain});}}return matches;""")
    print("BROKEN_BLOCK_ANCESTRY=" + json.dumps(broken_block, ensure_ascii=False), flush=True)
    if os.environ.get("ACULTA_ADMIN_DIAG_ONLY") == "1":
        diagnostic_width = int(os.environ.get("ACULTA_ADMIN_DIAG_WIDTH", "320"))
        viewport.set(diagnostic_width)
        if os.environ.get("ACULTA_ADMIN_DIAG_COLLAPSE") == "1":
            driver.js("document.querySelector('#admin-toolbar .admin-toolbar__expand-button')?.click()")
            time.sleep(0.35)
        dimensions = driver.js("""const bar=document.querySelector('.aculta-institution-bar'),inner=bar?.querySelector('.aculta-institution-bar__inner'),
          utility=bar?.querySelector('.aculta-utility'),trigger=bar?.querySelector('.aculta-utility-trigger'),brand=bar?.querySelector('.aculta-institution-bar__brand'),
          panel=bar?.querySelector('.aculta-account-menu__panel'),details=bar?.querySelector('.aculta-account-menu__details');
          const box=e=>{const r=e?.getBoundingClientRect(),s=e&&getComputedStyle(e);return r?{left:r.left,right:r.right,width:r.width,paddingInline:s.paddingInline,display:s.display}:null;};
          const overflow=[...document.querySelectorAll('body *')].map(e=>({e,r:e.getBoundingClientRect()})).filter(({e,r})=>r.width>0&&r.right>innerWidth+1&&getComputedStyle(e).position!=='fixed')
            .slice(0,12).map(({e,r})=>({tag:e.tagName.toLowerCase(),id:e.id,className:typeof e.className==='string'?e.className:'',left:r.left,right:r.right}));
          return {bar:box(bar),inner:box(inner),utility:box(utility),trigger:box(trigger),brand:box(brand),panel:box(panel),detailsOpen:!!details?.open,
            mainInlineSize:getComputedStyle(document.documentElement).getPropertyValue('--aculta-admin-main-inline-size'),sidebarInlineSize:getComputedStyle(document.documentElement).getPropertyValue('--aculta-admin-sidebar-inline-size'),overflow};""")
        print("DIMENSIONS=" + json.dumps(dimensions, ensure_ascii=False), flush=True)
        raise SystemExit(0)

    for width in WIDTHS:
        viewport.set(width)
        for sidebar_state in ("initial", "toggled"):
            if sidebar_state == "toggled":
                toggled = driver.js("""const button=document.querySelector('#admin-toolbar .admin-toolbar__expand-button');
                  if(!button)return false;button.click();return true;""")
                if not toggled:
                    raise RuntimeError("Botão de expansão do menu Gin não foi encontrado.")
                time.sleep(0.35)

            for color_mode in ("light", "dark"):
                driver.js("document.documentElement.setAttribute('data-theme', arguments[0])", [color_mode])
                time.sleep(0.08)
                metrics = driver.js("""return (()=>{
                  const bar=document.querySelector('.aculta-institution-bar');
                  const logo=bar?.querySelector('.aculta-institution-bar__logo');
                  const sidebar=document.querySelector('#admin-toolbar.admin-toolbar');
                  const br=bar?.getBoundingClientRect(),sr=sidebar?.getBoundingClientRect();
                  const direction=getComputedStyle(document.documentElement).direction;
                  const visibleSidebar=sidebar&&getComputedStyle(sidebar).display!=='none'&&sr.width>0;
                  const overlaps=visibleSidebar&&br.left<sr.right-1&&br.right>sr.left+1;
                  const targets=[bar?.querySelector('.aculta-institution-bar__brand'),
                    ...bar?.querySelectorAll('.aculta-utility-trigger, .aculta-account-menu__summary')??[]].filter(Boolean);
                  const overflowing=targets.filter(el=>{
                    const rect=el.getBoundingClientRect();
                    return rect.width>0&&(rect.left<br.left-1||rect.right>br.right+1);
                  }).map(el=>({selector:el.tagName.toLowerCase()+(el.className&&typeof el.className==='string'?'.'+el.className.trim().replace(/\\s+/g,'.'):''),
                    left:Math.round(el.getBoundingClientRect().left),right:Math.round(el.getBoundingClientRect().right)}));
                  const details=bar?.querySelector('.aculta-account-menu__details'),summary=details?.querySelector('summary'),panel=details?.querySelector('.aculta-account-menu__panel');
                  if(details?.open)summary?.click();
                  summary?.click();
                  const pr=panel?.getBoundingClientRect();
                  const panelOverlapsGin=!!(visibleSidebar&&pr?.width&&pr.left<sr.right-1&&pr.right>sr.left+1);
                  const panelInViewport=!!pr&&pr.left>=-1&&pr.right<=innerWidth+1;
                  summary?.click();
                  return {viewport:innerWidth,documentWidth:document.documentElement.scrollWidth,
                    barLeft:Math.round(br?.left??-1),barRight:Math.round(br?.right??-1),
                    barHeight:Math.round(br?.height??0),adminSidebarPresent:!!sidebar,
                    sidebarLeft:Math.round(sr?.left??-1),sidebarRight:Math.round(sr?.right??-1),
                    sidebarVisible:!!visibleSidebar,noHorizontalOverlap:!overlaps,
                    barContentFits:overflowing.length===0,overflowing,logoLoaded:!!logo&&logo.complete&&logo.naturalWidth>0,
                    accountPanelNoGinOverlap:!panelOverlapsGin&&panelInViewport,
                    brokenBlockMessageVisible:document.querySelector('#admin-toolbar')?.textContent
                      .replace(/\\s+/g,' ').includes('Este bloco está quebrado ou ausente')??false,
                    measuredInlineSize:getComputedStyle(document.documentElement)
                      .getPropertyValue('--aculta-admin-sidebar-inline-size').trim(),direction};
                })();""")
                record(width, color_mode, sidebar_state, metrics)
finally:
    viewport.close()
    driver.close()

(OUT / "results.json").write_text(json.dumps({
    "authState": "test-admin",
    "uid": ADMIN_UID,
    "purposes": {"main": MAIN, "account": ACCOUNT},
    "widths": WIDTHS,
    "results": results,
}, ensure_ascii=False, indent=2) + "\n")
failed = [item for item in results if not item["passed"]]
print(f"\n{len(results) - len(failed)}/{len(results)} checks passed; evidence: {OUT}")
sys.exit(1 if failed else 0)
