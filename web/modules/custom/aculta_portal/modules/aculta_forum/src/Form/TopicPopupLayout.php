<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

/**
 * Layout do popup de criação de tópico (Compositor de Criação de Tópico).
 *
 * Usado pelo hook do formulário no popup e pela story do SDC na UI Library, para que a
 * biblioteca mostre exatamente o formulário que abre no fórum.
 */
final class TopicPopupLayout {

  /**
   * Campos do nó que o popup mantém. Menu, autor, revisão e abas saem.
   */
  private const KEEP = ['title', 'body', 'field_aculta_categories', 'actions', 'form_id', 'form_build_id', 'form_token'];

  /**
   * Aplica o layout do popup ao formulário do nó aculta_forum_topic.
   */
  public static function apply(array &$form): void {
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && !in_array($key, self::KEEP, TRUE)) {
        unset($form[$key]);
      }
    }
    unset($form['body']['widget'][0]['summary']);
    // Sem pré-visualização. "Cancelar" (secundário) fecha o popup.
    unset($form['actions']['preview']);
    $form['actions']['cancel'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => t('Cancelar'),
      '#attributes' => [
        'type' => 'button',
        'class' => ['button', 'button--secondary'],
        'data-aculta-dialog-close' => '',
      ],
      '#weight' => 50,
    ];
    // O corpo do tópico só aceita o formato do fórum: o seletor sai e o formato fica oculto.
    if (isset($form['body']['widget'][0]['format']['format'])) {
      $formatElement = &$form['body']['widget'][0]['format'];
      $formatElement['format']['#type'] = 'hidden';
      $formatElement['format']['#value'] = 'forum';
      unset($formatElement['guidelines'], $formatElement['help']);
    }
  }

}
