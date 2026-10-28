<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Email\EmailConfirmationRequestResult;
use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_portal\Account\RegistrationMailPolicy;
use Drupal\aculta_portal\Account\AcultaUserAgreementRegistration;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Email\EmailConfirmationRequester;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\Form\UserLoginForm;
use Drupal\user\UserAuthenticationInterface;
use Drupal\user\UserFloodControlInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Dependency-injected callbacks used by altered Core/contrib forms.
 */
final class PortalFormCallbacks {

  public function __construct(
    private readonly MessengerInterface $messenger,
    private readonly AccountProxyInterface $currentUser,
    private readonly UserDataInterface $userData,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TranslationInterface $translation,
    private readonly UserAuthenticationInterface $userAuth,
    private readonly UserFloodControlInterface $floodControl,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EmailConfirmationRequester $confirmationRequester,
    private readonly AcultaUserAgreementRegistration $userAgreementRegistration,
    private readonly RegistrationMailPolicy $registrationMail,
    private readonly ?RouteMatchInterface $routeMatch = NULL,
    private readonly ?DomainPurposeManager $domainPurpose = NULL,
  ) {}

  /**
   * Returns the same registration message for both address types (DT-P06).
   */
  private function registrationStatusMessage(): string {
    return (string) $this->translation->translate('Sua solicitação foi recebida. Se este endereço tiver uma confirmação ou aviso disponível, verifique a caixa de entrada e a pasta de spam. Se nenhuma mensagem chegar, tente novamente mais tarde.');
  }

  /**
   * Combines street and number before saving the profile, as in checkout.
   *
   * This entity builder runs before the profile is saved.
   */
  public function buildStreetNumber(string $entityTypeId, $profile, array &$form, FormStateInterface $formState): void {
    if (!$profile instanceof ProfileInterface || $profile->get('field_address')->isEmpty()) {
      return;
    }
    AccountAddressForm::buildStreetNumber($entityTypeId, $profile, $form, $formState);
  }

  /**
   * Returns the messages and account form in the same AJAX response.
   */
  public function accountAjaxResponse(array &$form, FormStateInterface $formState): array {
    unset($form['#prefix'], $form['#suffix']);
    return ['messages' => ['#type' => 'status_messages'], 'form' => $form];
  }

  /**
   * Keeps account saves on the same page.
   */
  public function disableAccountRedirect(array &$form, FormStateInterface $formState): void {
    $formState->disableRedirect();
  }

  /**
   * Records acceptance of the required registration terms.
   */
  public function recordAcultaUserAgreementAcceptance(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $account = $formState->get('user') ?? $formState->getFormObject()->getEntity();
    if ($account instanceof UserInterface) {
      $this->userAgreementRegistration->record($account);
    }
  }

  /**
   * Sends the confirmation email for a newly created account.
   */
  public function requestRegistrationConfirmation(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $account = $formState->get('user') ?? $formState->getFormObject()->getEntity();
    if ($account instanceof UserInterface) {
      $result = $this->confirmationRequester->request($account);
      if ($result !== EmailConfirmationRequestResult::ACCEPTED) {
        // Keep the registration response identical for unknown and existing
        // addresses; its wording does not claim that an email was delivered.
        return;
      }
    }
  }

  /**
   * Authenticates the login form without disclosing blocked accounts.
   *
   * Core reports a blocked or unactivated account with its own message before
   * any password check. Here a blocked account is recognized first and leaves
   * the authentication step, so validateFinal() gives the generic message used
   * for unknown accounts. The IP flood check matches Core's condition and
   * outcome, so its response cannot reveal whether an account exists.
   * Every other account goes through Core's validateAuthentication().
   */
  public function validateLoginAuthentication(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof UserLoginForm) {
      // Never authenticate through an unexpected form object.
      return;
    }

    $password = trim((string) $formState->getValue('pass'));
    $account = FALSE;
    if (!$formState->isValueEmpty('name') && strlen($password) > 0) {
      $floodConfig = $this->configFactory->get('user.flood');
      if (!$this->floodControl->isAllowed('user.failed_login_ip', $floodConfig->get('ip_limit'), $floodConfig->get('ip_window'))) {
        $formState->set('flood_control_triggered', 'ip');
        return;
      }
      $account = $this->userAuth->lookupAccount($formState->getValue('name'));
    }

    if ($account instanceof UserInterface && $account->isBlocked()) {
      return;
    }
    $formObject->validateAuthentication($form, $formState);
  }

  /**
   * Validates the registration password and stores it for the save handler.
   */
  public function validateRegistrationPassword(array &$form, FormStateInterface $formState): void {
    $pass = (string) $formState->getValue('pass');
    if (mb_strlen($pass) < 8) {
      $formState->setErrorByName('pass', $this->translation->translate('A senha precisa ter pelo menos 8 caracteres.'));
      return;
    }
    $formState->set('aculta_register_pass', $pass);
  }

  /**
   * Keeps registration responses the same when an email is already used.
   *
   * The existing account receives a notice; no account is created again.
   * Other form errors are preserved.
   */
  public function validateRegistrationMail(array &$form, FormStateInterface $formState): void {
    $existing = $this->registrationMail->existingAccount((string) $formState->getValue('mail'));
    if ($existing === NULL) {
      return;
    }
    $remaining = [];
    foreach ($formState->getErrors() as $name => $message) {
      if ($name !== 'mail') {
        $remaining[$name] = $message;
      }
    }
    $formState->clearErrors();
    foreach ($remaining as $name => $message) {
      $formState->setErrorByName($name, $message);
    }
    $formState->set('aculta_registration_existing', (int) $existing->id());
  }

  /**
   * Saves a registration, replacing Core's submit handler.
   *
   * Existing accounts are not created again; both paths return the same
   * message and destination.
   */
  public function saveRegistration(array $form, FormStateInterface $formState): void {
    $existingId = $formState->get('aculta_registration_existing');
    if ($existingId !== NULL) {
      $existing = $this->entityTypeManager->getStorage('user')->load((int) $existingId);
      if ($existing instanceof UserInterface) {
        $this->registrationMail->notifyExistingAccount($existing);
      }
    }
    else {
      $formState->getFormObject()->save($form, $formState);
      // Replace Core's welcome message with the generic response.
      $this->messenger->deleteByType(MessengerInterface::TYPE_STATUS);
    }
    $this->messenger->addStatus($this->registrationStatusMessage());
    $formState->setRedirect('<front>');
  }

  /**
   * Checks whether this request matches an existing email account.
   */
  private function registrationAlreadyUsed(FormStateInterface $formState): bool {
    return $formState->get('aculta_registration_existing') !== NULL;
  }

  /**
   * Saves the chosen password on the account created by registration.
   */
  public function storeRegistrationPassword(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $pass = $formState->get('aculta_register_pass');
    $account = $formState->get('user') ?? $formState->getFormObject()->getEntity();
    if (!is_string($pass) || $pass === '' || !$account instanceof UserInterface) {
      return;
    }
    $account->setPassword($pass);
    $account->save();
  }

  /**
   * Returns the password form to Portal and records a local password. */
  public function securityPasswordRedirect(array &$form, FormStateInterface $formState): void {
    $uid = (int) $this->currentUser->id();
    if ($uid > 0) {
      $this->userData->delete('aculta_portal', $uid, 'social_auth_password_unset');
    }
    $formState->setRedirect('aculta_portal.security_password');
  }

  /**
   * Applies accessible labels/autocomplete to Core's password widget. */
  public function securityPasswordAfterBuild(array $form, FormStateInterface $formState): array {
    if (isset($form['account']['pass']['pass1'])) {
      $form['account']['pass']['pass1']['#title'] = $this->translation->translate('Nova senha');
      $form['account']['pass']['pass1']['#attributes']['autocomplete'] = 'new-password';
    }
    if (isset($form['account']['pass']['pass2'])) {
      $form['account']['pass']['pass2']['#title'] = $this->translation->translate('Confirmar nova senha');
      $form['account']['pass']['pass2']['#attributes']['autocomplete'] = 'new-password';
    }
    return $form;
  }

  /**
   * Redireciona para o painel após a recuperação da senha.
   */
  public function recoveryPasswordRedirect(array &$form, FormStateInterface $formState): void {
    // O domínio da conta é o que mostra a Visão geral; no domínio principal a página fica vazia.
    $url = $this->domainPurpose?->routeUrl('account', 'aculta_portal.dashboard');
    if ($url !== NULL) {
      $formState->setRedirectUrl($url);
      return;
    }
    $formState->setRedirect('aculta_portal.dashboard');
  }

  /**
   * Returns the profile-photo form to the account overview. */
  public function accountPhotoRedirect(array &$form, FormStateInterface $formState): void {
    $formState->setRedirect('aculta_portal.dashboard');
  }

  /**
   * Returns an Address submission to its original editing context.
   *
   * The saved Commerce Profile is identical in Portal and Studio; only the
   * post-submit destination differs. The existing Portal flow is unchanged.
   */
  public function addressRedirect(array &$form, FormStateInterface $formState): void {
    $route = $this->routeMatch?->getRouteName() === 'aculta_studio.account_address'
      ? 'aculta_studio.account_address'
      : 'aculta_portal.my_data_address';
    $formState->setRedirect($route);
  }

  /**
   * Returns private data submissions to Studio without changing legacy flow.
   */
  public function accountDataRedirect(array &$form, FormStateInterface $formState): void {
    if ($this->routeMatch?->getRouteName() === 'aculta_studio.account_data') {
      $formState->setRedirect('aculta_studio.account_data');
    }
  }

  /**
   * Synchronizes Commerce address names with the participant profile. */
  public function syncCustomerAddressNames(array &$form, FormStateInterface $formState): void {
    $participant = $formState->getFormObject()->getEntity();
    if (!$participant instanceof ProfileInterface || !PerfilDoPapel::ehTipoDePapel($participant)) {
      return;
    }

    $user = $participant->getOwner();
    if ($user === NULL) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('profile');
    $customer = $storage->loadByUser($user, 'authenticated');
    if ($customer === NULL || $customer->get('field_address')->isEmpty()) {
      return;
    }

    $address = $customer->get('field_address')->first();
    $changed = FALSE;
    foreach ([
      'given_name' => 'field_first_name',
      'family_name' => 'field_last_name',
    ] as $addressKey => $profileField) {
      $value = (string) ($participant->get($profileField)->value ?? '');
      if ($address->get($addressKey) !== $value) {
        $address->set($addressKey, $value);
        $changed = TRUE;
      }
    }

    if ($changed) {
      $customer->save();
    }
  }

  /**
   * Rejects empty/non-positive custom donation amounts. */
  public function validateDonationAmount(array &$form, FormStateInterface $formState): void {
    $giftType = $formState->getValue(['commerce_donation_pane', 'field_gift_type']);
    $giftType = is_array($giftType)
      ? ($giftType[0]['value'] ?? $giftType['value'] ?? reset($giftType))
      : $giftType;

    if ($giftType !== 'single') {
      $formState->setErrorByName(
        'commerce_donation_pane][field_gift_type',
        $this->translation->translate('Selecione apoio único.'),
      );
      return;
    }

    $amountValues = $formState->getValue(['commerce_donation_pane', 'field_donation_amount']);
    $amountItem = is_array($amountValues) ? ($amountValues[0] ?? []) : [];
    $donationLevel = $amountItem['donation_level'] ?? [];
    if (($donationLevel['value'] ?? NULL) !== 'custom_amount') {
      return;
    }

    $amount = $donationLevel['amount'] ?? NULL;
    if (!is_numeric($amount) || (float) $amount <= 0) {
      $formState->setErrorByName(
        'commerce_donation_pane][field_donation_amount][0][donation_level][amount',
        $this->translation->translate('Informe um valor de apoio maior que zero.'),
      );
    }
  }

}
