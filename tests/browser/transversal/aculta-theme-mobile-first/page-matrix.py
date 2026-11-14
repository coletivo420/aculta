#!/usr/bin/env python3
"""Consolidated page matrix using URL arguments.

A largura é aplicada por emulação de viewport (WebDriver BiDi
`browsingContext.setViewport`), porque redimensionar a janela do Firefox no
X11 não desce de ~500 px. Transições e animações são congeladas antes da
captura para os PNGs serem determinísticos. Cada linha do `results.json`
registra a largura medida e a matriz falha se ela não bater com a pedida.
"""
import asyncio,base64,json,os,sys,time
from pathlib import Path
from urllib.parse import urlparse
import websockets

ROOT=Path(__file__).resolve().parents[4];sys.path.insert(0,str(ROOT/"tests/browser/shop/aculta-cpf-checkout"))
from wdlib import Driver
O=Path(os.environ["SUITE_OUT"]).resolve();U=sys.argv[1:]
if not U or O.is_relative_to(ROOT) or os.environ.get("DISPLAY")!=":0": raise SystemExit("Uso: page-matrix.py URL...; SUITE_OUT externo; DISPLAY=:0.")
W=[int(x) for x in os.environ.get("MATRIX_WIDTHS","320,390,768,1280,1440").split(",")];H=int(os.environ.get("MATRIX_HEIGHT","900"));R=[]

class BiDi:
    """Cliente mínimo para os comandos BiDi usados pela matriz."""
    def __init__(self,url):
        self.url=url;self.n=0
    def cmd(self,method,params=None):
        self.n+=1;mid=self.n
        async def go():
            async with websockets.connect(self.url,max_size=2**22) as ws:
                await ws.send(json.dumps({"id":mid,"method":method,"params":params or {}}))
                while True:
                    msg=json.loads(await ws.recv())
                    if msg.get("id")==mid:return msg
        return asyncio.run(go())

FREEZE=("(() => { if (document.getElementById('aculta-matrix-freeze')) return;"
        " const s=document.createElement('style'); s.id='aculta-matrix-freeze';"
        " s.textContent='*,*::before,*::after{transition:none!important;animation:none!important;caret-color:transparent!important}';"
        " document.head.appendChild(s); })()")

d=Driver(bidi=True,browser="firefox")
try:
 for i,u in enumerate(U):
  if urlparse(u).scheme not in ("http","https"): raise SystemExit("URL inválida")
  d.get(u);time.sleep(.4)
  bidi=BiDi(d.websocket_url)
  ctx=bidi.cmd("browsingContext.getTree")["result"]["contexts"][0]["context"]
  d.js(FREEZE)
  for mode in os.environ.get("MATRIX_MODES","light,dark").split(","):
   d.js("document.documentElement.setAttribute('data-theme',arguments[0])",[mode])
   for w in W:
    rp=bidi.cmd("browsingContext.setViewport",{"context":ctx,"viewport":{"width":w,"height":H,"devicePixelRatio":1}})
    if rp.get("error"): raise SystemExit(f"setViewport {w}px falhou: {rp['error']}")
    time.sleep(.25)
    r=d.js("return {url:location.href,status:performance.getEntriesByType('navigation')[0]?.responseStatus||null,ready:document.readyState,width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1||document.body.scrollWidth>innerWidth+1}")
    r.update(page=i+1,mode=mode,widthExpected=w);R.append(r);print(f"page{i+1}/{mode}/{w}px width={r['width']} status={r['status']} overflow={r['overflow']}",flush=True)
    if w in (W[0],W[-1]): O.mkdir(parents=True,exist_ok=True,mode=0o700);(O/f"page-{i+1}-{mode}-{w}.png").write_bytes(base64.b64decode(d.req("GET",f"/session/{d.session}/screenshot")))
finally:d.close()
O.mkdir(parents=True,exist_ok=True,mode=0o700);(O/"results.json").write_text(json.dumps(R,indent=2))
bad=[r for r in R if r["ready"]!="complete" or r["overflow"] or r["status"] not in (None,200) or r["width"]!=r["widthExpected"]]
print(f"page matrix: {len(R)-len(bad)}/{len(R)} passed; evidence: {O}");sys.exit(bool(bad))
