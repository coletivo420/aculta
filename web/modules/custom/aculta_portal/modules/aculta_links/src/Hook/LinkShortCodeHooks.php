<?php

declare(strict_types=1);

namespace Drupal\aculta_links\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_links\Entity\Link;
use Drupal\aculta_links\Service\LinkShortCodeGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Assigns unique opaque codes to reusable destinations. */
final class LinkShortCodeHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_links.short_code_generator')]
    private readonly LinkShortCodeGenerator $generator,
  ) {}

  /**
   * Generates a globally unique code before a reusable destination is saved.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    if (!$entity instanceof Link || !$entity->get('short_code')->isEmpty()) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('aculta_link');
    for ($attempt = 0; $attempt < 10; $attempt++) {
      $code = $this->generator->generate();
      $matches = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('short_code', $code)
        ->range(0, 1)
        ->execute();
      if ($matches === []) {
        $entity->set('short_code', $code);
        return;
      }
    }

    throw new EntityStorageException('Unable to generate a unique ACULTA Links short code.');
  }

}
