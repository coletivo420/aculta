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
   * Builds the signed-in user's display name and policy-approved avatar.
   *
   * @return array{account_name: ?string, avatar: ?array<string, mixed>, cacheability: \Drupal\Core\Cache\CacheableMetadata}
   *   Portal-owned display name, avatar, and cache metadata.
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
      ? trim($this->textValue($profile->get('field_nickname')->value))
      : '';
    $fieldValue = static function ($entity, string $field, bool $default): bool {
      return $entity === NULL || !$entity->hasField($field) || $entity->get($field)->isEmpty()
        ? $default
        : (bool) $entity->get($field)->value;
    };
    $visibleItems = $this->profilePolicy->publicItems([
      'publico' => $fieldValue($profile, 'field_perfil_publico', TRUE),
      'obrigatorio' => PublicProfilePolicy::isObrigatorioPublico($this->stringsOf($account->getRoles())),
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
    if (is_array($avatar)) {
      $cacheability->applyTo($avatar);
      /** @var array<string, mixed> $avatar */
    }
    else {
      $avatar = NULL;
    }

    return [
      'account_name' => $nickname !== '' ? $nickname : NULL,
      'avatar' => $avatar,
      'cacheability' => $cacheability,
    ];
  }

  /**
   * Texto de um valor; vazio quando o valor não é escalar.
   */
  private function textValue(mixed $value): string {
    return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
  }

  /**
   * Só os itens textuais de uma lista (papéis são strings).
   *
   * @param array<array-key, mixed> $values
   *   Lista de valores não tipados.
   *
   * @return list<string>
   *   Itens que são strings, na mesma ordem.
   */
  private function stringsOf(array $values): array {
    $strings = [];
    foreach ($values as $value) {
      if (is_string($value)) {
        $strings[] = $value;
      }
    }
    return $strings;
  }

}
