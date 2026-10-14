<?php

/**
 * Gate: contrato do palco do álbum entre aculta_gallery (módulo), aculta420 (tema) e VVJB (contrib).
 *
 * Regra (docs: web/modules/custom/aculta_portal/modules/aculta_gallery/docs/FRONTEIRA-MODULO-TEMA.md):
 * 1. o módulo emite data-aculta-album-stage, data-aculta-album-thumbs e data-aculta-album-thumb, sem classes;
 * 2. o tema usa os mesmos atributos e cria data-aculta-album-toggle no JS;
 * 3. o tema não depende do nome da view do módulo (aculta_album_fotos);
 * 4. cada seletor .vvjb-* usado pelo CSS do palco existe no VVJB instalado;
 * 5. o VVJB instalado expõe o elemento vvjb-carousel com goToSlide(), getCurrentSlide(), pause() e resume().
 *
 * Só lê arquivos. Não consulta o Runtime. Sai com código 1 em caso de falha.
 * O VVJB é lido de web/modules/contrib/vvjb; se não existir, o gate falha (não pode passar sem a dependência).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$module = $root . '/web/modules/custom/aculta_portal/modules/aculta_gallery';
$theme = $root . '/web/themes/custom/aculta420';
$vvjb = $root . '/web/modules/contrib/vvjb';
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
$info = $read($theme . '/aculta420.info.yml');
$libs = $read($theme . '/aculta420.libraries.yml');

// 1. Módulo: atributos de comportamento e nenhuma classe de design.
foreach (['data-aculta-album-stage', 'data-aculta-album-thumbs', 'data-aculta-album-thumb'] as $attr) {
  if (!str_contains($hooks, $attr)) {
    $errors[] = "módulo não emite $attr (GalleryViewHooks)";
  }
}
if (preg_match("/'class'\s*=>/", $hooks)) {
  $errors[] = 'módulo emite classe de marcação em GalleryViewHooks (classes de design são do tema)';
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

// 4. Seletores .vvjb-* do CSS existem no VVJB instalado.
if (!is_dir($vvjb)) {
  $errors[] = 'VVJB ausente em web/modules/contrib/vvjb (dependência do palco)';
}
else {
  $vvjbText = '';
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vvjb, FilesystemIterator::SKIP_DOTS));
  foreach ($it as $file) {
    if (in_array($file->getExtension(), ['css', 'js', 'twig', 'php'], true)) {
      $vvjbText .= (string) file_get_contents($file->getPathname());
    }
  }
  preg_match_all('/\.(vvjb[a-z0-9_-]*)/', $css, $m);
  foreach (array_unique($m[1]) as $class) {
    if (!str_contains($vvjbText, $class)) {
      $errors[] = "seletor .$class do palco não existe no VVJB instalado";
    }
  }

  // 5. API pública do elemento vvjb-carousel.
  $element = $vvjb . '/js/vvjb-carousel-element.js';
  $elementText = is_file($element) ? (string) file_get_contents($element) : '';
  if (!str_contains($elementText, "customElements.define('vvjb-carousel'")) {
    $errors[] = 'elemento vvjb-carousel não definido em js/vvjb-carousel-element.js';
  }
  foreach (['goToSlide(', 'getCurrentSlide(', 'pause(', 'resume('] as $method) {
    if (!str_contains($elementText, $method)) {
      $errors[] = "VVJB não expõe $method";
    }
  }
}

if ($errors !== []) {
  echo "ALBUM STAGE CONTRACT GATE: FAIL\n";
  foreach ($errors as $e) {
    echo " - $e\n";
  }
  exit(1);
}
echo "ALBUM STAGE CONTRACT GATE: PASS (módulo, tema e VVJB alinhados; sem nome de view no tema)\n";
