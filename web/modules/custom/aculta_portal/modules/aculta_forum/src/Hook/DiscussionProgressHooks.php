<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\node\NodeInterface;

/**
 * Attaches progressive navigation to rendered shared discussions.
 */
final class DiscussionProgressHooks {

  /**
   * Remove o formulário de comentário do fim do tópico (substituído pelo botão Comentar).
   *
   * O formulário do core é um #lazy_builder, reconstruído na renderização; por isso a
   * remoção é feita no template (variável comment_form), na última etapa do pré-processamento.
   */
  #[Hook('preprocess_field', order: Order::Last)]
  public function preprocessDiscussionField(array &$variables): void {
    if (($variables['field_name'] ?? '') === 'field_aculta_discussion') {
      $variables['comment_form'] = NULL;
    }
  }

  /**
   * Marks the shared discussion field for its client-side reading controls.
   */
  #[Hook('entity_view_alter')]
  public function entityViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!$entity instanceof NodeInterface
      || ($build['#view_mode'] ?? NULL) !== 'full'
      || !$entity->hasField('field_aculta_discussion')
      || !isset($build['field_aculta_discussion'])
      || (isset($build['field_aculta_discussion']['#access']) && $build['field_aculta_discussion']['#access'] === FALSE)) {
      return;
    }

    $build['field_aculta_discussion']['#attributes']['data-aculta-discussion-thread'] = '';
    $build['field_aculta_discussion']['#attached']['library'][] = 'aculta_forum/discussion-progress';
    // Comportamento do popup de Responder/Citar e do botão Comentar (código no módulo; visual no tema).
    $build['field_aculta_discussion']['#attached']['library'][] = 'aculta_forum/discussion-reply-popup';
    // O compositor do popup é carregado por HTMX, que não leva as configurações do editor.
    // Os anexos (biblioteca e drupalSettings do CKEditor) vêm do formulário de comentário do nó,
    // renderizado em isolamento: o HTML do formulário não entra na página.
    $comment = \Drupal::entityTypeManager()->getStorage('comment')->create([
      'entity_type' => 'node',
      'entity_id' => $entity->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
    ]);
    if ($comment->access('create')) {
      $form = \Drupal::service('entity.form_builder')->getForm($comment);
      \Drupal::service('renderer')->renderInIsolation($form);
      $build['field_aculta_discussion']['#attached'] = \Drupal\Component\Utility\NestedArray::mergeDeep(
        $build['field_aculta_discussion']['#attached'] ?? [],
        $form['#attached'] ?? [],
      );
    }
  }

}
