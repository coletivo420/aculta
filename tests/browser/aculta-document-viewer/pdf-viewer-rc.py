#!/usr/bin/env python3
"""Firefox ESR visual, accessibility, and behavior test for aculta_document_viewer PDF engine pre-RC."""

import base64
import functools
import http.server
import json
import os
import socketserver
import sys
import threading
import time
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


def build_outlined_pdf() -> bytes:
    """Builds a valid 2-page PDF with an /Outlines bookmark tree pointing to page 1 and page 2."""
    objects = [
        b"1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R >>\nendobj\n",
        b"2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n",
        b"3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] >>\nendobj\n",
        b"4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 400] >>\nendobj\n",
        b"5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 7 0 R /Count 2 >>\nendobj\n",
        b"6 0 obj\n<< /Title (Capitulo 1 Introducao) /Parent 5 0 R /Next 7 0 R /Dest [3 0 R /XYZ 0 400 null] >>\nendobj\n",
        b"7 0 obj\n<< /Title (Capitulo 2 Conclusao) /Parent 5 0 R /Prev 6 0 R /Dest [4 0 R /XYZ 0 400 null] >>\nendobj\n",
    ]
    out = bytearray(b"%PDF-1.4\n")
    offsets = []
    for obj in objects:
        offsets.append(len(out))
        out.extend(obj)
    xref_pos = len(out)
    out.extend(f"xref\n0 {len(objects) + 1}\n0000000000 65535 f \n".encode("ascii"))
    for offset in offsets:
        out.extend(f"{offset:010d} 00000 n \n".encode("ascii"))
    out.extend(
        f"trailer\n<< /Size {len(objects) + 1} /Root 1 0 R >>\nstartxref\n{xref_pos}\n%%EOF\n".encode("ascii")
    )
    return bytes(out)


OUTLINED_PDF_BYTES = build_outlined_pdf()


def render_harness_html(theme: str) -> str:
    theme_links = (
        """
  <link rel="stylesheet" href="/themes/custom/aculta420/css/tokens.css">
  <link rel="stylesheet" href="/themes/custom/aculta420/css/base.css">
  <link rel="stylesheet" href="/themes/custom/aculta420/css/components/document-viewer.css">
"""
        if theme == "aculta420"
        else """
  <link rel="stylesheet" href="/core/themes/olivero/css/base/variables.css">
  <link rel="stylesheet" href="/core/themes/olivero/css/base/base.css">
  <link rel="stylesheet" href="/themes/custom/aculta420/css/components/document-viewer.css">
"""
    )
    pdf_url = "/libraries/pdf.js/web/compressed.tracemonkey-pldi-09.pdf"
    wrapper_off = (
        "/modules/custom/aculta_portal/modules/aculta_document_viewer/viewer-wrapper.html"
        f"?file={pdf_url}&locale=pt-BR&scroll=vertical&spread=0&spread_mobile=0&print=0&download=0"
    )
    wrapper_on = (
        "/modules/custom/aculta_portal/modules/aculta_document_viewer/viewer-wrapper.html"
        f"?file={pdf_url}&locale=pt-BR&scroll=horizontal&spread=1&spread_mobile=0&print=1&download=1"
    )
    return f"""<!doctype html>
<html lang="pt-BR" data-theme="{theme}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ACULTA PDF Viewer — Homologação RC ({theme})</title>
  {theme_links}
  <style>
    *, *::before, *::after {{ box-sizing: border-box; }}
    body {{
      margin: 0;
      padding: 1rem;
      font-family: system-ui, -apple-system, sans-serif;
      line-height: 1.5;
      overflow-x: hidden;
    }}
    main {{
      max-width: 72rem;
      width: 100%;
      margin: 0 auto;
      display: grid;
      grid-template-columns: minmax(0, 1fr);
      gap: 1.5rem;
    }}
    section, form, details {{
      min-width: 0;
      max-width: 100%;
    }}
    .admin-panel, .aculta-pdf-viewer {{
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      padding: 1rem;
      background: #ffffff;
      color: #0f172a;
      max-width: 100%;
      min-width: 0;
    }}
    .form-grid {{
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
      gap: 0.75rem;
      margin-top: 0.5rem;
    }}
    .form-item {{
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      min-width: 0;
    }}
    .form-item--checkbox {{
      flex-direction: row;
      align-items: center;
      gap: 0.5rem;
    }}
    button, input, select, summary, a {{
      min-height: 2.75rem;
      padding: 0.375rem 0.65rem;
      font: inherit;
      max-width: 100%;
    }}
    button:focus-visible, input:focus-visible, select:focus-visible, summary:focus-visible, a:focus-visible {{
      outline: 3px solid #0284c7;
      outline-offset: 2px;
    }}
    [data-aculta-pdf-control="toolbar"],
    [data-aculta-pdf-control="reader-controls"],
    [data-aculta-pdf-control="zoom-controls"],
    [data-aculta-pdf-control="search-controls"] {{
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
    }}
    [data-aculta-pdf-control="page-stage"] {{
      max-width: 100%;
      overflow-x: auto;
    }}
    [data-aculta-pdf-control="page"] {{
      max-width: 100%;
      height: auto;
      display: block;
    }}
    [data-aculta-pdf-control="page-thumbnail-items"] {{
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      list-style: none;
      padding: 0;
    }}
  </style>
</head>
<body>
  <main>
    <h1>Leitor de PDF — Homologação ({theme})</h1>

    <section class="admin-panel" aria-labelledby="admin-settings-heading">
      <h2 id="admin-settings-heading">Painel administrativo — /painel-administrativo/aculta/leitor-documentos (subaba PDF)</h2>
      <form id="aculta-document-viewer-settings" aria-label="Padrões globais do Leitor de documentos — PDF" data-aculta-document-admin-section="aculta_document_viewer_pdf">
        <details open data-admin-section="controls">
          <summary>Controles padrão</summary>
          <div class="form-grid">
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_download" checked> Exibir link para abrir ou baixar</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_print" checked> Exibir opção de impressão</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_zoom" checked> Exibir controles de zoom</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_rotation" checked> Exibir controle de rotação</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_outline" checked> Exibir contorno do PDF</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_page_thumbnails" checked> Exibir miniaturas de páginas</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_fullscreen" checked> Exibir tela cheia</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_search" checked> Exibir busca</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_page_navigation" checked> Exibir navegação de páginas</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="show_full_viewer" checked> Exibir link para o leitor completo PDF.js</label>
          </div>
        </details>

        <details open data-admin-section="defaults">
          <summary>Padrões de leitura</summary>
          <div class="form-grid">
            <label class="form-item">
              <span>Zoom inicial</span>
              <select name="default_zoom">
                <option value="fit-width" selected>Ajustar à largura</option>
                <option value="100">100%</option>
                <option value="125">125%</option>
              </select>
            </label>
            <label class="form-item">
              <span>Rolagem padrão</span>
              <select name="default_scroll">
                <option value="vertical" selected>Vertical</option>
                <option value="horizontal">Horizontal</option>
              </select>
            </label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="double_page"> Solicitar modo de duas páginas</label>
            <label class="form-item form-item--checkbox"><input type="checkbox" name="double_page_mobile"> Manter duas páginas em telas móveis</label>
            <label class="form-item">
              <span>Idioma da interface</span>
              <select name="language">
                <option value="pt-BR" selected>Português do Brasil</option>
              </select>
            </label>
          </div>
        </details>

        <details open data-admin-section="link">
          <summary>Padrões do link e embed</summary>
          <div class="form-grid">
            <label class="form-item"><span>Título acessível do leitor</span><input type="text" name="embed_title" value="Artigo científico ACULTA"></label>
            <label class="form-item"><span>Texto do link para abrir o documento</span><input type="text" name="link_text" value="Abrir ou baixar PDF"></label>
            <label class="form-item"><span>Classe CSS adicional</span><input type="text" name="css_class" value="aculta-pdf-rc"></label>
            <label class="form-item">
              <span>Abrir link em</span>
              <select name="link_target">
                <option value="_blank" selected>Nova aba</option>
                <option value="_self">Mesma aba</option>
              </select>
            </label>
          </div>
        </details>
      </form>
    </section>

    <section aria-labelledby="pdf-viewer-heading">
      <h2 id="pdf-viewer-heading">Leitor PDF incorporado</h2>
      <div id="pdf-viewer-main"
           class="aculta-pdf-viewer aculta-pdf-rc"
           data-aculta-pdf-viewer="true"
           role="region"
           aria-label="Artigo científico ACULTA"
           data-aculta-pdf-url="{pdf_url}"
           data-aculta-pdf-title="Artigo científico ACULTA"
           data-aculta-pdf-default-zoom="fit-width"
           data-aculta-pdf-default-scroll="vertical"
           data-aculta-pdf-double-page="false"
           data-aculta-pdf-double-page-mobile="false"
           data-aculta-pdf-show-zoom="true"
           data-aculta-pdf-show-fullscreen="true"
           data-aculta-pdf-show-search="true"
           data-aculta-pdf-show-page-navigation="true">
        <div data-aculta-pdf-control="toolbar">
          <button type="button" data-aculta-pdf-control="previous" disabled>Página anterior</button>
          <span data-aculta-pdf-control="status" aria-live="polite">Carregando PDF…</span>
          <button type="button" data-aculta-pdf-control="next" disabled>Próxima página</button>
        </div>
        <div data-aculta-pdf-control="reader-controls">
          <div data-aculta-pdf-control="zoom-controls">
            <button type="button" data-aculta-pdf-control="zoom-out" aria-label="Reduzir zoom">Reduzir zoom</button>
            <span data-aculta-pdf-control="zoom-status" aria-live="polite">100%</span>
            <button type="button" data-aculta-pdf-control="zoom-in" aria-label="Aumentar zoom">Aumentar zoom</button>
            <button type="button" data-aculta-pdf-control="zoom-reset">Restaurar zoom</button>
          </div>
          <button type="button" data-aculta-pdf-control="rotate" aria-label="Girar página 90 graus">Girar página 90 graus</button>
          <button type="button" data-aculta-pdf-control="fullscreen" aria-label="Abrir leitor em tela cheia" aria-pressed="false">Tela cheia</button>
        </div>
        <div data-aculta-pdf-control="search-controls">
          <input type="search" data-aculta-pdf-control="search-input" aria-label="Buscar texto no PDF">
          <button type="button" data-aculta-pdf-control="search-submit">Buscar</button>
          <button type="button" data-aculta-pdf-control="search-previous" disabled>Resultado anterior</button>
          <button type="button" data-aculta-pdf-control="search-next" disabled>Próximo resultado</button>
          <span data-aculta-pdf-control="search-status" aria-live="polite"></span>
        </div>
        <details data-aculta-pdf-control="outline">
          <summary>Contorno do documento</summary>
          <ul data-aculta-pdf-control="outline-items"></ul>
          <p data-aculta-pdf-control="outline-status" aria-live="polite">Este PDF não contém contorno.</p>
        </details>
        <details data-aculta-pdf-control="page-thumbnails">
          <summary>Miniaturas das páginas</summary>
          <ol data-aculta-pdf-control="page-thumbnail-items"></ol>
          <p data-aculta-pdf-control="page-thumbnail-status" aria-live="polite">As miniaturas serão preparadas ao abrir esta seção.</p>
        </details>
        <div data-aculta-pdf-control="page-stage">
          <canvas data-aculta-pdf-control="page" aria-label="Página do documento" hidden></canvas>
        </div>
        <a href="{pdf_url}" data-aculta-pdf-control="download">Abrir ou baixar PDF</a>
        <a href="{wrapper_on}" target="_blank" rel="noopener noreferrer" data-aculta-pdf-control="print">Abrir leitor completo para imprimir</a>
        <a href="{wrapper_off}" target="_blank" rel="noopener noreferrer" data-aculta-pdf-control="full-viewer">Abrir leitor completo do PDF (nova aba)</a>
      </div>

      <div id="pdf-viewer-outlined"
           class="aculta-pdf-viewer"
           style="margin-top: 1rem;"
           data-aculta-pdf-viewer="true"
           role="region"
           aria-label="Documento com contorno"
           data-aculta-pdf-url="/__harness/outlined.pdf"
           data-aculta-pdf-title="Documento com contorno"
           data-aculta-pdf-default-zoom="fit-width">
        <div data-aculta-pdf-control="toolbar">
          <button type="button" data-aculta-pdf-control="previous" disabled>Página anterior</button>
          <span data-aculta-pdf-control="status" aria-live="polite">Carregando PDF…</span>
          <button type="button" data-aculta-pdf-control="next" disabled>Próxima página</button>
        </div>
        <div data-aculta-pdf-control="reader-controls">
          <div data-aculta-pdf-control="zoom-controls">
            <button type="button" data-aculta-pdf-control="zoom-out" aria-label="Reduzir zoom">Reduzir zoom</button>
            <span data-aculta-pdf-control="zoom-status" aria-live="polite">100%</span>
            <button type="button" data-aculta-pdf-control="zoom-in" aria-label="Aumentar zoom">Aumentar zoom</button>
            <button type="button" data-aculta-pdf-control="zoom-reset">Restaurar zoom</button>
          </div>
          <button type="button" data-aculta-pdf-control="rotate" aria-label="Girar página 90 graus">Girar página 90 graus</button>
          <button type="button" data-aculta-pdf-control="fullscreen" aria-label="Abrir leitor em tela cheia" aria-pressed="false">Tela cheia</button>
        </div>
        <div data-aculta-pdf-control="search-controls">
          <input type="search" data-aculta-pdf-control="search-input" aria-label="Buscar texto no PDF">
          <button type="button" data-aculta-pdf-control="search-submit">Buscar</button>
          <button type="button" data-aculta-pdf-control="search-previous" disabled>Resultado anterior</button>
          <button type="button" data-aculta-pdf-control="search-next" disabled>Próximo resultado</button>
          <span data-aculta-pdf-control="search-status" aria-live="polite"></span>
        </div>
        <details open data-aculta-pdf-control="outline">
          <summary>Contorno do documento</summary>
          <ul data-aculta-pdf-control="outline-items"></ul>
          <p data-aculta-pdf-control="outline-status" aria-live="polite">Este PDF não contém contorno.</p>
        </details>
        <div data-aculta-pdf-control="page-stage">
          <canvas data-aculta-pdf-control="page" aria-label="Página do documento" hidden></canvas>
        </div>
      </div>
    </section>
  </main>
  <script type="module" src="/modules/custom/aculta_portal/modules/aculta_document_viewer/js/pdf-viewer.mjs"></script>
</body>
</html>"""


class HarnessHandler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(PROJECT / "web"), **kwargs)

    def do_GET(self):
        path = self.path.split("?", 1)[0]
        if path == "/__harness/aculta420.html":
            payload = render_harness_html("aculta420").encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)
            return
        if path == "/__harness/olivero.html":
            payload = render_harness_html("olivero").encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "text/html; charset=utf-8")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)
            return
        if path == "/__harness/outlined.pdf":
            self.send_response(200)
            self.send_header("Content-Type", "application/pdf")
            self.send_header("Content-Length", str(len(OUTLINED_PDF_BYTES)))
            self.end_headers()
            self.wfile.write(OUTLINED_PDF_BYTES)
            return
        super().do_GET()

    def log_message(self, format, *args):
        pass


class ThreadedHTTPServer(socketserver.ThreadingMixIn, http.server.HTTPServer):
    daemon_threads = True


server = ThreadedHTTPServer(("127.0.0.1", 0), functools.partial(HarnessHandler))
port = server.server_address[1]
server_thread = threading.Thread(target=server.serve_forever, daemon=True)
server_thread.start()

driver = None
bidi = None
bidi_id = 0


def wait_for(script, timeout=35):
    end = time.monotonic() + timeout
    expr = script.strip()
    while time.monotonic() < end:
        try:
            if driver.js(f"return ({expr});"):
                return True
        except Exception:
            pass
        time.sleep(0.2)
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
    bidi.send(
        json.dumps(
            {
                "id": command_id,
                "method": "browsingContext.setViewport",
                "params": {"context": context, "viewport": {"width": width, "height": 900}},
            }
        )
    )
    while True:
        response = json.loads(bidi.recv())
        if response.get("id") == command_id:
            break
    time.sleep(0.5)
    return int(driver.js("return window.innerWidth"))


def screenshot(name):
    data = driver.req("GET", f"/session/{driver.session}/screenshot")
    (OUT / f"pdf-viewer-{name}.png").write_bytes(base64.b64decode(data))


def press_tab():
    key = "\ue004"
    driver.req(
        "POST",
        f"/session/{driver.session}/actions",
        {
            "actions": [
                {
                    "type": "key",
                    "id": "pdf-viewer-keyboard",
                    "actions": [{"type": "keyDown", "value": key}, {"type": "keyUp", "value": key}],
                }
            ]
        },
    )


try:
    driver = Driver(bidi=True)
    bidi = connect(driver.websocket_url)

    for index, theme in enumerate(["aculta420", "olivero"]):
        set_width(1280)
        driver.get(f"http://127.0.0.1:{port}/__harness/{theme}.html")
        ready = wait_for(
            """
            document.querySelector('[data-aculta-pdf-control="status"]')?.textContent.includes('Página 1 de 14')
            && !document.querySelector('[data-aculta-pdf-control="page"]')?.hidden
            """
        )
        check(f"[{theme}] PDF.js carrega e renderiza página 1 de 14 no canvas", ready)

        if index == 0:
            admin_info = driver.js(
                """
                const form = document.getElementById('aculta-document-viewer-settings');
                return {
                  controlsCount: form.querySelectorAll('[data-admin-section="controls"] input[type="checkbox"]:checked').length,
                  zoomValue: form.querySelector('select[name="default_zoom"]')?.value,
                  scrollValue: form.querySelector('select[name="default_scroll"]')?.value,
                  languageValue: form.querySelector('select[name="language"]')?.value,
                  targetValue: form.querySelector('select[name="link_target"]')?.value,
                };
                """
            )
            check(
                "Painel administrativo exibe os 10 controles, padrões de leitura e opções de link/embed",
                admin_info["controlsCount"] == 10
                and admin_info["zoomValue"] == "fit-width"
                and admin_info["scrollValue"] == "vertical"
                and admin_info["languageValue"] == "pt-BR"
                and admin_info["targetValue"] == "_blank",
                json.dumps(admin_info, ensure_ascii=False),
            )

            # Page navigation: next -> Page 2, previous -> Page 1.
            driver.js("document.querySelector('[data-aculta-pdf-control=\"next\"]').click()")
            nav_next = wait_for(
                "document.querySelector('[data-aculta-pdf-control=\"status\"]')?.textContent === 'Página 2 de 14'"
            )
            driver.js("document.querySelector('[data-aculta-pdf-control=\"previous\"]').click()")
            nav_prev = wait_for(
                "document.querySelector('[data-aculta-pdf-control=\"status\"]')?.textContent === 'Página 1 de 14'"
            )
            check("Navegação de páginas avança e retorna com live status atualizado", nav_next and nav_prev)

            # Zoom in & reset.
            before_zoom = driver.js("return document.querySelector('[data-aculta-pdf-control=\"zoom-status\"]').textContent")
            driver.js("document.querySelector('[data-aculta-pdf-control=\"zoom-in\"]').click()")
            time.sleep(0.4)
            after_zoom = driver.js("return document.querySelector('[data-aculta-pdf-control=\"zoom-status\"]').textContent")
            driver.js("document.querySelector('[data-aculta-pdf-control=\"zoom-reset\"]').click()")
            time.sleep(0.4)
            reset_zoom = driver.js("return document.querySelector('[data-aculta-pdf-control=\"zoom-status\"]').textContent")
            check(
                "Controles de zoom ampliam e restauram a escala configurada",
                before_zoom == "100%" and after_zoom == "125%" and reset_zoom == "100%",
                f"before={before_zoom}, after={after_zoom}, reset={reset_zoom}",
            )

            # Rotation (90 degrees swaps portrait -> landscape aspect ratio).
            dims_before = driver.js(
                """
                const c = document.querySelector('[data-aculta-pdf-control="page"]');
                return {w: c.width, h: c.height};
                """
            )
            driver.js("document.querySelector('[data-aculta-pdf-control=\"rotate\"]').click()")
            rotated = wait_for(
                """
                (() => {
                  const c = document.querySelector('[data-aculta-pdf-control="page"]');
                  return c.width > 0 && c.height > 0 && c.width !== c.height;
                })()
                """
            )
            dims_after = driver.js(
                """
                const c = document.querySelector('[data-aculta-pdf-control="page"]');
                return {w: c.width, h: c.height};
                """
            )
            # Rotate 3 more times to restore 0 deg.
            for _ in range(3):
                driver.js("document.querySelector('[data-aculta-pdf-control=\"rotate\"]').click()")
                time.sleep(0.25)
            check(
                "Controle de rotação gira o canvas em 90 graus",
                rotated and dims_before["h"] > dims_before["w"] and dims_after["h"] < dims_after["w"],
                f"before={dims_before}, after={dims_after}",
            )

            # Outline behavior (hidden when absent, navigable when present) and on-demand page thumbnails.
            outline_ready = wait_for(
                """
                document.querySelectorAll('#pdf-viewer-outlined [data-aculta-pdf-control="outline-items"] button').length === 2
                """
            )
            driver.js(
                "document.querySelectorAll('#pdf-viewer-outlined [data-aculta-pdf-control=\"outline-items\"] button')[1]?.click()"
            )
            outline_nav = wait_for(
                "document.querySelector('#pdf-viewer-outlined [data-aculta-pdf-control=\"status\"]')?.textContent === 'Página 2 de 2'"
            )
            no_outline_hidden = driver.js(
                "return Boolean(document.querySelector('#pdf-viewer-main [data-aculta-pdf-control=\"outline\"]')?.hidden)"
            )
            driver.js(
                """
                const details = document.querySelector('#pdf-viewer-main [data-aculta-pdf-control="page-thumbnails"]');
                details.open = true;
                details.dispatchEvent(new Event('toggle'));
                """
            )
            thumbs_ready = wait_for(
                "document.querySelector('#pdf-viewer-main [data-aculta-pdf-control=\"page-thumbnail-status\"]')?.textContent === '14 miniaturas prontas.'"
            )
            check(
                "Contorno navega para a seção no PDF com bookmarks, oculta quando ausente e miniaturas geram sob demanda",
                outline_ready and outline_nav and no_outline_hidden and thumbs_ready,
                f"outlineReady={outline_ready}, outlineNav={outline_nav}, hiddenWhenEmpty={no_outline_hidden}, thumbs={thumbs_ready}",
            )

            # Search across pages.
            driver.js(
                """
                const input = document.querySelector('[data-aculta-pdf-control="search-input"]');
                input.value = 'TraceMonkey';
                document.querySelector('[data-aculta-pdf-control="search-submit"]').click();
                """
            )
            search_ok = wait_for(
                "document.querySelector('[data-aculta-pdf-control=\"search-status\"]')?.textContent.startsWith('Resultado 1 de ')"
            )
            search_status = driver.js(
                "return document.querySelector('[data-aculta-pdf-control=\"search-status\"]')?.textContent || ''"
            )
            check("Busca textual localiza ocorrências e atualiza live region", search_ok, search_status)

            # Keyboard focus (:focus-visible).
            focused_in_viewer = 0
            focused_in_admin = 0
            focus_visible = False
            for _ in range(55):
                press_tab()
                state = driver.js(
                    """
                    const el = document.activeElement;
                    if (!el || el === document.body) return null;
                    return {
                      inAdmin: Boolean(el.closest('#aculta-document-viewer-settings')),
                      inViewer: Boolean(el.closest('#pdf-viewer-main')),
                      visible: el.matches(':focus-visible'),
                    };
                    """
                )
                if state:
                    if state["inAdmin"]:
                        focused_in_admin += 1
                    if state["inViewer"]:
                        focused_in_viewer += 1
                    focus_visible = focus_visible or bool(state["visible"])
                if focused_in_admin >= 5 and focused_in_viewer >= 5 and focus_visible:
                    break
            check(
                "Teclado percorre painel administrativo e controles do leitor PDF com :focus-visible",
                focused_in_admin > 0 and focused_in_viewer > 0 and focus_visible,
                f"admin={focused_in_admin}, viewer={focused_in_viewer}, focusVisible={focus_visible}",
            )

        for width, label in WIDTHS:
            actual = set_width(width)
            driver.js("window.scrollTo(0, 0)")
            time.sleep(0.3)
            metrics = driver.js(
                """
                const viewer = document.getElementById('pdf-viewer-main');
                const rect = viewer.getBoundingClientRect();
                const canvas = viewer.querySelector('[data-aculta-pdf-control="page"]');
                return {
                  viewport: window.innerWidth,
                  scrollWidth: document.documentElement.scrollWidth,
                  viewerFits: rect.width > 0 && rect.left >= 0 && rect.right <= window.innerWidth + 1,
                  canvasVisible: Boolean(canvas && !canvas.hidden && canvas.getClientRects().length),
                };
                """
            )
            screenshot(f"{theme}-{label}")
            check(
                f"[{theme}] Sem overflow horizontal e canvas visível em {width}px",
                actual == width
                and metrics["scrollWidth"] <= width
                and metrics["viewerFits"]
                and metrics["canvasVisible"],
                json.dumps(metrics, ensure_ascii=False),
            )

    # Validate viewer-wrapper.html policies (print=0&download=0 vs print=1&download=1&scroll=horizontal&spread=1).
    set_width(1280)
    pdf_url = "/libraries/pdf.js/web/compressed.tracemonkey-pldi-09.pdf"
    driver.get(
        f"http://127.0.0.1:{port}/modules/custom/aculta_portal/modules/aculta_document_viewer/viewer-wrapper.html"
        f"?file={pdf_url}&locale=pt-BR&scroll=vertical&spread=0&spread_mobile=0&print=0&download=0"
    )
    wrapper_off_ready = wait_for(
        """
        (() => {
          const frame = document.getElementById('pdf-viewer');
          const win = frame?.contentWindow;
          const doc = win?.document;
          const btn = doc?.getElementById('downloadButton');
          return Boolean(win?.PDFViewerApplication?.pdfDocument && btn && btn.hidden === true && btn.getAttribute('aria-hidden') === 'true' && btn.tabIndex === -1);
        })()
        """
    )
    wrapper_off_state = driver.js(
        """
        const doc = document.getElementById('pdf-viewer').contentWindow.document;
        return ['printButton', 'secondaryPrint', 'downloadButton', 'secondaryDownload'].map(id => {
          const el = doc.getElementById(id);
          return {id, hidden: Boolean(el?.hidden), ariaHidden: el?.getAttribute('aria-hidden'), tabIndex: el?.tabIndex};
        });
        """
    )
    screenshot("wrapper-controls-off")
    check(
        "Wrapper PDF.js oculta e retira do foco os 4 botões de impressão/download quando desativados",
        wrapper_off_ready
        and all(item["hidden"] and item["ariaHidden"] == "true" and item["tabIndex"] == -1 for item in wrapper_off_state),
        json.dumps(wrapper_off_state, ensure_ascii=False),
    )

    driver.get(
        f"http://127.0.0.1:{port}/modules/custom/aculta_portal/modules/aculta_document_viewer/viewer-wrapper.html"
        f"?file={pdf_url}&locale=pt-BR&scroll=horizontal&spread=1&spread_mobile=0&print=1&download=1"
    )
    wrapper_on_ready = wait_for(
        """
        (() => {
          const win = document.getElementById('pdf-viewer')?.contentWindow;
          return Boolean(win?.PDFViewerApplication?.pdfDocument && win?.PDFViewerApplication?.pdfViewer?.pagesCount === 14);
        })()
        """
    )
    time.sleep(0.5)
    desktop_wrapper = driver.js(
        """
        const win = document.getElementById('pdf-viewer').contentWindow;
        const doc = win.document;
        return {
          scrollMode: win.PDFViewerApplication.pdfViewer.scrollMode,
          spreadMode: win.PDFViewerApplication.pdfViewer.spreadMode,
          downloadHidden: Boolean(doc.getElementById('downloadButton')?.hidden),
          printHidden: Boolean(doc.getElementById('printButton')?.hidden),
        };
        """
    )
    set_width(390)
    time.sleep(0.5)
    mobile_spread = driver.js(
        "return document.getElementById('pdf-viewer').contentWindow.PDFViewerApplication.pdfViewer.spreadMode"
    )
    screenshot("wrapper-controls-on-mobile")
    check(
        "Wrapper PDF.js aplica rolagem horizontal e página dupla no desktop e desliga spread no mobile por padrão",
        wrapper_on_ready
        and desktop_wrapper["scrollMode"] == 1
        and desktop_wrapper["spreadMode"] == 1
        and not desktop_wrapper["downloadHidden"]
        and not desktop_wrapper["printHidden"]
        and mobile_spread == 0,
        f"desktop={desktop_wrapper}, mobileSpread={mobile_spread}",
    )

finally:
    if bidi:
        bidi.close()
    if driver:
        driver.close()
    server.shutdown()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
raise SystemExit(0 if all(item["passed"] for item in results) else 1)
