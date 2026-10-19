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
    private readonly CacheableMetadata $cacheability,
  ) {}

  /**
   * Exporta somente os dados neutros permitidos para apresentação.
   *
   * @return array{variant: string, display_name: ?string, profile_url: ?string}
   */
  public function toArray(): array {
    return [
      'variant' => $this->variant,
      'display_name' => $this->displayName,
      'profile_url' => $this->profileUrl,
    ];
  }

  /** {@inheritdoc} */
  public function getCacheContexts(): array {
    return $this->cacheability->getCacheContexts();
  }

  /** {@inheritdoc} */
  public function getCacheTags(): array {
    return $this->cacheability->getCacheTags();
  }

  /** {@inheritdoc} */
  public function getCacheMaxAge(): int {
    return $this->cacheability->getCacheMaxAge();
  }

}
