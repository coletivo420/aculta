#!/usr/bin/env python3
"""Validate Studio form presentation against OpenCulturas 3.1.5 tokens."""

import base64
import json
import os
import sys
import time
from pathlib import Path
from urllib.parse import quote

PROJECT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(PROJECT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

if os.environ.get("DISPLAY") != ":0":
    raise SystemExit("Execute com DISPLAY=:0 e Firefox ESR visível.")

OUT_VALUE = os.environ.get("SUITE_OUT")
if not OUT_VALUE:
    raise SystemExit("Defina SUITE_OUT para guardar PNG e JSON fora do repositório.")
OUT = Path(OUT_VALUE)
if not OUT.is_absolute():
    raise SystemExit("SUITE_OUT deve ser absoluto e fora do repositório.")
OUT.mkdir(parents=True, exist_ok=True)
os.environ.setdefault("ACULTA_FF_PROFILE_ROOT", str(OUT / "profiles"))

WIDTHS = [320, 390, 768, 1440]
MODES = ("light", "dark")
results = []


def record(name, passed, detail=None):
    results.append({"name": name, "passed": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {json.dumps(detail, ensure_ascii=False)}" if detail else ""), flush=True)


css_files = [
    PROJECT / "web/themes/custom/aculta420/css/tokens.css",
    PROJECT / "web/themes/custom/aculta420/css/base.css",
    PROJECT / "web/themes/custom/aculta420/css/components/buttons.css",
    PROJECT / "web/themes/custom/aculta420/css/components/forms.css",
    PROJECT / "web/themes/custom/aculta420/css/components/studio.css",
]
css = "\n".join(path.read_text() for path in css_files)
font_dir = PROJECT / "web/themes/custom/aculta420/assets/fonts/montserrat"
for subset in ("latin", "latin-ext"):
    font_path = font_dir / f"Montserrat-{subset}.woff2"
    font_data = base64.b64encode(font_path.read_bytes()).decode("ascii")
    css = css.replace(
        f'url("../../assets/fonts/montserrat/Montserrat-{subset}.woff2")',
        f'url("data:font/woff2;base64,{font_data}")',
    )

markup = """<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>:root{--aculta-page-gutter:1rem;--bs-border-radius:0.375rem}
*{box-sizing:border-box}body{margin:0;font:16px/1.5 sans-serif}
.aculta-main{padding-block:1rem}.aculta-container{width:100%;margin-inline:auto}
.form-item{margin:0}.form-actions{display:flex;flex-wrap:wrap;gap:.5rem}
</style></head><body><main class="aculta-main"><div class="aculta-container">
<section class="studio"><div class="studio__main">
<form id="studio-form"><div class="form-item"><label for="title">Título</label>
<input id="title" class="form-control" type="text" placeholder="Digite um título">
<div class="description">Texto de orientação do campo.</div></div>
<div class="form-item"><label for="invalid">Campo inválido</label>
<input id="invalid" class="form-control error" type="email" value="inválido"></div>
<fieldset><legend>Preferências</legend><label class="option"><input type="checkbox"> Receber avisos</label>
<fieldset><legend>Opções adicionais</legend><label class="option"><input type="radio" name="extra"> Sim</label></fieldset></fieldset>
<div class="form-item"><label for="summary">Resumo</label><textarea id="summary" class="form-control" rows="3"></textarea></div>
<input id="image-action" class="form-control" type="image" alt="Ação com imagem" hidden>
<div class="form-actions"><button type="button">Ação padrão</button>
<input type="submit" class="form-submit" data-drupal-selector="edit-submit" value="Salvar">
<input type="submit" class="form-submit" data-drupal-selector="edit-reset" value="Limpar">
<button type="button" class="button--danger">Excluir</button></div></form>
</div></section>
<form id="outside-form"><input class="form-control" type="text" aria-label="Fora do Studio"></form>
</div></main>
<main class="aculta-main" id="editor-main"><h1>Editar publicação</h1>
<form class="node-revista-form" id="editor-form"><label for="editor-title">Título</label>
<input id="editor-title" class="form-control" type="text"></form></main>
<main class="aculta-main" id="profile-main"><h1>Editar biografia</h1>
<form class="studio-profile-form" id="profile-form"><label for="profile-bio">Biografia</label>
<textarea id="profile-bio" class="form-control"></textarea>
<input type="submit" class="form-submit" value="Salvar"></form></main>
<main class="aculta-main" id="account-main"><form id="account-modal-form" data-portal-ui="account-bio-dialog">
<input class="form-control" type="text" aria-label="Bio na conta"></form></main></body></html>"""

driver = Driver(bidi=True)
try:
    driver.get("data:text/html;charset=utf-8," + quote(markup))
    driver.js("const style=document.createElement('style');style.textContent=arguments[0];document.head.append(style);return true;", [css])
    time.sleep(0.25)
    measure = """return (() => {
      const form=document.querySelector('#studio-form');
      const outside=document.querySelector('#outside-form');
      const field=document.querySelector('#title');
      const invalid=document.querySelector('#invalid');
      const normal=form.querySelector('button[type=button]:not(.button--danger)');
      const primary=form.querySelector('[data-drupal-selector=edit-submit]');
      const reset=form.querySelector('[data-drupal-selector=edit-reset]');
      const danger=form.querySelector('.button--danger');
      const style=(el)=>getComputedStyle(el);
      const box=(el)=>{const s=style(el);return {padding:s.padding,background:s.backgroundColor,color:s.color,
        borderTop:s.borderTopColor,borderBottom:s.borderBottomColor,radius:s.borderRadius,
        blockSize:el.getBoundingClientRect().height,weight:s.fontWeight,
        outlineStyle:s.outlineStyle,outlineWidth:s.outlineWidth};};
      return {width:innerWidth,scrollWidth:document.documentElement.scrollWidth,
        form:{padding:style(form).padding,margin:style(form).marginBlockStart,background:style(form).backgroundColor},
        formPaddingBlock:style(form).paddingBlock,formPaddingInline:style(form).paddingInline,
        field:box(field),invalid:box(invalid),fieldset:{padding:style(form.querySelector('fieldset')).padding,
          border:style(form.querySelector('fieldset')).borderTopWidth},
        imageInputPadding:style(form.querySelector('#image-action')).padding,
        label:{display:style(form.querySelector('label[for=title]')).display,
          weight:style(form.querySelector('label[for=title]')).fontWeight},
        optionDisplay:style(form.querySelector('label.option')).display,
        normal:box(normal),primary:box(primary),reset:box(reset),danger:box(danger),
        outside:{padding:style(outside).padding,background:style(outside).backgroundColor,
          inputPadding:style(outside.querySelector('input')).padding,
          inputRadius:style(outside.querySelector('input')).borderRadius},
        mode:document.documentElement.getAttribute('data-bs-theme')};
    })();"""

    for mode in MODES:
        driver.js("document.documentElement.setAttribute('data-bs-theme', arguments[0])", [mode])
        time.sleep(0.05)
        for width in WIDTHS:
            driver.js("window.resizeTo(arguments[0], 900)", [width])
            # Firefox window management may constrain the outer window, so use BiDi CSS viewport.
            import asyncio
            import websockets

            async def set_viewport(target):
                async with websockets.connect(driver.websocket_url) as ws:
                    await ws.send(json.dumps({"id": 1, "method": "browsingContext.getTree", "params": {}}))
                    while True:
                        response = json.loads(await ws.recv())
                        if response.get("id") == 1:
                            context = response["result"]["contexts"][0]["context"]
                            break
                    await ws.send(json.dumps({"id": 2, "method": "browsingContext.setViewport",
                                             "params": {"context": context, "viewport": {"width": target, "height": 900}}}))
                    while True:
                        response = json.loads(await ws.recv())
                        if response.get("id") == 2:
                            return

            asyncio.run(set_viewport(width))
            time.sleep(0.1)
            data = driver.js(measure)
            record(f"{mode}/{width}px: viewport e sem overflow",
                   data["width"] == width and data["scrollWidth"] <= width,
                   {"viewport": data["width"], "scrollWidth": data["scrollWidth"]})
            record(f"{mode}/{width}px: formulário e campos Next",
                   data["formPaddingBlock"] == "20px"
                   and data["formPaddingInline"] == ("20px" if width < 1120 else "32px")
                   and data["form"]["margin"] == "20px"
                   and data["field"]["padding"] == "8px 16px"
                   and data["field"]["radius"] == "0px"
                   and data["field"]["background"] == ("rgb(255, 255, 255)" if mode == "light" else "rgb(22, 24, 26)")
                   and data["imageInputPadding"] != "8px 16px"
                   and data["fieldset"]["padding"] == ("20px" if width < 1120 else "20px 32px")
                   and data["fieldset"]["border"] == "2px",
                   {"form": data["form"], "field": data["field"], "fieldset": data["fieldset"], "imageInputPadding": data["imageInputPadding"]})
            record(f"{mode}/{width}px: alvos do formulário com mínimo de 44px",
                   data["field"]["blockSize"] >= 44
                   and all(data[name]["blockSize"] >= 44 for name in ("normal", "primary", "reset", "danger")),
                   {"field": data["field"]["blockSize"], "buttons": {name: data[name]["blockSize"]
                    for name in ("normal", "primary", "reset", "danger")}})
            record(f"{mode}/{width}px: label e opção",
                   data["label"] == {"display": "block", "weight": "600"}
                   and data["optionDisplay"] == "inline-block",
                   {"label": data["label"], "optionDisplay": data["optionDisplay"]})
            record(f"{mode}/{width}px: botões normal/primário/reset/destrutivo",
                   data["normal"]["padding"] == "8px 32px"
                   and data["normal"]["radius"] == "4px"
                   and data["primary"]["background"] == ("rgb(161, 22, 127)" if mode == "light" else "rgb(255, 221, 0)")
                   and data["reset"]["background"] == ("rgb(255, 255, 255)" if mode == "light" else "rgb(22, 24, 26)")
                   and data["danger"]["borderBottom"] == ("rgb(212, 19, 23)" if mode == "light" else "rgb(243, 129, 131)"),
                   {"normal": data["normal"], "primary": data["primary"], "reset": data["reset"], "danger": data["danger"]})
            record(f"{mode}/{width}px: estilos contidos no Studio",
                   data["outside"]["padding"] == "0px"
                   and data["outside"]["background"] == "rgba(0, 0, 0, 0)"
                   and data["outside"]["inputPadding"] != "8px 16px",
                   data["outside"])
            if width == 320:
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"studio-form-{mode}-320.png").write_bytes(base64.b64decode(shot))
            if width == 1440:
                shot = driver.req("GET", f"/session/{driver.session}/screenshot")
                (OUT / f"studio-form-{mode}-1440.png").write_bytes(base64.b64decode(shot))

        # The same Drupal node form class can appear outside Marley. Fidelity
        # applies only on the actual Studio route, never to Magazine/public
        # editorial pages merely because they share the Form API form ID.
        driver.js("document.body.className='path-magazine'")
        magazine_scope = driver.js("""const form=document.querySelector('#editor-form');
          const main=document.querySelector('#editor-main');
          return {formMargin:getComputedStyle(form).marginBlockStart,
            formPadding:getComputedStyle(form).paddingBlockStart,
            token:getComputedStyle(form).getPropertyValue('--aculta-studio-fidelity-text').trim(),
            mainFont:getComputedStyle(main).fontFamily};""")
        record(f"{mode}: Fidelity não vaza para Magazine",
               magazine_scope["formMargin"] == "0px"
               and magazine_scope["formPadding"] == "0px"
               and magazine_scope["token"] == "",
               magazine_scope)
        driver.js("document.body.className='path-marley'")
        marley_scope = driver.js("""const form=document.querySelector('#editor-form');
          return {formMargin:getComputedStyle(form).marginBlockStart,
            formPadding:getComputedStyle(form).paddingBlockStart,
            token:getComputedStyle(form).getPropertyValue('--aculta-studio-fidelity-text').trim(),
            font:getComputedStyle(form).fontFamily};""")
        record(f"{mode}: formulário Marley recebe Fidelity",
               marley_scope["formMargin"] == "20px"
               and marley_scope["formPadding"] == "20px"
               and marley_scope["token"] == ("#1f1f1f" if mode == "light" else "#efefef")
               and "Montserrat" in marley_scope["font"],
               marley_scope)
        driver.js("document.body.className=''")
        profile_scope = driver.js("""const form=document.querySelector('#profile-form');
          const field=form.querySelector('textarea');const submit=form.querySelector('[type=submit]');
          return {formMargin:getComputedStyle(form).marginBlockStart,
            formPadding:getComputedStyle(form).paddingBlockStart,
            token:getComputedStyle(form).getPropertyValue('--aculta-studio-fidelity-text').trim(),
            font:getComputedStyle(form).fontFamily,fieldHeight:field.getBoundingClientRect().height,
            submitHeight:submit.getBoundingClientRect().height};""")
        record(f"{mode}: bio/links do Perfil recebem Fidelity",
               profile_scope["formMargin"] == "20px"
               and profile_scope["formPadding"] == "20px"
               and profile_scope["token"] == ("#1f1f1f" if mode == "light" else "#efefef")
               and "Montserrat" in profile_scope["font"]
               and profile_scope["fieldHeight"] >= 44
               and profile_scope["submitHeight"] >= 44,
               profile_scope)
        account_modal = driver.js("""const form=document.querySelector('#account-modal-form');
          return {formMargin:getComputedStyle(form).marginBlockStart,
            formPadding:getComputedStyle(form).paddingBlockStart,
            token:getComputedStyle(form).getPropertyValue('--aculta-studio-fidelity-text').trim(),
            font:getComputedStyle(form).fontFamily};""")
        record(f"{mode}: formulário-modal da Conta mantém estilo próprio",
               account_modal["formMargin"] == "0px"
               and account_modal["formPadding"] == "0px"
               and account_modal["token"] == ""
               and "Montserrat" not in account_modal["font"],
               account_modal)
        driver.js("document.querySelector('#profile-form').scrollIntoView({block:'start'})")
        shot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"studio-profile-form-{mode}-1440.png").write_bytes(base64.b64decode(shot))
        asyncio.run(set_viewport(320))
        time.sleep(0.1)
        shot = driver.req("GET", f"/session/{driver.session}/screenshot")
        (OUT / f"studio-profile-form-{mode}-320.png").write_bytes(base64.b64decode(shot))
        asyncio.run(set_viewport(1440))
        driver.js("window.scrollTo(0,0)")

        # Keyboard tab checks that the existing global focus indicator survives the fidelity treatment.
        actions = {"actions": [{"type": "key", "id": "studio-keyboard", "actions": [
            {"type": "keyDown", "value": "\uE004"}, {"type": "keyUp", "value": "\uE004"},
        ]}]}
        driver.req("POST", f"/session/{driver.session}/actions", actions)
        focus = driver.js("const e=document.activeElement,s=getComputedStyle(e);return {id:e.id,outlineStyle:s.outlineStyle,outlineWidth:s.outlineWidth};")
        record(f"{mode}: foco de teclado preservado", focus["id"] in ("title", "invalid") and focus["outlineStyle"] == "solid" and focus["outlineWidth"] == "3px", focus)
        driver.req("DELETE", f"/session/{driver.session}/actions")
        driver.js("document.activeElement.blur()")

finally:
    driver.close()

(OUT / "summary.json").write_text(json.dumps(results, ensure_ascii=False, indent=2) + "\n")
failed = [result for result in results if not result["passed"]]
print(f"\n{len(results) - len(failed)}/{len(results)} verificações passaram. Evidências: {OUT}")
sys.exit(1 if failed else 0)
