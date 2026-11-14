<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Hook;

use Drupal\Core\Url;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\Order;
use Drupal\node\NodeInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Attaches progressive navigation to rendered shared discussions.
 */
final class DiscussionProgressHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'entity.form_builder')]
    private readonly EntityFormBuilderInterface $formBuilder,
    private readonly RendererInterface $renderer,
  ) {}

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
    // Comportamento do popup de Responder/Citar e do botão Comentar (código no módulo; visual no tema).
    $build['field_aculta_discussion']['#attached']['library'][] = 'aculta_forum/discussion-reply-popup';
    // Alternância normal/espelhado pela ordem exibida das respostas.
    $build['field_aculta_discussion']['#attached']['library'][] = 'aculta_forum/discussion-side';
    // Janela única da página (SDC popup) para Responder, Citar, Editar e login do visitante.
    // O conteúdo chega por HTMX ao corpo #aculta-reply-dialog-body; o JS só a abre.
    $build['aculta_reply_popup'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:popup',
      '#props' => [
        'id' => 'aculta-reply-dialog',
        'title' => (string) t('Responder à discussão'),
        'trigger_label' => (string) t('Responder'),
        'trigger_url' => Url::fromRoute('aculta_forum.discussion_composer', [
          'entity_type' => 'node',
          'entity_id' => $entity->id(),
        ])->toString(),
        'close_label' => (string) t('Fechar'),
      ],
      '#slots' => [
        'content' => [
          ['#type' => 'component', '#component' => 'aculta420_v2:posting-overlay', '#props' => ['title' => (string) t('Aguarde'), 'text' => (string) t('Postando seu comentário…')]],
          ['#markup' => '<div id="aculta-reply-dialog-body" class="aculta-reply-dialog__body"></div>'],
        ],
      ],
    ];
    // O compositor do popup é carregado por HTMX, que não leva as configurações do editor.
    // Os anexos (biblioteca e drupalSettings do CKEditor) vêm do formulário de comentário do nó,
    // renderizado em isolamento: o HTML do formulário não entra na página.
    $comment = $this->entityTypeManager->getStorage('comment')->create([
      'entity_type' => 'node',
      'entity_id' => $entity->id(),
      'field_name' => 'field_aculta_discussion',
      'comment_type' => 'aculta_discussion_post',
    ]);
    if ($comment->access('create')) {
      $form = $this->formBuilder->getForm($comment);
      $this->renderer->renderInIsolation($form);
      $build['field_aculta_discussion']['#attached'] = \Drupal\Component\Utility\NestedArray::mergeDeep(
        $build['field_aculta_discussion']['#attached'] ?? [],
        $form['#attached'] ?? [],
      );
    }
  }

}
