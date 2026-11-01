<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_events\Event\OrganizationContributionOutcomeRecordedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches persisted Events contribution outcomes for Messenger consumers.
 */
#[QueueWorker(
  id: 'aculta_events_contribution_events',
  title: new TranslatableMarkup('ACULTA Events contribution events'),
  cron: ['time' => 60],
)]
final class OrganizationContributionEventWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Creates a worker using Drupal's event dispatcher.
   */
  public function __construct(array $configuration, string $plugin_id, mixed $plugin_definition, private readonly EventDispatcherInterface $eventDispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates the worker from the service container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('event_dispatcher'));
  }

  /**
   * Dispatches only a validated versioned event envelope.
   */
  public function processItem($data): void {
    if (is_array($data) && ($data['_aculta_events_queue_bootstrap'] ?? FALSE) === TRUE) {
      return;
    }
    $legacyKeys = ['schema_version', 'event', 'source_uuid', 'source_revision_id', 'recipient_uid', 'outcome'];
    $currentKeys = [
      'schema_version',
      'event',
      'source',
      'source_uuid',
      'source_revision_id',
      'recipient_uid',
      'outcome',
    ];
    $legacy = is_array($data) && array_keys($data) === $legacyKeys;
    if (!is_array($data) || (!$legacy && array_keys($data) !== $currentKeys)
      || ($data['schema_version'] ?? NULL) !== OrganizationContributionOutcomeRecordedEvent::SCHEMA_VERSION
      || (!$legacy && !is_string($data['source'] ?? NULL))
      || !is_string($data['source_uuid'] ?? NULL)
      || !is_int($data['source_revision_id'] ?? NULL)
      || !is_int($data['recipient_uid'] ?? NULL)
      || !is_string($data['outcome'] ?? NULL)
      || ($data['event'] ?? NULL) !== OrganizationContributionOutcomeRecordedEvent::NAME) {
      throw new \UnexpectedValueException('Malformed Events contribution event payload.');
    }
    try {
      $event = new OrganizationContributionOutcomeRecordedEvent(
        $legacy ? 'events_organization' : $data['source'],
        $data['source_uuid'],
        $data['source_revision_id'],
        $data['recipient_uid'],
        $data['outcome'],
      );
    }
    catch (\InvalidArgumentException $exception) {
      throw new \UnexpectedValueException('Malformed Events contribution event payload.', 0, $exception);
    }
    if ($this->eventDispatcher->getListeners(OrganizationContributionOutcomeRecordedEvent::NAME) === []) {
      throw new \UnexpectedValueException('No consumer is registered for the Events contribution event.');
    }
    $this->eventDispatcher->dispatch($event, OrganizationContributionOutcomeRecordedEvent::NAME);
  }

}
