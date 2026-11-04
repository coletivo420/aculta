<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\aculta_patcher\Entity\AcultaPatch;

/**
 * Confirms enabling or disabling a Composer patch for the next build.
 */
final class AcultaPatchStatusForm extends ConfirmFormBase {

  /**
   * The patch label used by the confirmation question.
   */
  protected string $patchLabel = '';

  /**
   * Whether the form is enabling the patch.
   */
  protected bool $enableAction = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_patcher_patch_status_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?AcultaPatch $aculta_patch = NULL, ?string $patch_operation = NULL): array {
    $this->patchLabel = $aculta_patch?->label() ?? '';
    $this->enableAction = $patch_operation === 'enable';
    if ($aculta_patch === NULL || !in_array($patch_operation, ['enable', 'disable'], TRUE)) {
      $form_state->setRedirect('entity.aculta_patch.collection');
      return [];
    }
    if ($aculta_patch->status() === ($patch_operation === 'enable')) {
      $this->messenger()->addWarning($this->t('O estado deste patch já foi alterado.'));
      $form_state->setRedirect('entity.aculta_patch.collection');
      return [];
    }

    $form['patch_id'] = ['#type' => 'value', '#value' => $aculta_patch->id()];
    $form['patch_operation'] = ['#type' => 'value', '#value' => $patch_operation];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->enableAction
      ? (string) $this->t('Habilitar o patch @patch?', ['@patch' => $this->patchLabel])
      : (string) $this->t('Desabilitar o patch @patch?', ['@patch' => $this->patchLabel]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('A mudança afeta o próximo build Composer depois de exportar e revisar os artefatos.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return $this->enableAction ? (string) $this->t('Habilitar') : (string) $this->t('Desabilitar');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.aculta_patch.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $id = (string) $form_state->getValue('patch_id');
    $operation = (string) $form_state->getValue('patch_operation');
    $patch = \Drupal::entityTypeManager()->getStorage('aculta_patch')->load($id);
    if (!$patch instanceof AcultaPatch || !in_array($operation, ['enable', 'disable'], TRUE)) {
      $this->messenger()->addError($this->t('O patch não foi encontrado ou a operação é inválida.'));
      $form_state->setRedirect('entity.aculta_patch.collection');
      return;
    }

    $patch->setStatus($operation === 'enable');
    $patch->save();
    $this->messenger()->addStatus($operation === 'enable'
      ? $this->t('O patch %patch foi habilitado para o próximo build.', ['%patch' => $patch->label()])
      : $this->t('O patch %patch foi desabilitado para o próximo build.', ['%patch' => $patch->label()]));
    $form_state->setRedirect('entity.aculta_patch.collection');
  }

}
