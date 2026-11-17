<?php

declare(strict_types=1);

namespace Drupal\aculta_donation\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Formulário de campanha de doação.
 */
final class DonationCampaignForm extends EntityForm {

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   * @return array<mixed>
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = (array) parent::form($form, $form_state);
    /** @var \Drupal\aculta_donation\Entity\DonationCampaign $campanha */
    $campanha = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome da campanha'),
      '#default_value' => $campanha->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Identificador'),
      '#default_value' => $campanha->id(),
      '#machine_name' => ['exists' => [$this, 'exists'], 'source' => ['label']],
      '#disabled' => !$campanha->isNew(),
    ];
    $form['start'] = [
      '#type' => 'date',
      '#title' => $this->t('Início'),
      '#default_value' => $campanha->getStart() ?: NULL,
      '#required' => TRUE,
    ];
    $form['end'] = [
      '#type' => 'date',
      '#title' => $this->t('Término (inclusive)'),
      '#default_value' => $campanha->getEnd() ?: NULL,
      '#required' => TRUE,
    ];
    $form['amounts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Valores sugeridos (em reais)'),
      '#description' => $this->t('Separados por vírgula, por exemplo: 10, 20, 50, 100.'),
      '#default_value' => implode(', ', $campanha->getAmounts()),
      '#required' => TRUE,
    ];
    $form['customAmount'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permitir valor personalizado'),
      '#default_value' => $campanha->allowsCustomAmount(),
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Campanha ativa'),
      '#default_value' => $campanha->status(),
    ];
    return $form;
  }

  /**
   * Verifica se já existe uma campanha com o identificador informado.
   *
   * @param string $id
   *   Identificador da campanha.
   *
   * @return bool
   *   TRUE se a campanha existir.
   */
  public function exists(string $id): bool {
    return (bool) $this->entityTypeManager->getStorage('aculta_donation_campaign')->load($id);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $rawInicio = $form_state->getValue('start');
    $inicio = is_scalar($rawInicio) ? (string) $rawInicio : '';
    $rawFim = $form_state->getValue('end');
    $fim = is_scalar($rawFim) ? (string) $rawFim : '';
    if ($inicio !== '' && $fim !== '' && $fim < $inicio) {
      $form_state->setErrorByName('end', $this->t('O término não pode ser anterior ao início.'));
    }
    $rawAmounts = $form_state->getValue('amounts');
    $amountsStr = is_scalar($rawAmounts) ? (string) $rawAmounts : '';
    $valores = array_filter(array_map('trim', explode(',', $amountsStr)), static fn ($v) => $v !== '');
    $inteiros = array_filter($valores, static fn ($v) => ctype_digit($v) && (int) $v > 0);
    if ($valores === [] || count($inteiros) !== count($valores)) {
      $form_state->setErrorByName('amounts', $this->t('Informe valores inteiros maiores que zero, separados por vírgula.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $form
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_donation\Entity\DonationCampaign $campanha */
    $campanha = $this->entity;
    $rawAmounts = $form_state->getValue('amounts');
    $amountsStr = is_scalar($rawAmounts) ? (string) $rawAmounts : '';
    $valores = array_map('intval', array_filter(array_map('trim', explode(',', $amountsStr))));
    $rawStart = $form_state->getValue('start');
    $rawEnd = $form_state->getValue('end');
    $startStr = is_scalar($rawStart) ? (string) $rawStart : '';
    $endStr = is_scalar($rawEnd) ? (string) $rawEnd : '';
    $campanha
      ->setStart($startStr)
      ->setEnd($endStr)
      ->setAmounts($valores)
      ->setCustomAmount((bool) $form_state->getValue('customAmount'))
      ->set('status', (bool) $form_state->getValue('status'));
    $resultado = $campanha->save();
    $this->messenger()->addStatus($this->t('Campanha @nome salva.', ['@nome' => (string) $campanha->label()]));
    $form_state->setRedirectUrl($campanha->toUrl('collection'));
    return $resultado;
  }

}
