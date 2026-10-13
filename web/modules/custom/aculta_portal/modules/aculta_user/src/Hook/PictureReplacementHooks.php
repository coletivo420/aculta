<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;

/**
 * Apaga a foto anterior enviada pela pessoa quando ela troca a foto (economia de disco).
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
    if ($file === NULL || str_starts_with($file->getFileUri(), self::ACERVO_PREFIX) || $this->isUsedByOthers($old)) {
      return;
    }
    $file->delete();
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
