<?php
$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');
function walk($a, $s, $path, &$d) {
  if ($a === $s) return;
  if (!is_array($a) || !is_array($s)) return;
  if (!array_is_list($a) && !array_is_list($s)) {
    $ka = array_keys($a); $ks = array_keys($s);
    $sa = $ka; $ss = $ks; sort($sa); sort($ss);
    if ($sa === $ss && $ka !== $ks) { $d[] = [$path, 'MAP_ORDER', $ka, $ks]; }
  }
  foreach ($a as $k => $v) { if (array_key_exists($k, $s)) walk($v, $s[$k], $path === '' ? (string)$k : "$path.$k", $d); }
}
foreach (['core.entity_form_display.group.lms_course.default','core.entity_form_display.node.aculta_lms_topic.default','field.field.commerce_product.produto_loja.field_imagens','search_api.index.aculta_forum','views.view.aculta_forum_categories','views.view.aculta_forum_search','views.view.wiki_entries'] as $n) {
  $d = []; walk($active->read($n), $sync->read($n), '', $d);
  echo $n, " (" . count($d) . ")\n";
  foreach ($d as $x) { echo "   {$x[0]}\n     ACTIVE: ", json_encode($x[2]), "\n     SYNC:   ", json_encode($x[3]), "\n"; }
}
