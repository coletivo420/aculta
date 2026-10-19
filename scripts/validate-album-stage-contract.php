<?php

/**
 * Gate: contrato do palco do álbum entre aculta_gallery (módulo), aculta420 (tema) e VVJS (contrib).
 *
 * Regra (docs: web/modules/custom/aculta_portal/modules/aculta_gallery/docs/FRONTEIRA-MODULO-TEMA.md):
 * 1. o módulo emite data-aculta-album-stage, data-aculta-album-thumbs e data-aculta-album-thumb, sem classes;
 * 2. o tema usa os mesmos atributos e cria data-aculta-album-toggle no JS;
 * 3. o tema não depende do nome da view do módulo (aculta_album_fotos);
 * 4. a View do álbum usa o formato views_vvjs;
 * 5. cada seletor .vvjs-* usado pelo CSS do palco existe no VVJS instalado;
 * 6. VVJS expõe vvjs-slideshow com goToSlide()/getCurrentSlide(), e VVJ Core publica vvj:slideChanged.
 *
 * Só lê arquivos. Não consulta o Runtime. Sai com código 1 em caso de falha.
 * VVJS e VVJ Core são lidos de web/modules/contrib; se não existirem, o gate falha.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$module = $root . '/web/modules/custom/aculta_portal/modules/aculta_gallery';
$theme = $root . '/web/themes/custom/aculta420';
$vvjs = $root . '/web/modules/contrib/vvjs';
$vvjCore = $root . '/web/modules/contrib/vvj_core';
$errors = [];

$read = static function (string $path) use (&$errors): string {
  if (!is_file($path)) {
    $errors[] = 'arquivo ausente: ' . substr($path, strlen(dirname(__DIR__)) + 1);
    return '';
  }
  return (string) file_get_contents($path);
};

$hooks = $read($module . '/src/Hook/GalleryViewHooks.php');
$css = $read($theme . '/css/components/album-slideshow.css');
$js = $read($theme . '/js/album-slideshow.js');
$twig = $read($theme . '/templates/views-view-vvjb.html.twig');
$view = $read($root . '/config/sync/views.view.aculta_album_fotos.yml');
$info = $read($theme . '/aculta420.info.yml');
$libs = $read($theme . '/aculta420.libraries.yml');

// 1. Módulo: atributos de comportamento e nenhuma classe de design.
foreach (['data-aculta-album-stage', 'data-aculta-album-thumbs', 'data-aculta-album-thumb'] as $attr) {
  if (!str_contains($hooks, $attr)) {
    $errors[] = "módulo não emite $attr (GalleryViewHooks)";
  }
}
// Única classe permitida: photoswipe-gallery (comportamento do PhotoSwipe, agrupa o lightbox).
preg_match_all("/'class'\s*=>\s*\[([^\]]*)\]/", $hooks, $classes);
foreach ($classes[1] as $list) {
  if (trim($list, " '\"") !== 'photoswipe-gallery') {
    $errors[] = 'módulo emite classe fora do comportamento PhotoSwipe em GalleryViewHooks: ' . trim($list);
  }
}

// 2. Tema: usa os atributos do contrato e cria o botão do celular.
foreach (['data-aculta-album-stage', 'data-aculta-album-thumbs', 'data-aculta-album-thumb'] as $attr) {
  if (!str_contains($js . $css, $attr)) {
    $errors[] = "tema não usa $attr";
  }
}
if (!str_contains($js, 'data-aculta-album-toggle') || !str_contains($css, 'data-aculta-album-toggle')) {
  $errors[] = 'botão data-aculta-album-toggle não é criado pelo JS e estilizado pelo CSS';
}

// 3. Tema não conhece o nome da view do módulo.
foreach (['aculta_album_fotos', 'views-view-aculta_album'] as $name) {
  foreach (['twig' => $twig, 'css' => $css, 'js' => $js, 'info' => $info, 'libraries' => $libs] as $where => $text) {
    if (str_contains($text, $name)) {
      $errors[] = "tema cita a view do módulo ($name) em $where";
    }
  }
}
if (!str_contains($info, 'aculta420/album-slideshow')) {
  $errors[] = 'biblioteca aculta420/album-slideshow não está declarada em aculta420.info.yml';
}
if (!preg_match('/^\s+type:\s+views_vvjs\s*$/m', $view)) {
  $errors[] = 'View aculta_album_fotos não usa o formato views_vvjs';
}
if (!preg_match('/^\s+- vvjs\s*$/m', $view)) {
  $errors[] = 'View aculta_album_fotos não declara dependência de vvjs';
}

// 5. Seletores .vvjs-* do CSS existem no VVJS instalado.
if (!is_dir($vvjs)) {
  $errors[] = 'VVJS ausente em web/modules/contrib/vvjs (dependência do palco)';
}
else {
  $vvjsText = '';
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vvjs, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if (in_array($file->getExtension(), ['css', 'js', 'twig', 'php'], true)) {
      $vvjsText .= (string) file_get_contents($file->getPathname());
    }
  }
  preg_match_all('/\.(vvjs[a-z0-9_-]*)/', $css, $m);
  foreach (array_unique($m[1]) as $class) {
    if (!str_contains($vvjsText, $class)) {
      $errors[] = "seletor .$class do palco não existe no VVJS instalado";
    }
  }

  // 6. API pública e evento do elemento VVJS.
  $element = $vvjs . '/js/vvjs-slideshow-element.js';
  $elementText = is_file($element) ? (string) file_get_contents($element) : '';
  if (!str_contains($elementText, "customElements.define('vvjs-slideshow'")) {
    $errors[] = 'elemento vvjs-slideshow não definido em js/vvjs-slideshow-element.js';
  }
  foreach (['goToSlide(', 'getCurrentSlide('] as $method) {
    if (!str_contains($elementText, $method)) {
      $errors[] = "VVJS não expõe $method";
    }
  }
}
if (!is_dir($vvjCore)) {
  $errors[] = 'VVJ Core ausente em web/modules/contrib/vvj_core';
}
else {
  $base = $read($vvjCore . '/js/vvj-element-base.js');
  // O VVJ Core publica todo evento como CustomEvent `vvj:<nome>` no document, com source: this. O VVJS emite
  // slideChanged pelo próprio elemento (this.emit), então o contrato se verifica nos dois arquivos.
  if (!str_contains($base, 'new CustomEvent(`vvj:${name}`') || !str_contains($base, 'source: this')) {
    $errors[] = 'VVJ Core não publica os eventos vvj:<nome> com source: this';
  }
  if (!is_file($element) || !str_contains((string) file_get_contents($element), "this.emit('slideChanged'")) {
    $errors[] = "VVJS não emite slideChanged (this.emit('slideChanged'))";
  }
}
if (!str_contains($js, 'vvjs-slideshow') || !str_contains($js, 'vvj:slideChanged')) {
  $errors[] = 'tema não liga miniaturas ao elemento/evento público VVJS';
}

if ($errors !== []) {
  echo "ALBUM STAGE CONTRACT GATE: FAIL\n";
  foreach ($errors as $e) {
    echo " - $e\n";
  }
  exit(1);
}
echo "ALBUM STAGE CONTRACT GATE: PASS (módulo, tema e VVJS alinhados; sem nome de view no tema)\n";
