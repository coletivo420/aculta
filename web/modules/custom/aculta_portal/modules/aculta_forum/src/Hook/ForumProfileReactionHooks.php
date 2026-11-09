<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\aculta_forum\Service\DiscussionReactionManager;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Extends the public profile view with forum reaction statistics.
 */
final class ForumProfileReactionHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'aculta_forum.discussion_reaction_manager')]
    private readonly DiscussionReactionManager $reactionManager,
  ) {}

  /**
   * Implements hook_aculta_user_public_profile_alter().
   */
  #[Hook('aculta_user_public_profile_alter')]
  public function publicProfile(array &$build, ProfileInterface $profile): void {
    $uid = (int) $profile->getOwnerId();
    if ($uid <= 0) {
      return;
    }

    $stats = $this->reactionManager->getUserStats($uid);

    $build['forum_reactions'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'public-profile-reactions'],
      'title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h3',
        '#value' => $this->t('Reações da Comunidade'),
        '#element_attributes' => ['data-portal-ui' => 'public-profile-reactions-title'],
      ],
      'stats' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'public-profile-reactions-stats'],
        'received' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#value' => $this->t('Recebidas: @count', ['@count' => $stats['reactions_received']]),
          '#element_attributes' => ['data-portal-ui' => 'public-profile-reactions-received'],
        ],
        'given' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#value' => $this->t('Dadas: @count', ['@count' => $stats['reactions_given']]),
          '#element_attributes' => ['data-portal-ui' => 'public-profile-reactions-given'],
        ],
      ],
    ];
  }

}
