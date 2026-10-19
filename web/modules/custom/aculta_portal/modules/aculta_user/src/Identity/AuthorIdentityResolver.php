<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Identity;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Resolve a identidade compartilhada de autoria sem expor dados do perfil.
 *
 * Variantes são identificadores de apresentação; todas recebem a mesma
 * identidade mínima nesta etapa. Cada capacidade adicional precisa passar pela
 * policy do aculta_user e contribuir sua própria metadata de cache.
 */
final class AuthorIdentityResolver {

  /** Variantes de contas User atendidas por esta resolução de identidade. */
  public const USER_VARIANTS = [
    'profile_full',
    'profile_compact',
    'author_editorial',
    'author_comment',
    'author_forum',
    'author_forum_stacked',
    'author_lms',
    'author_wiki',
    'author_minimal',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PublicProfileLinkInterface $publicProfileLink,
  ) {}

  /**
   * Resolve apelido e link público canônico para uma conta Drupal.
   *
   * Perfis sem apelido não produzem identidade. O link pode ser NULL quando o
   * perfil é privado ou não possui um slug público válido. A conta e o perfil
   * são incorporados à metadata inclusive quando não há apelido, para permitir
   * que consumidores cacheiem também a ausência.
   */
  public function resolve(UserInterface $account, string $variant = 'author_minimal'): ?AuthorIdentity {
    if (!in_array($variant, self::USER_VARIANTS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('Variante de cartão desconhecida: %s', $variant));
    }

    $cacheability = (new CacheableMetadata())->addCacheableDependency($account);
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    if ($profile !== NULL) {
      $cacheability->addCacheableDependency($profile);
    }

    $nickname = $profile !== NULL && $profile->hasField('field_nickname')
      ? trim((string) $profile->get('field_nickname')->value)
      : '';
    if ($nickname === '') {
      return NULL;
    }

    $profileUrl = $this->publicProfileLink->build($account, $cacheability);
    return new AuthorIdentity(
      $variant,
      $nickname,
      $profileUrl instanceof Url ? $profileUrl->toString() : NULL,
      $cacheability,
    );
  }

}
