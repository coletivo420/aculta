<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer_user\Hook;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\user\UserDataInterface;

/**
 * Formulário da conta: avisa sobre a troca de e-mail pendente.
 */
final class UserEmailChangeFormHooks {

  /**
   * Creates the hooks.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface $manager
   *   The email confirmation service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $destination
   *   The redirect destination.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UserDataInterface $userData,
    private readonly AcultaMailConfirmerManagerInterface $manager,
    private readonly RedirectDestinationInterface $destination,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter() for the user form.
   */
  #[Hook('form_user_form_alter')]
  public function userFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $config = $this->configFactory->get('aculta_mail_confirmer_user.settings')->get('user_email_change');
    /** @var \Drupal\user\UserInterface $user */
    $user = $form_state->getFormObject()->getEntity();

    // Nothing to do with new accounts.
    if ($user->isNew()) {
      return;
    }

    // Tell the user about email changes pending confirmation.
    if ($config['enabled']
      && ($new_email = $this->userData->get('aculta_mail_confirmer_user', $user->id(), 'email_change_new_address'))
      && !empty($confirmations = $this->manager->getConfirmations($new_email, 'pending', 0, 'aculta_mail_confirmer_user'))) {
      foreach ($confirmations as $confirmation) {
        if ($confirmation->uid->target_id == $user->id()) {
          $confirmation_url = Url::fromRoute('entity.aculta_mail_confirmer.resend', ['confirmation' => $confirmation->id()], ['query' => $this->destination->getAsArray()])->toString();
          $form['account']['mail']['#description'] = t(
            '<strong>An update of your email address to %mail is pending of confirmation</strong>. <a href=":resend_url">Resend confirmation email</a> or <a href=":cancel_url">cancel the pending change</a>.',
            [
              '%mail' => $new_email,
              ':resend_url' => $confirmation_url,
              ':cancel_url' => Url::fromRoute('aculta_mail_confirmer_user.cancel_email_change', ['user' => $user->id()])->toString(),
            ]
          ) . ' ' . $form['account']['mail']['#description'];
          break;
        }
      }
    }
  }

}
