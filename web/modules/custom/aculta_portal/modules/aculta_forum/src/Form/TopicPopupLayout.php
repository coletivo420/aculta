<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Template\Attribute;

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
  private const KEEP = ['title', 'body', 'field_aculta_categories', 'field_aculta_tags', 'poll_builder', 'actions', 'form_id', 'form_build_id', 'form_token'];

  /**
   * Aplica o layout do popup ao formulário do nó aculta_forum_topic.
   *
   * @param array<mixed> $form
   *   Form render array.
   */
  public static function apply(array &$form): void {
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && !in_array($key, self::KEEP, TRUE)) {
        unset($form[$key]);
      }
    }
    if (isset($form['body']) && is_array($form['body'])) {
      $body = &$form['body'];
      if (isset($body['widget']) && is_array($body['widget'])) {
        $widget = &$body['widget'];
        if (isset($widget[0]) && is_array($widget[0])) {
          unset($widget[0]['summary']);
          if (isset($widget[0]['format']) && is_array($widget[0]['format'])) {
            $formatElement = &$widget[0]['format'];
            if (isset($formatElement['format']) && is_array($formatElement['format'])) {
              $formatElement['format']['#type'] = 'hidden';
              $formatElement['format']['#value'] = 'forum';
              unset($formatElement['guidelines'], $formatElement['help']);
            }
          }
        }
      }
    }
    // Moldura real: o SDC discussion-topic-composer envolve o formulário sem mover campos.
    $form['#theme_wrappers'] = ['discussion_topic_composer'];

    // Diretrizes do Compositor de Criação de Tópico (SDC forum-composer-guidelines).
    $form['forum_composer_guidelines'] = [
      '#theme' => 'portal_component',
      '#name' => 'forum-composer-guidelines',
      '#props' => [
        'collapsed' => TRUE,
        'tips' => [
          (string) t('Use um título que resuma a dúvida ou o assunto.'),
          (string) t('Cite a fonte quando citar pesquisas ou experiências de terceiros.'),
          (string) t('Respeite as regras da comunidade antes de publicar.'),
        ],
        'community_rules' => NULL,
      ],
      '#weight' => -10,
    ];
    if (isset($form['actions']) && is_array($form['actions'])) {
      // Sem pré-visualização. "Cancelar" (secundário) fecha o popup.
      unset($form['actions']['preview']);
      $form['actions']['cancel'] = [
        '#theme' => 'portal_component',
        '#name' => 'button',
        '#props' => [
          'text' => (string) t('Cancelar'),
          'variant' => 'secondary',
          'attributes' => new Attribute(['data-aculta-dialog-close' => '']),
        ],
        '#weight' => 50,
      ];
    }
  }

}
