<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Bridge;

use Drupal\aculta_portal\Statistics\ContentEntityMetricAggregatorInterface;
use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Catalog\MetricDefinition;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Applies Statistics metric/access rules to selected entity-ID dimensions.
 */
final class AuthorizedEntityMetricAggregator implements ContentEntityMetricAggregatorInterface {

  /**
   * Metrics whose dimensions are a single Drupal entity ID.
   *
   * Composite dimensions remain owned by their existing Statistics reports.
   *
   * @var array<string, array{entity_type: string, bundle: string}>
   */
  private const ENTITY_ID_METRICS = [
    'wiki.entries.read' => ['entity_type' => 'node', 'bundle' => 'wiki_entry'],
    'wiki.entries.published' => ['entity_type' => 'node', 'bundle' => 'wiki_entry'],
    'wiki.entries.updated' => ['entity_type' => 'node', 'bundle' => 'wiki_entry'],
    'media.files.requests' => ['entity_type' => 'file', 'bundle' => 'file'],
  ];

  public function __construct(
    private readonly MetricCatalog $catalog,
    private readonly AggregateStorageInterface $storage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function aggregateForAuthorizedEntities(
    string $metric,
    int $fromDay,
    int $toDay,
    array $entities,
    AccountInterface $account,
  ): array {
    if (!$account->hasPermission('administer aculta_statistics')) {
      throw new AccessDeniedHttpException();
    }
    if ($fromDay < 0 || $toDay < $fromDay) {
      throw new \InvalidArgumentException('Statistics day range is invalid.');
    }
    if (count($entities) > 100) {
      throw new \InvalidArgumentException('At most 100 authorized entities may be aggregated at once.');
    }

    $definition = $this->catalog->get($metric);
    if ($definition->sensitivity === MetricDefinition::SENSITIVITY_RESTRICTED) {
      throw new AccessDeniedHttpException();
    }
    $expected = self::ENTITY_ID_METRICS[$metric] ?? NULL;
    if ($expected === NULL) {
      throw new \InvalidArgumentException('This Statistics metric is not defined with a single entity-ID dimension.');
    }

    $dimensions = [];
    foreach ($entities as $entity) {
      if (!is_array($entity)
        || ($entity['entity_type'] ?? NULL) !== $expected['entity_type']
        || ($entity['bundle'] ?? NULL) !== $expected['bundle']
        || !is_string($entity['entity_id'] ?? NULL)
        || $entity['entity_id'] === ''
        || strlen($entity['entity_id']) > 128) {
        throw new \InvalidArgumentException('Authorized entity identities do not match this Statistics metric.');
      }
      $dimensions[] = $entity['entity_id'];
    }

    return $this->storage->dimensionTotalsForDimensions($metric, $fromDay, $toDay, $dimensions);
  }

}
