<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Account\AccountNames;
use Drupal\aculta_user\Account\NicknamePolicy;
use Drupal\aculta_user\Avatar\AvatarPool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\user\UserInterface;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Creates role Profiles and assigns initial avatars for new accounts.
 */
final class AccountCreationHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.avatar_pool')]
    private readonly AvatarPool $avatarPool,
    #[Autowire(service: 'transliteration')]
    private readonly PhpTransliteration $transliteration,
    #[Autowire(service: 'aculta_user.nickname_policy')]
    private readonly NicknamePolicy $nicknamePolicy,
  ) {}

  /**
   * Implements hook_user_insert().
   */
  #[Hook('user_insert')]
  public function userInsert(UserInterface $account): void {
    if ($account->id() <= 0) {
      return;
    }
    $this->createProfile($account);
  }

  /**
   * Makes imported provider photos permanent, or assigns an archive avatar.
   *
   * The account is not saved from this hook (see PictureReplacementHooks).
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $account): void {
    if (!$account->isNew()) {
      return;
    }
    $file = $account->hasField('user_picture') ? $account->get('user_picture')->entity : NULL;
    if ($file !== NULL && !$file->isPermanent() && !str_starts_with($file->getFileUri(), AvatarPool::DIRECTORY . '/')) {
      $file->setPermanent();
      $file->save();
      return;
    }
    $this->avatarPool->assignPresave($account);
  }

  /**
   * Creates the account's role Profile with an available initial nickname.
   */
  private function createProfile(UserInterface $account): void {
    $storage = $this->entityTypeManager->getStorage('profile');
    if (PerfilDoPapel::principal($storage, $account) !== NULL) {
      return;
    }
    $base = AccountNames::slug($this->transliteration->transliterate((string) $account->getAccountName(), 'pt-br'));
    $apelido = AccountNames::isValid($base) ? $base : 'pessoa-' . $account->id();
    // OAuth reserves a unique account name before the Profile is created. If
    // that reservation added `-N` because the e-mail local part already exists,
    // keep the public display convention `base#N` while its URL slug is base-N.
    $emailBase = AccountNames::slug($this->transliteration->transliterate(AccountNames::localPart((string) $account->getEmail()), 'pt-br'));
    if ($emailBase !== '' && preg_match(
      '/^' . preg_quote($emailBase, '/') . '-((?:[2-9]|[1-9][0-9]+))$/',
      $base,
      $matches,
    )) {
      $apelido = AccountNames::withAutomaticSuffix($emailBase, (int) $matches[1]);
    }
    $storage->create([
      'type' => PerfilDoPapel::tipoDaConta($account),
      'uid' => $account->id(),
      'field_nickname' => $this->unique($apelido, (int) $account->id()),
    ])->save();
  }

  /**
   * Adds a visual suffix when an automatically assigned nickname is occupied.
   */
  private function unique(string $apelido, int $uid): string {
    $candidate = $apelido;
    $base = $apelido;
    if (AccountNames::isHashSuffixNickname($apelido) && preg_match('/^(.+)#([1-9][0-9]*)$/', $apelido, $matches)) {
      $base = $matches[1];
    }
    $i = 2;
    while (!AccountNames::isValid($candidate) || $this->nicknamePolicy->isTaken($candidate)) {
      $candidate = AccountNames::withAutomaticSuffix($base, $i++);
      if ($i > 50) {
        return 'pessoa-' . $uid;
      }
    }
    return $candidate;
  }

}
