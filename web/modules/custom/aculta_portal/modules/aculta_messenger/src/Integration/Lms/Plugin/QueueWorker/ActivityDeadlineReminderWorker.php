<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_messenger\Integration\Lms\LearningNoticeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Publishes reminders for current class activity deadlines.
 */
#[QueueWorker(
  id: ActivityDeadlineReminderWorker::QUEUE_ID,
  title: new TranslatableMarkup('Lembretes de prazo de atividades LMS'),
  cron: ['time' => 30],
)]
final class ActivityDeadlineReminderWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public const QUEUE_ID = 'aculta_lms_deadline_reminders';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly LearningNoticeManagerInterface $notices,
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
      $container->get('aculta_messenger.lms_notice_manager'),
    );
  }

  /**
   * Processes opaque source identifiers and revalidates current deadline state.
   */
  public function processItem($data): void {
    if (!is_array($data) || !is_string($data['deadline_uuid'] ?? NULL)
      || !is_string($data['event_version'] ?? NULL)
      || !preg_match('/^[a-f0-9]{32}$/', $data['event_version'])) {
      return;
    }
    $this->notices->publishActivityDeadlineReminderForLearners(
      $data['deadline_uuid'],
      $data['event_version'],
    );
  }

}
