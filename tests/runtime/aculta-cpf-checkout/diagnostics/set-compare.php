<?php
$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');
$names = ["user.role.content_editor","user.role.email_confirmed","search_api.index.aculta_conteudo","search_api.index.aculta_forum"];
function norm($v) {
  if (!is_array($v)) return $v;
  $out = [];
  foreach ($v as $k => $x) $out[$k] = norm($x);
  if (array_is_list($out)) { sort($out); return $out; }
  ksort($out); return $out;
}
foreach ($names as $n) {
  $a = $active->read($n); $s = $sync->read($n);
  echo "=== $n ===\n";
  echo "set_equal=" . (norm($a) === norm($s) ? 'YES' : 'NO') . "\n";
  echo "raw_equal=" . ($a === $s ? 'YES' : 'NO') . "\n";
  if (isset($a['dependencies']) || isset($s['dependencies'])) {
    echo "deps_equal=" . (($a['dependencies'] ?? NULL) === ($s['dependencies'] ?? NULL) ? 'YES' : 'NO') . "\n";
    echo "active_deps=" . json_encode($a['dependencies'] ?? NULL) . "\n";
    echo "sync_deps=" . json_encode($s['dependencies'] ?? NULL) . "\n";
  }
  echo "active_keys=" . implode(',', array_keys($a)) . "\n";
  echo "sync_keys=" . implode(',', array_keys($s)) . "\n";
}
