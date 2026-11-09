<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Attaches progressive navigation to rendered shared discussions.
 */
final class DiscussionProgressHooks {

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

    // O formulário de comentário de topo sai da página: o botão Comentar abre o mesmo compositor no popup.
    // O core coloca o formulário em cada item do campo (chave numérica), em 'comment_form'.
    foreach (\Drupal\Core\Render\Element::children($build['field_aculta_discussion']) as $delta) {
      // Elemento vazio e sem acesso, não removido: o core lê a chave ao montar os itens.
      $build['field_aculta_discussion'][$delta]['comment_form'] = [];
    }
    \Drupal::logger('aculta_debug')->notice('DBG2 keys=@k deltas=@d cf=@cf', [
      '@k' => implode(',', array_slice(array_keys($build['field_aculta_discussion']), 0, 12)),
      '@d' => implode(',', \Drupal\Core\Render\Element::children($build['field_aculta_discussion'])),
      '@cf' => json_encode(array_map(static fn($d) => array_keys($build['field_aculta_discussion'][$d] ?? []), \Drupal\Core\Render\Element::children($build['field_aculta_discussion']))),
    ]);
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
