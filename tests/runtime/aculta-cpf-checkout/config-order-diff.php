<?php

$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');

$items = [
  'core.entity_form_display.group.lms_course.default',
  'core.entity_form_display.node.aculta_lms_topic.default',
  'field.field.commerce_product.produto_loja.field_imagens',
  'search_api.index.aculta_forum',
  'views.view.aculta_forum_categories',
  'views.view.aculta_forum_search',
  'views.view.wiki_entries',
  'user.role.content_editor',
  'user.role.email_confirmed',
  'search_api.index.aculta_conteudo',
];

function walk($a, $s, $path, &$out) {
  if ($a === $s) { return; }
  if (is_array($a) && is_array($s)) {
    $ka = array_keys($a); $ks = array_keys($s);
    if ($ka !== $ks) {
      sort($ka); sort($ks);
      if ($ka === $ks) { $out[] = [$path, 'MAP_KEY_ORDER', array_keys($a), array_keys($s)]; }
    }
    foreach (array_unique(array_merge(array_keys($a), array_keys($s))) as $k) {
      if (!array_key_exists($k, $a) || !array_key_exists($k, $s)) { continue; }
      walk($a[$k], $s[$k], $path === '' ? (string) $k : "$path.$k", $out);
    }
    return;
  }
  $out[] = [$path, 'VALUE', $a, $s];
}

foreach ($items as $name) {
  $a = $active->read($name);
  $s = $sync->read($name);
  $out = [];
  walk($a, $s, '', $out);
  echo "== $name\n";
  foreach ($out as [$p, $kind, $x, $y]) {
    if ($kind === 'VALUE') {
      echo "   $p: VALUE active=" . json_encode($x, JSON_UNESCAPED_UNICODE) . " sync=" . json_encode($y, JSON_UNESCAPED_UNICODE) . "\n";
      continue;
    }
    if ($p === '') {
      echo "   ROOT: " . $kind . "\n";
      echo "     active: " . implode('|', array_slice($x, 0, 12)) . "\n";
      echo "     sync  : " . implode('|', array_slice($y, 0, 12)) . "\n";
      continue;
    }
    // Lista ou mapa: mostra ordem das duas pontas.
    $xa = $a; $ys = $s;
    foreach (explode('.', $p) as $seg) { $xa = $xa[$seg]; $ys = $ys[$seg]; }
    if (array_is_list($xa)) {
      echo "   $p: LIST_ORDER active=" . implode('|', array_slice($xa, 0, 8)) . " sync=" . implode('|', array_slice($ys, 0, 8)) . "\n";
    }
    else {
      echo "   $p: MAP_ORDER active=" . implode('|', array_slice(array_keys($xa), 0, 10)) . " sync=" . implode('|', array_slice(array_keys($ys), 0, 10)) . "\n";
    }
  }
}
