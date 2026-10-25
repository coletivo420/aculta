<?php
$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');
function chk($label, $a, $s) {
  $sa = $a; sort($sa); $ss = $s; sort($ss);
  echo $label, ": active_sorted=", ($sa === $a ? 'YES' : 'NO'), " sync_sorted=", ($ss === $s ? 'YES' : 'NO'), " equal_sets=", ($sa === $ss ? 'YES' : 'NO'), "\n";
  if ($sa !== $a) { $first = NULL; foreach ($a as $i => $v) { if ($sa[$i] !== $v) { $first = [$i, $v, $sa[$i]]; break; } } echo "    first unsorted pos: ", json_encode($first, JSON_UNESCAPED_UNICODE), "\n"; }
  if ($ss !== $s) { $first = NULL; foreach ($s as $i => $v) { if ($ss[$i] !== $v) { $first = [$i, $v, $ss[$i]]; break; } } echo "    sync first unsorted: ", json_encode($first, JSON_UNESCAPED_UNICODE), "\n"; }
}
$n = 'user.role.content_editor';
chk($n, $active->read($n)['permissions'], $sync->read($n)['permissions']);
$n = 'user.role.email_confirmed';
chk($n, $active->read($n)['permissions'], $sync->read($n)['permissions']);
$n = 'search_api.index.aculta_conteudo';
chk("$n bundles", $active->read($n)['datasource_settings']['entity:node']['bundles']['selected'], $sync->read($n)['datasource_settings']['entity:node']['bundles']['selected']);
$n = 'search_api.index.aculta_forum';
chk("$n add_url", $active->read($n)['processor_settings']['add_url'], $sync->read($n)['processor_settings']['add_url']);
$n = 'views.view.wiki_entries';
chk("$n display_extenders", $active->read($n)['display']['page_1']['display_options']['display_extenders'], $sync->read($n)['display']['page_1']['display_options']['display_extenders']);
$n = 'field.field.commerce_product.produto_loja.field_imagens';
chk("$n default_value", $active->read($n)['default_value'], $sync->read($n)['default_value']);
// Map key order nos 2 form displays.
function keysig($v) { return json_encode(array_map(fn($x) => is_array($x) ? array_keys($x) : $x, $v)); }
foreach (['core.entity_form_display.group.lms_course.default','core.entity_form_display.node.aculta_lms_topic.default'] as $n) {
  $a = $active->read($n)['content']; $s = $sync->read($n)['content'];
  echo $n, " content_key_order_active=", json_encode(array_keys($a)), " sync=", json_encode(array_keys($s)), "\n";
  echo "   component key order equal=", (keysig($a) === keysig($s) ? 'YES' : 'NO'), "\n";
}
