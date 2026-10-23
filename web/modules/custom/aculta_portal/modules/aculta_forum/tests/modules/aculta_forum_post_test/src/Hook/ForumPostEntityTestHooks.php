<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_post_test\Hook;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\content_moderation\Entity\Handler\ModerationHandler;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Registers the real ForumPost definition without enabling Forum integrations.
 */
final class ForumPostEntityTestHooks {

  /**
   * Declares the fixture's fixed bundle for Content Moderation discovery.
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
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $attributes = (new \ReflectionClass(ForumPost::class))->getAttributes(ContentEntityType::class);
    $definition = $attributes[0]->newInstance();
    $definition->setClass(ForumPost::class);
    $definition->setProvider('aculta_forum_post_test');
    $entity_type = $definition->get();
    // Production discovers the entity type before alters, so Content
    // Moderation adds its handler. The fixture adds the type during the alter,
    // after that hook may have run; restore the handler for parity.
    if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
      $entity_type->setHandlerClass('moderation', ModerationHandler::class);
    }
    $entity_types['aculta_forum_post'] = $entity_type;
  }

}
