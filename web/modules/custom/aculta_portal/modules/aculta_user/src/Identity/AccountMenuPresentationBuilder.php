<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Identity;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\user\UserInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Builds the neutral account-menu identity contract for the theme. */
final class AccountMenuPresentationBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PublicProfilePolicy $profilePolicy,
    private readonly AvatarResolver $avatarResolver,
  ) {}

  /**
   * Builds a policy-approved avatar for a signed-in user.
   *
   * @return array{avatar: ?array, cacheability: \Drupal\Core\Cache\CacheableMetadata}
   *   Cacheable image render array and page metadata.
   */
  public function build(UserInterface $account): array {
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($account)
      ->addCacheContexts(['user']);
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    if ($profile !== NULL) {
      $cacheability->addCacheableDependency($profile);
    }

    $nickname = $profile !== NULL && $profile->hasField('field_nickname')
      ? trim((string) $profile->get('field_nickname')->value)
      : '';
    $fieldValue = static function ($entity, string $field, bool $default): bool {
      return $entity === NULL || !$entity->hasField($field) || $entity->get($field)->isEmpty()
        ? $default
        : (bool) $entity->get($field)->value;
    };
    $visibleItems = $this->profilePolicy->publicItems([
      'publico' => $fieldValue($profile, 'field_perfil_publico', TRUE),
      'obrigatorio' => PublicProfilePolicy::isObrigatorioPublico($account->getRoles()),
      'apelido' => $nickname,
      'exibir_foto' => $fieldValue($profile, 'field_exibir_foto', FALSE),
      'exibir_bio' => $fieldValue($profile, 'field_exibir_bio', FALSE),
      'exibir_email' => FALSE,
      'exibir_redes' => FALSE,
    ]);
    $avatar = $this->avatarResolver->build(
      $account,
      (string) new TranslatableMarkup('Avatar da conta'),
      isset($visibleItems['foto']),
      $cacheability,
    );
    if ($avatar !== NULL) {
      $cacheability->applyTo($avatar);
    }

    return ['avatar' => $avatar, 'cacheability' => $cacheability];
  }

}
