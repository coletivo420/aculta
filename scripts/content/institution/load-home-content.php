<?php

/**
 * Loads the home content from home-content.json (DT-T10). Idempotent.
 *
 * Matches entities by UUID, which is also what the block placements in config/sync reference,
 * so a fresh environment gets the same content with the same identifiers. Dry run by default:
 *   php ../vendor/drush/drush/drush.php php:script load-home-content --script-path=../scripts/content/institution
 * Writes only with ACULTA_APPLY=1 in the environment.
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;

$apply = getenv('ACULTA_APPLY') === '1';
$data = json_decode((string) file_get_contents(__DIR__ . '/home-content.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$report = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'changes' => []];

$normalize = static function (mixed $value): mixed {
  // Links are compared by URI and title only; options are not part of the content.
  return is_array($value) && array_key_exists('uri', $value) ? ['uri' => $value['uri'], 'title' => $value['title']] : $value;
};

$storage = \Drupal::entityTypeManager()->getStorage('block_content');
foreach ($data['block_content'] as $item) {
  $found = $storage->loadByProperties(['uuid' => $item['uuid']]);
  $block = $found ? reset($found) : NULL;
  if ($block === NULL) {
    $report['created']++;
    $report['changes'][] = 'create ' . $item['info'];
    if ($apply) {
      BlockContent::create(['uuid' => $item['uuid'], 'type' => 'basic', 'info' => $item['info'], 'body' => $item['body']] + $item['fields'])->save();
    }
    continue;
  }
  $changed = [];
  if ($block->label() !== $item['info']) { $changed[] = 'info'; }
  if ((string) $block->get('body')->value !== $item['body']['value'] || (string) $block->get('body')->format !== $item['body']['format']) { $changed[] = 'body'; }
  foreach ($item['fields'] as $field => $value) {
    if ($block->get($field)->value !== $value) { $changed[] = $field; }
  }
  if ($changed === []) {
    $report['unchanged']++;
    continue;
  }
  $report['updated']++;
  $report['changes'][] = 'update ' . $item['info'] . ' (' . implode(', ', $changed) . ')';
  if ($apply) {
    $block->set('info', $item['info'])->set('body', $item['body']);
    foreach ($item['fields'] as $field => $value) { $block->set($field, $value); }
    $block->save();
  }
}

foreach ($data['node'] as $item) {
  // Nó com nid (a home, /node/1) é localizado pelo nid; os demais, pelo UUID. Ausente: criado.
  $node = isset($item['nid'])
    ? Node::load((int) $item['nid'])
    : (($found = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => $item['uuid']])) ? reset($found) : NULL);
  if ($node === NULL) {
    // Ambiente novo: o nó não existe. A home é criada com o nid de page.front (/node/1); os destaques, pelo UUID.
    $report['changes'][] = 'create node ' . ($item['nid'] ?? 'auto') . ' (' . $item['uuid'] . ')';
    $report['created_nodes'] = ($report['created_nodes'] ?? 0) + 1;
    if ($apply) {
      $values = ['uuid' => $item['uuid'], 'type' => $item['bundle'], 'title' => $item['title'], 'status' => 1, 'uid' => 1];
      if (isset($item['nid'])) { $values['nid'] = (int) $item['nid']; }
      $node = Node::create($values);
      foreach ($item['fields'] as $field => $value) { $node->set($field, $value); }
      $node->save();
    }
    continue;
  }
  if ($node->uuid() !== $item['uuid']) {
    $report['changes'][] = 'skip node ' . $node->id() . ' (uuid differs from declared)';
    continue;
  }
  $changed = [];
  foreach ($item['fields'] as $field => $value) {
    $current = $node->get($field)->isEmpty() ? NULL : ($node->get($field)->getValue()[0] ?? NULL);
    $current = match (TRUE) {
      $current === NULL => NULL,
      array_key_exists('uri', $current) => $normalize(['uri' => $current['uri'], 'title' => $current['title']]),
      default => $current['value'] ?? NULL,
    };
    if ($normalize($current) !== $normalize($value)) { $changed[] = $field; }
  }
  if ($changed === []) {
    $report['unchanged']++;
    continue;
  }
  $report['updated']++;
  $report['changes'][] = 'update node ' . $node->id() . ' (' . implode(', ', $changed) . ')';
  if ($apply) {
    foreach ($item['fields'] as $field => $value) { $node->set($field, $value); }
    $node->save();
  }
}

echo json_encode(['mode' => $apply ? 'apply' : 'dry-run'] + $report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
