<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Plugin\QueueWorker;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_links\Service\LinkAnalytics;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Aggregates queued link click events by UTC day and destination.
 */
#[QueueWorker(
  id: LinkAnalytics::QUEUE_ID,
  title: new TranslatableMarkup('Agregação de métricas do ACULTA Links'),
  cron: ['time' => 10],
)]
final class LinkAnalyticsQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('cache_tags.invalidator'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!$this->configFactory->get('aculta_links.settings')->get('analytics_enabled')
      || !is_array($data)
      || ($data['event'] ?? NULL) !== 'click'
      || !is_int($data['target_id'] ?? NULL)
      || $data['target_id'] < 1
      || !is_int($data['occurred_at'] ?? NULL)
      || $data['occurred_at'] < 1) {
      return;
    }

    $now = $this->time->getRequestTime();
    if ($data['occurred_at'] < $now - LinkAnalytics::RETENTION_DAYS * 86400
      || $data['occurred_at'] > $now + 86400) {
      return;
    }

    $this->database->merge('aculta_links_stat_daily')
      ->keys([
        'event' => 'click',
        'target_id' => $data['target_id'],
        'event_date' => gmdate('Y-m-d', $data['occurred_at']),
      ])
      ->fields(['count' => 1])
      ->expression('count', 'count + 1')
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags(['aculta_links_analytics']);
  }

}
