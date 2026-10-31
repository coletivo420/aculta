#!/usr/bin/env python3
"""Exercise actual Firefox page zoom on the public Magazine landing page."""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from urllib.parse import urlsplit

ROOT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3])).resolve()
OUT = Path(os.environ["SUITE_OUT"]).resolve()
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

URL = os.environ.get("REVISTA_BASE_URL", "https://revista.aculta.toca.net.br/")
WIDTH = int(os.environ.get("REVISTA_ZOOM_DEVICE_WIDTH", "1280"))
TARGETS = (2.0, 4.0)


def set_viewport(driver, width, height):
    node = r"""
const ws = new WebSocket(process.argv[1]);
ws.onopen = () => ws.send(JSON.stringify({id: 1, method: 'browsingContext.getTree', params: {}}));
ws.onmessage = event => {
  const m = JSON.parse(event.data);
  if (m.id === 1) {
    ws.send(JSON.stringify({id: 2, method: 'browsingContext.setViewport', params: {
      context: m.result.contexts[0].context,
      viewport: {width: Number(process.argv[2]), height: Number(process.argv[3])}
    }}));
  } else if (m.id === 2) {
    if (m.error) { console.error(m.error.message); process.exitCode = 1; }
    ws.close();
  }
};
"""
    subprocess.run(["node", "-e", node, driver.websocket_url, str(width), "900"], check=True)


def press_zoom_in(driver):
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()
    if not windows:
        raise RuntimeError("Firefox ESR visível não foi encontrado pelo xdotool.")
    window = windows[-1]
    subprocess.run(["xdotool", "windowactivate", "--sync", window], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+plus"], check=True)


def reset_zoom(driver):
    windows = subprocess.check_output(
        ["xdotool", "search", "--onlyvisible", "--class", "firefox"], text=True
    ).splitlines()
    if not windows:
        raise RuntimeError("Firefox ESR visível não foi encontrado pelo xdotool.")
    subprocess.run(["xdotool", "windowactivate", "--sync", windows[-1]], check=True)
    subprocess.run(["xdotool", "key", "--clearmodifiers", "ctrl+0"], check=True)


def inspect_article(driver):
    return json.loads(driver.js("""
const d=document.documentElement,b=document.body;
const article=document.querySelector('.node--type-revista.node--view-mode-full');
const publication=article?.querySelector('.aculta-revista-publication');
const body=publication?.querySelector('.aculta-revista-publication__body')||article?.querySelector('.field--name-body');
const rect=(element)=>{if(!element)return null;const r=element.getBoundingClientRect();return {left:r.left,right:r.right,width:r.width,height:r.height}};
return JSON.stringify({innerWidth,devicePixelRatio,documentWidth:d.clientWidth,
documentScrollWidth:d.scrollWidth,bodyScrollWidth:b.scrollWidth,
articlePresent:!!article,publicationPresent:!!publication,bodyPresent:!!body,
article:rect(article),publication:rect(publication),body:rect(body),
bodyTextLength:body?.innerText.trim().length||0,
bodyClientWidth:body?.clientWidth||0,bodyScrollWidth:body?.scrollWidth||0,
headingCount:[...document.querySelectorAll('main#main-content h1')]
 .filter(node=>node.getClientRects().length&&node.innerText.trim()).length,
coverImages:[...(publication?.querySelectorAll('.aculta-revista-publication__cover img')||[])].map(img=>({
 loaded:img.complete&&img.naturalWidth>0,width:rect(img)?.width||0,right:rect(img)?.right||0})),
discussionPresent:!!publication?.querySelector('.aculta-revista-publication__discussion'),
institutionBarCount:document.querySelectorAll('.aculta-institution-bar').length,
domainHeaderCount:document.querySelectorAll('.aculta-header').length});
"""))


def inspect(driver):
    return json.loads(driver.js("""
const d=document.documentElement, b=document.body;
const cover=document.querySelector('.aculta-revista-teaser');
const coverRect=cover?.getBoundingClientRect();
const coverImage=cover?.querySelector('img')?.getBoundingClientRect();
return JSON.stringify({innerWidth, devicePixelRatio,
  documentWidth:d.clientWidth, documentScrollWidth:d.scrollWidth,
  bodyScrollWidth:b.scrollWidth, imageCount:document.querySelectorAll('.aculta-revista-teaser img').length,
  h1:Array.from(document.querySelectorAll('h1'), e=>e.innerText.trim()),
  firstCover:coverRect?{x:coverRect.x,y:coverRect.y,width:coverRect.width,height:coverRect.height}:null,
  firstCoverImage:coverImage?{x:coverImage.x,y:coverImage.y,width:coverImage.width,height:coverImage.height}:null,
  institutionBarCount:document.querySelectorAll('.aculta-institution-bar').length,
  domainHeaderCount:document.querySelectorAll('.aculta-header').length});
"""))


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise RuntimeError("Use Firefox ESR visível com DISPLAY=:0.")
    if subprocess.run(["which", "xdotool"], capture_output=True).returncode:
        raise RuntimeError("xdotool é necessário para o zoom real do Firefox.")
    if subprocess.check_output(["hostname"], text=True).strip() != "toca":
        raise RuntimeError("Este roteiro foi definido para o servidor de desenvolvimento toca.")
    status = subprocess.check_output([
        "php", "vendor/drush/drush/drush.php", "status", "--fields=uri,root,db-name"
    ], cwd=ROOT, text=True)
    if "aculta_runtime" not in status or str(ROOT / "web") not in status:
        raise RuntimeError("Drush não confirmou aculta_runtime no checkout esperado.")

    driver = Driver(bidi=True)
    records = []
    article_url = None
    try:
        driver.get(URL)
        time.sleep(1)
        set_viewport(driver, WIDTH, 900)
        baseline = inspect(driver)
        baseline["overflow"] = baseline["documentScrollWidth"] > baseline["documentWidth"] or baseline["bodyScrollWidth"] > baseline["innerWidth"]
        baseline["passed"] = (
            len(baseline["h1"]) == 1 and baseline["imageCount"] > 0
            and baseline["institutionBarCount"] == 1 and baseline["domainHeaderCount"] == 1
            and not baseline["overflow"]
        )
        records.append({"view": "home", "targetZoom": 1.0, **baseline})
        article_url = driver.js("return document.querySelector('.aculta-revista-teaser__title a, .aculta-revista-teaser__cta')?.href||null;")
        if article_url:
            parsed = urlsplit(article_url)
            expected_host = urlsplit(URL).hostname
            if parsed.scheme != "https" or parsed.hostname != expected_host or parsed.path == "/":
                raise RuntimeError("O link da primeira publicação saiu do host/caminho esperado da Revista.")
        else:
            records.append({"view": "article", "status": "DEFERRED",
                            "reason": "A home não renderizou link para uma publicação de teste."})
        for target in TARGETS:
            current = inspect(driver)["devicePixelRatio"]
            for _ in range(24):
                if current >= target - 0.08:
                    break
                press_zoom_in(driver)
                time.sleep(0.12)
                current = inspect(driver)["devicePixelRatio"]
            data = inspect(driver)
            data["targetZoom"] = target
            data["reachedZoom"] = current
            data["overflow"] = data["documentScrollWidth"] > data["documentWidth"] or data["bodyScrollWidth"] > data["innerWidth"]
            data["passed"] = (
                current >= target - 0.08 and len(data["h1"]) == 1
                and data["imageCount"] > 0 and data["institutionBarCount"] == 1
                and data["domainHeaderCount"] == 1 and not data["overflow"]
            )
            records.append(data)
            if current < target - 0.08:
                data["status"] = "DEFERRED"
                data["reason"] = "Firefox keyboard shortcut did not reach requested page zoom."
                break
            driver.js("document.querySelector('.aculta-revista-teaser')?.scrollIntoView({block:'start'}); return true")
            time.sleep(0.2)
            shot = driver.req("GET", f"/session/{driver.session}/screenshot")
            (OUT / f"revista-zoom-{round(target * 100)}.png").write_bytes(base64.b64decode(shot))
            driver.js("window.scrollTo(0,0); return true")

        if article_url:
            reset_zoom(driver)
            set_viewport(driver, WIDTH, 900)
            driver.get(article_url)
            time.sleep(0.8)
            initial = inspect_article(driver)
            article_base_ok = (
                initial["articlePresent"] and initial["bodyPresent"]
                and initial["bodyTextLength"] > 0 and initial["body"]["height"] > 0
                and initial["headingCount"] == 1
                and initial["institutionBarCount"] == 1
                and initial["domainHeaderCount"] == 1
                and initial["documentScrollWidth"] <= initial["documentWidth"]
                and initial["bodyScrollWidth"] <= initial["innerWidth"]
            )
            records.append({"view": "article", "targetZoom": 1.0,
                            "passed": article_base_ok, **initial})
            for target in TARGETS:
                reset_zoom(driver)
                set_viewport(driver, WIDTH, 900)
                driver.js("window.scrollTo(0,0); return true")
                current = inspect_article(driver)["devicePixelRatio"]
                for _ in range(24):
                    if current >= target - 0.08:
                        break
                    press_zoom_in(driver)
                    time.sleep(0.12)
                    current = inspect_article(driver)["devicePixelRatio"]
                data = inspect_article(driver)
                data["view"] = "article"
                data["targetZoom"] = target
                data["reachedZoom"] = current
                data["overflow"] = (
                    data["documentScrollWidth"] > data["documentWidth"]
                    or data["bodyScrollWidth"] > data["innerWidth"]
                    or (data["article"] and data["article"]["right"] > data["documentWidth"] + 1)
                    or (data["body"] and data["body"]["right"] > data["documentWidth"] + 1)
                    or (data["bodyPresent"] and data["bodyScrollWidth"] > data["bodyClientWidth"] + 1)
                    or any(image["right"] > data["documentWidth"] + 1 for image in data["coverImages"])
                )
                data["passed"] = (
                    current >= target - 0.08 and data["innerWidth"] <= int(WIDTH / target) + 8
                    and data["articlePresent"] and data["publicationPresent"]
                    and data["bodyPresent"] and data["bodyTextLength"] > 0
                    and data["body"]["height"] > 0 and data["headingCount"] == 1
                    and data["institutionBarCount"] == 1 and data["domainHeaderCount"] == 1
                    and not data["overflow"]
                )
                records.append(data)
                if current < target - 0.08:
                    data["status"] = "DEFERRED"
                    data["reason"] = "Firefox keyboard shortcut did not reach requested page zoom."
                    break
                driver.js("document.querySelector('main#main-content h1')?.scrollIntoView({block:'start'}); return true")
                time.sleep(0.2)
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"revista-article-heading-zoom-{round(target * 100)}.png").write_bytes(base64.b64decode(shot))
                driver.js("document.querySelector('.aculta-revista-publication__body > *')?.scrollIntoView({block:'start'}); return true")
                time.sleep(0.2)
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"revista-article-body-zoom-{round(target * 100)}.png").write_bytes(base64.b64decode(shot))
                driver.js("window.scrollTo(0,0); return true")
    finally:
        driver.close()

    (OUT / "summary.json").write_text(json.dumps(records, indent=2, ensure_ascii=False) + "\n")
    print(json.dumps({"records": len(records), "targets": [r.get("reachedZoom") for r in records if "targetZoom" in r],
                      "passed": sum(r.get("passed") is True for r in records),
                      "failed": sum(r.get("passed") is False for r in records),
                      "overflow": [r.get("overflow") for r in records if "targetZoom" in r and r.get("targetZoom") >= 2],
                      "output": str(OUT / "summary.json")}, ensure_ascii=False))
    if any(r.get("status") == "DEFERRED" for r in records):
        raise SystemExit(3)
    if any(r.get("overflow") or r.get("passed") is False for r in records):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
