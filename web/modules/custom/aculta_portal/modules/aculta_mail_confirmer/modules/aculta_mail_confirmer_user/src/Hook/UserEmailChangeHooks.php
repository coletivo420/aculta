<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer_user\Hook;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;

/**
 * Troca de e-mail da conta: intercepta o salvamento e conclui a confirmação.
 */
final class UserEmailChangeHooks {

  /**
   * Creates the hooks.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface $manager
   *   The email confirmation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountProxyInterface $currentUser,
    private readonly UserDataInterface $userData,
    private readonly AcultaMailConfirmerManagerInterface $manager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for users.
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $entity): void {
    // Do nothing if no user email update, currently in a confirmer operation or
    // email change confirmation is disabled.
    $original = $entity->getOriginal();
    if (!$original
      || drupal_static('aculta_mail_confirmer_user_' . $entity->id())
      || ($new_email = $entity->getEmail()) == ($old_email = $original->getEmail())
      || !($config = $this->configFactory->get('aculta_mail_confirmer_user.settings')->get('user_email_change'))
      || !$config['enabled']) {
      return;
    }

    // Accept email change when updated by an admin user.
    if ($this->currentUser->hasPermission('aculta mail confirmer user bypass email change')) {
      // Remove any pending email change confirmation on this user.
      $this->userData->delete('aculta_mail_confirmer_user', $entity->id(), 'email_change_new_address');
      return;
    }

    // Check for previous confirmations for the new email address.
    if ($config['consider_existent']
      && !empty($confirmations = $this->manager->getConfirmations($new_email, 'confirmed', 0, $config['limit_user_realm'] ? 'aculta_mail_confirmer_user' : ''))) {
      // Limit to user's own confirmations.
      /** @var \Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface $confirmation */
      foreach ($confirmations as $confirmation) {
        if ($confirmation->uid->target_id == $entity->id()) {
          return;
        }
      }
    }

    // Save the new email on user data space.
    $this->userData->set('aculta_mail_confirmer_user', $entity->id(), 'email_change_new_address', $new_email);
    // Restore original email address.
    $entity->setEmail($old_email);
    // Launch the confirmation for the new email address.
    $confirmation = $this->manager->createConfirmation($new_email);
    $confirmation->setRealm('aculta_mail_confirmer_user')
      ->setProperty('user', $entity->id())
      ->setPrivate()
      ->setResponseUrl($entity->toUrl('edit-form'), 'confirm');
    $this->manager->sendRequest($confirmation);
    $confirmation->save();
    $this->messenger->addStatus(t(
      'A message has been sent to the new email address %email to confirm the change. Until confirmed, your current address will be used.',
      ['%email' => $new_email]));

    // Send notification to the current email address.
    if ($config['notify_current']) {
      $this->mailManager->mail('aculta_mail_confirmer_user',
        'notify_current',
        $old_email,
        $this->languageManager->getDefaultLanguage()->getId(),
        ['context' => ['aculta_mail_confirmer' => $confirmation, 'user' => $entity]]);
    }
  }

  /**
   * Implements hook_aculta_mail_confirmer().
   */
  #[Hook('aculta_mail_confirmer')]
  public function confirmationOperation(string $op, AcultaMailConfirmationInterface $confirmation): void {
    $user_storage = $this->entityTypeManager->getStorage('user');
    if ($confirmation->getRealm() == 'aculta_mail_confirmer_user'
      && ($config = $this->configFactory->get('aculta_mail_confirmer_user.settings')->get('user_email_change'))
      && $config['enabled']
      && ($user_id = $confirmation->getProperty('user'))
      && ($new_email = $this->userData->get('aculta_mail_confirmer_user', $user_id, 'email_change_new_address'))
      && $new_email == $confirmation->getEmail()
      && ($user = $user_storage->load($user_id))) {
      // Delete the requested new email from user data.
      $this->userData->delete('aculta_mail_confirmer_user', $user_id, 'email_change_new_address');

      switch ($op) {
        case 'confirm':
          // User's email address must be unique on the site.
          if (!empty($user_storage->loadByProperties(['mail' => $new_email]))) {
            $this->messenger->addError(t(
              'The email address %value is already taken.',
              ['%value' => $new_email]));
            break;
          }

          $user->setEmail($new_email);
          // Prevents from cycling email change confirmation. The static must be
          // set by reference: drupal_static() with a default does not overwrite
          // a key that presave already initialized to NULL in this request.
          $confirmation_guard =& drupal_static('aculta_mail_confirmer_user_' . $user_id);
          $confirmation_guard = TRUE;
          $user->save();
          $this->messenger->addStatus($this->currentUser->id() == $user_id
            ? t('Your email address has been updated to %mail', ['%mail' => $new_email])
            : t("%user's email address has been updated to %mail", ['%user' => $user->getDisplayName(), '%mail' => $new_email]));
          break;

        case 'cancel':
          $this->loggerFactory->get('aculta_mail_confirmer_user')->warning('Requested user email change for %user has been rejected by the %mail email address owner.', ['%user' => $user->getDisplayName(), '%mail' => $new_email]);
          break;
      }
    }
  }

}
