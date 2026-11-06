#!/usr/bin/env python3
"""Consolidated page matrix using URL arguments."""
import base64,json,os,sys,time
from pathlib import Path
from urllib.parse import urlparse,urlunparse
ROOT=Path(__file__).resolve().parents[3];sys.path.insert(0,str(ROOT/"tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver
O=Path(os.environ["SUITE_OUT"]).resolve();U=sys.argv[1:]
if not U or O.is_relative_to(ROOT) or os.environ.get("DISPLAY")!=":0": raise SystemExit("Uso: page-matrix.py URL...; SUITE_OUT externo; DISPLAY=:0.")
W=[int(x) for x in os.environ.get("MATRIX_WIDTHS","320,390,768,1280,1440").split(",")];R=[];d=Driver(bidi=True,browser="firefox")
try:
 for i,u in enumerate(U):
  if urlparse(u).scheme not in ("http","https"): raise SystemExit("URL inválida")
  d.get(u);time.sleep(.4)
  for mode in os.environ.get("MATRIX_MODES","light,dark").split(","):
   d.js("document.documentElement.setAttribute('data-theme',arguments[0])",[mode])
   for w in W:
    r=d.js("return {url:location.href,status:performance.getEntriesByType('navigation')[0]?.responseStatus||null,ready:document.readyState,width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1||document.body.scrollWidth>innerWidth+1}")
    r.update(page=i+1,mode=mode,widthExpected=w);R.append(r);print(f"page{i+1}/{mode}/{w}px status={r['status']} overflow={r['overflow']}",flush=True)
    if w in (W[0],W[-1]): O.mkdir(parents=True,exist_ok=True,mode=0o700);(O/f"page-{i+1}-{mode}-{w}.png").write_bytes(base64.b64decode(d.req("GET",f"/session/{d.session}/screenshot")))
finally:d.close()
O.mkdir(parents=True,exist_ok=True,mode=0o700);(O/"results.json").write_text(json.dumps(R,indent=2))
bad=[r for r in R if r["ready"]!="complete" or r["overflow"] or r["status"] not in (None,200)]
print(f"page matrix: {len(R)-len(bad)}/{len(R)} passed; evidence: {O}");sys.exit(bool(bad))
