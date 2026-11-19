"""Pré-visualização de anexo e mover comentário com dados sintéticos locais.

Cria, no ambiente local (127.0.0.1), uma imagem PNG sintética, uma Media
despublicada, um tópico despublicado com o embed e um Comment despublicado;
abre a pré-visualização autenticada e o formulário de mover comentário no
Firefox ESR/X11 e apaga tudo no final, restaurando `comment_move_enabled`.

Nenhum dado é enviado ao servidor de testes e nenhum formulário é submetido: as
páginas são apenas abertas. O link único de login e os identificadores
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
from wdlib import Driver, WDError  # noqa: E402

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
MAIN = "https://aculta.toca.net.br"
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
$dir = "public://forum-anexos-synthetic";
$fs->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
$uri = $dir . "/synthetic-preview-" . time() . ".png";
$img = imagecreatetruecolor(48, 32);
imagefill($img, 0, 0, imagecolorallocate($img, 40, 120, 60));
ob_start(); imagepng($img); $bytes = ob_get_clean();
file_put_contents($uri, $bytes);
$file = \Drupal\file\Entity\File::create(["uri" => $uri, "filename" => basename($uri), "filemtime" => time(), "filesize" => strlen($bytes), "status" => 1]);
$file->save();
$media = \Drupal\media\Entity\Media::create([
  "bundle" => "forum_attachment",
  "name" => "Synthetic preview",
  "uid" => 1,
  "status" => 0,
  "field_media_forum_image" => ["target_id" => $file->id(), "alt" => "Pré-visualização sintética"],
  "field_media_forum_attachment" => ["target_id" => $file->id()],
]);
$media->save();
$node = \Drupal\node\Entity\Node::create([
  "type" => "aculta_forum_topic",
  "title" => "[SYNTH] pré-visualização de anexo",
  "status" => 0,
  "uid" => 1,
  "field_domain_source" => ["target_id" => "forum_aculta_org"],
  "body" => ["value" => '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"></drupal-media>', "format" => "forum"],
]);
$node->save();
$topic_ids = \Drupal::entityQuery("node")->accessCheck(FALSE)
  ->condition("type", "aculta_forum_topic")->condition("status", 1)
  ->sort("nid")->range(0, 1)->execute();
$topic_id = $topic_ids === [] ? 0 : (int) reset($topic_ids);
$comment = \Drupal\comment\Entity\Comment::create([
  "comment_type" => "aculta_discussion_post",
  "entity_type" => "node",
  "entity_id" => $topic_id,
  "field_name" => "field_aculta_discussion",
  "uid" => 1,
  "subject" => "[SYNTH] comentário para mover",
  "comment_body" => ["value" => "Comentário sintético somente para leitura da UI.", "format" => "forum"],
  "status" => 0,
]);
$comment->save();
echo json_encode([
  "mid" => (int) $media->id(),
  "fid" => (int) $file->id(),
  "uri" => $uri,
  "nid" => (int) $node->id(),
  "cid" => (int) $comment->id(),
]);
'''

cleanup_php = r'''
$result = [];
$cid = (int) %CID%;
$c = \Drupal\comment\Entity\Comment::load($cid);
if ($c) { $c->delete(); $result[] = "comment"; }
$nid = (int) %NID%;
$n = \Drupal\node\Entity\Node::load($nid);
if ($n) { $n->delete(); $result[] = "node"; }
$mid = (int) %MID%;
$m = \Drupal\media\Entity\Media::load($mid);
if ($m) { $m->delete(); $result[] = "media"; }
$fid = (int) %FID%;
$f = \Drupal\file\Entity\File::load($fid);
if ($f) {
  $uri = $f->getFileUri();
  $f->delete();
  $path = \Drupal::service("file_system")->realpath($uri);
  if ($path && is_file($path)) { @unlink($path); }
  $result[] = "file";
}
echo implode(",", $result);
'''

created = None
driver = None
original_move = None
try:
    original_move = php(r'''echo \Drupal::config("aculta_forum.settings")->get("comment_move_enabled") ? "1" : "0";''')
    php(r'''\Drupal::configFactory()->getEditable("aculta_forum.settings")->set("comment_move_enabled", TRUE)->save();''')
    drush("cr")
    created = json.loads(php(create_php))

    uli = drush("uli", "--uid=1", "--no-browser", f"--uri={ACCOUNT}")
    driver = Driver(prefs=PREFS)
    driver.get(uli)
    logged = False
    for _ in range(15):
        logged = driver.js(
            "return !!document.querySelector('a[href*=\"/user/logout\"], a[href*=\"/sair\"]')"
        )
        if logged:
            break
        time.sleep(1)
    check("Sessão autenticada (uid 1) no host local simulado", logged)

    driver.get(f"{FORUM}/node/{created['nid']}")
    preview = driver.js("""return {
      path: location.pathname,
      title: document.title,
      images: [...document.querySelectorAll('main img')].map(i => ({src: i.getAttribute('src'), alt: i.getAttribute('alt')})),
      errors: [...document.querySelectorAll('.messages--error, [role=alert]')]
        .map(e => e.innerText.trim())
        .filter(t => t && !t.includes('atualizações de segurança') && !t.includes('Minha conta > Segurança')),
      body: (document.querySelector('main') || document.body).innerText.slice(0, 200),
    }""")
    matches = [img for img in preview.get("images", [])
               if "synthetic-preview-" in (img.get("src") or "")]
    check("Pré-visualização: imagem sintética renderizada com alt",
          bool(matches) and matches[0].get("alt") == "Pré-visualização sintética",
          json.dumps({"path": preview.get("path"), "images": matches}, ensure_ascii=False))
    check("Pré-visualização: sem erro visível do Fórum", not preview.get("errors"),
          json.dumps(preview.get("errors"), ensure_ascii=False))
    screenshot(driver, "forum-synthetic-preview.png")

    driver.get(f"{MAIN}/painel-administrativo/forum/comentarios/{created['cid']}/mover")
    move = driver.js("""return {
      path: location.pathname,
      title: document.title,
      select: !!document.querySelector('select[name="destination"]'),
      options: document.querySelectorAll('select[name="destination"] option').length,
      confirm: !!document.querySelector('input[name="confirm_move"]'),
      submit: !!document.querySelector('input[type="submit"]'),
      errors: [...document.querySelectorAll('.messages--error, [role=alert]')]
        .map(e => e.innerText.trim())
        .filter(t => t && !t.includes('atualizações de segurança') && !t.includes('Minha conta > Segurança')),
    }""")
    check("Mover comentário: formulário abre com destinos e confirmação",
          bool(move) and move.get("select") and move.get("options", 0) > 0
          and move.get("confirm") and move.get("submit") and not move.get("errors"),
          json.dumps(move, ensure_ascii=False))
    screenshot(driver, "forum-synthetic-comment-move.png")
finally:
    if driver is not None:
        driver.close()
    if created is not None:
        cleanup = (cleanup_php
                   .replace("%CID%", str(created["cid"]))
                   .replace("%NID%", str(created["nid"]))
                   .replace("%MID%", str(created["mid"]))
                   .replace("%FID%", str(created["fid"])))
        try:
            print("limpou:", php(cleanup))
        except RuntimeError as error:
            print("FALHA NA LIMPEZA:", error)
    if original_move is not None:
        php(r'''\Drupal::configFactory()->getEditable("aculta_forum.settings")
          ->set("comment_move_enabled", %s)->save();''' % ("TRUE" if original_move == "1" else "FALSE"))
        drush("cr")
    passed = sum(1 for item in checks if item["pass"])
    with open(os.path.join(OUT, "results.json"), "w", encoding="utf-8") as handle:
        json.dump({"checks": checks}, handle, ensure_ascii=False, indent=2)
    print(f"Resultado: {passed}/{len(checks)} verificações; capturas em {OUT}")
    if passed != len(checks) or len(checks) < 4:
        sys.exit(1)
