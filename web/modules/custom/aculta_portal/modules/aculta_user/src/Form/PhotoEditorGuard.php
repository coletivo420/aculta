<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\file\FileInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Regras do popup de foto quando a foto atual vem do acervo do sistema (não é foto enviada pela pessoa).
 *
 * - Não há "remover foto" nem recorte para foto do acervo. O recorte é só para foto personalizada enviada.
 * - Salvar sem enviar nada não apaga o avatar do acervo: a foto anterior é restaurada antes da gravação.
 */
final class PhotoEditorGuard {

  /**
   * Pasta das cópias do acervo (ver AvatarPool).
   */
  public const ACERVO_PREFIX = 'public://pictures/acervo/';

  /**
   * Chave do form_state com a foto de acervo atual, para restaurar no salvamento.
   */
  public const STATE_KEY = 'aculta_user_acervo_fid';

  /**
   * Verdadeiro se a foto da conta é uma cópia do acervo do sistema.
   */
  public static function isAcervo(UserInterface $account, FileInterface|null $file): bool {
    if ($file === NULL) {
      return FALSE;
    }
    $uri = $file->getFileUri();
    return is_string($uri) && str_starts_with($uri, self::ACERVO_PREFIX);
  }

  /**
   * Ajusta o formulário de foto para foto do acervo: sem remover e sem recorte; upload disponível.
   *
   * @param array<array-key, mixed> $form
   *   Estrutura do formulário, alterada no lugar.
   */
  public function adjustForm(array &$form, FormStateInterface $form_state, int $fid): void {
    $form_state->set(self::STATE_KEY, $fid);
    $userPicture = $form['user_picture'] ?? NULL;
    $widgets = is_array($userPicture) ? ($userPicture['widget'] ?? NULL) : NULL;
    $widget = is_array($widgets) ? ($widgets[0] ?? NULL) : NULL;
    if (!is_array($widget)) {
      return;
    }
    unset($widget['remove_button'], $widget['image_crop']);
    $widget['#default_value'] = NULL;
    if (is_array($widget['fids'] ?? NULL)) {
      $fids = $widget['fids'];
      $fids['#default_value'] = '';
      $widget['fids'] = $fids;
    }
    // Grava o widget de volta no formulário, sem referência, para manter o tipo conhecido.
    $widgets[0] = $widget;
    $userPicture['widget'] = $widgets;
    $form['user_picture'] = $userPicture;
    $builders = is_array($form['#entity_builders'] ?? NULL) ? $form['#entity_builders'] : [];
    $builders[] = [$this, 'restoreAcervoPicture'];
    $form['#entity_builders'] = $builders;
  }

  /**
   * Entity builder: se nada foi enviado, mantém a foto do acervo.
   *
   * @param array<array-key, mixed> $form
   *   Estrutura do formulário.
   */
  public function restoreAcervoPicture(string $entity_type, UserInterface $entity, array &$form, FormStateInterface $form_state): void {
    $fid = $this->intValue($form_state->get(self::STATE_KEY));
    if ($fid > 0 && $entity->get('user_picture')->isEmpty()) {
      $entity->set('user_picture', ['target_id' => $fid]);
    }
  }

  /**
   * After build do widget de foto: retira o recorte inline (o recorte fica no popup próprio).
   *
   * @param array<array-key, mixed> $element
   *   Elemento do widget.
   *
   * @return array<array-key, mixed>
   *   Elemento sem o recorte inline.
   */
  public static function hideInlineCrop(array $element, FormStateInterface $form_state): array {
    unset($element['image_crop']);
    return $element;
  }

  /**
   * Inteiro de um valor do form_state; zero quando o valor não é numérico.
   */
  private function intValue(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

}
