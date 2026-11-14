<?php

/**
 * @file
 * Guards the reviewed set of SDC props without a UI Patterns prop type.
 *
 * Run with Drupal bootstrapped:
 * `vendor/bin/drush php:script
 * scripts/validate-aculta420-ui-pattern-prop-types.php`.
 */

declare(strict_types=1);

use Drupal\ui_patterns\ComponentPluginManager;

$expectedUnknownProps = [
  'aculta420_v2:account-shell' => ['logout'],
  'aculta420_v2:breadcrumb' => ['trail'],
  'aculta420_v2:discussion-reaction-bar' => ['allowed_reactions', 'reactions'],
  'aculta420_v2:discussion-reactions-received' => ['allowed_reactions', 'reactions'],
  'aculta420_v2:forum-category-header' => ['stats'],
  'aculta420_v2:forum-composer-guidelines' => ['community_rules', 'tips'],
  'aculta420_v2:forum-post-card' => ['reactions'],
  'aculta420_v2:forum-sidebar-panels' => ['featured', 'moderators', 'related', 'rules', 'stats'],
  'aculta420_v2:forum-topic-card' => ['badges'],
  'aculta420_v2:forum-topic-row' => ['badges'],
  'aculta420_v2:mail-envelope' => ['colors'],
  'aculta420_v2:statistics-category-ranking' => ['rows'],
  'aculta420_v2:studio-calendar' => ['filters', 'selected_day_events', 'weeks'],
];

$componentManager = \Drupal::service(ComponentPluginManager::class);
$componentManager->useCaches(FALSE);
$definitions = $componentManager->getDefinitions();
$actualUnknownProps = [];
$missingTypeProps = [];

foreach ($definitions as $componentId => $definition) {
  if (!str_starts_with($componentId, 'aculta420_v2:')) {
    continue;
  }

  foreach (($definition['props']['properties'] ?? []) as $propName => $prop) {
    $typeDefinition = $prop['ui_patterns']['type_definition'] ?? NULL;
    if (!is_object($typeDefinition) || !method_exists($typeDefinition, 'getPluginId')) {
      $missingTypeProps[] = $componentId . '.' . $propName;
      continue;
    }
    $propType = $typeDefinition->getPluginId();
    if ($propType === 'unknown') {
      $actualUnknownProps[$componentId][] = $propName;
    }
  }
}

foreach ($actualUnknownProps as &$propNames) {
  sort($propNames);
}
unset($propNames);
ksort($actualUnknownProps);

if ($missingTypeProps !== [] || $actualUnknownProps !== $expectedUnknownProps) {
  $expected = json_encode($expectedUnknownProps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  $actual = json_encode($actualUnknownProps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  fwrite(STDERR, "FAIL — conjunto de props UI Patterns sem tipo foi alterado.\n");
  if ($missingTypeProps !== []) {
    fwrite(STDERR, 'Props sem anotação de tipo: ' . implode(', ', $missingTypeProps) . "\n");
  }
  fwrite(STDERR, "Esperado (registrado na ADR-056):\n{$expected}\n");
  fwrite(STDERR, "Encontrado no provider instalado:\n{$actual}\n");
  exit(1);
}

$unknownCount = array_sum(array_map('count', $actualUnknownProps));
printf(
  "PASS — validate-aculta420-ui-pattern-prop-types (%d props unknown em %d SDCs; inventário coincide com ADR-056)\n",
  $unknownCount,
  count($actualUnknownProps),
);
