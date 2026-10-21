<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Exposes the fixed Forum post bundle to Core bundle-aware integrations.
 */
final class ForumPostBundleHooks {

  /**
   * Declares the single logical bundle for the unbundled post entity.
   *
   * Content Moderation associates workflows with bundles and derives its
   * moderation_state field from bundle metadata. The entity's bundle() method
   * alone is not enough: EntityTypeBundleInfo must expose the same bundle.
   *
   * @return array<string, array<string, array<string, mixed>>>
   *   Bundle metadata keyed by entity type and bundle ID.
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

}
