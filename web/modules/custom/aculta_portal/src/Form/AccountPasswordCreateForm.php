<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Criar senha para conta criada com acesso externo (Social Auth, sem senha escolhida pelo usuário).
 *
 * Não pede a senha atual, porque a conta nunca teve uma escolhida. Ao gravar, remove a marca
 * social_auth_password_unset (gravada por SocialAuthUserCreatedSubscriber), e a aba Senha passa a mostrar o formulário
 * de troca. O envio é comum (sem AJAX): a aba recarrega para mostrar o estado novo. Ver docs/portal/SENHA-CONTA-OAUTH.md.
 */
final class AccountPasswordCreateForm extends FormBase {

  /** Mínimo igual ao do cadastro (FormHooks::alter, user_register_form). */
  private const MIN_LENGTH = 8;

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly UserDataInterface $userData,
    private readonly MessengerInterface $messages,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('user.data'),
      $container->get('messenger'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_account_password_create';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#plain_text' => $this->t('Sua conta foi criada com acesso externo e ainda não tem senha. Crie uma senha para também entrar com seu e-mail e senha.'),
    ];
    $form['pass'] = [
      '#type' => 'password_confirm',
      '#required' => TRUE,
      '#size' => 25,
      '#description' => $this->t('Escolha uma senha com pelo menos @min caracteres.', ['@min' => self::MIN_LENGTH]),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Criar senha'),
      '#button_type' => 'primary',
    ];
    $form['#cache'] = ['contexts' => ['user'], 'max-age' => 0];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $password = (string) $form_state->getValue('pass');
    if ($password !== '' && mb_strlen($password) < self::MIN_LENGTH) {
      $form_state->setErrorByName('pass', $this->t('A senha precisa ter pelo menos @min caracteres.', ['@min' => self::MIN_LENGTH]));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Sempre a conta da sessão: o formulário não recebe o usuário como parâmetro.
    $account = $this->entities->getStorage('user')->load($this->currentUser()->id());
    if ($account === NULL) {
      return;
    }
    $account->setPassword((string) $form_state->getValue('pass'));
    $account->save();
    $this->userData->delete('aculta_portal', $account->id(), 'social_auth_password_unset');
    $this->messages->addStatus($this->t('Senha criada. Agora você pode trocar sua senha quando quiser.'));
    $form_state->setRedirect('aculta_portal.security_password');
  }

}
