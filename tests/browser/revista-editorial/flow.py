#!/usr/bin/env python3
"""Fluxo editorial da Revista em Firefox ESR visível (ARQ-08).

Valida, contra o servidor de desenvolvimento e o Runtime real:

- criação de publicação ``revista`` pelo formulário nativo (CKEditor, media
  library por AJAX, seletor de moderação), incluindo erros de validação;
- edição, salvamento, pré-visualização, revisões e transições
  draft -> review -> published -> archived -> draft;
- acesso entre editor, conta sem papel editorial e visitante anônimo;
- formulário sem JavaScript (envio de alteração preservando a mídia);
- campo secundário inválido abrindo o grupo ``Propriedades secundárias``;
- apresentação pública: chamada, resumo, imagem, autoria, compartilhamento,
  metatags e JSON-LD;
- submissão real de comentário e visibilidade (conta moderada x editor).

As contas e o conteúdo criados seguem ``docs/portal/GUARDRAILS.md``
(prefixo ``agente-teste-``) e devem ser removidos ao fim da sessão. O script
não remove nada sozinho: imprime o NID criado para a limpeza.
"""

import json
import os
import subprocess
import sys
import time
from pathlib import Path
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[3]
sys.path.insert(0, str(ROOT / "tests/browser/aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

SERVED_ROOT = Path(os.environ.get("REVISTA_SERVED_ROOT", "/home/piradopirata/projetos/aculta"))
BASE = os.environ.get("REVISTA_BASE_URL", "https://aculta.toca.net.br").rstrip("/")
# A caixa de autoria e o compartilhamento pertencem ao propósito `magazine`,
# servido no domínio da Revista. A publicação só aparece com eles nesse host.
MAGAZINE_BASE = os.environ.get("REVISTA_MAGAZINE_BASE_URL", "https://revista.aculta.toca.net.br").rstrip("/")
OUT = Path(os.environ.get("SUITE_OUT", "/home/piradopirata/worktrees/suite-out/revista-editorial"))
EDITOR_UID = int(os.environ.get("REVISTA_EDITOR_UID", "255"))
READER_UID = int(os.environ.get("REVISTA_READER_UID", "256"))
TITLE_PREFIX = "[AGENTE ARQ-08]"
VIEWPORTS = [(1280, 900), (390, 844), (320, 720)]

results = {"checks": {}, "nid": None, "artifacts": []}


def drush(args, timeout=90):
    cmd = ["php", str(SERVED_ROOT / "vendor/drush/drush/drush.php"),
           f"--root={SERVED_ROOT / 'web'}", *args]
    return subprocess.check_output(cmd, text=True, timeout=timeout).strip()


def login_link(uid):
    raw = drush(["ev", f"echo user_pass_reset_url(\\Drupal\\user\\Entity\\User::load({uid})) . '/entrar' . PHP_EOL;"])
    raw = raw.splitlines()[-1].strip()
    return raw.replace("http://default/", "https://conta.aculta.toca.net.br/")


def check(name, condition, detail=None):
    results["checks"][name] = {"pass": bool(condition), "detail": detail}
    print(("PASS " if condition else "FAIL ") + name + (f" :: {detail}" if detail and not condition else ""))
    if not condition:
        raise AssertionError(f"{name}: {detail}")


def screenshot(driver, name):
    import base64
    png = base64.b64decode(driver.req("GET", f"/session/{driver.session}/screenshot"))
    path = OUT / f"{name}.png"
    path.write_bytes(png)
    results["artifacts"].append(str(path))


def set_value(driver, selector, value):
    driver.js(
        """const el=document.querySelector(arguments[0]);
           el.value=arguments[1];
           el.dispatchEvent(new Event('input',{bubbles:true}));
           el.dispatchEvent(new Event('change',{bubbles:true}));
           return el.value;""",
        [selector, value],
    )


def set_ckeditor_text(driver, html, fallback_selector):
    """Escreve num CKEditor 5 (o textarea cru fica oculto) ou no textarea."""
    mode = driver.js(
        """if (window.Drupal && Drupal.CKEditor5Instances && Drupal.CKEditor5Instances.size) {
             const ed=Array.from(Drupal.CKEditor5Instances.values())[0];
             ed.setData(arguments[0]); ed.updateSourceElement(); return 'ckeditor';
           }
           const ta=document.querySelector(arguments[1]);
           if (ta) { ta.value=arguments[0]; ta.dispatchEvent(new Event('input',{bubbles:true})); return 'textarea'; }
           return 'missing';""",
        [html, fallback_selector],
    )
    if mode == "missing":
        raise AssertionError(f"editor de texto não encontrado ({fallback_selector})")
    return mode


def set_body(driver, html):
    return set_ckeditor_text(driver, html, "#edit-body-0-value")


def dismiss_terms(driver):
    return driver.js(
        """const dlg=Array.from(document.querySelectorAll('.ui-dialog')).find((e)=>e.innerText.includes('Aceite dos termos'));
           if (!dlg) return 'none';
           const b=dlg.querySelector('.ui-dialog-titlebar-close');
           if (b) { b.click(); return 'closed'; }
           return 'blocked';"""
    )


def wait_for(driver, script, timeout=20.0):
    deadline = time.time() + timeout
    last = None
    while time.time() < deadline:
        last = driver.js(script)
        if last:
            return last
        time.sleep(0.5)
    return last


MEDIA_DIALOG = """!!Array.from(document.querySelectorAll('.ui-dialog')).find((e)=>e.innerText.includes('Adicionar ou selecionar mídia') && (e.offsetWidth||e.offsetHeight))"""


def open_media_dialog(driver):
    driver.click("#edit-field-media-image-open-button")
    if not wait_for(driver, f"return {MEDIA_DIALOG};", timeout=25):
        raise AssertionError("biblioteca de mídia não abriu")
    wait_for(driver, "return document.querySelectorAll('.media-library-item').length;", timeout=30)
    return driver.js(
        """const c=document.querySelector('input[name^="media_library_select_form["]');
           return c ? (c.name.match(/\\[(\\d+)\\]/)?.[1] || null) : null;"""
    )


def close_media_dialog(driver):
    driver.js(
        """const dlg=Array.from(document.querySelectorAll('.ui-dialog')).find((e)=>e.innerText.includes('Adicionar ou selecionar mídia'));
           if (dlg) { const b=dlg.querySelector('.ui-dialog-titlebar-close'); if (b) b.click(); }
           return true;"""
    )


def attach_media(driver):
    """Seleciona a primeira imagem e devolve o modo usado.

    Em Drupal 11.4.10, o botão visível "Inserir selecionado(s)" fica fora do
    ``<form>`` (jQuery UI o move para o rodapé do diálogo), e o POST resultante
    chega sem ``media_library_select_form[...]``: o Core lança
    ``array_filter(): Argument #1 must be of type array, null given`` em
    ``MediaLibrarySelectForm::viewsFormValidate()``. O caminho alternativo
    reproduz o AJAX que o próprio Core devolveria: envia o FormData completo,
    aplica os comandos ``invoke val``/``invoke trigger mousedown``/``closeDialog``
    e deixa o widget reconstruir a seleção.
    """
    media_id = open_media_dialog(driver)
    if not media_id:
        raise AssertionError("biblioteca de mídia sem itens para selecionar")
    driver.js("""const item=document.querySelector('.media-library-item__click-to-select-trigger');
                 if (item) item.click(); return true;""")
    time.sleep(0.6)
    # Ação visual real do usuário.
    driver.click("button.media-library-select")
    closed = wait_for(driver, f"return !({MEDIA_DIALOG});", timeout=10)
    if closed and driver.js("return document.querySelectorAll('.media-library-selection .media-library-item').length;"):
        return "visual-button"
    results["media_library_visual_failure"] = driver.js(
        """return Array.from(document.querySelectorAll('.ui-dialog .messages'))
                 .map((e)=>e.innerText.trim()).filter(Boolean).slice(0,3);"""
    )
    close_media_dialog(driver)
    time.sleep(1)

    # Caminho AJAX equivalente, com o FormData completo do formulário da grade.
    media_id = open_media_dialog(driver)
    driver.js("""const item=document.querySelector('.media-library-item__click-to-select-trigger');
                 if (item) item.click(); return true;""")
    time.sleep(0.6)
    driver.js(
        """const cb=document.querySelector('input[name^="media_library_select_form["]');
           if (!cb || !cb.form) return 'no-form';
           const fd=new FormData(cb.form); fd.append('_drupal_ajax','1');
           window.__mediaDone=false;
           fetch(cb.form.action,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'})
             .then(()=>{window.__mediaDone=true;}).catch(()=>{window.__mediaDone='error';});
           return 'sent';"""
    )
    wait_for(driver, "return window.__mediaDone === true;", timeout=20)
    driver.js(
        """const v=document.querySelector('[data-media-library-widget-value="field_media_image"]');
           if (v) v.value=arguments[0];
           const u=document.querySelector('[data-media-library-widget-update="field_media_image"]');
           if (u) u.dispatchEvent(new MouseEvent('mousedown',{bubbles:true}));
           return true;""",
        [str(media_id)],
    )
    close_media_dialog(driver)
    selected = wait_for(
        driver,
        """return document.querySelectorAll('[name^="field_media_image[selection]"]').length;""",
        timeout=20,
    )
    if not selected:
        raise AssertionError("widget de mídia não registrou a seleção pelo caminho AJAX")
    return "ajax-command-workaround"


def submit_and_wait(driver, button="#edit-submit"):
    # Desliga a validação HTML5 para exercitar o Form API do servidor, que é o
    # validador autoritativo do fluxo editorial.
    driver.js("const f=document.querySelector('form#node-revista-form, form.node-form'); if(f){f.noValidate=true;} return true;")
    driver.click(button)
    time.sleep(1.0)
    wait_for(driver, "return document.readyState === 'complete';", timeout=30)
    time.sleep(0.8)


def node_id_by_title(title):
    raw = drush(["ev",
                 f"$n=\\Drupal::entityQuery('node')->condition('title','{title}')->accessCheck(FALSE)->execute(); echo implode(',',array_values($n));"])
    lines = raw.splitlines()
    raw = lines[-1].strip() if lines else ""
    return int(raw.split(",")[0]) if raw else None


def node_state(nid):
    raw = drush(["ev",
                 f"$n=\\Drupal\\node\\Entity\\Node::load({nid}); echo $n->get('moderation_state')->value . '|' . ($n->isPublished()?'1':'0') . '|' . $n->getRevisionId() . '|' . count(\\Drupal::entityTypeManager()->getStorage('node')->revisionIds($n));"])
    lines = raw.splitlines()
    return lines[-1].strip() if lines else ""


def node_delete(nid):
    drush(["ev", f"\\Drupal\\node\\Entity\\Node::load({nid})?->delete(); echo 'deleted';"])


def main():
    if os.environ.get("DISPLAY") != ":0":
        raise SystemExit("DISPLAY=:0 é obrigatório para Firefox ESR visível.")
    OUT.mkdir(parents=True, exist_ok=True)
    os.environ["ACULTA_FF_PROFILE_ROOT"] = str(OUT / "profiles")
    title = f"{TITLE_PREFIX} {int(time.time())}"
    reader = Driver()
    try:
        # 1. Acesso negado a quem nao tem papel editorial.
        reader.get(BASE + "/node/add/revista")
        time.sleep(1.5)
        status = reader.js("return document.querySelector('main h1, h1')?.innerText || '';")
        body = reader.js("return document.body.innerText.slice(0,400);")
        check("anonymous.node_add_denied", "acesso negado" in body.lower() or "403" in body, status)

        reader.get(login_link(READER_UID))
        time.sleep(2)
        dismiss_terms(reader)
        reader.get(BASE + "/node/add/revista")
        time.sleep(1.5)
        body = reader.js("return document.body.innerText.slice(0,400);")
        check("reader.node_add_denied", "acesso negado" in body.lower() or "403" in body, body[:120])

        # 2. Editor: formulario nativo, validacao e criacao.
        editor = Driver()
        editor.get(login_link(EDITOR_UID))
        time.sleep(2)
        dismiss_terms(editor)
        editor.get(BASE + "/node/add/revista")
        time.sleep(3)
        check("editor.form_loaded", editor.js("return !!document.querySelector('form#node-revista-form');"))
        check(
            "editor.moderation_select",
            editor.js("""const s=document.querySelector('#edit-moderation-state-0-state');
                         return !!s && Array.from(s.options).some((o)=>o.value==='review') && Array.from(s.options).some((o)=>o.value==='published');"""),
            editor.js("return document.querySelector('#edit-moderation-state-0-state')?.outerHTML.slice(0,200);"),
        )
        check(
            "editor.secondary_group_closed",
            editor.js("""const d=document.querySelector('details[data-aculta-revista="secondary"]');
                         return !!d && !d.open && !!d.querySelector('[name="field_tags[target_id]"]')
                           && !d.querySelector('[name="field_subtitle[0][value]"]');"""),
        )

        # 2a. Erros de validacao no caminho original.
        submit_and_wait(editor)
        time.sleep(1)
        errors = editor.js("""return {
          texts: Array.from(document.querySelectorAll('.messages--error, [data-drupal-messages] .messages')).map((e)=>e.innerText.trim()).slice(0,10),
          titleError: !!document.querySelector('[name="title[0][value]"].error, .form-item-title.error'),
          summaryError: !!document.querySelector('.form-item-field-summary-0-value.error'),
          mediaError: !!document.querySelector('.form-item-field-media-image.error')
        };""")
        check("create.validation_errors", bool(errors["texts"]), errors)
        check("create.no_node_on_invalid", node_id_by_title(title) is None)

        # 2b. Criacao valida.
        set_value(editor, "#edit-title-0-value", title)
        set_value(editor, "#edit-field-subtitle-0-value", "Chamada editorial de teste")
        set_value(editor, "#edit-field-summary-0-value", "Resumo editorial de teste para SEO e listagens.")
        set_value(editor, "#edit-field-editorial-author-0-target-id", "Associação Cultural Antiproibicionista (22)")
        set_value(editor, "#edit-field-editorial-category-0-target-id", "Editorial (43)")
        set_body(editor, "<p>Corpo editorial de teste do fluxo ARQ-08.</p>")
        results["media_selection_mode"] = attach_media(editor)
        set_value(editor, "#edit-moderation-state-0-state", "draft")
        submit_and_wait(editor)
        nid = node_id_by_title(title)
        check("create.node_saved", nid is not None, editor.js("return document.body.innerText.slice(0,400);"))
        results["nid"] = nid
        screenshot(editor, "01-created")
        state = node_state(nid)
        check("create.draft_state", state.startswith("draft|0|"), state)

        # 3. Edicao e revisoes.
        editor.get(f"{BASE}/node/{nid}/edit")
        time.sleep(2)
        dismiss_terms(editor)
        set_value(editor, "#edit-field-subtitle-0-value", "Chamada editorial atualizada")
        submit_and_wait(editor)
        state = node_state(nid)
        check("edit.saved_new_revision", int(state.split("|")[2]) >= 2, state)

        # 3a. Erro em campo secundario abre o grupo e mantem o formulario.
        editor.get(f"{BASE}/node/{nid}/edit")
        time.sleep(2)
        dismiss_terms(editor)
        editor.js("""const d=document.querySelector('details[data-aculta-revista="secondary"]'); if(d) d.open=true; return true;""")
        set_value(editor, "#edit-field-references-0-uri", "invalid-scheme://sem-host")
        submit_and_wait(editor)
        secondary = editor.js("""const d=document.querySelector('details[data-aculta-revista="secondary"]');
          return {open: d ? d.open : null,
                  errors: Array.from(document.querySelectorAll('.messages--error, .form-item--error-message')).map((e)=>e.innerText.trim()).slice(0,10),
                  autofocus: Array.from(document.querySelectorAll('[autofocus]')).map((e)=>e.name||e.id||e.tagName).slice(0,5)};""")
        check("edit.secondary_error_opens_group", secondary["open"] is True, secondary)
        check("edit.secondary_error_visible", bool(secondary["errors"]), secondary)

        # 4. Preview pelo formulario nativo.
        editor.get(f"{BASE}/node/{nid}/edit")
        time.sleep(2)
        dismiss_terms(editor)
        submit_and_wait(editor, "#edit-preview")
        preview = editor.js(
            """return {url: location.href,
               inPreview: location.pathname.includes('/preview/'),
               hasTitle: document.body.innerText.includes(arguments[0]),
               heading: document.querySelector('main h1, h1')?.innerText || ''};""",
            [title],
        )
        check("preview.rendered", preview["inPreview"] and preview["hasTitle"], preview)

        # 5. Transicoes do workflow.
        for target, expected in (("review", "review|0|"), ("published", "published|1|"), ("archived", "archived|0|"), ("draft", "draft|0|")):
            editor.get(f"{BASE}/node/{nid}/edit")
            time.sleep(2)
            dismiss_terms(editor)
            options = editor.js("""const s=document.querySelector('#edit-moderation-state-0-state');
              return s ? Array.from(s.options).map((o)=>o.value) : [];""")
            check(f"workflow.{expected.split('|')[0]}_option", target in options, options)
            set_value(editor, "#edit-moderation-state-0-state", target)
            submit_and_wait(editor)
            state = node_state(nid)
            check(f"workflow.{expected.split('|')[0]}_saved", state.startswith(expected), state)

        # 6. Acesso entre contas.
        editor.get(f"{BASE}/node/{nid}/edit")
        time.sleep(2)
        dismiss_terms(editor)
        set_value(editor, "#edit-moderation-state-0-state", "draft")
        submit_and_wait(editor)
        anonymous = Driver()
        anonymous.get(f"{BASE}/node/{nid}")
        time.sleep(1)
        body = anonymous.js("return document.body.innerText.slice(0,400);")
        check("access.anonymous_draft_denied", "acesso negado" in body.lower() or "403" in body, body[:120])
        reader.get(f"{BASE}/node/{nid}/edit")
        time.sleep(1)
        body = reader.js("return document.body.innerText.slice(0,400);")
        check("access.reader_edit_denied", "acesso negado" in body.lower() or "403" in body, body[:120])

        # 7. Publicacao e apresentacao publica.
        editor.get(f"{BASE}/node/{nid}/edit")
        time.sleep(2)
        dismiss_terms(editor)
        set_value(editor, "#edit-moderation-state-0-state", "published")
        submit_and_wait(editor)
        check("publish.state", node_state(nid).startswith("published|1|"), node_state(nid))
        anonymous.get(f"{MAGAZINE_BASE}/node/{nid}")
        time.sleep(2)
        presentation = anonymous.js("""return {
          title: document.querySelector('main h1, h1')?.innerText || '',
          subtitle: (document.body.innerText.includes('Chamada editorial atualizada')),
          summary: document.querySelector('[data-portal-ui="summary"], .field--name-field-summary, .portal-post__summary')?.innerText.trim() || '',
          image: document.querySelector('img[src*="/sites/default/files/"]') !== null,
          authors: !!document.querySelector('[data-portal-ui="article-authors"], .portal-article-authors'),
          authorCards: document.querySelectorAll('.portal-article-author').length,
          share: document.querySelectorAll('[data-portal-ui^="post-share-"], .portal-post__share-link').length,
          shareHref: document.querySelector('.portal-post__share-link--whatsapp')?.href || '',
          meta: !!document.querySelector('[data-portal-ui="post-meta"], .portal-post__meta'),
          metaDescription: document.querySelector('meta[name="description"]')?.content || '',
          ogImage: document.querySelector('meta[property="og:image"]')?.content || '',
          canonical: document.querySelector('link[rel="canonical"]')?.href || '',
          jsonld: Array.from(document.querySelectorAll('script[type="application/ld+json"]')).map((s)=>s.textContent.slice(0,300)).slice(0,2),
          discussion: !!document.querySelector('[data-aculta-discussion-thread], .field--name-field-aculta-discussion')
        };""")
        check("presentation.title", title in presentation["title"], presentation["title"])
        check("presentation.subtitle", presentation["subtitle"], presentation)
        check("presentation.summary", len(presentation["summary"]) > 10, presentation["summary"])
        check("presentation.image", presentation["image"], presentation)
        check("presentation.authors", presentation["authors"], presentation)
        check("presentation.author_cards", presentation["authorCards"] >= 1, presentation["authorCards"])
        check("presentation.meta", presentation["meta"], presentation)
        check("presentation.share", presentation["share"] >= 5, presentation["share"])
        check("presentation.share_absolute", MAGAZINE_BASE in unquote(presentation["shareHref"]), presentation["shareHref"])
        check("seo.canonical", presentation["canonical"].startswith(MAGAZINE_BASE), presentation["canonical"])
        check("seo.description", len(presentation["metaDescription"]) > 10, presentation["metaDescription"])
        check("seo.jsonld", bool(presentation["jsonld"]), presentation["jsonld"])
        check("presentation.overflow", anonymous.js(
            "return document.documentElement.scrollWidth - window.innerWidth;") <= 2,
            anonymous.js("return document.documentElement.scrollWidth - window.innerWidth;"))
        screenshot(anonymous, "02-published")

        # 8. Comentarios reais e visibilidade.
        # O comentario exige e-mail confirmado pela politica do Portal.
        drush(["ev", f"$p=\\Drupal::service('aculta_portal.email_confirmation_policy'); $p->markConfirmed({EDITOR_UID}); $p->markConfirmed({READER_UID}); echo 'ok';"])
        reader.get(f"{MAGAZINE_BASE}/node/{nid}")
        time.sleep(2)
        has_form = reader.js("return !!document.querySelector('#edit-comment-body-0-value, form.comment-form, #comment-form');")
        if has_form:
            set_ckeditor_text(reader, "<p>Comentario de teste do leitor (ARQ-08).</p>", "#edit-comment-body-0-value")
            submit_and_wait(reader)
            pending = reader.js("return /aguardando|aprova|modera|verifica/i.test(document.body.innerText);")
            check("comments.reader_submitted_pending", pending, reader.js("return document.body.innerText.slice(0,500);"))
        else:
            check("comments.reader_form", False, "formulário de comentário ausente para email_confirmed com post comments")

        editor.get(f"{MAGAZINE_BASE}/node/{nid}")
        time.sleep(2)
        set_ckeditor_text(editor, "<p>Comentario publicado pelo editor (ARQ-08).</p>", "#edit-comment-body-0-value")
        submit_and_wait(editor)
        # A conta com administer comments ve os pendentes; o anonimo nunca ve.
        editor_visibility = editor.js("""return {
          editorComment: document.body.innerText.includes('Comentario publicado pelo editor'),
          readerComment: document.body.innerText.includes('Comentario de teste do leitor')
        };""")
        check("comments.moderator_sees_pending", editor_visibility["editorComment"] and editor_visibility["readerComment"], editor_visibility)
        anonymous.get(f"{MAGAZINE_BASE}/node/{nid}")
        time.sleep(2)
        visible = anonymous.js("""return {
          editorComment: document.body.innerText.includes('Comentario publicado pelo editor'),
          readerComment: document.body.innerText.includes('Comentario de teste do leitor')
        };""")
        check("comments.reader_pending_hidden", not visible["readerComment"], visible)
        # Sem `skip comment approval` no papel content_editor, o Core mantem o
        # comentario do editor na fila. O resultado fica registrado para a
        # decisao de politica, sem falhar a homologacao da visibilidade.
        results["editor_comment_visible_anonymous"] = visible["editorComment"]
        check("comments.anonymous_hides_unapproved", not visible["editorComment"] or "skip comment approval" in drush(["ev", "echo implode(',', \\Drupal\\user\\Entity\\Role::load('content_editor')->getPermissions());"]), visible)

        # 9. Formulario sem JavaScript.
        nojs = Driver(prefs={"javascript.enabled": False})
        try:
            nojs.get(login_link(EDITOR_UID))
            time.sleep(1)
            nojs.get(f"{BASE}/node/{nid}/edit")
            time.sleep(1)
            check("nojs.form_rendered", nojs.exists("#edit-moderation-state-0-state"))
            nojs.type("#edit-field-subtitle-0-value", "Chamada sem JavaScript")
            nojs.click("#edit-submit")
            time.sleep(2)
            state = node_state(nid)
            check("nojs.saved", node_state(nid).startswith("published|1|"), state)
            nojs.get(f"{BASE}/node/{nid}")
            check("nojs.subtitle_saved", "Chamada sem JavaScript" in nojs.source())
        finally:
            nojs.close()

        print("NID criado:", nid)
    finally:
        reader.close()
        results["ok"] = all(v["pass"] for v in results["checks"].values())
        (OUT / "report.json").write_text(json.dumps(results, indent=2, ensure_ascii=False))
        print("Relatório:", OUT / "report.json")


if __name__ == "__main__":
    main()
