<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edit form for the opening post of a forum topic inside the popup modal.
 */
class TopicOpeningEditForm extends FormBase {

  /**
   * The topic whose opening ForumPost is being edited.
   */
  protected ?NodeInterface $topic = NULL;

  /**
   * Returns the unique form ID.
   */
  public function getFormId(): string {
    return 'aculta_forum_topic_opening_edit_form';
  }

  /**
   * Builds the form.
   *
   * @param array<string, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   * @param ?NodeInterface $node
   *   Value used for $node.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $this->topic = $node;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'aculta_forum_topic') {
      $form['message'] = [
        '#markup' => '<div class="alert alert--danger">' . $this->t('Tópico inválido ou não encontrado.') . '</div>',
      ];
      return $form;
    }

    if (!$node->access('update', $this->currentUser())) {
      $form['message'] = [
        '#markup' => '<div class="alert alert--warning">' . $this->t('Você não tem permissão para editar este tópico.') . '</div>',
      ];
      return $form;
    }

    $targetUrl = Url::fromRoute('aculta_forum.discussion_composer', [
      'entity_type' => 'node',
      'entity_id' => $node->id(),
    ], [
      'query' => ['editar_topico' => 1],
    ]);

    $form['#action'] = $targetUrl->toString();
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['id'] = 'aculta-topic-edit-form-' . $node->id();

    $htmx = new Htmx();
    $htmx->post($targetUrl)
      ->target('#aculta-reply-dialog-body')
      ->swap('innerHTML')
      ->onlyMainContent()
      ->applyTo($form);
    if (!isset($form['#attributes']) || !is_array($form['#attributes'])) {
      $form['#attributes'] = [];
    }
    $form['#attributes']['data-hx-select'] = '[data-discussion-reply-composer]';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título do tópico'),
      '#default_value' => $node->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $bodyValue = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $bodyItem = $node->get('body')->first();
      if ($bodyItem !== NULL) {
        $rawBody = $bodyItem->get('value')->getValue();
        $bodyValue = is_scalar($rawBody) ? (string) $rawBody : '';
      }
    }

    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Mensagem de abertura'),
      '#default_value' => $bodyValue,
      '#format' => 'forum',
      '#allowed_formats' => ['forum'],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar alterações'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancelar'),
      '#attributes' => [
        'data-portal-ui' => 'button-secondary',
        'data-aculta-dialog-close' => '',
      ],
    ];

    /**
   * @var array<string, mixed> $form */
    return $form;
  }

  /**
   * Submits the form.
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->topic instanceof NodeInterface) {
      return;
    }

    $title_val = $form_state->getValue('title', '');
    $title = is_scalar($title_val) ? (string) $title_val : '';
    $bodyVal = $form_state->getValue('body');

    $this->topic->setTitle($title);
    if (is_array($bodyVal) && isset($bodyVal['value'])) {
      $this->topic->get('body')->setValue([
        'value' => $bodyVal['value'],
        'format' => $bodyVal['format'] ?? 'forum',
      ]);
    }
    $this->topic->save();

    $this->messenger()->addStatus($this->t('Tópico atualizado com sucesso.'));
    $destination = $this->topic->toUrl()->toString();

    $response = new Response('', 200, ['HX-Redirect' => $destination]);
    $form_state->setResponse($response);
  }

}
