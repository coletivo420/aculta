<?php

declare(strict_types=1);

namespace Drupal\aculta_htmx_f0_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;

/**
 * Test-only authenticated form submitted through HTMX.
 */
final class HtmxF0Form extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_htmx_f0_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if ($form_state->get('submitted')) {
      $form['result'] = [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'htmx-f0-result',
          'role' => 'status',
        ],
        'message' => [
          '#markup' => $this->t('Formulário enviado via HTMX.'),
        ],
      ];
    }
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mensagem'),
      '#required' => TRUE,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar formulário'),
    ];

    (new Htmx())
      ->post(Url::fromRoute('aculta_htmx_f0_test.form'))
      ->target('#htmx-f0-dialog-content')
      ->swap('innerHTML')
      ->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->set('submitted', TRUE);
    $form_state->setRebuild();
  }

}
