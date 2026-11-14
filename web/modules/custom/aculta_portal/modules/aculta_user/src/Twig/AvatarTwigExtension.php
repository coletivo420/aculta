<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Twig;

use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expõe um avatar real do Avatar Pool para exemplos de componentes (stories).
 */
class AvatarTwigExtension extends AbstractExtension {

  public function __construct(
    private readonly AvatarResolver $avatarResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    return [
      new TwigFunction('aculta_avatar_sample', [$this, 'avatarSample']),
    ];
  }

  /**
   * Avatar do Avatar Pool para uma conta sem foto (sem usuário real).
   *
   * Usa o mesmo sorteio estável do AvatarResolver; devolve render array ou NULL.
   */
  public function avatarSample(string $name = 'Avatar de exemplo'): ?array {
    return $this->avatarResolver->build(NULL, $name, FALSE, new CacheableMetadata());
  }

}
