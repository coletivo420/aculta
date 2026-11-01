<?php

declare(strict_types=1);

namespace Drupal\aculta_index_forum_test\Hook;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Registers the production ForumPost entity for isolated Index tests.
 */
final class ForumPostEntityTestHooks {

  /**
   * Declares the fixed ForumPost bundle.
   */
  #[Hook('entity_bundle_info')]
  public function entityBundleInfo(): array {
    return [
      'aculta_forum_post' => [
        'aculta_forum_post' => [
          'label' => new TranslatableMarkup('Publicação do Fórum'),
          'translatable' => TRUE,
        ],
      ],
    ];
  }

  /**
   * Adds the real ForumPost entity definition without enabling Forum.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $attributes = (new \ReflectionClass(ForumPost::class))
      ->getAttributes(ContentEntityType::class);
    if ($attributes === []) {
      throw new \LogicException('The production ForumPost entity attribute is missing.');
    }
    $definition = $attributes[0]->newInstance();
    $definition->setClass(ForumPost::class);
    $definition->setProvider('aculta_index_forum_test');
    $entity_types['aculta_forum_post'] = $definition->get();
  }

}
