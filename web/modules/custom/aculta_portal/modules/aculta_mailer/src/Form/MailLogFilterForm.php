<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Form;

use Drupal\aculta_mailer\Message\MailStatus;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Filtros da lista de envios: tipo, estado, transporte e período. Envio por GET, sem token, para a própria página.
 */
final class MailLogFilterForm extends FormBase {

  /**
   * Períodos oferecidos, em dias.
   */
  public const PERIODS = [1 => 'Último dia', 7 => 'Últimos 7 dias', 30 => 'Últimos 30 dias', 90 => 'Últimos 90 dias'];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_mailer_log_filter_form';
  }

  /**
   * Campos dos filtros.
   *
   * @param array $form
   *   Formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado do formulário.
   * @param array $defaults
   *   Valores atuais dos filtros (tipo, estado, transporte e dias).
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $defaults = []): array {
    $form['#method'] = 'get';
    $form['#token'] = FALSE;
    $form['#action'] = Url::fromRoute('aculta_mailer.log_list')->toString();
    $form['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tipo'),
      '#default_value' => $defaults['type'] ?? '',
      '#maxlength' => 64,
      '#size' => 24,
    ];
    $statusOptions = ['' => $this->t('Todos')];
    foreach (MailStatus::cases() as $case) {
      $statusOptions[$case->value] = $case->value;
    }
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Estado'),
      '#options' => $statusOptions,
      '#default_value' => $defaults['status'] ?? '',
    ];
    $form['transport'] = [
      '#type' => 'select',
      '#title' => $this->t('Transporte'),
      '#options' => ['' => $this->t('Todos'), 'smtp' => 'smtp', 'smtp2go' => 'smtp2go', 'brevo' => 'brevo'],
      '#default_value' => $defaults['transport'] ?? '',
    ];
    $form['days'] = [
      '#type' => 'select',
      '#title' => $this->t('Período'),
      '#options' => self::PERIODS,
      '#default_value' => $defaults['days'] ?? 7,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Filtrar')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Envio por GET: a própria página lê os valores da URL.
  }

}
