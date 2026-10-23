<?php
$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');
$names = array_unique(array_merge($sync->listAll(), $active->listAll()));
sort($names);
function walk($a, $s, $path, &$d) {
  if ($a === $s) { return; }
  if (is_array($a) && is_array($s)) {
    $ka = array_keys($a); $ks = array_keys($s);
    if ($ka !== $ks) { $sa = $ka; $ss = $ks; sort($sa); sort($ss); if ($sa === $ss) { $d[] = 'MAP_ORDER'; } }
    foreach (array_unique(array_merge($ka, $ks)) as $k) {
      if (!array_key_exists($k, $a) || !array_key_exists($k, $s)) { $d[] = 'MISSING'; continue; }
      walk($a[$k], $s[$k], $path === '' ? (string) $k : "$path.$k", $d);
    }
    return;
  }
  $d[] = 'VALUE';
}
$onlySync = []; $onlyActive = []; $diff = [];
foreach ($names as $n) {
  $a = $active->read($n); $s = $sync->read($n);
  if ($a === $s) { continue; }
  if ($a === FALSE) { $onlySync[] = $n; continue; }
  if ($s === FALSE) { $onlyActive[] = $n; continue; }
  $d = []; walk($a, $s, '', $d);
  $c = array_count_values($d);
  $diff[$n] = $c;
}
echo "only_sync=" . count($onlySync) . " only_active=" . count($onlyActive) . " differing=" . count($diff) . "\n";
if ($onlySync) { echo "ONLY_SYNC: " . implode(', ', $onlySync) . "\n"; }
if ($onlyActive) { echo "ONLY_ACTIVE: " . implode(', ', $onlyActive) . "\n"; }
foreach ($diff as $n => $c) {
  echo str_pad($n, 62) . json_encode($c) . "\n";
}
