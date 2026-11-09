<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Rótulos e ordem do formulário de criação de tópico (Compositor de Criação de Conteúdo).
 *
 * O tópico é o único compositor com Assunto. O Assunto fica antes do Comentário.
 */
final class TopicComposerFormHooks {

  /**
   * Alters the aculta_forum_topic node form used by the topic composer.
   */
  #[Hook('form_node_aculta_forum_topic_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $formState, string $formId): void {
    if (isset($form['title'])) {
      $form['title']['#weight'] = -10;
      $form['title']['widget'][0]['value']['#title'] = t('Assunto');
    }
    if (isset($form['body'])) {
      $form['body']['#weight'] = 0;
      $form['body']['widget'][0]['#title'] = t('Comentário');
    }
  }

}
