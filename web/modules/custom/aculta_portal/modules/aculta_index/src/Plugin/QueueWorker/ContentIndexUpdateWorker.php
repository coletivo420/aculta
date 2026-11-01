<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Plugin\QueueWorker;

use Drupal\aculta_index\Generation\ContentIndexGenerationManager;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_index\Storage\ContentIndexWriter;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Applies the latest source projection from a minimal Core Queue payload.
 */
#[QueueWorker(
  id: ContentIndexWorkManager::QUEUE_ID,
  title: new TranslatableMarkup('ACULTA Content Index update'),
  cron: ['time' => 30],
)]
final class ContentIndexUpdateWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly ContentIndexWorkManager $work,
    private readonly ContentIndexGenerationManager $generations,
    private readonly ContentIndexProviderRegistry $providers,
    private readonly ContentIndexWriter $writer,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
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
      $container->get('aculta_index.work_manager'),
      $container->get('aculta_index.generation_manager'),
      $container->get('aculta_index.provider_registry'),
      $container->get('aculta_index.writer'),
      $container->get('entity_type.manager'),
      $container->get('logger.channel.aculta_index'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $workId = is_array($data) ? filter_var($data['work_id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
    if ($workId === FALSE || $workId < 1) {
      return;
    }
    $claim = $this->work->claim((int) $workId);
    if ($claim === NULL) {
      return;
    }

    try {
      $generationIds = $this->generations->getWritableGenerations();
      if ($generationIds === []) {
        // The first backfill will discover current source state.
        $this->work->complete($claim['work_id'], $claim['sequence']);
        return;
      }

      foreach ($generationIds as $generationId) {
        if ($claim['operation'] === 'delete') {
          $this->writer->remove($generationId, $claim['provider_id'], $claim['entity_type'], $claim['entity_id'], $claim['langcode'], $claim['sequence']);
        }
        else {
          $this->projectCurrentSource($generationId, $claim);
        }
      }
      $this->work->complete($claim['work_id'], $claim['sequence']);
    }
    catch (\Throwable $exception) {
      $this->logger->error('ACULTA Content Index worker failed. Error class: @class', ['@class' => $exception::class]);
      $this->work->fail($claim['work_id'], $claim['sequence'], 'worker_failure');
      throw $exception;
    }
  }

  /**
   * Projects the current source state or removes a vanished source entry.
   *
   * @param string $generationId
   *   Active index generation.
   * @param array{provider_id: string, entity_type: string, entity_id: string, langcode: string, sequence: int} $claim
   *   Claimed work identity and sequence.
   */
  private function projectCurrentSource(string $generationId, array $claim): void {
    if (!$this->entityTypeManager->hasDefinition($claim['entity_type'])) {
      $this->writer->remove($generationId, $claim['provider_id'], $claim['entity_type'], $claim['entity_id'], $claim['langcode'], $claim['sequence']);
      return;
    }
    $provider = $this->providers->get($claim['provider_id']);
    $entity = $this->entityTypeManager->getStorage($claim['entity_type'])->load($claim['entity_id']);
    $supportedBundles = $provider->getSupportedEntityTypes()[$claim['entity_type']] ?? [];
    if (!$entity instanceof ContentEntityInterface || !in_array($entity->bundle(), $supportedBundles, TRUE) || !$entity->hasTranslation($claim['langcode'])) {
      $this->writer->remove($generationId, $claim['provider_id'], $claim['entity_type'], $claim['entity_id'], $claim['langcode'], $claim['sequence']);
      return;
    }
    $projection = $provider->project($entity->getTranslation($claim['langcode']));
    if ($projection === NULL) {
      $this->writer->remove($generationId, $claim['provider_id'], $claim['entity_type'], $claim['entity_id'], $claim['langcode'], $claim['sequence']);
      return;
    }
    $this->writer->write($generationId, $claim['provider_id'], $projection, $claim['sequence']);
  }

}
