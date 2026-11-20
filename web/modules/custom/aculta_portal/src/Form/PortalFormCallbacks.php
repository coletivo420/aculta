<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\aculta_portal\Email\EmailConfirmationRequestResult;
use Drupal\aculta_portal\Account\AccountAddressForm;
use Drupal\aculta_portal\Account\RegistrationMailPolicy;
use Drupal\aculta_portal\Account\AcultaUserAgreementRegistration;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\aculta_portal\Email\EmailConfirmationRequester;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\ProfileStorageInterface;
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
    private readonly ?DomainSiteManager $siteManager = NULL,
  ) {}

  /**
   * Returns the same registration message for both address types (DT-P06).
   */
  private function registrationStatusMessage(): string {
    return (string) $this->translation->translate('Sua solicitação foi recebida. Se este endereço tiver uma confirmação ou aviso disponível, verifique a caixa de entrada e a pasta de spam. Se nenhuma mensagem chegar, tente novamente mais tarde.');
  }

  /**
   * Combines street and number before saving the profile, as in checkout. This entity builder runs before the profile is saved.
   *
   * @param string $entityTypeId
   *   Value used for $entityTypeId.
   * @param ProfileInterface $profile
   *   Value used for $profile.
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function buildStreetNumber(string $entityTypeId, ProfileInterface $profile, array &$form, FormStateInterface $formState): void {
    if ($profile->get('field_address')->isEmpty()) {
      return;
    }
    AccountAddressForm::buildStreetNumber($entityTypeId, $profile, $form, $formState);
  }

  /**
   * Keeps account saves on the same page.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function disableAccountRedirect(array &$form, FormStateInterface $formState): void {
    $formState->disableRedirect();
  }

  /**
   * Records acceptance of the required registration terms.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function recordAcultaUserAgreementAcceptance(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $account = $formState->get('user') ?? $this->formEntity($formState);
    if ($account instanceof UserInterface) {
      $this->userAgreementRegistration->record($account);
    }
  }

  /**
   * Sends the confirmation email for a newly created account.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function requestRegistrationConfirmation(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $account = $formState->get('user') ?? $this->formEntity($formState);
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
   * Authenticates the login form without disclosing blocked accounts. Core reports a blocked or unactivated account with its own message before any password check. Here a blocked account is recognized first and leaves the authentication step, so validateFinal() gives the generic message used for unknown accounts. The IP flood check matches Core's condition and outcome, so its response cannot reveal whether an account exists. Every other account goes through Core's validateAuthentication().
   *
   * @param array<array-key, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function validateLoginAuthentication(array &$form, FormStateInterface $formState): void {
    $formObject = $formState->getFormObject();
    if (!$formObject instanceof UserLoginForm && !$formObject instanceof LoginPopupForm) {
      // Never authenticate through an unexpected form object.
      return;
    }

    $rawPass = $formState->getValue('pass');
    $password = trim(is_scalar($rawPass) ? (string) $rawPass : '');
    $account = FALSE;
    if (!$formState->isValueEmpty('name') && strlen($password) > 0) {
      $floodConfig = $this->configFactory->get('user.flood');
      $ipLimit = is_numeric($floodConfig->get('ip_limit')) ? (int) $floodConfig->get('ip_limit') : 50;
      $ipWindow = is_numeric($floodConfig->get('ip_window')) ? (int) $floodConfig->get('ip_window') : 3600;
      if (!$this->floodControl->isAllowed('user.failed_login_ip', $ipLimit, $ipWindow)) {
        $formState->set('flood_control_triggered', 'ip');
        return;
      }
      $rawName = $formState->getValue('name');
      $name = is_scalar($rawName) ? (string) $rawName : '';
      $account = $this->userAuth->lookupAccount($name);
    }

    if ($account instanceof UserInterface && $account->isBlocked()) {
      return;
    }
    $formObject->validateAuthentication($form, $formState);
  }

  /**
   * Validates the registration password and stores it for the save handler.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function validateRegistrationPassword(array &$form, FormStateInterface $formState): void {
    $rawPass = $formState->getValue('pass');
    $pass = is_scalar($rawPass) ? (string) $rawPass : '';
    if (mb_strlen($pass) < 8) {
      $formState->setErrorByName('pass', $this->translation->translate('A senha precisa ter pelo menos 8 caracteres.'));
      return;
    }
    $formState->set('aculta_register_pass', $pass);
  }

  /**
   * Keeps registration responses the same when an email is already used. The existing account receives a notice; no account is created again. Other form errors are preserved.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function validateRegistrationMail(array &$form, FormStateInterface $formState): void {
    $rawMail = $formState->getValue('mail');
    $mail = is_scalar($rawMail) ? (string) $rawMail : '';
    $existing = $this->registrationMail->existingAccount($mail);
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
      $formState->setErrorByName($name, is_scalar($message) || $message instanceof \Stringable ? (string) $message : '');
    }
    $formState->set('aculta_registration_existing', (int) $existing->id());
  }

  /**
   * Saves a registration, replacing Core's submit handler. Existing accounts are not created again; both paths return the same message and destination.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function saveRegistration(array $form, FormStateInterface $formState): void {
    $existingId = $formState->get('aculta_registration_existing');
    if ($existingId !== NULL && is_numeric($existingId)) {
      $existing = $this->entityTypeManager->getStorage('user')->load((int) $existingId);
      if ($existing instanceof UserInterface) {
        $this->registrationMail->notifyExistingAccount($existing);
      }
    }
    else {
      $formObject = $formState->getFormObject();
      if ($formObject instanceof EntityFormInterface) {
        $formObject->save($form, $formState);
      }
      // Replace Core's welcome message with the generic response.
      $this->messenger->deleteByType(MessengerInterface::TYPE_STATUS);
    }
    $this->messenger->addStatus($this->registrationStatusMessage());
    $formState->setRedirect('<front>');
  }

  /**
   * Checks whether this request matches an existing email account.
   *
   * @param FormStateInterface $formState
   *   Current form state.
   */
  private function registrationAlreadyUsed(FormStateInterface $formState): bool {
    return $formState->get('aculta_registration_existing') !== NULL;
  }

  /**
   * Saves the chosen password on the account created by registration.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function storeRegistrationPassword(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $pass = $formState->get('aculta_register_pass');
    $account = $formState->get('user') ?? $this->formEntity($formState);
    if (!is_string($pass) || $pass === '' || !$account instanceof UserInterface) {
      return;
    }
    $account->setPassword($pass);
    $account->save();
  }

  /**
   * Returns the password form to Portal and records a local password.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function securityPasswordRedirect(array &$form, FormStateInterface $formState): void {
    $uid = (int) $this->currentUser->id();
    if ($uid > 0) {
      $this->userData->delete('aculta_portal', $uid, 'social_auth_password_unset');
    }
    $formState->setRedirect('aculta_portal.security_password');
  }

  /**
   * Applies accessible labels/autocomplete to Core's password widget.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   *
   * @return array<array-key, mixed>
   *   Render array returned by the method.
   */
  public function securityPasswordAfterBuild(array $form, FormStateInterface $formState): array {
    $account = &$form['account'];
    if (is_array($account)) {
      $pass = &$account['pass'];
      if (is_array($pass)) {
        if (isset($pass['pass1']) && is_array($pass['pass1'])) {
          $pass['pass1']['#title'] = $this->translation->translate('Nova senha');
          $pass1Attrs = &$pass['pass1']['#attributes'];
          if (!is_array($pass1Attrs)) {
            $pass1Attrs = [];
          }
          $pass1Attrs['autocomplete'] = 'new-password';
        }
        if (isset($pass['pass2']) && is_array($pass['pass2'])) {
          $pass['pass2']['#title'] = $this->translation->translate('Confirmar nova senha');
          $pass2Attrs = &$pass['pass2']['#attributes'];
          if (!is_array($pass2Attrs)) {
            $pass2Attrs = [];
          }
          $pass2Attrs['autocomplete'] = 'new-password';
        }
      }
    }
    return $form;
  }

  /**
   * Redireciona para o painel após a recuperação da senha.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function recoveryPasswordRedirect(array &$form, FormStateInterface $formState): void {
    // O domínio da conta é o que mostra a Visão geral; no domínio principal a página fica vazia.
    $url = $this->siteManager?->routeUrl(SiteKey::Account, 'aculta_portal.dashboard');
    if ($url !== NULL) {
      $formState->setRedirectUrl($url);
      return;
    }
    $formState->setRedirect('aculta_portal.dashboard');
  }

  /**
   * Returns the profile-photo form to the account overview.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function accountPhotoRedirect(array &$form, FormStateInterface $formState): void {
    $formState->setRedirect('aculta_portal.dashboard');
  }

  /**
   * Returns an Address submission to its original editing context. The saved Commerce Profile is identical in Portal and Studio; only the post-submit destination differs. The existing Portal flow is unchanged.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function addressRedirect(array &$form, FormStateInterface $formState): void {
    $route = $this->routeMatch?->getRouteName() === 'aculta_studio.account_address'
      ? 'aculta_studio.account_address'
      : 'aculta_portal.my_data_address';
    $formState->setRedirect($route);
  }

  /**
   * Returns private data submissions to Studio without changing legacy flow.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function accountDataRedirect(array &$form, FormStateInterface $formState): void {
    if ($this->routeMatch?->getRouteName() === 'aculta_studio.account_data') {
      $formState->setRedirect('aculta_studio.account_data');
    }
  }

  /**
   * Synchronizes Commerce address names with the participant profile.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function syncCustomerAddressNames(array &$form, FormStateInterface $formState): void {
    $participant = $this->formEntity($formState);
    if (!$participant instanceof ProfileInterface || !PerfilDoPapel::ehTipoDePapel($participant)) {
      return;
    }

    $user = $participant->getOwner();
    $storage = $this->entityTypeManager->getStorage('profile');
    if (!$storage instanceof ProfileStorageInterface) {
      return;
    }
    $customer = $storage->loadByUser($user, 'authenticated');
    if ($customer === NULL || $customer->get('field_address')->isEmpty()) {
      return;
    }

    $addressField = $customer->get('field_address');
    $address = $addressField->first();
    if (!$address instanceof AddressItem) {
      return;
    }
    $addressValues = $address->getValue();
    $changed = FALSE;
    foreach ([
      'given_name' => 'field_first_name',
      'family_name' => 'field_last_name',
    ] as $addressKey => $profileField) {
      $profileItem = $participant->get($profileField);
      $rawVal = $profileItem->value ?? '';
      $value = is_scalar($rawVal) ? (string) $rawVal : '';
      $rawCurrent = is_array($addressValues) ? ($addressValues[$addressKey] ?? '') : '';
      $current = is_scalar($rawCurrent) ? (string) $rawCurrent : '';
      if ($current !== $value) {
        $address->set($addressKey, $value);
        $changed = TRUE;
      }
    }

    if ($changed) {
      $customer->save();
    }
  }

  /**
   * Rejects empty/non-positive custom donation amounts.
   *
   * @param array<string, mixed> $form
   *   Form render array.
   * @param FormStateInterface $formState
   *   Current form state.
   */
  public function validateDonationAmount(array &$form, FormStateInterface $formState): void {
    $giftType = $formState->getValue(['commerce_donation_pane', 'field_gift_type']);
    if (is_array($giftType)) {
      $first = reset($giftType);
      $giftType = is_array($first) ? ($first['value'] ?? '') : ($giftType['value'] ?? $first);
    }

    if ($giftType !== 'single') {
      $formState->setErrorByName(
        'commerce_donation_pane][field_gift_type',
        $this->translation->translate('Selecione apoio único.'),
      );
      return;
    }

    $amountValues = $formState->getValue(['commerce_donation_pane', 'field_donation_amount']);
    $amountItem = is_array($amountValues) && isset($amountValues[0]) && is_array($amountValues[0]) ? $amountValues[0] : [];
    $donationLevel = is_array($amountItem['donation_level'] ?? NULL) ? $amountItem['donation_level'] : [];
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

  /**
   * Returns the entity behind an entity-backed form, when available.
   */
  private function formEntity(FormStateInterface $formState): ?EntityInterface {
    $formObject = $formState->getFormObject();
    return $formObject instanceof EntityFormInterface ? $formObject->getEntity() : NULL;
  }

}
