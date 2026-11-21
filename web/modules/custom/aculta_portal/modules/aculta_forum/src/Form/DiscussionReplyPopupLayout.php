<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Template\Attribute;

/**
 * Layout do popup de resposta e edição (Compositor de Resposta da Discussão).
 *
 * Usado pelo hook do formulário de comentário no popup e pela story do SDC na UI Library.
 */
final class DiscussionReplyPopupLayout {

  /**
   * Campos do comentário que o popup mantém. Autoria, nome, data, status e idioma saem.
   */
  private const KEEP = ['comment_body', 'actions', 'form_id', 'form_build_id', 'form_token'];

  /**
   * Aplica o layout do popup ao formulário de comentário.
   *
   * @param array<mixed> $form
   *   Form render array.
   * @param bool $isNew
   *   Value used for $isNew.
   */
  public static function apply(array &$form, bool $isNew): void {
    // Resposta e edição não têm Assunto nem a marca "Rejeitado".
    unset($form['subject'], $form['field_aculta_rejected']);
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && !in_array($key, self::KEEP, TRUE)) {
        unset($form[$key]);
      }
    }

    if (isset($form['actions']) && is_array($form['actions'])) {
      // Sem pré-visualização; "Publicar" ou "Salvar", conforme o caso.
      unset($form['actions']['preview']);
      if (isset($form['actions']['submit']) && is_array($form['actions']['submit'])) {
        $form['actions']['submit']['#value'] = $isNew ? t('Publicar comentário') : t('Salvar alterações');
        $form['actions']['submit']['#button_type'] = 'primary';
      }
      // "Limpar" zera o formulário (reset nativo, sem JavaScript).
      $form['actions']['clear'] = [
        '#theme' => 'portal_component',
        '#name' => 'button',
        '#props' => [
          'text' => (string) t('Limpar'),
          'variant' => 'secondary',
          'attributes' => new Attribute(['data-aculta-reply-clear' => '']),
        ],
        '#weight' => 50,
      ];
      // "Cancelar" (secundário, como Limpar) fecha o popup.
      $form['actions']['cancel'] = [
        '#theme' => 'portal_component',
        '#name' => 'button',
        '#props' => [
          'text' => (string) t('Cancelar'),
          'variant' => 'secondary',
          'attributes' => new Attribute(['data-aculta-dialog-close' => '']),
        ],
        '#weight' => 51,
      ];
    }
  }

}
