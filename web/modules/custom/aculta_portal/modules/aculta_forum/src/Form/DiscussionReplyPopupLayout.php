<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

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
   */
  public static function apply(array &$form, bool $isNew): void {
    // Resposta e edição não têm Assunto nem a marca "Rejeitado".
    unset($form['subject'], $form['field_aculta_rejected']);
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && !in_array($key, self::KEEP, TRUE)) {
        unset($form[$key]);
      }
    }

    // Sem pré-visualização; "Publicar" ou "Salvar", conforme o caso.
    unset($form['actions']['preview']);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = $isNew ? t('Publicar comentário') : t('Salvar alterações');
      $form['actions']['submit']['#button_type'] = 'primary';
    }
    // "Limpar" zera o formulário (reset nativo, sem JavaScript).
    $form['actions']['clear'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => t('Limpar'),
      '#attributes' => [
        'type' => 'button',
        'class' => ['button', 'button--secondary'],
        'data-aculta-reply-clear' => '',
      ],
      '#weight' => 50,
    ];
    // "Cancelar" (secundário, como Limpar) fecha o popup.
    $form['actions']['cancel'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => t('Cancelar'),
      '#attributes' => [
        'type' => 'button',
        'class' => ['button', 'button--secondary'],
        'data-aculta-dialog-close' => '',
      ],
      '#weight' => 51,
    ];
  }

}
