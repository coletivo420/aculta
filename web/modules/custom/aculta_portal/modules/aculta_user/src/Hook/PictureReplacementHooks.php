<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_image\FileRemover;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\user\UserInterface;

/**
 * Apaga a foto anterior enviada pela pessoa quando ela troca ou remove a foto (economia de disco).
 *
 * Remover a foto personalizada faz a conta voltar ao sorteio de avatar do acervo.
 *
 * Só apaga arquivo que a própria pessoa enviou (ou que veio do provedor OAuth). Nunca apaga cópia do acervo
 * (public://pictures/acervo/) e nunca apaga arquivo que ainda esteja em uso por outra conta.
 */
final class PictureReplacementHooks {

  /** Pasta das cópias do acervo de avatares. Essas não são fotos enviadas pela pessoa. */
  private const ACERVO_PREFIX = 'public://pictures/acervo/';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.avatar_pool')]
    private readonly AvatarPool $avatarPool,
    #[Autowire(service: 'aculta_image.file_remover')]
    private readonly FileRemover $fileRemover,
  ) {}

  /**
   * Implements hook_user_update().
   */
  #[Hook('user_update')]
  public function userUpdate(UserInterface $account): void {
    $original = $account->getOriginal();
    if (!$original instanceof UserInterface || !$original->hasField('user_picture')) {
      return;
    }
    $old = (int) ($original->get('user_picture')->target_id ?? 0);
    $new = (int) ($account->get('user_picture')->target_id ?? 0);
    if ($old === 0 || $old === $new) {
      return;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($old);
    if ($file !== NULL && !str_starts_with($file->getFileUri(), self::ACERVO_PREFIX) && !$this->isUsedByOthers($old)) {
      $this->fileRemover->delete($file, 'troca de foto de perfil');
    }
  }

  /**
   * Implements hook_user_presave().
   *
   * Foto personalizada removida: a conta volta ao sorteio de avatar do sistema (decisão do responsável). Feito antes
   * da gravação, para não salvar a conta dentro de um hook de usuário.
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $account): void {
    $original = $account->getOriginal();
    if (!$original instanceof UserInterface || !$account->hasField('user_picture') || !$account->get('user_picture')->isEmpty()) {
      return;
    }
    $old = (int) ($original->get('user_picture')->target_id ?? 0);
    $file = $old > 0 ? $this->entityTypeManager->getStorage('file')->load($old) : NULL;
    if ($file !== NULL && !str_starts_with($file->getFileUri(), self::ACERVO_PREFIX)) {
      $this->avatarPool->assignPresave($account);
    }
  }

  /** Verdadeiro se alguma conta ainda referencia a foto. */
  private function isUsedByOthers(int $fid): bool {
    return $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('user_picture.target_id', $fid)
      ->range(0, 1)
      ->count()
      ->execute() > 0;
  }

}
