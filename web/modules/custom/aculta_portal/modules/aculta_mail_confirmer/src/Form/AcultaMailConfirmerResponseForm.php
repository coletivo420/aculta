<?php

namespace Drupal\aculta_mail_confirmer\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\aculta_mail_confirmer\InvalidConfirmationStateException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Email confirmation response form.
 */
class AcultaMailConfirmerResponseForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->config('aculta_mail_confirmer.settings')
      ->get('confirmation_response.questions.' . $this->entity->getStatus());
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    /** @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation */
    $confirmation = $this->getEntity();
    return $confirmation->isPending() ? $this->t('Send') : $this->t('OK');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormName() {
    return $this->getFormId();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aculta_mail_confirmer_response';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Skip the response form and positively confirm the confirmation according
    // to the settings.
    if ($this->config('aculta_mail_confirmer.settings')
      ->get('confirmation_response.skip_confirmation_form')) {
      return $this->skipConfirmationForm($form_state);
    }

    // Build the response form.
    $form = parent::buildForm($form, $form_state);
    unset($form['#process']);
    unset($form['#after_build']);
    // No cancel option needed.
    unset($form['actions']['cancel']);

    if ($this->getEntity()->isPending()) {
      $form['cancel'] = [
        '#type' => 'radios',
        '#default_value' => 0,
        '#options' => [
          0 => $this->t('Confirm'),
          1 => $this->t('Cancel'),
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    /** @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation */
    $confirmation = $this->getEntity();
    $question = '';

    switch ($confirmation->getStatus()) {
      case 'pending':
        // @todo obfuscate email address if no administer permission, user is anonymous or not owner
        $question = $this->t('Confirm %email', ['%email' => $this->entity->label()]);
        break;

      case 'expired':
        if ($confirmation->isConfirmed() || $confirmation->isCancelled()) {
          $question = $this->t('Already processed');
        }
        else {
          $question = $this->t('Confirmation expired');
        }
        break;

      case 'cancelled':
        $question = $this->t('Confirmation cancelled');
        break;

      case 'confirmed':
        $question = $this->t('Confirmation done');
        break;
    }

    return $question;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing if it is not a real response.
    if (!$this->getEntity()->isPending()) {
      $form_state->setRedirectUrl($this->getRedirectUrl('error'));
      return;
    }

    try {
      if ($form_state->getValue('cancel')) {
        // Explicitly cancelled.
        $this->cancelConfirmation($form_state);
      }
      else {
        // Confirmed.
        $this->confirmConfirmation($form_state);
      }
    }

    catch (InvalidConfirmationStateException $exception) {
      $this->confirmationError($form_state);
    }
  }

  /**
   * Cancels the email confirmation.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\aculta_mail_confirmer\InvalidConfirmationStateException
   *   If confirmation is expired, confirmed or already cancelled.
   */
  protected function cancelConfirmation(FormStateInterface $form_state) {
    /** @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation */
    $confirmation = $this->getEntity();
    $confirmation->cancel();
    $this->messenger()->addStatus($this->t('Email confirmation cancelled.'));
    $confirmation->save();
    $form_state->setRedirectUrl($this->getRedirectUrl('cancel'));
  }

  /**
   * Confirms the email confirmation.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @throws \Drupal\aculta_mail_confirmer\InvalidConfirmationStateException
   *   If confirmation is cancelled, expired or already confirmed.
   */
  protected function confirmConfirmation(FormStateInterface $form_state) {
    /** @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation */
    $confirmation = $this->getEntity();
    if (!$confirmation->confirm($this->getRouteMatch()->getParameter('hash'))) {
      $this->confirmationError($form_state);
      return;
    }

    $this->messenger()->addStatus($this->t('Email confirmation confirmed.'));
    $confirmation->save();
    $form_state->setRedirectUrl($this->getRedirectUrl('confirm'));
  }

  /**
   * Common behaviour when there ware errors in confirmation.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function confirmationError(FormStateInterface $form_state) {
    $this->messenger()->addError($this->t('There was an error processing your email confirmation.'), 'error');
    $form_state->setRedirectUrl($this->getRedirectUrl('error'));
  }

  /**
   * Returns the URL to redirect to after the form is completed or skipped.
   *
   * @param string $operation
   *   Possible values:
   *   - confirm
   *   - cancel
   *   - error
   *   Defines the page that will be redirected to.
   *
   * @return \Drupal\Core\Url
   *   The url to redirect to.
   */
  protected function getRedirectUrl($operation) {
    /** @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation */
    $confirmation = $this->getEntity();
    // Go to confirmation response URL, response path from settings or front.
    if (!$url = $confirmation->getResponseUrl($operation)) {
      $path = $this->config('aculta_mail_confirmer.settings')
        ->get('confirmation_response.url.' . $operation) ?: '<front>';
      $url = Url::fromUri('internal:/' . $path);
    }

    return $url;
  }

  /**
   * Skip the response form and positively confirm the confirmation.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirection response according to the confirmation result.
   */
  protected function skipConfirmationForm(FormStateInterface $form_state) {
    try {
      $this->confirmConfirmation($form_state);
    }

    catch (InvalidConfirmationStateException $exception) {
      $this->confirmationError($form_state);
    }

    // Use RedirectResponse because redirecting with form state is not working
    // inside buildForm.
    $redirect_state = $form_state->getRedirect()
      ?: $this->getRedirectUrl('error');
    if ($redirect_state instanceof RedirectResponse) {
      $redirect_response = $redirect_state;
    }
    else {
      $redirect_response = new RedirectResponse($redirect_state instanceof Url
        ? $redirect_state->setAbsolute()->toString()
        : $redirect_state);
    }
    return $redirect_response;
  }

}
