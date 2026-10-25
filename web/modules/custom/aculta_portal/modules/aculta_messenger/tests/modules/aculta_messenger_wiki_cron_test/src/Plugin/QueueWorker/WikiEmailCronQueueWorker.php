<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_wiki_cron_test\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_messenger\Integration\Wiki\Plugin\QueueWorker\WikiNoticeEmailWorker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delegates Core Cron queue processing to the production Wiki email worker.
 */
#[QueueWorker(
  id: 'aculta_wiki_messenger_email',
  title: new TranslatableMarkup('Test Wiki Messenger email queue'),
  cron: ['time' => 60],
)]
final class WikiEmailCronQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly WikiNoticeEmailWorker $worker,
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
      $container->get('aculta_messenger_test.wiki_notice_email_worker'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->worker->processItem($data);
  }

}
