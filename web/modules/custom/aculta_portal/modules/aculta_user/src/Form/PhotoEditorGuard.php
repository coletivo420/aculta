<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Regras do popup de foto quando a foto atual vem do acervo do sistema (não é foto enviada pela pessoa).
 *
 * - Não há "remover foto" nem recorte para foto do acervo. O recorte é só para foto personalizada enviada.
 * - Salvar sem enviar nada não apaga o avatar do acervo: a foto anterior é restaurada antes da gravação.
 */
final class PhotoEditorGuard {

  /** Pasta das cópias do acervo (ver AvatarPool). */
  public const ACERVO_PREFIX = 'public://pictures/acervo/';

  /** Chave do form_state com a foto de acervo atual, para restaurar no salvamento. */
  public const STATE_KEY = 'aculta_user_acervo_fid';

  /** Verdadeiro se a foto da conta é uma cópia do acervo do sistema. */
  public static function isAcervo(UserInterface $account, \Drupal\file\FileInterface|null $file): bool {
    return $file !== NULL && str_starts_with($file->getFileUri(), self::ACERVO_PREFIX);
  }

  /** Ajusta o formulário de foto para foto do acervo: sem remover e sem recorte; upload disponível. */
  public function adjustForm(array &$form, FormStateInterface $form_state, int $fid): void {
    $form_state->set(self::STATE_KEY, $fid);
    if (!isset($form['user_picture']['widget'][0])) {
      return;
    }
    $widget = &$form['user_picture']['widget'][0];
    unset($widget['remove_button'], $widget['image_crop']);
    $widget['#default_value'] = NULL;
    if (isset($widget['fids'])) {
      $widget['fids']['#default_value'] = '';
    }
    $form['#entity_builders'][] = [$this, 'restoreAcervoPicture'];
  }

  /** Entity builder: se nada foi enviado, mantém a foto do acervo. */
  public function restoreAcervoPicture(string $entity_type, UserInterface $entity, array &$form, FormStateInterface $form_state): void {
    $fid = (int) $form_state->get(self::STATE_KEY);
    if ($fid > 0 && $entity->get('user_picture')->isEmpty()) {
      $entity->set('user_picture', ['target_id' => $fid]);
    }
  }

}
