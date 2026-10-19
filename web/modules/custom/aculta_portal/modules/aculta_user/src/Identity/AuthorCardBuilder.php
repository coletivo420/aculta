<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Identity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\aculta_user\Entity\LegacyAuthor;
use Drupal\user\UserInterface;

/**
 * Builds a cacheable, theme-neutral author card from the shared identity API.
 */
final class AuthorCardBuilder {

  public function __construct(
    private readonly AuthorIdentityResolver $identityResolver,
  ) {}

  /**
   * Builds a historical card without creating or exposing a User account.
   */
  public function buildLegacy(LegacyAuthor $author): array {
    $build = [
      '#theme' => 'aculta_user_author_card',
      '#variant' => 'author_growroom_historico',
      '#display_name' => (string) $author->label(),
      '#profile_url' => NULL,
      '#avatar' => NULL,
      '#actions' => [],
      '#source_label' => 'Acervo histórico Growroom',
    ];
    CacheableMetadata::createFromObject($author)->applyTo($build);
    return $build;
  }

  /**
   * Builds an author card render array for a supported user variant.
   *
   * Actions remain caller-provided render arrays, so this service never grants
   * access or invents social actions. Only identity and the avatar permitted
   * by PublicProfilePolicy enter the card.
   *
   * @return array|null
   *   Cacheable theme-hook array, or NULL if no public nickname exists.
   */
  public function build(
    UserInterface $account,
    string $variant = 'author_minimal',
    array $actions = [],
  ): ?array {
    $identity = $this->identityResolver->resolve($account, $variant);
    if ($identity === NULL) {
      return NULL;
    }

    $data = $identity->toArray();
    $build = [
      '#theme' => 'aculta_user_author_card',
      '#variant' => $data['variant'],
      '#display_name' => $data['display_name'],
      '#profile_url' => $data['profile_url'],
      '#avatar' => $data['avatar'],
      '#actions' => $actions,
    ];
    CacheableMetadata::createFromObject($identity)->applyTo($build);
    return $build;
  }

}
