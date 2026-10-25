<?php
$sync = \Drupal::service('config.storage.sync');
$active = new \Drupal\Core\Config\DatabaseStorage(\Drupal::database(), 'config');
$names = [
  'field.field.comment.aculta_discussion_post.field_aculta_published_at',
  'field.field.media.forum_attachment.field_media_forum_attachment',
  'field.field.media.forum_attachment.field_media_forum_image',
  'field.field.media_gallery.media_gallery.field_gallery_purpose',
  'field.storage.comment.field_aculta_published_at',
  'field.storage.group.field_roster_visible',
  'field.storage.media.field_media_forum_attachment',
  'field.storage.media.field_media_forum_image',
  'field.storage.media_gallery.field_gallery_purpose',
  'media.type.forum_attachment',
  'workflows.workflow.aculta_forum_post',
];
function walk($a, $s, $path, &$out) {
  if ($a === $s) { return; }
  if (is_array($a) && is_array($s)) {
    foreach (array_unique(array_merge(array_keys($a), array_keys($s))) as $k) {
      $p = $path === '' ? (string) $k : "$path.$k";
      if (!array_key_exists($k, $a)) { $out[] = "ONLY_SYNC $p = " . json_encode($s[$k]); continue; }
      if (!array_key_exists($k, $s)) { $out[] = "ONLY_ACTIVE $p = " . json_encode($a[$k]); continue; }
      walk($a[$k], $s[$k], $p, $out);
    }
    return;
  }
  $out[] = "VALUE $path: active=" . json_encode($a) . " sync=" . json_encode($s);
}
foreach ($names as $n) {
  $a = $active->read($n); $s = $sync->read($n);
  echo "=== $n\n";
  if ($a === FALSE) { echo "  ONLY_SYNC\n"; continue; }
  if ($s === FALSE) { echo "  ONLY_ACTIVE\n"; continue; }
  $out = []; walk($a, $s, '', $out);
  foreach (array_slice($out, 0, 40) as $l) { echo "  $l\n"; }
  if (count($out) > 40) { echo "  ... +" . (count($out) - 40) . "\n"; }
}
