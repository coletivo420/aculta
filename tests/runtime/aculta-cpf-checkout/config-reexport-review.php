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
$dir = getenv('REEXPORT_OUT') ?: '/tmp/aculta-reexport';
@mkdir($dir, 0775, TRUE);
foreach ($names as $n) {
  $a = $active->read($n);
  if ($a === FALSE) { echo "AUSENTE_NO_ATIVO $n\n"; continue; }
  $yaml = \Symfony\Component\Yaml\Yaml::dump($a, PHP_INT_MAX, 2);
  file_put_contents("$dir/$n.yml", $yaml);
  echo "OK $n\n";
}
