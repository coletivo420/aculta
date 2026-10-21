<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pedido de troca de e-mail, na aba E-mail da Segurança da conta.
 *
 * O formulário não grava o novo endereço. O salvamento passa pelo aculta_mail_confirmer_user, que guarda o pedido e
 * envia o link de confirmação de uso único (24 horas). O e-mail só muda depois que o novo endereço é confirmado.
 *
 * Substitui o ChangeMailForm do contrib change_mail_page. Diferenças: a senha atual é conferida aqui, com limite de
 * tentativas; um e-mail igual ao atual é recusado; e as mensagens ficam no próprio formulário, sem alterações
 * posteriores pelo Portal.
 */
final class ChangemailForm extends FormBase {

  /** Tentativas erradas de senha atual, por conta, dentro da janela. */
  private const MAX_PASSWORD_ATTEMPTS = 5;

  /** Janela de contagem das tentativas erradas, em segundos. */
  private const PASSWORD_WINDOW = 3600;

  /** Nome do evento de flood das tentativas de senha atual. */
  private const FLOOD_EVENT = 'aculta_user_changemail_password';

  /** Ajuste do aculta_mail_confirmer_user que liga a confirmação da troca. */
  private const CONFIRMER_SETTING = 'aculta_mail_confirmer_user.settings';

  public function __construct(
    private readonly ConfigFactoryInterface $confirmerConfig,
    private readonly PasswordInterface $password,
    private readonly FloodInterface $flood,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('password'),
      $container->get('flood'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_changemail_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    $form_state->set('user', $user);

    $form['account'] = ['#type' => 'container'];
    $form['account']['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Novo e-mail'),
      '#description' => $this->t('Você precisará confirmar este endereço antes que ele passe a ser usado na sua conta.'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'email'],
    ];
    $form['account']['current_pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Senha atual'),
      '#description' => $this->t('Obrigatória para solicitar a troca de e-mail.'),
      '#required' => TRUE,
      '#size' => 25,
      '#attributes' => ['autocomplete' => 'current-password'],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Solicitar alteração de e-mail'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Sem a confirmação ligada, o Core gravaria o novo e-mail direto: o pedido não existe nesse caso.
    if (!$this->confirmerConfig->get(self::CONFIRMER_SETTING)->get('user_email_change.enabled')) {
      $form_state->setErrorByName('mail', $this->t('A troca de e-mail está indisponível no momento.'));
      return;
    }

    /** @var \Drupal\user\UserInterface $account */
    $account = $form_state->get('user');
    $uid = (string) $account->id();
    $mail = trim((string) $form_state->getValue('mail'));
    $pass = (string) $form_state->getValue('current_pass');

    if (!$this->flood->isAllowed(self::FLOOD_EVENT, self::MAX_PASSWORD_ATTEMPTS, self::PASSWORD_WINDOW, $uid)) {
      $form_state->setErrorByName('current_pass', $this->t('Muitas tentativas com a senha atual. Tente novamente em uma hora.'));
      return;
    }
    if (!$this->password->check($pass, $account->getPassword())) {
      $this->flood->register(self::FLOOD_EVENT, self::PASSWORD_WINDOW, $uid);
      $form_state->setErrorByName('current_pass', $this->t('A senha atual não confere.'));
      return;
    }
    if (strcasecmp($mail, $account->getEmail()) === 0) {
      $form_state->setErrorByName('mail', $this->t('Informe um endereço diferente do seu e-mail atual.'));
      return;
    }

    // Core confere o endereço (formato e unicidade) e a senha informada para a troca de e-mail.
    $account->setEmail($mail);
    $account->setExistingPassword($pass);
    foreach ($account->validate() as $violation) {
      $form_state->setErrorByName('mail', (string) $violation->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\user\UserInterface $account */
    $account = $form_state->get('user');
    $account->setEmail(trim((string) $form_state->getValue('mail')));
    $account->setExistingPassword((string) $form_state->getValue('current_pass'));
    // A confirmação do aculta_mail_confirmer_user intercepta este save e mantém o e-mail atual até a confirmação.
    $account->save();

    // Substitui a mensagem de envio do módulo de confirmação por uma que vale para o pedido da Segurança.
    $this->messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
    $this->messenger()->addStatus($this->t('A solicitação foi registrada. Seu e-mail atual permanece ativo até a confirmação do novo endereço.'));
    $form_state->setRedirect('aculta_portal.security');
  }

}
