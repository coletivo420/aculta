<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Prepares the latest public commenter identity for topic list rows.
 */
final class PublicForumLastCommentAuthorHooks {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
  ) {}

  /**
   * Adds a cacheable public identity card to public forum topic rows.
   *
   * The Core statistics field supplies only the account ID. Public display
   * name, profile link, avatar policy, and cache metadata remain owned by
   * aculta_user; Drupal login names are never used for presentation.
   */
  #[Hook('preprocess_views_view_fields')]
  public function preprocessViewsViewFields(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if ($view === NULL || $view->id() !== 'aculta_forum_public_topics') {
      return;
    }

    $uid = (int) ($variables['fields']['last_comment_uid']->raw ?? 0);
    if ($uid < 1) {
      return;
    }

    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($account === NULL) {
      return;
    }

    if (!$account->isActive()) {
      $placeholder = ['#markup' => ''];
      CacheableMetadata::createFromObject($account)->applyTo($placeholder);
      $variables['last_comment_author_cache'] = $placeholder;
      return;
    }

    $card = $this->authorCardBuilder->build($account, 'author_forum');
    if ($card !== NULL) {
      $variables['last_comment_author'] = $card;
      return;
    }

    // Keep the no-card result fresh if a public nickname/profile is added.
    $placeholder = ['#markup' => ''];
    CacheableMetadata::createFromObject($account)->applyTo($placeholder);
    $placeholder['#cache']['max-age'] = 0;
    $variables['last_comment_author_cache'] = $placeholder;
  }

}
