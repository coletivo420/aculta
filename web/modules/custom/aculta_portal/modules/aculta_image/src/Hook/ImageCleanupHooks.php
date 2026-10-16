<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Hook;

use Drupal\aculta_image\ImageCleanup;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Enfileira imagens retiradas de campos para rechecagem após salvar a entidade. */
final class ImageCleanupHooks {

  public function __construct(
    #[Autowire(service: 'aculta_image.cleanup')]
    private readonly ImageCleanup $cleanup,
  ) {}

  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->cleanup->enqueueReplaced($entity);
  }

  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    $this->cleanup->enqueueChangedText($entity);
  }

  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    $this->cleanup->enqueueBeforeDelete($entity);
  }

  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->cleanup->enqueueDeleted($entity);
  }

}
