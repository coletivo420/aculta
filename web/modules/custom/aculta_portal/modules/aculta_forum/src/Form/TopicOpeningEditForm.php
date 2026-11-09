<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edit form for the opening post of a forum topic inside the popup modal.
 */
class TopicOpeningEditForm extends FormBase {

  protected ?NodeInterface $topic = NULL;

  public function getFormId(): string {
    return 'aculta_forum_topic_opening_edit_form';
  }

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

    $form['#attributes']['id'] = 'aculta-topic-edit-form-' . $node->id();

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título do tópico'),
      '#default_value' => $node->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $bodyValue = '';
    $bodyFormat = 'basic_html';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $bodyItem = $node->get('body')->first();
      $bodyValue = $bodyItem->get('value')->getValue() ?? '';
      $bodyFormat = $bodyItem->get('format')->getValue() ?? 'basic_html';
    }

    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Mensagem de abertura'),
      '#default_value' => $bodyValue,
      '#format' => $bodyFormat,
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
        'onclick' => 'const d = document.getElementById("aculta-reply-dialog"); if (d) d.close(); return false;',
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->topic instanceof NodeInterface) {
      return;
    }

    $title = (string) $form_state->getValue('title', '');
    $bodyVal = $form_state->getValue('body');

    $this->topic->setTitle($title);
    if (is_array($bodyVal) && isset($bodyVal['value'])) {
      $this->topic->get('body')->setValue([
        'value' => $bodyVal['value'],
        'format' => $bodyVal['format'] ?? 'basic_html',
      ]);
    }
    $this->topic->save();

    $this->messenger()->addStatus($this->t('Tópico atualizado com sucesso.'));
    $destination = $this->topic->toUrl()->toString();

    $response = new Response('', 200, ['HX-Redirect' => $destination]);
    $form_state->setResponse($response);
  }

}
