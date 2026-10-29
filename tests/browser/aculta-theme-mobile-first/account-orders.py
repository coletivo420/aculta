#!/usr/bin/env python3
"""Read-only Firefox check for the mobile-first account orders table."""

import json
import os
import subprocess
import sys
import time
from pathlib import Path
from websockets.sync.client import connect

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", ROOT))
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))
BASE = os.environ.get("ACCOUNT_ORDERS_BASE_URL", "https://conta.aculta.toca.net.br").rstrip("/")
LOGIN_URI = os.environ.get("ACCOUNT_ORDERS_LOGIN_URI", BASE).rstrip("/")
UID = os.environ.get("ACCOUNT_ORDERS_UID", "1")
PATH = "/pedidos"
WIDTHS = [int(width) for width in os.environ.get("ACCOUNT_ORDERS_WIDTHS", "320,360,390,599,600,767,768,1024,1280,1440").split(",")]
CSS_OVERRIDE = os.environ.get("ACCOUNT_ORDERS_CSS_OVERRIDE") == "1"
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "status": "pass" if passed else "fail", "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


def deferred(name, detail=None):
    results.append({"name": name, "status": "deferred", "passed": None, "detail": detail})
    print(f"DEFERRED {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


def one_time_login_url():
    env = os.environ.copy()
    env.pop("DISPLAY", None)
    process = subprocess.run(
        ["php", "vendor/drush/drush/drush.php", "uli", PATH, "--no-browser", f"--uid={UID}", f"--uri={LOGIN_URI}"],
        cwd=PROJECT, check=True, capture_output=True, text=True, timeout=30, env=env,
    )
    return process.stdout.strip().splitlines()[-1]


class Viewport:
    def __init__(self, driver):
        self.socket = connect(driver.websocket_url)
        self.command_id = 0
        self.context = self.command("browsingContext.getTree")["contexts"][0]["context"]

    def command(self, method, params=None):
        self.command_id += 1
        command_id = self.command_id
        self.socket.send(json.dumps({"id": command_id, "method": method, "params": params or {}}))
        while True:
            response = json.loads(self.socket.recv())
            if response.get("id") != command_id:
                continue
            if "error" in response:
                raise RuntimeError(f"WebDriver BiDi {method}: {response['error']}")
            return response.get("result", {})

    def set(self, driver, width):
        self.command("browsingContext.setViewport", {
            "context": self.context,
            "viewport": {"width": width, "height": 900},
        })
        time.sleep(0.2)
        return int(driver.js("return innerWidth"))

    def close(self):
        self.socket.close()


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

driver = Driver(bidi=True)
viewport = Viewport(driver)
try:
    driver.get(one_time_login_url())
    driver.get(f"{BASE}{PATH}")
    deadline = time.monotonic() + 15
    authenticated = False
    while time.monotonic() < deadline:
        authenticated = bool(driver.js("return document.body.classList.contains('user-logged-in')"))
        if authenticated:
            break
        time.sleep(0.2)
    record("sessão autenticada no purpose Conta", authenticated)

    if authenticated:
        selector = ".portal-account__commerce--orders table"
        ready = False
        deadline = time.monotonic() + 15
        while time.monotonic() < deadline:
            ready = driver.exists(selector)
            if ready:
                break
            time.sleep(0.2)
        record("tabela real de pedidos carregada", ready)

        fixture_css = None
        if ready and CSS_OVERRIDE:
            fixture_css = (ROOT / "web/themes/custom/aculta420/css/components/portal-account.css").read_text()
        if ready:
            driver.js("""const wrapper=document.createElement('div');
              wrapper.id='account-orders-css-fixture';
              wrapper.className='portal-account__commerce--orders';
              wrapper.style.cssText='position:fixed;top:-20000px;inset-inline:0;width:calc(100vw - 32px)';
              const table=document.createElement('table');table.className='table';
              const head=document.createElement('thead');const headRow=document.createElement('tr');
              const labels=['Pedido','Data','Total','Situação'];
              for(const label of labels){const th=document.createElement('th');th.textContent=label;headRow.append(th)}
              head.append(headRow);const body=document.createElement('tbody');const row=document.createElement('tr');
              for(const label of labels){const td=document.createElement('td');td.dataset.label=label;td.textContent='Exemplo';row.append(td)}
              body.append(row);table.append(head,body);wrapper.append(table);document.body.append(wrapper);return true;""")

        if ready:
            for width in WIDTHS:
                actual = viewport.set(driver, width)
                if CSS_OVERRIDE:
                    driver.js("document.querySelector('[data-account-orders-candidate]')?.remove()")
                fixture_baseline = driver.js("""const table=document.querySelector('#account-orders-css-fixture table');
                  const head=table.querySelector('thead');const cell=table.querySelector('tbody td');
                  const row=table.querySelector('tbody tr');const ts=getComputedStyle(table);
                  const hs=getComputedStyle(head);const cs=getComputedStyle(cell);const rs=getComputedStyle(row);
                  return {tableDisplay:ts.display,tableWidth:ts.width,tableBorderColor:ts.borderColor,tableBorderVar:ts.getPropertyValue('--bs-table-border-color').trim(),headPosition:hs.position,headClip:hs.clipPath,
                    cellDisplay:cs.display,cellPadding:cs.padding,cellBorder:cs.border,cellBorderColor:cs.borderColor,cellBottomColor:cs.borderBottomColor,textAlign:cs.textAlign,
                    rowPadding:rs.padding,rowBorder:rs.border,label:getComputedStyle(cell,'::before').content,
                    documentWidth:document.documentElement.scrollWidth};""")
                if CSS_OVERRIDE:
                    driver.js("""const style=document.createElement('style');
                      style.dataset.accountOrdersCandidate='true';style.textContent=arguments[0];
                      document.head.append(style);return true;""", [fixture_css])
                fixture = driver.js("""const table=document.querySelector('#account-orders-css-fixture table');
                  const head=table.querySelector('thead');const cell=table.querySelector('tbody td');
                  const row=table.querySelector('tbody tr');const ts=getComputedStyle(table);
                  const hs=getComputedStyle(head);const cs=getComputedStyle(cell);const rs=getComputedStyle(row);
                  return {tableDisplay:ts.display,tableWidth:ts.width,tableBorderColor:ts.borderColor,tableBorderVar:ts.getPropertyValue('--bs-table-border-color').trim(),headPosition:hs.position,headClip:hs.clipPath,
                    cellDisplay:cs.display,cellPadding:cs.padding,cellBorder:cs.border,cellBorderColor:cs.borderColor,cellBottomColor:cs.borderBottomColor,textAlign:cs.textAlign,
                    rowPadding:rs.padding,rowBorder:rs.border,label:getComputedStyle(cell,'::before').content,
                    documentWidth:document.documentElement.scrollWidth};""")
                metrics = driver.js("""const table=document.querySelector(arguments[0]);
                  const head=table?.querySelector('thead');
                  const firstRowCells=[...table?.querySelectorAll('tbody tr:first-child td')||[]];
                  const cells=[...table?.querySelectorAll('tbody td')||[]];
                  const headers=[...table?.querySelectorAll('thead th')||[]];
                  const style=table?getComputedStyle(table):null;
                  const headerStyle=head?getComputedStyle(head):null;
                  const cellStyle=cells[0]?getComputedStyle(cells[0]):null;
                  return {width:innerWidth,documentWidth:document.documentElement.scrollWidth,
                    tableDisplay:style?.display||null,headPosition:headerStyle?.position||null,
                    headClip:headerStyle?.clipPath||null,bodyRows:table?.querySelectorAll('tbody tr').length||0,
                    rowCellCounts:[...table?.querySelectorAll('tbody tr')||[]].map(row=>row.querySelectorAll('td').length),
                    headerCount:headers.length,headerLabels:headers.map(cell=>cell.textContent.trim()),
                    cellCount:firstRowCells.length,totalCellCount:cells.length,
                    labelCount:cells.filter(cell=>cell.hasAttribute('data-label')).length,
                    firstCellColspan:firstRowCells[0]?.getAttribute('colspan')||null,
                    firstLabel:firstRowCells[0]?.getAttribute('data-label')||null,
                    firstLabelContent:firstRowCells[0]?getComputedStyle(firstRowCells[0],'::before').content:null,
                    cellDisplay:cellStyle?.display||null};""", [selector])
                mobile = width < 768
                layout_ok = (
                    metrics["tableDisplay"] == ("block" if mobile else "table")
                    and metrics["cellDisplay"] == ("flex" if mobile else "table-cell")
                    and (metrics["headPosition"] == "absolute" if mobile else metrics["headPosition"] == "static")
                )
                empty_row = metrics["rowCellCounts"] == [1] and metrics["firstCellColspan"] == "4"
                data_rows = bool(metrics["rowCellCounts"]) and all(count == 4 for count in metrics["rowCellCounts"])
                structure_ok = metrics["headerCount"] == 4 and (empty_row or data_rows)
                record(f"layout de pedidos {'mobile' if mobile else 'desktop'} em {width}px",
                       actual == width and layout_ok and structure_ok and metrics["documentWidth"] <= width,
                       {key: metrics[key] for key in ("width", "documentWidth", "tableDisplay", "headPosition", "headClip", "bodyRows", "rowCellCounts", "headerCount", "cellDisplay")})
                mobile_fixture_ok = (
                    fixture["tableDisplay"] == "block" and fixture["cellDisplay"] == "flex"
                    and fixture["headPosition"] == "absolute" and fixture["label"] == "attr(data-label)"
                )
                desktop_style_same = all(
                    fixture[key] == fixture_baseline[key]
                    for key in ("tableWidth", "cellPadding", "cellBorder", "textAlign")
                )
                desktop_fixture_ok = (
                    fixture["tableDisplay"] == "table" and fixture["cellDisplay"] == "table-cell"
                    and fixture["headPosition"] == "static" and fixture["label"] == "none" and desktop_style_same
                )
                record(f"linha sintética {'mobile' if mobile else 'desktop'} em {width}px",
                       actual == width and (mobile_fixture_ok if mobile else desktop_fixture_ok) and fixture["documentWidth"] <= width,
                       {"table": fixture["tableDisplay"], "head": fixture["headPosition"],
                        "cell": fixture["cellDisplay"], "generated_label": fixture["label"],
                        "documentWidth": fixture["documentWidth"],
                        "desktop_restores_baseline": desktop_style_same if not mobile else None})

            if data_rows:
                labels_ok = metrics["labelCount"] == metrics["totalCellCount"]
                deferred("rótulos de cada coluna em linha real", {"presente": labels_ok, "origem": "UID de teste autorizado"})
            elif empty_row:
                deferred("rótulos em linha real", "a conta autorizada só tem pedidos em rascunho; a tabela apresenta o estado vazio")
finally:
    viewport.close()
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if result["status"] == "fail"]
passed = sum(result["status"] == "pass" for result in results)
deferred_count = sum(result["status"] == "deferred" for result in results)
print(f"\n{passed} passed, {deferred_count} deferred, {len(failed)} failed; evidence: {OUT}")
sys.exit(1 if failed else 0)
