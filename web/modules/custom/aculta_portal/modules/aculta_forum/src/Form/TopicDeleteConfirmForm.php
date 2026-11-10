<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Service\TopicDeletionModerationManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirmation form for deleting a forum topic within the reply popup modal.
 */
class TopicDeleteConfirmForm extends FormBase {

  protected ?NodeInterface $topic = NULL;

  public function __construct(
    protected readonly TopicDeletionModerationManager $moderationManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_forum.topic_deletion_moderation_manager')
    );
  }

  public function getFormId(): string {
    return 'aculta_forum_topic_delete_confirm_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $this->topic = $node;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'aculta_forum_topic') {
      $form['message'] = [
        '#markup' => '<div class="alert alert--danger">' . $this->t('Tópico inválido ou não encontrado.') . '</div>',
      ];
      return $form;
    }

    if (!$this->moderationManager->canDeleteOrRequest($node, $this->currentUser())) {
      $form['message'] = [
        '#markup' => '<div class="alert alert--warning">' . $this->t('Você não tem permissão para excluir este tópico.') . '</div>',
      ];
      return $form;
    }

    $targetUrl = Url::fromRoute('aculta_forum.discussion_composer', [
      'entity_type' => 'node',
      'entity_id' => $node->id(),
    ], [
      'query' => ['excluir_topico' => 1],
    ]);

    $form['#action'] = $targetUrl->toString();
    $form['#attributes']['class'][] = 'aculta-topic-delete-confirm';
    $form['#attributes']['id'] = 'aculta-topic-delete-form-' . $node->id();

    $htmx = new Htmx();
    $htmx->post($targetUrl)
      ->target('#aculta-reply-dialog-body')
      ->swap('innerHTML')
      ->onlyMainContent()
      ->applyTo($form);
    $form['#attributes']['data-hx-select'] = '[data-discussion-reply-composer]';

    $form['prompt'] = [
      '#markup' => '<div class="discussion-reply-target__warning" style="margin-block: var(--spacing-md); text-align: center;">'
        . '<h3 style="margin-block-end: var(--spacing-sm); color: var(--color-danger, #d32f2f); font-size: 1.25rem;">'
        . $this->t('Confirma a Exclusão do tópico')
        . '</h3>'
        . '<p style="color: var(--color-text-secondary);">'
        . $this->t('Tem certeza de que deseja excluir o tópico «@title»? Esta ação removerá a publicação e suas respostas.', ['@title' => $node->label()])
        . '</p>'
        . '</div>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['style' => 'display: flex; gap: var(--spacing-sm); justify-content: center; margin-block-start: var(--spacing-md);'],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirmar exclusão'),
      '#button_type' => 'danger',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancelar'),
      '#attributes' => [
        'data-portal-ui' => 'button-secondary',
        'data-aculta-dialog-close' => '',
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->topic instanceof NodeInterface) {
      return;
    }

    $result = $this->moderationManager->processDeletion($this->topic, $this->currentUser());
    $redirectUrl = Url::fromRoute('<front>')->toString();

    if ($result === 'deleted') {
      $this->messenger()->addStatus($this->t('O tópico foi excluído com sucesso.'));
    }
    else {
      $this->messenger()->addStatus($this->t('A solicitação de exclusão do tópico foi enviada para a fila de moderação.'));
    }

    $response = new Response('', 200, ['HX-Redirect' => $redirectUrl]);
    $form_state->setResponse($response);
  }

}
