<?php

/**
 * @file
 * Gate: contrato SDC/PhotoSwipe do álbum editorial e isolamento do Fórum.
 *
 * Só lê arquivos versionados do projeto e usa a biblioteca YAML do Composer.
 * Não consulta Runtime nem abre os diretórios de Core/contrib.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$module = $root . '/web/modules/custom/aculta_portal/modules/aculta_gallery';
$viewPath = $root . '/config/sync/views.view.aculta_album_fotos.yml';
$forumViewPath = $root . '/config/sync/views.view.aculta_forum_gallery_slides.yml';
$componentPath = $root . '/web/themes/custom/aculta420_v2/components/layout-grid/layout-grid.component.yml';
$hooksPath = $module . '/src/Hook/GalleryViewHooks.php';
$errors = [];

foreach ([$viewPath, $forumViewPath, $componentPath, $hooksPath] as $path) {
  if (!is_file($path)) {
    $errors[] = 'arquivo ausente: ' . substr($path, strlen($root) + 1);
  }
}

if ($errors === []) {
  $view = Yaml::parseFile($viewPath);
  $forumView = Yaml::parseFile($forumViewPath);
  $component = Yaml::parseFile($componentPath);
  $hooks = (string) file_get_contents($hooksPath);
  $display = $view['display']['default']['display_options'] ?? [];
  $style = $display['style'] ?? [];
  $pattern = $style['options']['ui_patterns']['ui_patterns'] ?? [];
  $forumStyle = $forumView['display']['default']['display_options']['style']['type'] ?? NULL;

  if (($style['type'] ?? NULL) !== 'ui_patterns') {
    $errors[] = 'álbum editorial não usa UI Patterns Views style';
  }
  if (($pattern['component_id'] ?? NULL) !== 'aculta420_v2:layout-grid') {
    $errors[] = 'álbum editorial não usa o SDC layout-grid';
  }
  if (($pattern['slots']['content']['sources'][0]['source_id'] ?? NULL) !== 'view_rows') {
    $errors[] = 'layout-grid não recebe view_rows';
  }
  if (!in_array('ui_patterns_views', $view['dependencies']['module'] ?? [], TRUE)) {
    $errors[] = 'View não declara dependência de ui_patterns_views';
  }
  if (in_array('vvjs', $view['dependencies']['module'] ?? [], TRUE)) {
    $errors[] = 'View editorial ainda declara VVJS';
  }
  if (($display['fields']['field_media_image']['type'] ?? NULL) !== 'photoswipe_field_formatter') {
    $errors[] = 'formatter PhotoSwipe das imagens não foi preservado';
  }
  if (($display['row']['type'] ?? NULL) !== 'fields') {
    $errors[] = 'row Fields da View não foi preservada';
  }
  if (($display['pager']['type'] ?? NULL) !== 'some'
    || ($display['pager']['options']['items_per_page'] ?? NULL) !== 150) {
    $errors[] = 'pager/página de 150 fotos foi alterado';
  }
  if (($display['access']['type'] ?? NULL) !== 'none') {
    $errors[] = 'access efetivo herdado da galeria foi alterado';
  }
  if (($component['slots']['content']['title'] ?? NULL) === NULL) {
    $errors[] = 'layout-grid não declara o slot content';
  }
  if ($forumStyle !== 'views_vvjs' || !in_array('vvjs', $forumView['dependencies']['module'] ?? [], TRUE)) {
    $errors[] = 'View de slides do Fórum deixou de manter sua configuração isolada';
  }
  $requiredHookContracts = [
    "'aculta_forum_gallery_slides'",
    "'data-aculta-album-thumbs'",
    "'photoswipe-gallery'",
    "\$media->access('view', NULL, TRUE)",
  ];
  foreach ($requiredHookContracts as $contract) {
    if (!str_contains($hooks, $contract)) {
      $errors[] = 'GalleryViewHooks perdeu contrato necessário: ' . $contract;
    }
  }
  if (!str_contains($hooks, 'if ($is_forum_gallery)')) {
    $errors[] = 'miniaturas VVJS não estão restritas ao ramo do Fórum';
  }
  if (!str_contains($hooks, '$cacheability->applyTo($build[\'album_stage\'])')) {
    $errors[] = 'cache metadata de álbum/Media/File/access não é aplicada ao palco';
  }
}

if ($errors !== []) {
  echo "ALBUM UI PATTERNS CONTRACT: FAIL\n";
  foreach ($errors as $error) {
    echo " - $error\n";
  }
  exit(1);
}

echo "ALBUM UI PATTERNS CONTRACT: PASS (grid SDC, PhotoSwipe, pager/access/cache e Fórum isolado)\n";
