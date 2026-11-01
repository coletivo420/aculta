<?php

/**
 * Exports the home content declared by the institution pages (DT-T10).
 *
 * Read-only: writes home-content.json next to this script. Run from web/:
 *   php ../vendor/drush/drush/drush.php php:script export-home-content --script-path=../scripts/content/institution
 */

use Drupal\block_content\Entity\BlockContent;
use Drupal\node\Entity\Node;

$data = ['schema' => 1, 'block_content' => [], 'node' => []];

$ids = \Drupal::entityQuery('block_content')->condition('type', 'basic')->accessCheck(FALSE)->execute();
foreach (BlockContent::loadMultiple($ids) as $block) {
  $data['block_content'][] = [
    'uuid' => $block->uuid(),
    'info' => $block->label(),
    'body' => ['value' => (string) $block->get('body')->value, 'format' => (string) $block->get('body')->format],
    'fields' => [
      'field_section_title' => $block->get('field_section_title')->value,
      'field_section_heading' => $block->get('field_section_heading')->value,
      'field_section_variant' => $block->get('field_section_variant')->value,
    ],
  ];
}
usort($data['block_content'], static fn(array $a, array $b): int => strcmp($a['uuid'], $b['uuid']));

// The front page hero (node 1, alias /inicio).
$hero = Node::load(1);
if ($hero) {
  $link = static fn($field): ?array => $hero->get($field)->isEmpty() ? NULL : ['uri' => $hero->get($field)->uri, 'title' => $hero->get($field)->title];
  $data['node'][] = [
    'uuid' => $hero->uuid(),
    'bundle' => 'page',
    'title' => $hero->label(),
    'fields' => [
      'field_hero_eyebrow' => $hero->get('field_hero_eyebrow')->value,
      'field_hero_title' => $hero->get('field_hero_title')->value,
      'field_hero_slogan' => $hero->get('field_hero_slogan')->value,
      'field_hero_lead' => $hero->get('field_hero_lead')->value,
      'field_hero_primary' => $link('field_hero_primary'),
      'field_hero_secondary' => $link('field_hero_secondary'),
    ],
  ];
}

$path = __DIR__ . '/home-content.json';
file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
echo sprintf("exported %d blocks and %d nodes to %s\n", count($data['block_content']), count($data['node']), basename($path));
