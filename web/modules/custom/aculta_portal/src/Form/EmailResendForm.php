<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Email\EmailConfirmationRequestResult;
use Drupal\aculta_portal\Email\EmailConfirmationRequester;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** POST form for requesting a new registration email confirmation. */
final class EmailResendForm extends FormBase {

  public function __construct(
    private readonly EmailConfirmationRequester $requester,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly MessengerInterface $portalMessenger,
    private readonly TranslationInterface $translation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.email_confirmation_requester'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('string_translation'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_email_resend_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->translation->translate('Solicite um novo link de confirmação para o endereço da sua conta.'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->translation->translate('Reenviar confirmação'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());
    $result = $account instanceof UserInterface
      ? $this->requester->request($account)
      : EmailConfirmationRequestResult::INVALID_ACCOUNT;

    switch ($result) {
      case EmailConfirmationRequestResult::ACCEPTED:
        $this->portalMessenger->addStatus($this->translation->translate('A solicitação foi aceita. Se a mensagem não chegar, solicite outro link após 15 minutos.'));
        break;

      case EmailConfirmationRequestResult::RATE_LIMITED:
        $this->portalMessenger->addWarning($this->translation->translate('Aguarde 15 minutos antes de solicitar outra confirmação.'));
        break;

      case EmailConfirmationRequestResult::BUSY:
        $this->portalMessenger->addWarning($this->translation->translate('Outra solicitação está sendo processada. Tente novamente em instantes.'));
        break;

      case EmailConfirmationRequestResult::INVALID_ACCOUNT:
      case EmailConfirmationRequestResult::FAILED:
        $this->portalMessenger->addError($this->translation->translate('Não foi possível processar a solicitação agora. Tente novamente mais tarde.'));
        break;
    }

    $form_state->setRedirect('aculta_portal.dashboard');
  }

}
