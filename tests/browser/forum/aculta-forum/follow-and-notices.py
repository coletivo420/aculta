"""Seguir, avisos e leitura de tópico no Firefox ESR/X11 (ambiente local).

Cria um tópico publicado sintético e uma conta sintética, segue o tópico pelo
link da Flag como uid 1, gera uma resposta publicada por outra conta e verifica
a central de avisos, o contador, a leitura pela rota canônica e o abandono do
acompanhamento. Mede teclado e largura real da janela. Apaga tudo no final.

Nenhum dado sai do ambiente local; o link único de login e os identificadores
sintéticos não são impressos; artefatos ficam em SUITE_OUT com modo 0600.
"""

import base64
import json
import os
import subprocess
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, "..", "..", ".."))
sys.path.insert(0, os.path.join(os.path.dirname(HERE), "aculta-cpf-checkout"))
from wdlib import Driver  # noqa: E402

OUT = os.environ.get("SUITE_OUT")
DISPLAY = os.environ.get("DISPLAY")
if not OUT or not os.path.isabs(OUT) or not DISPLAY:
    raise SystemExit("Defina DISPLAY da sessão X11 e SUITE_OUT absoluto fora do checkout.")
os.umask(0o077)
os.makedirs(OUT, exist_ok=True)

PREFS = {"network.dns.localDomains": ",".join([
    "aculta.toca.net.br",
    "conta.aculta.toca.net.br",
    "growroom420.aculta.toca.net.br",
])}
ACCOUNT = "https://conta.aculta.toca.net.br"
FORUM = "https://growroom420.aculta.toca.net.br"
checks = []


def check(name, passed, detail=""):
    checks.append({"name": name, "pass": bool(passed), "detail": detail})
    print(f"{'PASS' if passed else 'FAIL'} {name}" + (f" — {detail}" if detail else ""))


def drush(*args):
    result = subprocess.run(
        [os.path.join(ROOT, "vendor/bin/drush"), *args],
        cwd=ROOT, capture_output=True, text=True, timeout=180,
    )
    if result.returncode != 0:
        raise RuntimeError("drush falhou: " + (result.stderr or result.stdout).strip()[:300])
    return result.stdout.strip()


def php(code):
    return drush("php:eval", code)


def screenshot(driver, name):
    png = driver.req("GET", f"/session/{driver.session}/screenshot")
    path = os.path.join(OUT, name)
    with open(path, "wb") as handle:
        handle.write(base64.b64decode(png))
    os.chmod(path, 0o600)


create_php = r'''
$fs = \Drupal::service("file_system");
$topic = \Drupal\node\Entity\Node::create([
  "type" => "aculta_forum_topic",
  "title" => "[SYNTH] seguir e avisos " . time(),
  "status" => 1,
  "uid" => 1,
  "field_domain_source" => ["target_id" => "forum_aculta_org"],
  "body" => ["value" => "Tópico sintético para homologar seguir e avisos.", "format" => "forum"],
]);
$topic->save();
$user = \Drupal\user\Entity\User::create([
  "name" => "synth_reply_" . time(),
  "mail" => "synth_reply_" . time() . "@example.invalid",
  "status" => 1,
  "pass" => \Drupal::service("password_generator")->generate(24),
]);
$user->save();
echo json_encode(["nid" => (int) $topic->id(), "uid" => (int) $user->id(), "title" => $topic->getTitle()]);
'''

reply_php = r'''
$topic = \Drupal\node\Entity\Node::load((int) %NID%);
$comment = \Drupal\comment\Entity\Comment::create([
  "comment_type" => "aculta_discussion_post",
  "entity_type" => "node",
  "entity_id" => (int) $topic->id(),
  "field_name" => "field_aculta_discussion",
  "uid" => (int) %UID%,
  "subject" => "Resposta sintética",
  "comment_body" => ["value" => "Resposta sintética para o aviso." . time(), "format" => "forum"],
  "status" => 0,
]);
$purpose = \Drupal::service("aculta_portal.domain_purpose");
$purpose->runInPurpose("forum", function () use ($comment) {
  $comment->save();
});
$purpose->runInPurpose("forum", function () use ($comment) {
  $comment->setPublished();
  $comment->save();
});
echo (int) $comment->id();
'''

cleanup_php = r'''
$result = [];
$nid = (int) %NID%;
$storage = \Drupal::entityTypeManager()->getStorage("aculta_aviso");
$ids = $storage->getQuery()->accessCheck(FALSE)->condition("assunto_nid", $nid)->execute();
if ($ids !== []) {
  foreach ($storage->loadMultiple($ids) as $aviso) { $aviso->delete(); }
  $result[] = "avisos:" . count($ids);
}
\Drupal::database()->delete("aculta_messenger_forum_notice_event")->condition("nid", $nid)->execute();
$queue = \Drupal::database()->select("queue", "q")->fields("q", ["item_id", "data"])
  ->condition("name", "aculta_messenger_forum_direct_email")->execute()->fetchAll();
foreach ($queue as $row) {
  if (str_contains((string) $row->data, "i:" . $nid . ";") || str_contains((string) $row->data, "i:" . %CID% . ";")) {
    \Drupal::database()->delete("queue")->condition("item_id", $row->item_id)->execute();
    $result[] = "queue";
  }
}
$flag_ids = \Drupal::entityTypeManager()->getStorage("flagging")->getQuery()->accessCheck(FALSE)
  ->condition("flag_id", "seguir_topico")->condition("entity_type", "node")->condition("entity_id", $nid)->execute();
if ($flag_ids !== []) {
  \Drupal::entityTypeManager()->getStorage("flagging")->delete(\Drupal::entityTypeManager()->getStorage("flagging")->loadMultiple($flag_ids));
  $result[] = "flaggings:" . count($flag_ids);
}
foreach ([%CID%, %CID2%] as $cid) {
  if ($cid > 0 && ($c = \Drupal\comment\Entity\Comment::load($cid))) { $c->delete(); $result[] = "comment:$cid"; }
}
if ($n = \Drupal\node\Entity\Node::load($nid)) { $n->delete(); $result[] = "node"; }
if ($u = \Drupal\user\Entity\User::load((int) %UID%)) { $u->delete(); $result[] = "user"; }
echo implode(",", $result);
'''


def query(code):
    return json.loads(php(code))


def flag_link(driver):
    return driver.js("""const link = [...document.querySelectorAll('a[href*="/flag/"]')]
      .find(a => a.href.includes('seguir_topico'));
      if (!link) { return null; }
      return {text: link.innerText.trim(), href: link.getAttribute('href'),
              hrefAbs: link.href, tabIndex: link.tabIndex,
              focusable: link.tabIndex >= 0 && !link.hasAttribute('disabled')};""")


def tab_to(driver, selector_fragment, limit=80):
    driver.js("document.body.setAttribute('tabindex','-1'); document.body.focus();")
    for _ in range(limit):
        driver.req("POST", f"/session/{driver.session}/actions", {"actions": [{
            "type": "key", "id": "keyboard", "actions": [
                {"type": "keyDown", "value": ""},
                {"type": "keyUp", "value": ""},
            ],
        }]})
        hit = driver.js("""const el = document.activeElement;
          return el ? {tag: el.tagName, href: el.getAttribute && el.getAttribute('href'),
                       text: (el.innerText || '').trim()} : null;""")
        if hit and hit.get("href") and selector_fragment in hit["href"]:
            return hit
    return None


created = None
comment1 = 0
comment2 = 0
driver = None
try:
    created = query(create_php)
    baseline = query(r'''$uid = 1; $storage = \Drupal::entityTypeManager()->getStorage("aculta_aviso");
      $threads = (int) \Drupal::entityTypeManager()->getStorage("private_message_thread")
        ->getQuery()->accessCheck(FALSE)->count()->execute();
      echo json_encode(["avisos" => (int) $storage->getQuery()->accessCheck(FALSE)->condition("uid", $uid)->count()->execute(), "threads" => $threads]);''')

    uli = drush("uli", "--uid=1", "--no-browser", f"--uri={ACCOUNT}")
    driver = Driver(prefs=PREFS)
    driver.get(uli)
    for _ in range(15):
        if driver.js("return !!document.querySelector('a[href*=\"/user/logout\"], a[href*=\"/sair\"]')"):
            break
        time.sleep(1)

    driver.get(f"{FORUM}/node/{created['nid']}")
    link = flag_link(driver)
    check("Seguir: link da Flag presente com nome acessível",
          bool(link) and "Seguir" in link["text"] and link["focusable"],
          json.dumps(link, ensure_ascii=False))
    focused = tab_to(driver, "/flag/")
    check("Seguir: link alcançável por Tab", bool(focused), json.dumps(focused, ensure_ascii=False))

    if link:
        driver.get(f"{FORUM}{link['href']}")
    after = flag_link(driver)
    flags = query(r'''$nid = %NID%; echo json_encode(["flags" => (int) \Drupal::entityTypeManager()
      ->getStorage("flagging")->getQuery()->accessCheck(FALSE)->condition("flag_id", "seguir_topico")
      ->condition("entity_type", "node")->condition("entity_id", $nid)->count()->execute()]);'''.replace("%NID%", str(created["nid"])))
    check("Seguir: acompanhamento criado e rótulo muda",
          bool(after) and "Seguindo" in after["text"] and flags["flags"] == 1,
          json.dumps({"label": after and after["text"], "flags": flags["flags"]}, ensure_ascii=False))
    screenshot(driver, "forum-follow-followed.png")

    driver.get(f"{ACCOUNT}/avisos-mensagens/avisos")
    empty = driver.js("return document.querySelector('main') ? document.querySelector('main').innerText : ''")
    check("Central de avisos abre na conta",
          "Avisos" in empty and "Respostas e atualizações" in empty,
          json.dumps({"len": len(empty)}, ensure_ascii=False))
    if baseline["avisos"] == 0:
        driver.get(f"{ACCOUNT}/avisos-mensagens/avisos?origem=forum&leitura=unread")
        filtered = driver.js("return document.querySelector('main') ? document.querySelector('main').innerText : ''")
        for _ in range(10):
            if "Nenhum aviso corresponde" in filtered:
                break
            time.sleep(0.5)
            filtered = driver.js("return document.querySelector('main') ? document.querySelector('main').innerText : ''")
        check("Filtro Fórum/não lidos vazio antes da novidade",
              "Nenhum aviso corresponde" in filtered,
              json.dumps({"text": filtered[:200]}, ensure_ascii=False))

    comment1 = int(php(reply_php.replace("%NID%", str(created["nid"])).replace("%UID%", str(created["uid"]))))
    aviso = query(r'''$nid = %NID%; $storage = \Drupal::entityTypeManager()->getStorage("aculta_aviso");
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition("uid", 1)->condition("assunto_nid", $nid)->execute();
      $out = [];
      foreach ($storage->loadMultiple($ids) as $a) {
        $out[] = ["tipo" => $a->get("tipo")->value, "site" => (int) $a->get("contador_site")->value,
                  "email" => (int) $a->get("contador_email")->value,
                  "ultimo_comentario" => (int) $a->get("ultimo_comentario")->value];
      }
      echo json_encode(["avisos" => $out]);'''.replace("%NID%", str(created["nid"])))
    check("Aviso: uma linha por destinatário com contador de site",
          len(aviso["avisos"]) == 1 and aviso["avisos"][0]["site"] == 1
          and aviso["avisos"][0]["ultimo_comentario"] == comment1,
          json.dumps(aviso["avisos"], ensure_ascii=False))

    driver.get(f"{ACCOUNT}/avisos-mensagens/avisos")
    listed = driver.js("return document.querySelector('main').innerText")
    for _ in range(10):
        if created["title"] in listed:
            break
        time.sleep(0.5)
        listed = driver.js("return document.querySelector('main').innerText")
    check("Central de avisos lista o tópico seguido",
          created["title"] in listed and "(1)" in listed,
          json.dumps({"title": created["title"], "len": len(listed)}, ensure_ascii=False))
    screenshot(driver, "forum-follow-notice.png")

    driver.get(f"{FORUM}/node/{created['nid']}")
    read = query(r'''$nid = %NID%; $storage = \Drupal::entityTypeManager()->getStorage("aculta_aviso");
      $ids = $storage->getQuery()->accessCheck(FALSE)->condition("uid", 1)->condition("assunto_nid", $nid)->execute();
      $a = $storage->load(reset($ids));
      echo json_encode(["site" => $a ? (int) $a->get("contador_site")->value : -1]);'''.replace("%NID%", str(created["nid"])))
    check("Leitura pela rota canônica zera o contador do site", read["site"] == 0,
          json.dumps(read, ensure_ascii=False))

    driver.get(f"{ACCOUNT}/avisos-mensagens/avisos?origem=forum&leitura=unread")
    unread = driver.js("return document.querySelector('main').innerText")
    for _ in range(10):
        if created["title"] not in unread:
            break
        time.sleep(0.5)
        unread = driver.js("return document.querySelector('main').innerText")
    check("Filtro de não lidos deixa de listar o tópico lido",
          created["title"] not in unread, json.dumps({"len": len(unread)}, ensure_ascii=False))

    driver.get(f"{FORUM}/node/{created['nid']}")
    link = flag_link(driver)
    if link and "/flag/unflag/" in link["href"]:
        driver.get(f"{FORUM}{link['href']}")
    flags = query(r'''$nid = %NID%; echo json_encode(["flags" => (int) \Drupal::entityTypeManager()
      ->getStorage("flagging")->getQuery()->accessCheck(FALSE)->condition("flag_id", "seguir_topico")
      ->condition("entity_type", "node")->condition("entity_id", $nid)->count()->execute()]);'''.replace("%NID%", str(created["nid"])))
    check("Deixar de seguir remove o acompanhamento", flags["flags"] == 0,
          json.dumps(flags, ensure_ascii=False))

    comment2 = int(php(reply_php.replace("%NID%", str(created["nid"])).replace("%UID%", str(created["uid"]))))
    after_unfollow = query(r'''$nid = %NID%; $storage = \Drupal::entityTypeManager()->getStorage("aculta_aviso");
      echo json_encode(["avisos" => (int) $storage->getQuery()->accessCheck(FALSE)
        ->condition("uid", 1)->condition("assunto_nid", $nid)->count()->execute()]);'''.replace("%NID%", str(created["nid"])))
    check("Resposta após deixar de seguir não cria aviso novo",
          after_unfollow["avisos"] == 1, json.dumps(after_unfollow, ensure_ascii=False))

    threads = query(r'''echo json_encode(["threads" => (int) \Drupal::entityTypeManager()
      ->getStorage("private_message_thread")->getQuery()->accessCheck(FALSE)->count()->execute()]);''')
    check("Seguir e responder não criam conversa privada",
          threads["threads"] == baseline["threads"],
          json.dumps({"antes": baseline["threads"], "depois": threads["threads"]}, ensure_ascii=False))

    driver.req("POST", f"/session/{driver.session}/window/rect", {"width": 390, "height": 844, "x": 0, "y": 0})
    time.sleep(1)
    layout = driver.js("""return {innerWidth: window.innerWidth, innerHeight: window.innerHeight,
      scrollWidth: document.documentElement.scrollWidth,
      overflow: document.documentElement.scrollWidth > window.innerWidth + 1};""")
    check("Central de avisos sem overflow horizontal na menor janela",
          layout and not layout["overflow"],
          json.dumps(layout, ensure_ascii=False))
    screenshot(driver, "forum-follow-notices-narrow.png")
finally:
    if driver is not None:
        driver.close()
    if created is not None:
        cleanup = (cleanup_php
                   .replace("%NID%", str(created["nid"]))
                   .replace("%UID%", str(created["uid"]))
                   .replace("%CID2%", str(comment2))
                   .replace("%CID%", str(comment1)))
        try:
            print("limpou:", php(cleanup))
        except RuntimeError as error:
            print("FALHA NA LIMPEZA:", error)
    passed = sum(1 for item in checks if item["pass"])
    with open(os.path.join(OUT, "results.json"), "w", encoding="utf-8") as handle:
        json.dump({"checks": checks}, handle, ensure_ascii=False, indent=2)
    print(f"Resultado: {passed}/{len(checks)} verificações; capturas em {OUT}")
    if passed != len(checks) or len(checks) < 10:
        sys.exit(1)
