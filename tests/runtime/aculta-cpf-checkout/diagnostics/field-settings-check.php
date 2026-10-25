<?php
$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');
foreach (['field.field.media_gallery.media_gallery.field_gallery_purpose','field.storage.group.field_roster_visible'] as $n) {
  $a = $active->read($n); $s = $sync->read($n);
  echo "=== $n ===\n";
  echo "type_active=" . ($a['field_type'] ?? $a['type'] ?? '?') . " type_sync=" . ($s['field_type'] ?? $s['type'] ?? '?') . "\n";
  echo "settings_active=" . json_encode($a['settings'] ?? NULL) . "\n";
  echo "settings_sync=" . json_encode($s['settings'] ?? NULL) . "\n";
  echo "deps_active=" . json_encode($a['dependencies'] ?? NULL) . "\n";
  echo "deps_sync=" . json_encode($s['dependencies'] ?? NULL) . "\n";
}
