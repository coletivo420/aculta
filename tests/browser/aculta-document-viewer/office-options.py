#!/usr/bin/env python3
"""Firefox ESR visual, accessibility, and behavior test for D-48 DOCX/spreadsheet options."""

import base64
import functools
import http.server
import io
import json
import os
import socketserver
import sys
import threading
import time
import zipfile
from pathlib import Path

from websockets.sync.client import connect

PROJECT = Path(os.environ.get("ACULTA_PROJECT_ROOT", Path(__file__).resolve().parents[3]))
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = Path(os.environ["SUITE_OUT"])
WIDTHS = [(1280, "desktop"), (390, "mobile-390"), (320, "mobile-320")]
results = []


def check(name, passed, detail=""):
    results.append({"name": name, "passed": bool(passed), "detail": str(detail)})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""), flush=True)


if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")
if not OUT.is_absolute():
    raise SystemExit("SUITE_OUT deve ser um caminho absoluto fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))


def build_sample_docx() -> bytes:
    """Builds a minimal valid OOXML DOCX with header, footer, footnote, endnote, and page break."""
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.writestr(
            "[Content_Types].xml",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>
</Types>""",
        )
        archive.writestr(
            "_rels/.rels",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>""",
        )
        archive.writestr(
            "word/_rels/document.xml.rels",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHeader1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rIdFooter1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
  <Relationship Id="rIdEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="endnotes.xml"/>
</Relationships>""",
        )
        archive.writestr(
            "word/header1.xml",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p><w:r><w:t>CABECALHO_DOCX_ACULTA</w:t></w:r></w:p>
</w:hdr>""",
        )
        archive.writestr(
            "word/footer1.xml",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p><w:r><w:t>RODAPE_DOCX_ACULTA</w:t></w:r></w:p>
</w:ftr>""",
        )
        archive.writestr(
            "word/footnotes.xml",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:footnote w:id="1"><w:p><w:r><w:t>NOTA_RODAPE_DOCX_ACULTA</w:t></w:r></w:p></w:footnote>
</w:footnotes>""",
        )
        archive.writestr(
            "word/endnotes.xml",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:endnote w:id="1"><w:p><w:r><w:t>NOTA_FIM_DOCX_ACULTA</w:t></w:r></w:p></w:endnote>
</w:endnotes>""",
        )
        archive.writestr(
            "word/document.xml",
            """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    <w:p>
      <w:r><w:t>CORPO_PAGINA_1_ACULTA</w:t></w:r>
      <w:r><w:footnoteReference w:id="1"/></w:r>
      <w:r><w:endnoteReference w:id="1"/></w:r>
    </w:p>
    <w:p><w:r><w:br w:type="page"/></w:r></w:p>
    <w:p><w:r><w:t>CORPO_PAGINA_2_ACULTA</w:t></w:r></w:p>
    <w:sectPr>
      <w:headerReference w:type="default" r:id="rIdHeader1"/>
      <w:footerReference w:type="default" r:id="rIdFooter1"/>
      <w:pgSz w:w="11906" w:h="16838"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>
    </w:sectPr>
  </w:body>
</w:document>""",
        )
    return buffer.getvalue()


def build_sample_csv() -> bytes:
    """Builds a 30-row CSV fixture to exercise 25-row table pagination."""
    rows = ["codigo,item,quantidade"]
    for idx in range(1, 31):
        rows.append(f"ITEM-{idx:02d},Insumo editorial {idx},{idx * 3}")
    return ("\n".join(rows) + "\n").encode("utf-8")


def build_test_page(theme: str) -> str:
    """Builds a self-contained HTML harness loading the real module JS/CSS and theme styles."""
    if theme == "olivero":
        theme_css = '<link rel="stylesheet" href="/core/themes/olivero/css/base/base.css">'
        body_class = "theme-olivero"
    else:
        theme_css = (
            '<link rel="stylesheet" href="/themes/custom/aculta420/css/tokens.css">\n'
            '  <link rel="stylesheet" href="/themes/custom/aculta420/css/components/document-viewer.css">'
        )
        body_class = "theme-aculta420"

    docx_libs = json.dumps([
        "/libraries/docx-preview/dist/jszip.min.js",
        "/libraries/docx-preview/dist/docx-preview.min.js",
    ])
    docx_default_opts = json.dumps({
        "renderHeaders": True,
        "renderFooters": True,
        "renderFootnotes": True,
        "renderEndnotes": True,
        "respectPageBreaks": False,
    })
    docx_custom_opts = json.dumps({
        "renderHeaders": False,
        "renderFooters": False,
        "renderFootnotes": False,
        "renderEndnotes": False,
        "respectPageBreaks": True,
    })
    sheet_libs = json.dumps(["/libraries/sheetjs/dist/xlsx.full.min.js"])

    return f"""<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ACULTA Document Viewer — D-48 ({theme})</title>
  {theme_css}
  <link rel="stylesheet" href="/libraries/tabulator/dist/tabulator.min.css">
  <style>
    *, *::before, *::after {{ box-sizing: border-box; }}
    body {{ margin: 0; padding: 12px; font-family: system-ui, sans-serif; overflow-x: hidden; }}
    main {{ max-width: 100%; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; }}
    section, form, details {{ min-width: 0; max-width: 100%; }}
    details {{ border: 1px solid #8884; border-radius: 6px; padding: 10px; }}
    .form-item {{ margin: 8px 0; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; min-width: 0; }}
    select {{ max-width: 100%; }}
    .description {{ width: 100%; font-size: 0.875rem; opacity: 0.85; }}
    [data-aculta-document-viewer] {{ border: 1px solid #8884; border-radius: 6px; padding: 10px; max-width: 100%; overflow: hidden; }}
    [data-aculta-document-control="toolbar"] {{ display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 8px; }}
    [data-aculta-document-part="modes"] {{ display: inline-flex; flex-wrap: wrap; gap: 6px; align-items: center; }}
    [data-aculta-document-part="frame"] {{ width: 100%; max-width: 100%; overflow-x: auto; }}
    [data-aculta-document-part="document"] {{ width: 100%; min-height: 220px; border: 0; display: block; }}
    button:focus-visible, select:focus-visible, input:focus-visible, a:focus-visible {{
      outline: 3px solid #0b5fff;
      outline-offset: 2px;
    }}
  </style>
</head>
<body class="{body_class}">
  <main>
    <h1>Leitor de documentos — opções administrativas D-48 ({theme})</h1>
    <nav aria-label="Subabas horizontais do Leitor de documentos">
      <ul class="tabs tabs--secondary" style="display:flex; gap:8px; list-style:none; padding:0; margin:0 0 12px 0;">
        <li><a href="/painel-administrativo/aculta/leitor-documentos" data-subtab="aculta_document_viewer_pdf">PDF</a></li>
        <li><a href="/painel-administrativo/aculta/leitor-documentos/documentos" data-subtab="aculta_document_viewer_doc" aria-current="page">Documentos</a></li>
        <li><a href="/painel-administrativo/aculta/leitor-documentos/planilhas" data-subtab="aculta_document_viewer_sheet">Planilhas</a></li>
      </ul>
    </nav>
    <form id="aculta-document-viewer-settings" aria-label="Configurações globais do leitor de documentos">
      <p>Estes são os padrões globais de PDF, documentos DOCX e planilhas. A autorização do arquivo no Drupal continua prevalecendo para leitura e download.</p>
      <details open id="edit-docx" data-aculta-document-admin-section="aculta_document_viewer_doc">
        <summary>Apresentação de documentos DOCX</summary>
        <div class="description">As opções só afetam a prévia no navegador. O arquivo original e o link autorizado para download permanecem disponíveis.</div>
        <div class="form-item">
          <input type="checkbox" id="edit-docx-render-headers" name="docx_render_headers" checked data-config-target="aculta_document_viewer.settings:docx_render_headers">
          <label for="edit-docx-render-headers">Exibir cabeçalhos</label>
        </div>
        <div class="form-item">
          <input type="checkbox" id="edit-docx-render-footers" name="docx_render_footers" checked data-config-target="aculta_document_viewer.settings:docx_render_footers">
          <label for="edit-docx-render-footers">Exibir rodapés</label>
        </div>
        <div class="form-item">
          <input type="checkbox" id="edit-docx-render-footnotes" name="docx_render_footnotes" checked data-config-target="aculta_document_viewer.settings:docx_render_footnotes">
          <label for="edit-docx-render-footnotes">Exibir notas de rodapé</label>
        </div>
        <div class="form-item">
          <input type="checkbox" id="edit-docx-render-endnotes" name="docx_render_endnotes" checked data-config-target="aculta_document_viewer.settings:docx_render_endnotes">
          <label for="edit-docx-render-endnotes">Exibir notas de fim</label>
        </div>
        <div class="form-item">
          <input type="checkbox" id="edit-docx-respect-page-breaks" name="docx_respect_page_breaks" data-config-target="aculta_document_viewer.settings:docx_respect_page_breaks">
          <label for="edit-docx-respect-page-breaks">Respeitar quebras de página gravadas no documento</label>
        </div>
      </details>

      <details open id="edit-spreadsheet" data-aculta-document-admin-section="aculta_document_viewer_sheet">
        <summary>Apresentação de planilhas</summary>
        <div class="description">As planilhas continuam somente para leitura. Os limites máximos de abas, linhas, colunas e texto de célula não podem ser aumentados pelo painel.</div>
        <div class="form-item">
          <label for="edit-spreadsheet-default-mode">Modo inicial</label>
          <select id="edit-spreadsheet-default-mode" name="spreadsheet_default_mode" data-config-target="aculta_document_viewer.settings:spreadsheet_default_mode">
            <option value="sheet" selected>Planilha com coordenadas de células</option>
            <option value="table">Tabela com filtros e paginação</option>
          </select>
        </div>
        <div class="form-item">
          <label for="edit-spreadsheet-page-size">Linhas por página no modo tabela</label>
          <select id="edit-spreadsheet-page-size" name="spreadsheet_page_size" data-config-target="aculta_document_viewer.settings:spreadsheet_page_size">
            <option value="25">25</option>
            <option value="50" selected>50</option>
            <option value="100">100</option>
          </select>
        </div>
      </details>
    </form>

    <section aria-label="Prévia DOCX padrão">
      <div id="docx-default"
           data-aculta-document-viewer="docx"
           data-aculta-docx="true"
           data-aculta-docx-url="/__fixtures__/sample.docx"
           data-aculta-docx-frame-url="/modules/custom/aculta_portal/modules/aculta_document_viewer/docx/docx-frame.html"
           data-aculta-docx-libs='{docx_libs}'
           data-aculta-docx-render-options='{docx_default_opts}'
           data-aculta-docx-title="Ata padrão">
        <div data-aculta-document-part="header">
          <span data-aculta-document-part="badge">Documento Word (DOCX)</span>
          <span data-aculta-document-part="title">Ata padrão</span>
        </div>
        <div data-aculta-document-control="toolbar" role="toolbar" aria-label="Controles do leitor: Ata padrão"></div>
        <p data-aculta-document-control="status" role="status" aria-live="polite" hidden></p>
        <div data-aculta-document-part="frame" role="region" aria-label="Ata padrão"></div>
        <a href="/__fixtures__/sample.docx" download>Baixar Ata padrão</a>
      </div>
    </section>

    <section aria-label="Prévia DOCX sem cabeçalho/rodapé/notas e com quebra de página">
      <div id="docx-custom"
           data-aculta-document-viewer="docx"
           data-aculta-docx="true"
           data-aculta-docx-url="/__fixtures__/sample.docx"
           data-aculta-docx-frame-url="/modules/custom/aculta_portal/modules/aculta_document_viewer/docx/docx-frame.html"
           data-aculta-docx-libs='{docx_libs}'
           data-aculta-docx-render-options='{docx_custom_opts}'
           data-aculta-docx-title="Ata enxuta">
        <div data-aculta-document-part="header">
          <span data-aculta-document-part="badge">Documento Word (DOCX)</span>
          <span data-aculta-document-part="title">Ata enxuta</span>
        </div>
        <div data-aculta-document-control="toolbar" role="toolbar" aria-label="Controles do leitor: Ata enxuta"></div>
        <p data-aculta-document-control="status" role="status" aria-live="polite" hidden></p>
        <div data-aculta-document-part="frame" role="region" aria-label="Ata enxuta"></div>
        <a href="/__fixtures__/sample.docx" download>Baixar Ata enxuta</a>
      </div>
    </section>

    <section aria-label="Planilha em modo inicial Tabela com 25 linhas por página">
      <div id="sheet-table-25"
           data-aculta-document-viewer="spreadsheet"
           data-aculta-sheet="true"
           data-aculta-sheet-url="/__fixtures__/sample.csv"
           data-aculta-sheet-format="csv"
           data-aculta-sheet-tabulator="/libraries/tabulator/dist/tabulator_esm.min.mjs"
           data-aculta-sheet-frame-url="/modules/custom/aculta_portal/modules/aculta_document_viewer/js/sheet-frame.html"
           data-aculta-sheet-libs='{sheet_libs}'
           data-aculta-sheet-title="Orçamento 2026"
           data-aculta-sheet-default-mode="table"
           data-aculta-sheet-page-size="25">
        <div data-aculta-document-part="header">
          <span data-aculta-document-part="badge">Planilha</span>
          <span data-aculta-document-part="title">Orçamento 2026</span>
        </div>
        <div data-aculta-document-control="toolbar" role="toolbar" aria-label="Controles do leitor: Orçamento 2026"></div>
        <p data-aculta-document-control="status" role="status" aria-live="polite" hidden></p>
        <div data-aculta-document-part="frame" role="region" aria-label="Orçamento 2026"></div>
        <a href="/__fixtures__/sample.csv" download>Baixar Orçamento 2026</a>
      </div>
    </section>
  </main>

  <script>
    window.Drupal = {{
      behaviors: {{}},
      t: (str) => str,
    }};
    window.once = (id, selector, context = document) => {{
      const root = context || document;
      return [...root.querySelectorAll(selector)].filter((el) => {{
        const key = `data-once-${{id}}`;
        if (el.hasAttribute(key)) return false;
        el.setAttribute(key, 'true');
        return true;
      }});
    }};
  </script>
  <script type="module">
    import '/modules/custom/aculta_portal/modules/aculta_document_viewer/js/docx-viewer-behavior.mjs';
    import '/modules/custom/aculta_portal/modules/aculta_document_viewer/js/spreadsheet-viewer.mjs';
    window.addEventListener('DOMContentLoaded', () => {{
      Object.values(window.Drupal.behaviors).forEach((behavior) => {{
        if (behavior && typeof behavior.attach === 'function') {{
          behavior.attach(document);
        }}
      }});
    }});
  </script>
</body>
</html>"""


class FixtureHandler(http.server.SimpleHTTPRequestHandler):
    """Serves candidate web/ assets and in-memory DOCX/CSV fixtures."""

    docx_bytes = build_sample_docx()
    csv_bytes = build_sample_csv()

    def log_message(self, format, *args):
        return

    def do_GET(self):
        if self.path.startswith("/__harness__/"):
            theme = "olivero" if "olivero" in self.path else "aculta420"
            body = build_test_page(theme).encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        if self.path == "/__fixtures__/sample.docx":
            self.send_response(200)
            self.send_header(
                "Content-Type",
                "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            )
            self.send_header("Content-Length", str(len(self.docx_bytes)))
            self.end_headers()
            self.wfile.write(self.docx_bytes)
            return
        if self.path == "/__fixtures__/sample.csv":
            self.send_response(200)
            self.send_header("Content-Type", "text/csv; charset=utf-8")
            self.send_header("Content-Length", str(len(self.csv_bytes)))
            self.end_headers()
            self.wfile.write(self.csv_bytes)
            return
        super().do_GET()


class ThreadedTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True


driver = None
bidi = None
bidi_id = 0


def wait_for(script, timeout=35):
    end = time.monotonic() + timeout
    expr = script.strip()
    while time.monotonic() < end:
        try:
            value = driver.js(f"return ({expr});")
            if value:
                return value
        except Exception:
            pass
        time.sleep(0.25)
    return False


def set_width(width):
    global bidi_id
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({"id": command_id, "method": "browsingContext.getTree", "params": {}}))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    context = response["result"]["contexts"][0]["context"]
    bidi_id += 1
    command_id = bidi_id
    bidi.send(json.dumps({
        "id": command_id,
        "method": "browsingContext.setViewport",
        "params": {"context": context, "viewport": {"width": width, "height": 900}},
    }))
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    time.sleep(0.4)
    return int(driver.js("return window.innerWidth"))


def screenshot(name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"document-viewer-{name}.png").write_bytes(base64.b64decode(data))


def press_tab():
    key = "\ue004"
    driver.req("POST", f"/session/{driver.session}/actions", {
        "actions": [{
            "type": "key",
            "id": "document-viewer-office-keyboard",
            "actions": [{"type": "keyDown", "value": key}, {"type": "keyUp", "value": key}],
        }],
    })


handler_cls = functools.partial(FixtureHandler, directory=str(PROJECT / "web"))
server = ThreadedTCPServer(("127.0.0.1", 0), handler_cls)
port = server.server_address[1]
server_thread = threading.Thread(target=server.serve_forever, daemon=True)
server_thread.start()

try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)

    for theme in ("aculta420", "olivero"):
        set_width(1280)
        driver.get(f"http://127.0.0.1:{port}/__harness__/{theme}")
        ready = wait_for("""
          document.querySelector('#docx-default')?.dataset.acultaDocxStatus === 'done' &&
          document.querySelector('#docx-custom')?.dataset.acultaDocxStatus === 'done' &&
          document.querySelector('#sheet-table-25')?.dataset.acultaSheetStatus === 'ok'
        """, timeout=40)
        check(f"[{theme}] leitores DOCX (padrão/custom) e planilha montados sem erro", bool(ready))

        form_audit = driver.js("""
          const targets = [...document.querySelectorAll('#aculta-document-viewer-settings [data-config-target]')]
            .map(el => ({
              name: el.name,
              target: el.dataset.configTarget,
              labelled: Boolean(el.id && document.querySelector(`label[for="${el.id}"]`)),
            }));
          const modeOpts = [...document.querySelectorAll('#edit-spreadsheet-default-mode option')].map(o => o.value);
          const sizeOpts = [...document.querySelectorAll('#edit-spreadsheet-page-size option')].map(o => o.value);
          return { targets, modeOpts, sizeOpts };
        """)
        expected_targets = {
            "aculta_document_viewer.settings:docx_render_headers",
            "aculta_document_viewer.settings:docx_render_footers",
            "aculta_document_viewer.settings:docx_render_footnotes",
            "aculta_document_viewer.settings:docx_render_endnotes",
            "aculta_document_viewer.settings:docx_respect_page_breaks",
            "aculta_document_viewer.settings:spreadsheet_default_mode",
            "aculta_document_viewer.settings:spreadsheet_page_size",
        }
        found_targets = {item["target"] for item in form_audit["targets"]}
        all_labelled = all(item["labelled"] for item in form_audit["targets"])
        check(
            f"[{theme}] opções administrativas D-48 presentes e rotuladas",
            expected_targets == found_targets and all_labelled
            and form_audit["modeOpts"] == ["sheet", "table"]
            and form_audit["sizeOpts"] == ["25", "50", "100"],
            json.dumps(form_audit, ensure_ascii=False),
        )

        # Inspect sandboxed DOCX iframes via WebDriver frame switching
        default_frame = driver.find("#docx-default iframe[data-aculta-document-part='document']")
        driver.req("POST", f"/session/{driver.session}/frame", {"id": default_frame})
        default_text = driver.js("return document.body ? document.body.innerText : ''")
        driver.req("POST", f"/session/{driver.session}/frame", {"id": None})

        custom_frame = driver.find("#docx-custom iframe[data-aculta-document-part='document']")
        driver.req("POST", f"/session/{driver.session}/frame", {"id": custom_frame})
        custom_info = driver.js("""
          return {
            text: document.body ? document.body.innerText : '',
            sections: document.querySelectorAll('section.docx').length,
          };
        """)
        driver.req("POST", f"/session/{driver.session}/frame", {"id": None})

        check(
            f"[{theme}] DOCX padrão exibe cabeçalho, rodapé, nota de rodapé e nota de fim",
            all(token in default_text for token in (
                "CABECALHO_DOCX_ACULTA",
                "RODAPE_DOCX_ACULTA",
                "NOTA_RODAPE_DOCX_ACULTA",
                "NOTA_FIM_DOCX_ACULTA",
                "CORPO_PAGINA_1_ACULTA",
                "CORPO_PAGINA_2_ACULTA",
            )),
        )
        check(
            f"[{theme}] DOCX com cabeçalho/rodapé/notas desligados e quebra de página ativa",
            all(token not in custom_info["text"] for token in (
                "CABECALHO_DOCX_ACULTA",
                "RODAPE_DOCX_ACULTA",
                "NOTA_RODAPE_DOCX_ACULTA",
                "NOTA_FIM_DOCX_ACULTA",
            ))
            and "CORPO_PAGINA_1_ACULTA" in custom_info["text"]
            and "CORPO_PAGINA_2_ACULTA" in custom_info["text"]
            and custom_info["sections"] >= 2,
            f"sections={custom_info['sections']}",
        )

        sheet_info = driver.js("""
          const host = document.querySelector('#sheet-table-25');
          const modeButtons = [...host.querySelectorAll('[data-aculta-document-part="mode"]')].map(b => ({
            text: b.textContent.trim(),
            pressed: b.getAttribute('aria-pressed'),
          }));
          const select = host.querySelector('[data-aculta-document-part="sheet-select"]');
          const rows = host.querySelectorAll('.tabulator-row').length;
          const pages = host.querySelectorAll('.tabulator-page').length;
          const editable = host.querySelectorAll('.tabulator-cell[tabulator-field] input').length;
          return {
            modeButtons,
            selectHidden: select ? select.hidden : true,
            rows,
            pages,
            editable,
          };
        """)
        check(
            f"[{theme}] planilha respeita modo inicial Tabela e paginação de 25 linhas somente leitura",
            sheet_info["modeButtons"] == [
                {"text": "Planilha", "pressed": "false"},
                {"text": "Tabela", "pressed": "true"},
            ]
            and sheet_info["selectHidden"] is False
            and sheet_info["rows"] == 25
            and sheet_info["pages"] >= 2
            and sheet_info["editable"] == 0,
            json.dumps(sheet_info, ensure_ascii=False),
        )

        if theme == "aculta420":
            focused_form = 0
            focused_viewer = 0
            focus_visible = False
            driver.js("document.querySelector('#edit-docx-render-headers').focus()")
            for _ in range(30):
                press_tab()
                state = driver.js("""
                  const el = document.activeElement;
                  if (!el) return null;
                  return {
                    inForm: Boolean(el.closest('#aculta-document-viewer-settings')),
                    inViewer: Boolean(el.closest('[data-aculta-document-viewer]')),
                    visible: el.matches(':focus-visible'),
                  };
                """)
                if state:
                    if state["inForm"]:
                        focused_form += 1
                    if state["inViewer"]:
                        focused_viewer += 1
                    focus_visible = focus_visible or state["visible"]
            check(
                "[aculta420] navegação por Tab percorre formulário e leitores com :focus-visible",
                focused_form >= 4 and focused_viewer >= 4 and focus_visible,
                f"form={focused_form}, viewer={focused_viewer}, focusVisible={focus_visible}",
            )

        for width, label in WIDTHS:
            actual = set_width(width)
            driver.js("window.scrollTo(0, 0)")
            time.sleep(0.25)
            metrics = driver.js("""
              return {
                viewport: window.innerWidth,
                scrollWidth: document.documentElement.scrollWidth,
                docxVisible: Boolean(document.querySelector('#docx-default')?.getBoundingClientRect().width > 0),
                sheetVisible: Boolean(document.querySelector('#sheet-table-25')?.getBoundingClientRect().width > 0),
              };
            """)
            screenshot(f"{theme}-{label}")
            check(
                f"[{theme}] sem overflow horizontal em {width}px",
                actual == width and metrics["scrollWidth"] <= width
                and metrics["docxVisible"] and metrics["sheetVisible"],
                json.dumps(metrics),
            )
finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()
    server.shutdown()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
