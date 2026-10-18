<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\aculta_portal\Email\EmailConfirmationRequester;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\profile\ProfileInterface;
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
    private readonly \Drupal\aculta_portal\Account\RegistrationTerms $registrationTerms,
    private readonly \Drupal\aculta_portal\Account\RegistrationMailPolicy $registrationMail,
  ) {}

  /** Mensagem única do cadastro, igual com e-mail novo ou já cadastrado (DT-P06). */
  private function registrationStatusMessage(): string {
    return (string) $this->translation->translate('Sua solicitação foi recebida. Se este endereço tiver uma confirmação ou aviso disponível, verifique a caixa de entrada e a pasta de spam. Se nenhuma mensagem chegar, tente novamente mais tarde.');
  }

  /**
   * Entity builder de Meus Dados: junta a rua e o número ("Rua, número") antes de gravar o perfil, no mesmo formato
   * do checkout. Roda na montagem da entidade, antes do save.
   */
  public function buildStreetNumber(string $entityTypeId, $profile, array &$form, FormStateInterface $formState): void {
    if (!$profile instanceof \Drupal\profile\Entity\ProfileInterface || $profile->get('field_address')->isEmpty()) {
      return;
    }
    \Drupal\aculta_portal\Account\AccountAddressForm::buildStreetNumber($entityTypeId, $profile, $form, $formState);
  }

  /** Resposta AJAX do salvamento na Minha conta: mensagens e o formulário, no mesmo bloco. */
  public function accountAjaxResponse(array &$form, FormStateInterface $formState): array {
    unset($form['#prefix'], $form['#suffix']);
    return ['messages' => ['#type' => 'status_messages'], 'form' => $form];
  }

  /** Último handler de envio da Minha conta: o salvamento fica na página (sem redirecionamento). */
  public function disableAccountRedirect(array &$form, FormStateInterface $formState): void {
    $formState->disableRedirect();
  }

  /** Registra o aceite dos termos do cadastro (checkbox obrigatório no formulário). */
  public function acceptRegistrationTerms(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $account = $formState->get('user') ?? $formState->getFormObject()->getEntity();
    if ($account instanceof \Drupal\user\UserInterface) {
      $this->registrationTerms->record($account);
    }
  }

  /** Envia a confirmação de e-mail do cadastro recém-criado (conta ativa, e-mail não confirmado). */
  public function requestRegistrationConfirmation(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $account = $formState->get('user') ?? $formState->getFormObject()->getEntity();
    if ($account instanceof \Drupal\user\UserInterface) {
      $result = $this->confirmationRequester->request($account);
      if ($result !== \Drupal\aculta_portal\Email\EmailConfirmationRequestResult::ACCEPTED) {
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
   * for unknown accounts. The IP flood check runs first, with the same condition
   * and outcome as Core, so the flood response cannot tell the accounts apart.
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

  /** Validates conditional Activity fields. */
  public function validateActivity(array &$form, FormStateInterface $formState): void {
    $node = $formState->getFormObject()->buildEntity($form, $formState);
    $mode = $node->get('field_modality')->value;

    if (in_array($mode, ['presencial', 'hibrido'], TRUE)
      && $node->get('field_place_name')->isEmpty()) {
      $formState->setErrorByName(
        'field_place_name',
        $this->translation->translate('Informe o local da atividade presencial.'),
      );
    }

    if (in_array($mode, ['online', 'hibrido'], TRUE)
      && $node->get('field_online_url')->isEmpty()) {
      $formState->setErrorByName(
        'field_online_url',
        $this->translation->translate('Informe o endereço online da atividade.'),
      );
    }

    if (!$node->get('field_event_end')->isEmpty()
      && $node->get('field_event_end')->value < $node->get('field_event_start')->value) {
      $formState->setErrorByName(
        'field_event_end',
        $this->translation->translate('O término deve ocorrer após o início.'),
      );
    }
  }


  /** Replaces Change Mail Page's premature success claim. */
  public function changeMailConfirmationMessage(array &$form, FormStateInterface $formState): void {
    $this->messenger->deleteByType('status');
    $this->messenger->addStatus($this->translation->translate(
      'A solicitação foi registrada. Seu e-mail atual permanece ativo até a confirmação do novo endereço.',
    ));
    $formState->setRedirect('aculta_portal.security');
  }

  /** Valida a senha escolhida no cadastro e guarda-a no form state para gravar depois do salvamento. */
  public function validateRegistrationPassword(array &$form, FormStateInterface $formState): void {
    $pass = (string) $formState->getValue('pass');
    if (mb_strlen($pass) < 8) {
      $formState->setErrorByName('pass', $this->translation->translate('A senha precisa ter pelo menos 8 caracteres.'));
      return;
    }
    $formState->set('aculta_register_pass', $pass);
  }

  /**
   * Cadastro com e-mail já usado: o erro "already taken" do Core sai (ele revela a existência da conta) e a
   * submissão segue até saveRegistration(), que não cria conta. Outros erros do formulário são mantidos.
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
   * Salvamento do cadastro (substitui o ::save do Core no botão). Com e-mail já cadastrado, nada é criado: o dono da
   * conta recebe um aviso. Nos dois casos a resposta é a mesma: mesma mensagem e mesmo destino.
   */
  public function saveRegistration(array $form, FormStateInterface $formState): void {
    $existingId = $formState->get('aculta_registration_existing');
    if ($existingId !== NULL) {
      $existing = $this->entityTypeManager->getStorage('user')->load((int) $existingId);
      if ($existing instanceof \Drupal\user\UserInterface) {
        $this->registrationMail->notifyExistingAccount($existing);
      }
    }
    else {
      $formState->getFormObject()->save($form, $formState);
      // A mensagem do Core ("A welcome message...") é trocada pela mensagem única.
      $this->messenger->deleteByType(MessengerInterface::TYPE_STATUS);
    }
    $this->messenger->addStatus($this->registrationStatusMessage());
    $formState->setRedirect('<front>');
  }

  private function registrationAlreadyUsed(FormStateInterface $formState): bool {
    return $formState->get('aculta_registration_existing') !== NULL;
  }

  /** Grava a senha escolhida na conta criada (que segue bloqueada até a confirmação do e-mail). */
  public function storeRegistrationPassword(array &$form, FormStateInterface $formState): void {
    if ($this->registrationAlreadyUsed($formState)) {
      return;
    }
    $pass = $formState->get('aculta_register_pass');
    $account = $formState->get('user') ?? $formState->getFormObject()->getEntity();
    if (!is_string($pass) || $pass === '' || !$account instanceof \Drupal\user\UserInterface) {
      return;
    }
    $account->setPassword($pass);
    $account->save();
  }

  /** Returns the password form to Portal and records a local password. */
  public function securityPasswordRedirect(array &$form, FormStateInterface $formState): void {
    $uid = (int) $this->currentUser->id();
    if ($uid > 0) {
      $this->userData->delete('aculta_portal', $uid, 'social_auth_password_unset');
    }
    $formState->setRedirect('aculta_portal.security_password');
  }

  /** Applies accessible labels/autocomplete to Core's password widget. */
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

  /** Returns the profile-photo form to the account overview. */
  public function accountPhotoRedirect(array &$form, FormStateInterface $formState): void {
    $formState->setRedirect('aculta_portal.dashboard');
  }

  /** Returns an Address submission to the nested Portal route. */
  public function addressRedirect(array &$form, FormStateInterface $formState): void {
    $formState->setRedirect('aculta_portal.my_data_address');
  }

  /** Synchronizes Commerce address names with the participant profile. */
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

  /** Rejects empty/non-positive custom donation amounts. */
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
