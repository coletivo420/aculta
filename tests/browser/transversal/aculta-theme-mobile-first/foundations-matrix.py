#!/usr/bin/env python3
"""Consolidated foundations checks for generic purpose URLs."""
import json,os,subprocess,sys,time
from pathlib import Path
from urllib.parse import urlparse
ROOT=Path(__file__).resolve().parents[4];sys.path.insert(0,str(ROOT/"tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver
OUT=Path(os.environ["SUITE_OUT"]).resolve()
if OUT.is_relative_to(ROOT) or os.environ.get("DISPLAY")!=":0": raise SystemExit("SUITE_OUT externo e DISPLAY=:0 são obrigatórios.")
raw=os.environ.get("PURPOSES_JSON") or json.dumps([{"purpose":"main","url":os.environ.get("BASE_URL","")}])
P=json.loads(raw); W=[int(x) for x in os.environ.get("FOUNDATIONS_WIDTHS","320,390,768,1280,1440").split(",")]
if not P or any(urlparse(x["url"]).scheme not in ("http","https") for x in P): raise SystemExit("URLs http(s) obrigatórias.")
def css():
 return "\n".join(subprocess.check_output(["git","show",f"{os.environ.get('BASELINE_REF','HEAD')}:web/themes/custom/aculta420_v2/src/styles/{x}"],cwd=ROOT,text=True) for x in ("tokens.css","base.css","layout.css"))
def run(pref=None):
 d=Driver(prefs=pref,bidi=True,browser="firefox"); out=[]
 try:
  for x in P:
   d.get(x["url"]); time.sleep(.4)
   for mode in ("light","dark"):
    d.js("document.documentElement.setAttribute('data-theme',arguments[0])",[mode])
    for w in W:
     time.sleep(.1)
     out.append(d.js("""return {purpose:arguments[0],mode:arguments[1],widthExpected:arguments[2],width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1||document.body.scrollWidth>innerWidth+1,status:performance.getEntriesByType('navigation')[0]?.responseStatus||null}""",[x["purpose"],mode,w]))
 finally:d.close()
 return out
R=run()
d=Driver(bidi=True,browser="firefox");d.get(P[0]["url"]);d.js("const s=document.createElement('style');s.textContent=arguments[0];document.head.append(s)",[css()]);d.js("document.body.focus()")
key={"actions":[{"type":"keyDown","value":"\ue004"},{"type":"keyUp","value":"\ue004"}]}
d.req("POST",f"/session/{d.session}/actions",{"actions":[{"type":"key","id":"k","actions":key["actions"]}]})
R.append(d.js("const e=document.activeElement,s=getComputedStyle(e);return {type:'keyboard-focus',tag:e.tagName,outline:s.outlineStyle,width:s.outlineWidth}"))
d.req("POST",f"/session/{d.session}/actions",{"actions":[{"type":"key","id":"k2","actions":key["actions"]}]})
d.req("POST",f"/session/{d.session}/actions",{"actions":[{"type":"key","id":"shift","actions":[{"type":"keyDown","value":"\ue008"},{"type":"keyDown","value":"\ue004"},{"type":"keyUp","value":"\ue004"},{"type":"keyUp","value":"\ue008"}]}]})
d.close()
for pref in ({"javascript.enabled":False},{"ui.prefersReducedMotion":1}): R+=run(pref)
OUT.mkdir(parents=True,exist_ok=True,mode=0o700);(OUT/"results.json").write_text(json.dumps(R,indent=2))
bad=[x for x in R if x.get("overflow") or x.get("status") not in (None,200)]
focus=[x for x in R if x.get("type")=="keyboard-focus"]
bad+=[] if focus and focus[0]["tag"]!="BODY" and focus[0]["outline"]!="none" else [{"focus":"bad"}]
print(f"foundations: {len(R)-len(bad)}/{len(R)} passed; evidence: {OUT}");sys.exit(bool(bad))
