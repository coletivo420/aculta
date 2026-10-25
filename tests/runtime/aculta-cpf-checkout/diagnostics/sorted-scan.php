<?php
$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');
function is_sorted_list($v) { $s = $v; sort($s); return $s === $v; }
function sorted_maps($v) { if (!is_array($v)) return TRUE; if (!array_is_list($v)) { $k = array_keys($v); $s = $k; sort($s); if ($s !== $k) return FALSE; } foreach ($v as $x) { if (!sorted_maps($x)) return FALSE; } return TRUE; }
$names = ['user.role.content_editor','user.role.email_confirmed','search_api.index.aculta_conteudo','search_api.index.aculta_forum','views.view.aculta_forum_categories','views.view.aculta_forum_search','views.view.wiki_entries','core.entity_form_display.group.lms_course.default','core.entity_form_display.node.aculta_lms_topic.default','field.field.commerce_product.produto_loja.field_imagens'];
foreach ($names as $n) {
  $a = $active->read($n); $s = $sync->read($n);
  echo $n, " active_canonical=", (sorted_maps($a) ? 'YES' : 'NO'), " sync_canonical=", (sorted_maps($s) ? 'YES' : 'NO'), "\n";
}
