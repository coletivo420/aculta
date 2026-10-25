<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_wiki\Event\WikiEntryReviewRequestedEvent;
use Drupal\aculta_wiki\Event\WikiProposalOutcomeRecordedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Dispatches persisted Wiki events and leaves failures for lease-based retry.
 */
#[QueueWorker(
  id: 'aculta_wiki_editorial_events',
  title: new TranslatableMarkup('ACULTA Wiki editorial events'),
  cron: ['time' => 60],
)]
final class WikiEditorialEventWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
    );
  }

  /**
   * Dispatches a validated v1 event; exceptions are retried after its lease.
   *
   * @param mixed $data
   *   Queue payload.
   *
   * @throws \UnexpectedValueException
   *   When the payload is malformed or no consumer is installed yet. Core
   *   keeps the claimed queue item for retry after the lease expires.
   */
  public function processItem($data): void {
    if (is_array($data) && ($data['_aculta_wiki_queue_bootstrap'] ?? FALSE) === TRUE) {
      return;
    }
    if (!is_array($data) || ($data['schema_version'] ?? NULL) !== 1) {
      throw new \UnexpectedValueException('Unsupported Wiki editorial event payload.');
    }

    $event = match ($data['event'] ?? NULL) {
      WikiEntryReviewRequestedEvent::NAME => $this->reviewEvent($data),
      WikiProposalOutcomeRecordedEvent::NAME => $this->proposalEvent($data),
      default => throw new \UnexpectedValueException('Unknown Wiki editorial event.'),
    };
    $name = $event::NAME;
    if ($this->eventDispatcher->getListeners($name) === []) {
      throw new \UnexpectedValueException('No consumer is registered for the Wiki editorial event.');
    }

    $this->eventDispatcher->dispatch($event, $name);
  }

  /**
   * Builds a validated review event from the stable queue fields.
   *
   * @param array<string, mixed> $data
   *   Queue payload fields.
   *
   * @throws \UnexpectedValueException
   *   When the fields cannot construct a valid event.
   */
  private function reviewEvent(array $data): WikiEntryReviewRequestedEvent {
    if (array_keys($data) !== ['schema_version', 'event', 'source_uuid', 'source_revision_id']
      || !is_string($data['source_uuid'] ?? NULL)
      || !is_int($data['source_revision_id'] ?? NULL)) {
      throw new \UnexpectedValueException('Malformed Wiki review event payload.');
    }
    try {
      return new WikiEntryReviewRequestedEvent($data['source_uuid'], $data['source_revision_id']);
    }
    catch (\InvalidArgumentException $exception) {
      throw new \UnexpectedValueException('Malformed Wiki review event payload.', 0, $exception);
    }
  }

  /**
   * Builds a validated proposal event from the stable queue fields.
   *
   * @param array<string, mixed> $data
   *   Queue payload fields.
   *
   * @throws \UnexpectedValueException
   *   When the fields cannot construct a valid event.
   */
  private function proposalEvent(array $data): WikiProposalOutcomeRecordedEvent {
    if (array_keys($data) !== [
      'schema_version',
      'event',
      'source_uuid',
      'source_revision_id',
      'recipient_uid',
      'outcome',
    ]
      || !is_string($data['source_uuid'] ?? NULL)
      || !is_int($data['source_revision_id'] ?? NULL)
      || !is_int($data['recipient_uid'] ?? NULL)
      || !is_string($data['outcome'] ?? NULL)) {
      throw new \UnexpectedValueException('Malformed Wiki proposal event payload.');
    }
    try {
      return new WikiProposalOutcomeRecordedEvent(
        $data['source_uuid'],
        $data['source_revision_id'],
        $data['recipient_uid'],
        $data['outcome'],
      );
    }
    catch (\InvalidArgumentException $exception) {
      throw new \UnexpectedValueException('Malformed Wiki proposal event payload.', 0, $exception);
    }
  }

}
