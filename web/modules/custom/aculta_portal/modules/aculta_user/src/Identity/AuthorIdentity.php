<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Identity;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Identidade pública mínima, preparada pelo aculta_user para um consumidor.
 *
 * Não contém entidade, e-mail, campos privados, estatísticas ou markup visual.
 */
final class AuthorIdentity implements CacheableDependencyInterface {

  public function __construct(
    private readonly string $variant,
    private readonly ?string $displayName,
    private readonly ?string $profileUrl,
    private readonly ?array $avatar,
    private readonly ?string $memberSince,
    private readonly CacheableMetadata $cacheability,
    private readonly ?string $bio = NULL,
    private readonly array $socialLinks = [],
    private readonly int $reactionsReceived = 0,
    private readonly int $reactionsGiven = 0,
  ) {}

  /**
   * Exporta somente os dados neutros permitidos para apresentação.
   *
   * @return array{variant: string, display_name: ?string, profile_url: ?string, avatar: ?array, member_since: ?string, bio: ?string, social_links: array, reactions_received: int, reactions_given: int}
   *   Identidade pública preparada para apresentação.
   */
  public function toArray(): array {
    return [
      'variant' => $this->variant,
      'display_name' => $this->displayName,
      'profile_url' => $this->profileUrl,
      'avatar' => $this->avatar,
      'member_since' => $this->memberSince,
      'bio' => $this->bio,
      'social_links' => $this->socialLinks,
      'reactions_received' => $this->reactionsReceived,
      'reactions_given' => $this->reactionsGiven,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
