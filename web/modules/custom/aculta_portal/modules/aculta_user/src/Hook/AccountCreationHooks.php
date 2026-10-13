<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Account\AccountNames;
use Drupal\aculta_user\Avatar\AvatarPool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Transliteration\PhpTransliteration;
use Drupal\user\UserInterface;

/**
 * Criação de conta: perfil participante com apelido inicial, e avatar do acervo quando não há foto.
 */
final class AccountCreationHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.avatar_pool')]
    private readonly AvatarPool $avatarPool,
    #[Autowire(service: 'transliteration')]
    private readonly PhpTransliteration $transliteration,
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
   * Conta nova: foto importada do provedor (OAuth, via Social Auth) é foto personalizada, então fica permanente;
   * sem foto, recebe avatar do acervo. Sem salvar a conta dentro do hook (ver PictureReplacementHooks).
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

  /** Cria o perfil participante com o nome de usuário como apelido inicial, sem colidir. */
  private function createProfile(UserInterface $account): void {
    $storage = $this->entityTypeManager->getStorage('profile');
    if ($storage->loadByUser($account, 'participante') !== NULL) {
      return;
    }
    $base = AccountNames::slug($this->transliteration->transliterate((string) $account->getAccountName(), 'pt-br'));
    $apelido = AccountNames::isValid($base) ? $base : 'pessoa-' . $account->id();
    $storage->create([
      'type' => 'participante',
      'uid' => $account->id(),
      'field_nickname' => $this->unique($apelido, (int) $account->id()),
    ])->save();
  }

  /** Acrescenta sufixo numérico quando o apelido já existe ou é reservado. */
  private function unique(string $apelido, int $uid): string {
    $candidate = $apelido;
    $i = 2;
    while (!AccountNames::isValid($candidate) || $this->taken($candidate)) {
      $candidate = mb_substr($apelido, 0, AccountNames::MAX - strlen('-' . $i)) . '-' . $i++;
      if ($i > 50) {
        return 'pessoa-' . $uid;
      }
    }
    return $candidate;
  }

  private function taken(string $apelido): bool {
    return $this->entityTypeManager->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)->condition('type', 'participante')->condition('field_nickname', $apelido)->range(0, 1)->count()->execute() > 0;
  }

}
