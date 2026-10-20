<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Identity;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\SocialNetworksInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Resolve a identidade compartilhada de autoria sem expor dados do perfil.
 *
 * Variantes identificam o contexto de apresentação, mas não concedem acesso.
 * A identidade compartilhada aplica a policy pública ao apelido, link e avatar;
 * cada dado contextual adicional exige policy e metadata de cache próprias.
 */
final class AuthorIdentityResolver {

  /**
   * Variantes de contas User atendidas por esta resolução de identidade.
   */
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
    private readonly ImageRenderer $imageRenderer,
    private readonly PublicProfilePolicy $profilePolicy,
    private readonly SocialNetworksInterface $socialNetworks,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Resolve apelido e link público canônico para uma conta Drupal.
   *
   * Variantes comuns sem apelido não produzem identidade; a editorial usa o
   * nome de exibição como fallback. O link pode ser NULL quando o perfil é
   * privado ou não possui um slug público válido. A conta e o perfil
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
    $editorialFallback = $variant === 'author_editorial' ? trim($account->getDisplayName()) : '';
    $displayName = $nickname !== '' ? $nickname : $editorialFallback;
    if ($displayName === '') {
      return NULL;
    }

    $profileUrl = $nickname !== '' ? $this->publicProfileLink->build($account, $cacheability) : NULL;
    $fieldBoolean = static function ($entity, string $field, bool $default): bool {
      return $entity === NULL || !$entity->hasField($field) || $entity->get($field)->isEmpty()
        ? $default
        : (bool) $entity->get($field)->value;
    };
    $publicProfile = $fieldBoolean($profile, 'field_perfil_publico', TRUE);
    $visibleItems = $this->profilePolicy->publicItems([
      'publico' => $publicProfile,
      'obrigatorio' => PublicProfilePolicy::isObrigatorioPublico($account->getRoles()),
      'apelido' => $nickname,
      'exibir_foto' => $fieldBoolean($profile, 'field_exibir_foto', FALSE),
      'exibir_bio' => $fieldBoolean($profile, 'field_exibir_bio', FALSE),
      'exibir_email' => $fieldBoolean($profile, 'field_exibir_email', FALSE),
      'exibir_redes' => $fieldBoolean($profile, 'field_exibir_redes', FALSE),
    ]);
    $avatar = NULL;
    if (isset($visibleItems['foto']) && $account->hasField('user_picture') && !$account->get('user_picture')->isEmpty()) {
      $file = $account->get('user_picture')->entity;
      if ($file !== NULL) {
        $avatar = $this->imageRenderer->build('avatar', $file->getFileUri(), $displayName, ['loading' => 'lazy']);
        $cacheability->addCacheableDependency($file);
      }
    }
    $memberSince = NULL;
    if (in_array($variant, ['author_forum', 'author_forum_stacked'], TRUE)
      && $publicProfile
      && ($created = $account->getCreatedTime()) > 0) {
      $memberSince = $this->dateFormatter->format($created, 'custom', 'Y', 'UTC');
    }
    $bio = NULL;
    $socialLinks = [];
    if ($variant === 'author_editorial' && $profile !== NULL) {
      if (isset($visibleItems['bio']) && $profile->hasField('field_bio') && !$profile->get('field_bio')->isEmpty()) {
        $bio = trim((string) $profile->get('field_bio')->value);
      }
      if (isset($visibleItems['redes'])) {
        foreach ($this->socialNetworks->linksOf($profile) as $network => $url) {
          $socialLinks[] = [
            'network' => $network,
            'label' => (string) (SocialNetworksInterface::NETWORKS[$network]['label'] ?? ''),
            'url' => $url,
          ];
        }
      }
    }
    return new AuthorIdentity(
      $variant,
      $displayName,
      $profileUrl instanceof Url ? $profileUrl->toString() : NULL,
      $avatar,
      $memberSince,
      $cacheability,
      $bio !== '' ? $bio : NULL,
      $socialLinks,
    );
  }

}
